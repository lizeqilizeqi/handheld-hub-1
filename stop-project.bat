@echo off
chcp 65001 >nul
title Handheld Hub - Stop
setlocal

set "ROOT=%~dp0"
cd /d "%ROOT%"

set "PATH=C:\Program Files\Docker\Docker\resources\bin;%PATH%"

echo.
echo 正在停止 Handheld Hub 容器...
docker compose down

if exist "%ROOT%.run\launcher.pid" del /f /q "%ROOT%.run\launcher.pid"

echo.
echo [完成] 项目已停止。
echo.
pause
