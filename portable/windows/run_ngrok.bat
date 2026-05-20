@echo off
setlocal enabledelayedexpansion

REM ============================================================
REM Archive Viewer - Lanceur Ngrok pour Windows
REM ============================================================

set PROJECT_ROOT=%~dp0..\..\
cd /d "%PROJECT_ROOT%"

echo === Archive Viewer (GED-MEF) - Tunnel Ngrok ===

REM 1. Vérification de ngrok.exe
set NGROK_EXE=ngrok.exe
if not exist "%NGROK_EXE%" (
    echo [ERREUR] ngrok.exe est introuvable a la racine du projet.
    echo Veuillez le telecharger sur https://ngrok.com/download
    pause
    exit /b 1
)

REM 2. Récupération du port depuis env.bat
set WEB_PORT=8000
if exist "portable\windows\env.bat" (
    for /f "tokens=2 delims==" %%a in ('findstr "WEB_PORT" portable\windows\env.bat') do set WEB_PORT=%%a
)

echo [INFO] Ouverture du tunnel sur le port %WEB_PORT%...
echo [INFO] Si vous utilisez Apache (XAMPP/WAMP), assurez-vous de mettre le port 80.
echo.

"%NGROK_EXE%" http %WEB_PORT%

pause
