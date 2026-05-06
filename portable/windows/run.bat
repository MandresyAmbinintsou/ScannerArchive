@echo off
setlocal enabledelayedexpansion

set ROOT=%~dp0
set APP_DIR=%ROOT%app

if exist "%ROOT%env.bat" (
  call "%ROOT%env.bat"
) else (
  call "%ROOT%env.example.bat"
)

set PHP_EXE=%ROOT%php\php.exe
set PG_CTL=%ROOT%postgres\bin\pg_ctl.exe
set INITDB=%ROOT%postgres\bin\initdb.exe
set PSQL=%ROOT%postgres\bin\psql.exe

if not exist "%APP_DIR%\index.php" (
  echo [ERREUR] Projet introuvable: %APP_DIR%\index.php
  echo Copie ce repo dans %APP_DIR%
  pause
  exit /b 1
)

if not exist "%PHP_EXE%" (
  echo [ERREUR] PHP introuvable: %PHP_EXE%
  echo Place PHP portable dans %ROOT%php\
  pause
  exit /b 1
)

if not exist "%PG_CTL%" (
  echo [ERREUR] PostgreSQL introuvable: %PG_CTL%
  echo Place PostgreSQL portable dans %ROOT%postgres\
  pause
  exit /b 1
)

set DATA_DIR=%ROOT%data
set LOG_DIR=%ROOT%logs
set PG_LOG=%LOG_DIR%\postgres.log
set WEB_LOG=%LOG_DIR%\php-web.log
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%" >NUL 2>&1

REM Init DB si nécessaire
if not exist "%DATA_DIR%\PG_VERSION" (
  echo [INFO] Initialisation PostgreSQL...
  if not exist "%DATA_DIR%" mkdir "%DATA_DIR%" >NUL 2>&1
  "%INITDB%" -D "%DATA_DIR%" -U postgres -A trust > "%PG_LOG%" 2>&1
  if errorlevel 1 (
    echo [ERREUR] initdb a echoue. Voir: %PG_LOG%
    pause
    exit /b 1
  )
)

REM Start PostgreSQL
echo [INFO] Demarrage PostgreSQL (port %PG_PORT%)...
"%PG_CTL%" -D "%DATA_DIR%" -o "-p %PG_PORT% -c listen_addresses=127.0.0.1" -l "%PG_LOG%" start >NUL 2>&1

REM Appliquer schema
echo [INFO] Application schema PostgreSQL...
set PGPASSWORD=%DB_PASS%
"%PSQL%" -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -c "SELECT 1" >NUL 2>&1
if errorlevel 1 (
  echo [INFO] Creation DB %DB_NAME%...
  "%PSQL%" -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d postgres -c "CREATE DATABASE %DB_NAME%;" >> "%PG_LOG%" 2>&1
)
"%PSQL%" -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -f "%APP_DIR%\scripts\schema.pg.sql" >> "%PG_LOG%" 2>&1

REM Export env pour PHP
set DB_HOST=%DB_HOST%
set DB_PORT=%DB_PORT%
set DB_NAME=%DB_NAME%
set DB_USER=%DB_USER%
set DB_PASS=%DB_PASS%
if not "%ARCHIVE_ROOT%"=="" set ARCHIVE_ROOT=%ARCHIVE_ROOT%
if not "%GO_SCANNERFS_PATH%"=="" set GO_SCANNERFS_PATH=%GO_SCANNERFS_PATH%

echo [INFO] Demarrage serveur web PHP: http://%WEB_HOST%:%WEB_PORT%/index.php
pushd "%APP_DIR%"
"%PHP_EXE%" -S %WEB_HOST%:%WEB_PORT% > "%WEB_LOG%" 2>&1
popd

endlocal

