@echo off
setlocal enabledelayedexpansion

:: Database Configuration
set DB_NAME=tesda_db
set DB_USER=root
set DB_PASS=
set DB_PORT=3307
set BACKUP_DIR=backups

:: Timestamp Configuration
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /format:list') do set datetime=%%I
set TIMESTAMP=%datetime:~0,4%-%datetime:~4,2%-%datetime:~6,2%_%datetime:~8,2%%datetime:~10,2%

:: Create backup directory if it doesn't exist
if not exist "%BACKUP_DIR%" (
    echo Creating backup directory: %BACKUP_DIR%
    mkdir "%BACKUP_DIR%"
)

set BACKUP_FILE=%BACKUP_DIR%\%DB_NAME%_backup_%TIMESTAMP%.sql

echo --- DATABASE BACKUP UTILITY ---
echo Database: %DB_NAME%
echo Port:     %DB_PORT%
echo Target:   %BACKUP_FILE%
echo.

:: Run mysqldump
"C:\xampp\mysql\bin\mysqldump.exe" --user=%DB_USER% --port=%DB_PORT% --databases %DB_NAME% > "%BACKUP_FILE%"

if %ERRORLEVEL% equ 0 (
    echo [SUCCESS] Backup created successfully at %BACKUP_FILE%
) else (
    echo [ERROR] Backup failed. Please ensure MySQL is running on port %DB_PORT%.
    pause
)

timeout /t 5
