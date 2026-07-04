@echo off
title RetroHome NetPlay Server
echo.
echo  ===========================================
echo    RetroHome - Serveur NetPlay (LAN)
echo  ===========================================
echo.

cd /d "%~dp0netplay-server"

REM Verifier que Node.js est installe
where node >nul 2>nul
if errorlevel 1 (
    echo  [ERREUR] Node.js n'est pas installe.
    echo  Telechargez-le sur https://nodejs.org puis relancez ce fichier.
    echo.
    pause
    exit /b 1
)

REM Installer les dependances au premier lancement
if not exist "node_modules" (
    echo  Premiere utilisation : installation des dependances...
    call npm install
    echo.
)

echo  Demarrage du serveur... (laissez cette fenetre ouverte)
echo  Pour arreter : fermez cette fenetre ou appuyez sur Ctrl+C.
echo.
node server.js

pause
