@echo off
title ShopSW Manager - Setup
color 0A
echo.
echo  ============================================
echo    ShopSW Manager - First Time Setup
echo  ============================================
echo.

:: ── Find XAMPP ───────────────────────────────────────────────────────────────
set XAMPP_PATH=
for %%P in (C:\xampp D:\xampp E:\xampp C:\XAMPP D:\XAMPP) do (
    if exist "%%P\xampp-control.exe" (
        set XAMPP_PATH=%%P
        goto :found_xampp
    )
)

echo  [ERROR] XAMPP not found!
echo.
echo  Please install XAMPP first:
echo  1. Go to: https://www.apachefriends.org/download.html
echo  2. Download XAMPP for Windows
echo  3. Install it (keep default location C:\xampp)
echo  4. Run this file again after installing
echo.
pause
exit /b 1

:found_xampp
echo  [OK] Found XAMPP at: %XAMPP_PATH%

:: ── Copy app to htdocs ───────────────────────────────────────────────────────
set APP_DEST=%XAMPP_PATH%\htdocs\NEWSHOPSW

if exist "%APP_DEST%" (
    echo  [OK] App folder already exists, updating files...
) else (
    echo  [..] Creating app folder...
    mkdir "%APP_DEST%"
)

echo  [..] Copying app files...
xcopy /E /Y /Q "%~dp0*" "%APP_DEST%\" >nul 2>&1
echo  [OK] Files copied

:: ── Start Apache and MySQL ───────────────────────────────────────────────────
echo  [..] Starting Apache...
"%XAMPP_PATH%\apache\bin\httpd.exe" -k start >nul 2>&1
timeout /t 2 /nobreak >nul

echo  [..] Starting MySQL...
"%XAMPP_PATH%\mysql\bin\mysqld.exe" --install >nul 2>&1
net start mysql >nul 2>&1
timeout /t 3 /nobreak >nul

:: ── Run database setup ───────────────────────────────────────────────────────
echo  [..] Setting up database...
start "" "http://localhost/NEWSHOPSW/setup.php"
timeout /t 4 /nobreak >nul

:: ── Create desktop shortcut ──────────────────────────────────────────────────
echo  [..] Creating desktop shortcut...
set SHORTCUT_TARGET=%APP_DEST%\Start ShopSW.vbs
set DESKTOP=%USERPROFILE%\Desktop

powershell -NoProfile -Command "$ws = New-Object -ComObject WScript.Shell; $sc = $ws.CreateShortcut('%DESKTOP%\ShopSW Manager.lnk'); $sc.TargetPath = 'wscript.exe'; $sc.Arguments = '\"%SHORTCUT_TARGET%\"'; $sc.WorkingDirectory = '%APP_DEST%'; $sc.IconLocation = '%XAMPP_PATH%\xampp-control.exe,0'; $sc.Description = 'Start ShopSW Manager'; $sc.Save()" >nul 2>&1

echo  [OK] Desktop shortcut created: "ShopSW Manager"

:: ── Done ─────────────────────────────────────────────────────────────────────
echo.
echo  ============================================
echo    Setup Complete!
echo  ============================================
echo.
echo  - A shortcut "ShopSW Manager" is on your Desktop
echo  - Double-click it every time to start the system
echo  - Opening the app now...
echo.

start "" "http://localhost/NEWSHOPSW/"
timeout /t 2 /nobreak >nul

echo  Press any key to close this window.
pause >nul
