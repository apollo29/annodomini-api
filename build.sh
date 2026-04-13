#!/bin/bash
set -e

VERSION=$(date +%Y%m%d-%H%M)
OUTPUT="annodomini-api-${VERSION}.zip"

echo "==> Building Anno Domini API..."
echo ""

# Dependencies (ohne Dev-Pakete)
composer install --no-dev --optimize-autoloader --no-interaction

# Alte ZIPs aufraeumen
rm -f annodomini-api-*.zip

# Temporaeres Build-Verzeichnis
BUILDDIR=$(mktemp -d)
trap "rm -rf $BUILDDIR" EXIT

echo ""
echo "==> Kopiere Dateien..."

# Dateien kopieren (rsync oder cp Fallback)
if command -v rsync &> /dev/null; then
    rsync -a \
        --exclude='.git' --exclude='.github' --exclude='.claude' \
        --exclude='tests' --exclude='.idea' --exclude='.vscode' \
        --exclude='.phpunit.cache' --exclude='*.cache' \
        --exclude='*.zip' --exclude='build.sh' --exclude='build.bat' \
        --exclude='deploy.sh' \
        ./ "$BUILDDIR/"
else
    cp -r . "$BUILDDIR/"
    rm -rf "$BUILDDIR/.git" "$BUILDDIR/.github" "$BUILDDIR/.claude"
    rm -rf "$BUILDDIR/tests" "$BUILDDIR/.idea" "$BUILDDIR/.vscode"
    rm -rf "$BUILDDIR/.phpunit.cache"
    rm -f "$BUILDDIR"/*.zip "$BUILDDIR/build.sh" "$BUILDDIR/build.bat" "$BUILDDIR/deploy.sh"
fi

# Ausgeschlossene Config-Dateien entfernen
rm -f "$BUILDDIR/config/env.php"
rm -f "$BUILDDIR/config/local.dev.php"
rm -f "$BUILDDIR/config/local.test.php"
rm -f "$BUILDDIR/config/local.github.php"
rm -f "$BUILDDIR/config/local.scrutinizer.php"
rm -f "$BUILDDIR"/logs/*.log 2>/dev/null || true
rm -f "$BUILDDIR"/tmp/rate_limit/*.json 2>/dev/null || true

echo "==> Erstelle ZIP..."

# ZIP erstellen - verschiedene Methoden je nach Plattform
if command -v zip &> /dev/null; then
    (cd "$BUILDDIR" && zip -r - .) > "$OUTPUT"
elif command -v powershell.exe &> /dev/null; then
    # Windows / Git Bash
    WINPATH=$(cygpath -w "$BUILDDIR")
    WINOUT=$(cygpath -w "$(pwd)/$OUTPUT")
    powershell.exe -NoProfile -Command \
        "Compress-Archive -Path '$WINPATH\*' -DestinationPath '$WINOUT' -Force"
elif command -v powershell &> /dev/null; then
    powershell -NoProfile -Command \
        "Compress-Archive -Path '$BUILDDIR/*' -DestinationPath '$(pwd)/$OUTPUT' -Force"
else
    echo "FEHLER: Weder zip noch PowerShell gefunden."
    exit 1
fi

# Ergebnis
SIZE=$(wc -c < "$OUTPUT" 2>/dev/null || echo "0")
SIZE_KB=$((SIZE / 1024))

echo ""
echo "==> Fertig: $OUTPUT (${SIZE_KB} KB)"
echo ""
echo "Naechste Schritte:"
echo "  1. ZIP in Plesk Dateimanager nach httpdocs/ hochladen"
echo "  2. Rechtsklick -> 'Dateien extrahieren' nach annodomini-api/"
echo "  3. config/env.php erstellen (falls Erstinstallation)"
echo "  4. Testen: curl https://api.annodomini.app/ping"
