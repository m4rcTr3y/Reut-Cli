@echo off
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo PHP is not installed or not found in system PATH.
    echo Please install PHP ^(>=7.4^) and ensure it is added to your system PATH.
    echo Download PHP from https://www.php.net/downloads
    echo Add PHP to PATH ^(e.g., C:\php^) in System Environment Variables.
    exit /b 1
)
php "%~dp0Reut" %*