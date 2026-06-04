@echo off
REM AutoThreads — publish worker (Task Scheduler: every 1 minute)
REM Uses full path to WAMP PHP — Task Scheduler does not load your user PATH.
setlocal EnableDelayedExpansion

cd /d "%~dp0.."

set LOGDIR=storage\logs
if not exist "%LOGDIR%" mkdir "%LOGDIR%"

set LOG=%LOGDIR%\cron-publish.log
set RUNNER_LOG=%LOGDIR%\cron-runner.log
echo.>> "%RUNNER_LOG%"
echo ===== Task Scheduler run %date% %time% =====>> "%RUNNER_LOG%"

set PHP_EXE=

REM Optional override: copy php-path.bat.example to php-path.bat and set PHP_EXE=
if exist "%~dp0php-path.bat" call "%~dp0php-path.bat"

REM Auto-detect WAMP (newest versions first)
if not defined PHP_EXE (
  for %%V in (php8.2.26 php8.3.14 php8.1.31 php8.0.30 php8.4.0 php7.4.33 php7.3.33) do (
    if exist "C:\wamp64\bin\php\%%V\php.exe" (
      set PHP_EXE=C:\wamp64\bin\php\%%V\php.exe
      goto :php_found
    )
  )
)

REM Fallback: php on PATH (works in interactive CMD)
if not defined PHP_EXE (
  where php >nul 2>&1
  if not errorlevel 1 (
    for /f "delims=" %%i in ('where php 2^>nul') do (
      set PHP_EXE=%%i
      goto :php_found
    )
  )
)

if not defined PHP_EXE (
  echo ERROR: PHP not found. Create cron\php-path.bat with: set PHP_EXE=C:\wamp64\bin\php\php8.2.26\php.exe>> "%RUNNER_LOG%"
  exit /b 1
)

:php_found
echo Using PHP: !PHP_EXE!>> "%RUNNER_LOG%"

REM CronLogger writes to cron-publish.log — do not redirect stdout here (Windows file lock)
"!PHP_EXE!" cron\publish_posts.php
set EXITCODE=!ERRORLEVEL!

echo Exit code: !EXITCODE!>> "%RUNNER_LOG%"
exit /b !EXITCODE!
