<?php
/**
 * Anno Domini — Icon Extraction Script (Issue #196)
 *
 * One-shot script that reads every row from `game_set`, decodes the base64
 * `icon` column into real SVG files under `public/icons/{uid}.svg`, and
 * updates the `icon_url` column to the full public URL.
 *
 * Idempotent: re-running overwrites existing SVG files and leaves `icon_url`
 * in a consistent state.
 *
 * Usage:
 *   cd /path/to/annodomini-api
 *   php bin/extract-icons.php
 *
 * On Metanet/Plesk with custom PHP binary:
 *   /opt/plesk/php/8.3/bin/php bin/extract-icons.php
 *
 * Exit codes: 0 ok, 1 db connect failed, 2 write failed.
 */

declare(strict_types=1);

// --- Bootstrap just enough to get DB credentials + icon base URL ---

require __DIR__ . '/../vendor/autoload.php';

$settings = require __DIR__ . '/../config/settings.php';
$db = $settings['db'];
$iconBaseUrl = rtrim((string)($settings['icon_base_url'] ?? 'https://api.annodomini.app'), '/');
$iconsDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'icons';

if (!is_dir($iconsDir)) {
    if (!mkdir($iconsDir, 0775, true) && !is_dir($iconsDir)) {
        fwrite(STDERR, "FAIL: could not create $iconsDir\n");
        exit(2);
    }
}

echo "Anno Domini — Icon extraction\n";
echo "===============================\n";
echo "DB:       {$db['database']}@{$db['host']}\n";
echo "Icons →   $iconsDir\n";
echo "URL base: $iconBaseUrl/icons/{uid}.svg\n\n";

// --- Connect ---

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['database']);
    $pdo = new PDO(
        $dsn,
        $db['username'],
        $db['password'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "FAIL: could not connect: " . $e->getMessage() . "\n");
    exit(1);
}

// --- Read all sets with an icon ---

$rows = $pdo->query('SELECT uid, icon FROM game_set WHERE icon IS NOT NULL AND icon <> ""')->fetchAll();
echo "Found " . count($rows) . " sets with icon data.\n\n";

$updateStmt = $pdo->prepare('UPDATE game_set SET icon_url = :url WHERE uid = :uid');

$written = 0;
$skipped = 0;
$failed = 0;

foreach ($rows as $row) {
    $uid = (int)$row['uid'];
    $base64 = (string)$row['icon'];

    $svg = base64_decode($base64, true);
    if ($svg === false || $svg === '' || !str_contains($svg, '<svg')) {
        echo "  SKIP uid=$uid (not a valid base64 SVG)\n";
        $skipped++;
        continue;
    }

    $path = $iconsDir . DIRECTORY_SEPARATOR . $uid . '.svg';
    $ok = @file_put_contents($path, $svg);
    if ($ok === false) {
        fwrite(STDERR, "  FAIL uid=$uid (could not write $path)\n");
        $failed++;
        continue;
    }

    $url = $iconBaseUrl . '/icons/' . $uid . '.svg';
    $updateStmt->execute([':url' => $url, ':uid' => $uid]);

    echo "  OK   uid=$uid → $url (" . strlen($svg) . " bytes)\n";
    $written++;
}

echo "\nSummary: $written written, $skipped skipped, $failed failed\n";

if ($failed > 0) {
    exit(2);
}
exit(0);
