@echo off
setlocal enabledelayedexpansion

REM ============================================================
REM Archive Viewer - Lanceur Portable Windows
REM ============================================================

REM On remonte de deux niveaux pour atteindre la racine du projet
set PROJECT_ROOT=%~dp0..\..\
cd /d "%PROJECT_ROOT%"

echo === Archive Viewer (GED-MEF) - Windows Portable ===

REM 1. Chargement de la configuration
if exist "portable\windows\env.bat" (
  call "portable\windows\env.bat"
) else (
  call "portable\windows\env.example.bat"
)

REM 2. Détection des binaires (priorité au local portable, puis au PATH)
set PHP_EXE=php
if exist "php\php.exe" set PHP_EXE="%PROJECT_ROOT%php\php.exe"

set PG_CTL=pg_ctl
if exist "postgres\bin\pg_ctl.exe" set PG_CTL="%PROJECT_ROOT%postgres\bin\pg_ctl.exe"

set INITDB=initdb
if exist "postgres\bin\initdb.exe" set INITDB="%PROJECT_ROOT%postgres\bin\initdb.exe"

REM 3. Vérification de PHP
"%PHP_EXE%" -v >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] PHP n'est pas installe ou n'est pas dans le dossier /php/
    pause
    exit /b 1
)

REM 4. Gestion de PostgreSQL (si version portable détectée)
if exist "postgres\bin\pg_ctl.exe" (
    set DATA_DIR=%PROJECT_ROOT%data
    if not exist "!DATA_DIR!\PG_VERSION" (
        echo [INFO] Initialisation de la base de donnees...
        if not exist "!DATA_DIR!" mkdir "!DATA_DIR!"
        %INITDB% -D "!DATA_DIR!" -U postgres -A trust
    )
    
    echo [INFO] Demarrage de PostgreSQL sur le port %DB_PORT%...
    %PG_CTL% -D "!DATA_DIR!" -o "-p %DB_PORT% -c listen_addresses=127.0.0.1" start
) else (
    echo [INFO] Utilisation de PostgreSQL systeme (assurez-vous que le service tourne).
)

REM 5. Configuration des variables d'environnement pour PHP
set DB_HOST=%DB_HOST%
set DB_PORT=%DB_PORT%
set DB_NAME=%DB_NAME%
set DB_USER=%DB_USER%
set DB_PASS=%DB_PASS%

REM 6. Lancement du serveur Web
echo [INFO] Lancement du serveur : http://%WEB_HOST%:%WEB_PORT%
echo [INFO] Appuyez sur Ctrl+C pour arreter le serveur.

"%PHP_EXE%" -S %WEB_HOST%:%WEB_PORT% index.php

pause
