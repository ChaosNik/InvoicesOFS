@echo off
setlocal

cd /d "%~dp0"

set "START_VITE=0"
if /I "%~1"=="dev" set "START_VITE=1"

if not exist ".tools\php\php.exe" (
    echo Local PHP was not found at ".tools\php\php.exe".
    echo Please restore the bundled tools folder first.
    exit /b 1
)

if not exist "vendor\autoload.php" (
    echo Missing PHP dependencies. Run first-time setup:
    echo   .\.tools\php\php.exe .\.tools\composer\composer.phar install
    exit /b 1
)

if "%START_VITE%"=="1" (
    if not exist "node_modules" (
        echo Missing frontend dependencies. Run first-time setup:
        echo   npm install
        exit /b 1
    )
) else (
    if not exist "public\build\manifest.json" (
        echo Missing built frontend assets. Run:
        echo   npm install
        echo   npm run build
        exit /b 1
    )
    if exist "public\hot" del /q "public\hot" >nul 2>&1
)

echo Starting InvoiceShelf...
echo Backend: http://127.0.0.1:8000/
if "%START_VITE%"=="1" (
    echo Frontend live reload: http://127.0.0.1:5173/
)
echo.

start "InvoiceShelf Backend" powershell -NoExit -Command "Set-Location '%CD%'; .\.tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000"

if "%START_VITE%"=="1" (
    start "InvoiceShelf Frontend" powershell -NoExit -Command "Set-Location '%CD%'; npm run dev -- --host 127.0.0.1 --port 5173"
)

echo Waiting for backend to respond...
powershell -NoProfile -Command "$deadline=(Get-Date).AddSeconds(20); while((Get-Date) -lt $deadline){ try { $r=Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8000/' -TimeoutSec 1; if($r.StatusCode -ge 200){ exit 0 } } catch {}; Start-Sleep -Milliseconds 250 }; exit 1"

if errorlevel 1 (
    echo Backend is still starting. Check the 'InvoiceShelf Backend' window.
    exit /b 1
)

echo InvoiceShelf is ready: http://127.0.0.1:8000/
