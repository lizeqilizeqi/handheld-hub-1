@echo off
chcp 65001 >nul
title Handheld Hub - Start
setlocal

set "ROOT=%~dp0"
cd /d "%ROOT%"

echo.
echo ========================================
echo   掌机百科 - 一键启动
echo ========================================
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%ROOT%scripts\start-project.ps1"
set "EC=%ERRORLEVEL%"

echo.
if not "%EC%"=="0" (
    echo [失败] 启动未完成，请查看上方错误信息。
    echo.
    pause
    exit /b %EC%
)

echo [成功] 项目已启动。
echo   后台: http://localhost:8080/admin/  （左侧有概览/掌机/抓取/翻译/Blogger）
echo   前台: http://localhost:8080/en/handhelds  （对外展示页，给访客和 SEO 用）
echo.
echo 关闭本窗口不会停止 Docker 容器。
echo 若要停止项目，请运行 stop-project.bat
echo.
pause
exit /b 0
