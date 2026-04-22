# Anno Domini API - Deployment auf Metanet Plesk

Anleitung fuer das Deployment der API auf [Metanet.ch](https://www.metanet.ch) Shared Hosting mit Plesk.

---

## Voraussetzungen

- Metanet Hosting-Abo mit Plesk
- Domain `api.annodomini.app` (oder Subdomain) eingerichtet
- SSH-Zugang aktiviert ([Anleitung](https://support.metanet.ch/713))

---

## Schritt 1: Plesk konfigurieren

### 1.1 Domain einrichten

1. Plesk einloggen -> **Websites & Domains**
2. **Domain hinzufuegen** -> `api.annodomini.app`
3. Hosting-Typ: **Website-Hosting**
4. **Dokumentenstamm** auf `/annodomini-api/public` setzen
   (Wichtig: Plesk muss auf das `public/`-Unterverzeichnis zeigen, nicht auf das Projekt-Root)

> Referenz: [Domain als Hosting einrichten](https://support.metanet.ch/582)

### 1.2 PHP-Version einstellen

1. **Websites & Domains** -> Domain aufklappen -> **PHP-Einstellungen**
2. PHP-Version: **8.2** oder **8.3**
3. Ausfuehrungsmethode: **FastCGI Application (Apache)** (Standard, empfohlen)
4. PHP-Direktiven anpassen:
   - `memory_limit`: `256M`
   - `max_execution_time`: `60`
   - `upload_max_filesize`: `10M`
   - `error_reporting`: `E_ALL & ~E_DEPRECATED & ~E_STRICT`
   - `opcache.enable`: `On`

> Referenz: [PHP Version aendern](https://support.metanet.ch/606)

### 1.3 SSL aktivieren

1. **Websites & Domains** -> Domain -> **SSL/TLS-Zertifikate**
2. **Let's Encrypt** auswaehlen und installieren

### 1.4 SSH aktivieren

1. **Websites & Domains** -> **Webhosting-Zugang**
2. Serverzugriff: **/bin/bash (chrooted)** auswaehlen
3. Passwort setzen

> Referenz: [SSH-Zugriff](https://support.metanet.ch/713)

---

## Schritt 2: Per SSH deployen

### 2.1 Verbinden

```bash
# Metanet SSH verwendet Port 2121
ssh BENUTZERNAME@SERVER -p2121
```

### 2.2 Code hochladen

```bash
# In das Webverzeichnis wechseln
cd httpdocs

# Projekt klonen
git clone git@github.com:apollo29/annodomini-api.git annodomini-api
cd annodomini-api

# PHP 8.3 verwenden (Metanet stellt mehrere Versionen bereit)
/opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install \
    --no-dev --optimize-autoloader
```

> Hinweis: Auf Metanet ist Composer global als `/usr/lib/plesk-9.0/composer.phar` verfuegbar.
> Die PHP-Binary liegt unter `/opt/plesk/php/8.3/bin/php`.

### 2.3 Kurzform mit Alias

Fuer einfacheres Arbeiten, am Anfang der Session:

```bash
alias php83="/opt/plesk/php/8.3/bin/php"
alias composer="php83 /usr/lib/plesk-9.0/composer.phar"

# Dann einfach:
composer install --no-dev --optimize-autoloader
```

---

## Alternative: Lokaler Build + ZIP Upload (ohne SSH/Git)

Falls kein SSH oder Git auf dem Server verfuegbar ist, kann die API lokal gebaut und als ZIP hochgeladen werden.

### Lokaler Build

```bash
# Im Projekt-Verzeichnis
cd annodomini-api

# Dependencies installieren (ohne Dev-Pakete)
composer install --no-dev --optimize-autoloader

# env.php fuer Produktion erstellen
cp config/env.php config/env.prod.php
# -> config/env.prod.php bearbeiten (DB-Credentials, API Key)

# ZIP erstellen (ohne unnoetige Dateien)
zip -r annodomini-api.zip . \
    -x ".git/*" \
    -x ".github/*" \
    -x "tests/*" \
    -x "logs/*.log" \
    -x "tmp/rate_limit/*" \
    -x ".idea/*" \
    -x ".vscode/*" \
    -x "config/env.php" \
    -x "config/local.dev.php" \
    -x "config/local.test.php" \
    -x "config/local.github.php" \
    -x "config/local.scrutinizer.php" \
    -x "*.cache" \
    -x ".phpunit.cache/*"
```

Oder als wiederverwendbares Script:

### build.sh

```bash
#!/bin/bash
set -e

VERSION=$(date +%Y%m%d-%H%M)
OUTPUT="annodomini-api-${VERSION}.zip"

echo "==> Building Anno Domini API..."

# Dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# ZIP erstellen
zip -r "$OUTPUT" . \
    -x ".git/*" \
    -x ".github/*" \
    -x "tests/*" \
    -x "logs/*.log" \
    -x "tmp/rate_limit/*" \
    -x ".idea/*" \
    -x ".vscode/*" \
    -x "config/env.php" \
    -x "config/local.dev.php" \
    -x "config/local.test.php" \
    -x "config/local.github.php" \
    -x "config/local.scrutinizer.php" \
    -x "*.cache" \
    -x ".phpunit.cache/*" \
    -x "build.sh" \
    -x "deploy.sh" \
    -x "*.zip"

echo "==> Fertig: $OUTPUT ($(du -h $OUTPUT | cut -f1))"
```

```bash
chmod +x build.sh
./build.sh
# -> annodomini-api-20260413-1430.zip
```

### Upload ueber Plesk Dateimanager

1. Plesk einloggen -> **Dateien**
2. In `httpdocs/` navigieren
3. **Hochladen** -> `annodomini-api-XXXXXXXX.zip` auswaehlen
4. Nach dem Upload: Rechtsklick auf die ZIP -> **Dateien extrahieren**
5. Zielverzeichnis: `httpdocs/annodomini-api/`

### Upload ueber FTP

```bash
# Per FTP-Client (FileZilla, WinSCP, etc.)
# Host: SERVER
# Port: 21 (oder 990 fuer FTPS)
# Benutzer/Passwort: wie in Plesk konfiguriert

# ZIP hochladen nach /httpdocs/
# Dann per Plesk Dateimanager extrahieren
```

### Nach dem Upload: env.php einrichten

1. Plesk -> **Dateien** -> `httpdocs/annodomini-api/config/`
2. Falls `env.prod.php` im ZIP enthalten:
   - Umbenennen: `env.prod.php` -> `env.php`
3. Falls nicht: Neue Datei `env.php` erstellen mit folgendem Inhalt:

```php
<?php
$settings['db']['host'] = '127.0.0.1';
$settings['db']['database'] = 'annodomini';
$settings['db']['username'] = 'DB_BENUTZER';
$settings['db']['password'] = 'DB_PASSWORT';

$apiKey = 'DEIN-SICHERER-API-KEY';
$settings['apikey'] = [
    'api_key' => $apiKey,
];
```

### Updates per ZIP

Bei Updates denselben Prozess wiederholen:

1. Lokal `./build.sh` ausfuehren
2. ZIP hochladen und extrahieren (ueberschreiben)
3. **Wichtig:** `config/env.php` wird NICHT ueberschrieben (ist im ZIP ausgeschlossen)
4. Testen: `curl https://api.annodomini.app/ping`

---

## Schritt 3: Datenbank einrichten

### 3.1 Datenbank in Plesk erstellen

1. **Websites & Domains** -> **Datenbanken** -> **Datenbank hinzufuegen**
2. Name: `annodomini`
3. Datenbankbenutzer und Passwort vergeben

### 3.2 Schema importieren

**Option A: Per Plesk phpMyAdmin**

1. **Datenbanken** -> `annodomini` -> **phpMyAdmin**
2. Tab **Importieren** -> `database/annodomini.sql` hochladen

**Option B: Per SSH**

```bash
# Wichtig bei Metanet: 127.0.0.1 statt localhost verwenden!
mysql -u DBUSER -p -h 127.0.0.1 annodomini < database/annodomini.sql
```

---

## Schritt 4: Environment konfigurieren

```bash
cd ~/httpdocs/annodomini-api
nano config/env.php
```

```php
<?php

// Database - WICHTIG: Bei Metanet 127.0.0.1 statt localhost verwenden!
$settings['db']['host'] = '127.0.0.1';
$settings['db']['database'] = 'annodomini';
$settings['db']['username'] = 'DB_BENUTZER';
$settings['db']['password'] = 'DB_PASSWORT';

// API Key generieren: php -r "echo bin2hex(random_bytes(32));"
$apiKey = 'HIER-SICHEREN-KEY-EINSETZEN';
$settings['apikey'] = [
    'api_key' => $apiKey,
];
```

> **Wichtig:** Metanet verwendet `127.0.0.1` statt `localhost` fuer MySQL-Verbindungen.

---

## Schritt 5: Verzeichnisse vorbereiten

```bash
cd ~/httpdocs/annodomini-api

# Schreibbare Verzeichnisse
chmod -R 775 logs/ tmp/
mkdir -p tmp/rate_limit
```

---

## Schritt 6: DocumentRoot pruefen

Der Plesk-Dokumentenstamm muss auf `annodomini-api/public` zeigen.

**In Plesk pruefen:**
1. **Websites & Domains** -> Domain-Einstellungen
2. Dokumentenstamm: `/httpdocs/annodomini-api/public`

Falls der Dokumentenstamm nicht geaendert werden kann, die `.htaccess` im httpdocs-Root verwenden:

```bash
# ~/httpdocs/.htaccess
nano ~/httpdocs/.htaccess
```

```apache
RewriteEngine On
RewriteRule ^(.*)$ annodomini-api/public/$1 [L]
```

---

## Schritt 7: Testen

```bash
# Health Check
curl https://api.annodomini.app/ping
# Erwartet: {"success":true}

# Sync testen
curl -H "Authorization: Bearer DEIN-API-KEY" \
     "https://api.annodomini.app/v6/sync?since=0"
```

---

## Updates deployen

### Manuell per SSH

```bash
ssh BENUTZERNAME@SERVER -p2121

cd ~/httpdocs/annodomini-api
git pull origin main
/opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install \
    --no-dev --optimize-autoloader
rm -f tmp/rate_limit/*.json
```

### Deploy-Script (vom lokalen Rechner)

Erstelle `deploy.sh` im Projekt-Root:

```bash
#!/bin/bash
set -e

USER="BENUTZERNAME"
HOST="SERVER"
PORT=2121
PATH_REMOTE="httpdocs/annodomini-api"

echo "==> Deploying Anno Domini API to Metanet..."

ssh -p$PORT $USER@$HOST << 'DEPLOY'
    cd ~/httpdocs/annodomini-api

    git pull origin main

    /opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install \
        --no-dev --optimize-autoloader --no-interaction

    chmod -R 775 logs/ tmp/
    rm -f tmp/rate_limit/*.json

    echo "==> Fertig! $(git log --oneline -1)"
DEPLOY
```

```bash
chmod +x deploy.sh
./deploy.sh
```

### GitHub Actions Auto-Deploy

```yaml
name: Deploy to Metanet

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: slim_skeleton_test
        ports: ['3306:3306']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo, pdo_mysql, intl
      - run: composer install
      - run: composer test
        env:
          APP_ENV: github

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.METANET_HOST }}
          username: ${{ secrets.METANET_USER }}
          key: ${{ secrets.METANET_SSH_KEY }}
          port: 2121
          script: |
            cd ~/httpdocs/annodomini-api
            git pull origin main
            /opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install \
                --no-dev --optimize-autoloader
            chmod -R 775 logs/ tmp/
            rm -f tmp/rate_limit/*.json
```

GitHub Secrets: `METANET_HOST`, `METANET_USER`, `METANET_SSH_KEY`

---

## Troubleshooting

### 500 Internal Server Error

```bash
# Logs pruefen
tail -20 ~/httpdocs/annodomini-api/logs/app-$(date +%Y-%m-%d).log

# PHP-Fehlerlog pruefen (Plesk)
tail -20 ~/logs/error_log
```

### Authorization Header kommt nicht an

Die `.htaccess` im `public/`-Verzeichnis leitet den Authorization-Header weiter. Falls das nicht funktioniert:

```bash
# Pruefen ob mod_rewrite aktiv ist
cat ~/httpdocs/annodomini-api/public/.htaccess
```

Die Zeilen muessen vorhanden sein:
```apache
SetEnvIf Authorization .+ HTTP_AUTHORIZATION=$0
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]
```

### MySQL-Verbindung schlaegt fehl

Bei Metanet **immer** `127.0.0.1` statt `localhost` in `config/env.php` verwenden:

```php
$settings['db']['host'] = '127.0.0.1';  // NICHT 'localhost'
```

### Composer findet PHP nicht

PHP-Binary explizit angeben:

```bash
/opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install
```

---

## Metanet-spezifische Hinweise

| Thema | Detail |
|-------|--------|
| SSH-Port | `2121` (nicht Standard-22) |
| PHP-Pfad | `/opt/plesk/php/8.3/bin/php` |
| Composer | `/usr/lib/plesk-9.0/composer.phar` |
| MySQL-Host | `127.0.0.1` (nicht `localhost`) |
| DocumentRoot | Ueber Plesk-GUI aenderbar |
| SSL | Let's Encrypt ueber Plesk |
| Cron Jobs | Ueber Plesk -> **Geplante Aufgaben** |

---

## Datenbank-Migrationen

Migrations liegen unter `database/migrations/` und werden manuell per `mysql`
oder Plesk phpMyAdmin eingespielt.

### Icons als statische SVGs (Issue #196)

Seit v6 werden Set-Icons als statische SVG-Dateien unter `/icons/{uid}.svg`
ausgeliefert. Die Sync-Response enthält weiterhin das base64 `icon`-Feld fuer
Rueckwaertskompatibilitaet plus ein neues `icon_url`-Feld.

```bash
# 1. Schema-Migration anwenden (einmalig)
mysql -u DBUSER -p -h 127.0.0.1 annodomini \
    < database/migrations/20260422_add_icon_url_to_game_set.sql

# 2. Bestehende Icons aus base64 extrahieren und unter public/icons/ speichern
/opt/plesk/php/8.3/bin/php bin/extract-icons.php

# 3. Verify
curl https://api.annodomini.app/icons/1.svg   # sollte SVG liefern
```

Das Extraktions-Script ist idempotent — bei Re-Run werden SVGs überschrieben
und `icon_url` konsistent gehalten.

Das Verzeichnis `public/icons/` hat ein eigenes `.htaccess` mit
`Cache-Control: max-age=31536000, immutable`. Icons sind per `{uid}`
inhaltsadressiert — bei Icon-Änderung muss ein Cache-Buster (z.B. `?v={date}`)
angefügt werden oder die `uid` wechseln.

---

## Checkliste Erstinstallation

- [ ] Domain in Plesk eingerichtet
- [ ] PHP 8.2/8.3 + FastCGI eingestellt
- [ ] SSL (Let's Encrypt) aktiviert
- [ ] SSH aktiviert und verbunden (Port 2121)
- [ ] `git clone` im `httpdocs/`-Verzeichnis
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] Datenbank in Plesk erstellt
- [ ] Schema importiert (`database/annodomini.sql`)
- [ ] Alle Migrations aus `database/migrations/` angewendet
- [ ] Icons extrahiert: `php bin/extract-icons.php`
- [ ] `config/env.php` erstellt (DB: `127.0.0.1`, API Key)
- [ ] DocumentRoot zeigt auf `annodomini-api/public`
- [ ] `logs/` und `tmp/` schreibbar (chmod 775)
- [ ] `public/icons/` schreibbar fuer PHP-User (chmod 775)
- [ ] `curl https://api.annodomini.app/ping` -> `{"success":true}`
- [ ] `curl https://api.annodomini.app/icons/1.svg` -> SVG-Inhalt

## Checkliste Update

- [ ] `git pull origin main`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] Neue Migrations aus `database/migrations/` anwenden (falls vorhanden)
- [ ] Bei neuen/geänderten Icons: `php bin/extract-icons.php`
- [ ] `rm -f tmp/rate_limit/*.json`
- [ ] Health Check: `/ping`

---

## Referenzen

- [Metanet Plesk: Domain einrichten](https://support.metanet.ch/582)
- [Metanet Plesk: PHP-Version aendern](https://support.metanet.ch/606)
- [Metanet Plesk: SSH-Zugriff](https://support.metanet.ch/713)
- [Metanet Plesk: Einfuehrung](https://support.metanet.ch/589)
