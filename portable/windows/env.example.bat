@echo off
REM Copie ce fichier en env.bat et modifie si besoin.

REM Dossier app (le projet)
set APP_DIR=%~dp0app

REM Ports
set WEB_HOST=127.0.0.1
set WEB_PORT=8000
set PG_PORT=5432

REM DB (PostgreSQL)
set DB_HOST=127.0.0.1
set DB_PORT=%PG_PORT%
set DB_NAME=archive_db
set DB_USER=postgres
set DB_PASS=

REM Archive par défaut (optionnel)
REM set ARCHIVE_ROOT=C:\archive

REM Scanner Go (optionnel)
REM set GO_SCANNERFS_PATH=%APP_DIR%\bin\scannerfs.exe

