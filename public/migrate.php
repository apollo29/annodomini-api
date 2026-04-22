<?php
/**
 * Anno Domini API - Browser-aufrufbares Migrations-Tool
 *
 * Fuer Hosting ohne SSH/CLI. Wendet alle noch nicht angewendeten SQL-Migrations
 * aus database/migrations/ an und extrahiert anschliessend die Icons.
 *
 * Idempotent: Legt eine schema_migrations-Tabelle an und merkt sich, welche
 * Migrations schon gelaufen sind. Mehrfaches Aufrufen macht nichts kaputt.
 *
 * Aufruf:
 *   https://api.annodomini.app/migrate.php?key=SETUP_KEY
 *
 * SICHERHEIT: Datei im Repository gehalten, aber nur mit gueltigem Key
 * ansprechbar. Aendere $setupKey vor Deploy!
 */

declare(strict_types=1);

$setupKey = 'annodomini-migrate-2026';

if (!isset($_GET['key']) || !hash_equals($setupKey, (string)$_GET['key'])) {
    http_response_code(403);
    die('Zugriff verweigert.');
}

set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
$settings = require __DIR__ . '/../config/settings.php';

$db = $settings['db'];
$iconBaseUrl = rtrim((string)($settings['icon_base_url'] ?? 'https://api.annodomini.app'), '/');
$migrationsDir = realpath(__DIR__ . '/..') . '/database/migrations';
$iconsDir = realpath(__DIR__) . '/icons';

echo "Anno Domini API - Migrate\n";
echo "==========================\n\n";
echo "DB:             {$db['database']}@{$db['host']}\n";
echo "Migrations:     $migrationsDir\n";
echo "Icons Ziel:     $iconsDir\n";
echo "Icon-URL-Basis: $iconBaseUrl\n\n";

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['database']);
    $pdo = new PDO($dsn, $db['username'], $db['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[OK] DB verbunden\n";
} catch (PDOException $e) {
    echo "[FAIL] DB-Verbindung: " . $e->getMessage() . "\n";
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `schema_migrations` (' .
    ' `name` VARCHAR(255) NOT NULL PRIMARY KEY,' .
    ' `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
echo "[OK] schema_migrations Tabelle bereit\n\n";

$applied = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

if (!is_dir($migrationsDir)) {
    echo "[INFO] Kein Migrations-Verzeichnis ($migrationsDir) - skip\n";
} else {
    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files);

    echo "Gefundene Migrations: " . count($files) . "\n";
    echo "Bereits angewendet:   " . count($applied) . "\n\n";

    $insertMigration = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (:name)');
    $ranCount = 0;

    foreach ($files as $file) {
        $name = basename($file);

        if (isset($applied[$name])) {
            echo "  [skip] $name (schon angewendet)\n";
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            echo "  [skip] $name (leer)\n";
            continue;
        }

        echo "  [run ] $name ... ";
        try {
            $pdo->exec($sql);
            $insertMigration->execute([':name' => $name]);
            echo "OK\n";
            $ranCount++;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column name')) {
                $insertMigration->execute([':name' => $name]);
                echo "OK (Spalte existierte bereits)\n";
                $ranCount++;
            } else {
                echo "FAIL\n    " . $e->getMessage() . "\n";
                exit(2);
            }
        }
    }

    echo "\n==> $ranCount neue Migration(en) angewendet.\n\n";
}

echo "Icon-Extraktion\n";
echo "---------------\n";

if (!is_dir($iconsDir)) {
    if (!mkdir($iconsDir, 0775, true) && !is_dir($iconsDir)) {
        echo "[FAIL] Konnte $iconsDir nicht erstellen\n";
        exit(2);
    }
    echo "[OK] Verzeichnis $iconsDir erstellt\n";
}

$rows = $pdo->query('SELECT uid, icon FROM game_set WHERE icon IS NOT NULL AND icon <> ""')->fetchAll();
echo count($rows) . " Sets mit Icon-Daten gefunden.\n\n";

$updateStmt = $pdo->prepare('UPDATE game_set SET icon_url = :url WHERE uid = :uid');
$written = 0;
$skipped = 0;
$failed = 0;

foreach ($rows as $row) {
    $uid = (int)$row['uid'];
    $base64 = (string)$row['icon'];

    $svg = base64_decode($base64, true);
    if ($svg === false || $svg === '' || !str_contains($svg, '<svg')) {
        echo "  SKIP uid=$uid (kein valides base64 SVG)\n";
        $skipped++;
        continue;
    }

    $path = $iconsDir . '/' . $uid . '.svg';
    if (@file_put_contents($path, $svg) === false) {
        echo "  FAIL uid=$uid ($path nicht schreibbar)\n";
        $failed++;
        continue;
    }

    $url = $iconBaseUrl . '/icons/' . $uid . '.svg';
    $updateStmt->execute([':url' => $url, ':uid' => $uid]);

    echo "  OK   uid=$uid  -  " . strlen($svg) . " bytes\n";
    $written++;
}

echo "\n==> $written geschrieben, $skipped uebersprungen, $failed fehlgeschlagen\n\n";

echo "Verifikation\n";
echo "------------\n";

try {
    $verify = $pdo->query("SHOW COLUMNS FROM game_set LIKE 'icon_url'")->fetch();
    if ($verify) {
        echo "[OK] game_set.icon_url existiert (" . $verify['Type'] . ")\n";
    } else {
        echo "[FAIL] game_set.icon_url fehlt!\n";
    }
} catch (PDOException $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

$sampleSet = $pdo->query('SELECT uid, icon_url FROM game_set WHERE icon_url IS NOT NULL LIMIT 1')->fetch();
if ($sampleSet) {
    echo "[OK] Beispiel: uid={$sampleSet['uid']} -> {$sampleSet['icon_url']}\n";
    echo "     Teste im Browser: {$sampleSet['icon_url']}\n";
} else {
    echo "[WARN] Kein Set hat icon_url gesetzt\n";
}

echo "\nFertig.\n";

if ($failed > 0) {
    exit(2);
}
exit(0);
