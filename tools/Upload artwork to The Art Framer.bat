@echo off
REM ---------------------------------------------------------------------------
REM  Sends every picture in the Synology Drive artwork folder straight into
REM  The Art Framer's WordPress Media Library.
REM
REM  Double-click this file. That is the whole procedure.
REM
REM  It exists because Windows blocks .ps1 files from running on a double-click
REM  by default, and changing that setting machine-wide to run one script is a
REM  worse idea than bypassing it for this one script.
REM ---------------------------------------------------------------------------
cd /d "%~dp0"
if not exist "upload-folder-to-site.ps1" (
  echo.
  echo   upload-folder-to-site.ps1 is missing.
  echo   Keep both files in the same folder and try again.
  echo.
  pause
  exit /b 1
)
powershell -NoProfile -ExecutionPolicy Bypass -File "upload-folder-to-site.ps1"
if errorlevel 1 pause
