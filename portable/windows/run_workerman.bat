@echo off
setlocal enabledelayedexpansion

REM ============================================================
REM Archive Viewer (GED-MEF) - Lanceur Workerman pour Windows
REM Alternative à Swoole : PHP pur, pas d'extension C
REM ============================================================

title Archive Viewer - Serveur Workerman

cd /d "%~dp0.."

cls
echo.
echo ======================================================
echo   Archive Viewer (GED-MEF) - Serveur Workerman
echo ======================================================
echo.

REM Chargement des variables d'environnement
if exist portable\windows\env.bat (
    call portable\windows\env.bat
) else if exist portable\windows\env.example.bat (
    call portable\windows\env.example.bat
    echo [INFO] Utilisation de env.example.bat
)

REM Détection de PHP
set PHP_EXE=php
if exist portable\windows\php\php.exe set PHP_EXE=%cd%\portable\windows\php\php.exe

REM Vérifier PHP
"%PHP_EXE%" -v >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] PHP n est pas installe ou non trouvable 
    echo.
    echo Essayez:
    echo   1. Ajouter PHP au PATH systeme
    echo   2. Copier PHP portable dans portable\windows\php\
    pause
    exit /b 1
)

echo [-] PHP trouve
echo.

REM Vérifier Composer et dépendances
if not exist vendor\workerman\workerman (
    echo [INFO] Installation de Workerman...
    "%PHP_EXE%" -d memory_limit=512M composer require workerman/workerman
)

echo [-] Dependances OK
echo.
echo [-] Demarrage du serveur Workerman...
echo.
echo     Ports:
echo       - WebSocket : ws://localhost:8000
echo       - HTTP API : http://localhost:8000
echo.
echo     Accédez à : http://localhost:8000
echo.
echo [-] Appuyez sur Ctrl+C pour arreter le serveur
echo.
echo ======================================================
echo.

REM Lancer le serveur Workerman
"%PHP_EXE%" app/workerman_server.php

echo [x] Serveur arrete

pause
