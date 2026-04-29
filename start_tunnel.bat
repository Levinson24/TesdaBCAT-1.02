@echo off
title TESDA-BCAT Tunnel Multi-Option
:menu
cls
echo --------------------------------------------------
echo          TESDA-BCAT TUNNEL MULTI-OPTION
echo --------------------------------------------------
echo.
echo [1] Cloudflare Tunnel (Standard)
echo [2] Localtunnel (Custom: tesdabcat.loca.lt)
echo [3] Run Cloudflare in BACKGROUND
echo [4] Run DUAL TUNNEL in BACKGROUND (Cloudflare + Localtunnel)
echo [5] Show Background Tunnel Links
echo [6] Stop ALL Tunnels
echo [7] Modern UI Tunnel (Port 5173)
echo [8] Exit
echo.
set /p choice="Enter your choice (1-8): "

if "%choice%"=="1" goto cloudflare
if "%choice%"=="2" goto localtunnel
if "%choice%"=="3" goto background
if "%choice%"=="4" goto dual
if "%choice%"=="5" goto showlink
if "%choice%"=="6" goto stop
if "%choice%"=="7" goto modern_ui
if "%choice%"=="8" exit
goto menu

:cloudflare
echo.
echo [INF] Starting Cloudflare...
echo [TIP] Look for the "trycloudflare.com" link!
echo [TIP] Close this window to stop the tunnel.
echo.
npx cloudflared tunnel --url http://localhost:8080
pause
goto menu

:localtunnel
echo.
echo [INF] Starting Localtunnel (Custom: tesdabcat.loca.lt)...
echo [TIP] You must enter your Public IP on your phone to bypass security!
echo.
npx localtunnel --port 8080 --subdomain tesdabcat
pause
goto menu

:background
echo.
echo [INF] Launching Cloudflare in background...
del cloudflare.log >nul 2>&1
echo Set WshShell = CreateObject("WScript.Shell") > start_hidden.vbs
echo WshShell.Run "cmd /c npx cloudflared tunnel --url http://localhost:8080 > cloudflare.log 2>&1", 0 >> start_hidden.vbs
echo Set WshShell = Nothing >> start_hidden.vbs
wscript start_hidden.vbs
timeout /t 2 /nobreak > nul
del start_hidden.vbs
echo [OK] Tunnel started in background.
echo [INF] Waiting for link... (takes ~10 seconds)
timeout /t 10 /nobreak > nul
echo [INF] Saving link to tunnel_link.log...
cscript //nologo "C:\xampp\htdocs\TesdaBCAT-1.02\extract_link.vbs"
goto showlink

:dual
echo.
echo [INF] Launching DUAL TUNNEL in background...
del cloudflare.log >nul 2>&1
del localtunnel.log >nul 2>&1
echo Set WshShell = CreateObject("WScript.Shell") > start_hidden.vbs
:: Start Cloudflare
echo WshShell.Run "cmd /c npx cloudflared tunnel --url http://localhost:8080 > cloudflare.log 2>&1", 0 >> start_hidden.vbs
:: Start Localtunnel
echo WshShell.Run "cmd /c npx localtunnel --port 8080 --subdomain tesdabcat > localtunnel.log 2>&1", 0 >> start_hidden.vbs
echo Set WshShell = Nothing >> start_hidden.vbs
wscript start_hidden.vbs
timeout /t 2 /nobreak > nul
del start_hidden.vbs
echo [OK] Dual tunnel started in background.
echo [INF] Waiting for links... (takes ~10 seconds)
timeout /t 10 /nobreak > nul
echo [INF] Saving link to tunnel_link.log...
cscript //nologo "C:\xampp\htdocs\TesdaBCAT-1.02\extract_link.vbs"
goto showlink

:showlink
echo.
echo --------------------------------------------------
echo          CURRENT TUNNEL LINKS
echo --------------------------------------------------
echo [CLOUDFLARE LINK]:
if exist cloudflare.log (
    type cloudflare.log | find "trycloudflare.com"
    if %errorlevel% neq 0 echo   (Link not found yet. Try again in 5 seconds.)
) else (
    echo   (Cloudflare is not running in background)
)
echo.
echo [SAVED LINK LOG (tunnel_link.log on Desktop)]:
if exist "%USERPROFILE%\Desktop\tunnel_link.log" (
    echo   Last entry:
    powershell -NoProfile -Command "Get-Content \"$env:USERPROFILE\Desktop\tunnel_link.log\" | Select-Object -Last 1"
) else (
    echo   (No saved links yet)
)
echo.
echo [LOCALTUNNEL LINK]:
echo   https://tesdabcat.loca.lt
echo.
echo --------------------------------------------------
echo [H] Hide Menu (Exit script, keep tunnels running)
echo [M] Back to Menu
echo.
set /p post_choice="Choose [H] or [M]: "

if /i "%post_choice%"=="H" exit
if /i "%post_choice%"=="M" goto menu
goto showlink

:stop
echo.
echo [INF] Stopping ALL tunnel processes...
taskkill /f /im cloudflared.exe > nul 2>&1
taskkill /f /im node.exe > nul 2>&1
taskkill /f /im lt.exe > nul 2>&1
echo [OK] All tunnels stopped.
pause
goto menu

:modern_ui
echo.
echo [INF] Starting Modern UI Tunnel (Port 5173)...
echo [TIP] Make sure "npm run dev" is running in the frontend folder!
echo.
npx cloudflared tunnel --url http://localhost:5173
pause
goto menu
