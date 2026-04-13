@echo off
setlocal enabledelayedexpansion

:: Versionsnummer aus Datum/Zeit
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set DATETIME=%%I
set VERSION=%DATETIME:~0,8%-%DATETIME:~8,4%
set OUTPUT=annodomini-api-%VERSION%.zip

echo ==========================================================
echo   Anno Domini API - Build
echo ==========================================================
echo.

:: Dependencies (ohne Dev-Pakete)
echo [1/4] Composer install...
call composer install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 (
    echo FEHLER: composer install fehlgeschlagen
    exit /b 1
)
echo.

:: Alte ZIPs aufraeumen
del /q annodomini-api-*.zip 2>nul

:: Temporaeres Verzeichnis
set BUILDDIR=%TEMP%\annodomini-build-%RANDOM%
mkdir "%BUILDDIR%"

:: Dateien kopieren
echo [2/4] Dateien kopieren...
robocopy . "%BUILDDIR%" /E /NFL /NDL /NJH /NJS /NP ^
    /XD .git .github .claude tests .idea .vscode .phpunit.cache tmp\rate_limit ^
    /XF *.cache *.zip build.sh build.bat deploy.sh > nul

:: Robocopy gibt 0-7 bei Erfolg zurueck
if errorlevel 8 (
    echo FEHLER: Dateien kopieren fehlgeschlagen
    rmdir /s /q "%BUILDDIR%" 2>nul
    exit /b 1
)

:: Ausgeschlossene Config-Dateien entfernen
del /q "%BUILDDIR%\config\env.php" 2>nul
del /q "%BUILDDIR%\config\local.dev.php" 2>nul
del /q "%BUILDDIR%\config\local.test.php" 2>nul
del /q "%BUILDDIR%\config\local.github.php" 2>nul
del /q "%BUILDDIR%\config\local.scrutinizer.php" 2>nul

:: Logs entfernen (Verzeichnisse behalten)
del /q "%BUILDDIR%\logs\*.log" 2>nul

:: Leere Verzeichnisse sicherstellen
if not exist "%BUILDDIR%\logs" mkdir "%BUILDDIR%\logs"
if not exist "%BUILDDIR%\tmp" mkdir "%BUILDDIR%\tmp"
if not exist "%BUILDDIR%\tmp\rate_limit" mkdir "%BUILDDIR%\tmp\rate_limit"

:: ZIP erstellen
echo [3/4] ZIP erstellen...
powershell -NoProfile -Command ^
    "Compress-Archive -Path '%BUILDDIR%\*' -DestinationPath '%OUTPUT%' -Force"

if errorlevel 1 (
    echo FEHLER: ZIP erstellen fehlgeschlagen
    rmdir /s /q "%BUILDDIR%" 2>nul
    exit /b 1
)

:: Aufraeumen
echo [4/4] Aufraeumen...
rmdir /s /q "%BUILDDIR%"

:: Ergebnis
for %%A in (%OUTPUT%) do set SIZE=%%~zA
set /a SIZEKB=!SIZE! / 1024

echo.
echo ==========================================================
echo   Fertig: %OUTPUT% (!SIZEKB! KB)
echo ==========================================================
echo.
echo   Naechste Schritte:
echo     1. ZIP in Plesk Dateimanager nach httpdocs/ hochladen
echo     2. Rechtsklick -^> "Dateien extrahieren" nach annodomini-api/
echo     3. config/env.php erstellen (falls Erstinstallation)
echo     4. Testen: curl https://api.annodomini.app/ping
echo.

endlocal
