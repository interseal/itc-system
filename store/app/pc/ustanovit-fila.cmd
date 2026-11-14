@echo off
chcp 65001 >nul
echo Устанавливаю Фила на компьютер...
powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol='Tls12'; $p=Join-Path $env:TEMP 'fil_install.ps1'; Invoke-WebRequest -UseBasicParsing 'https://itcpartner.store/app/pc/install.ps1' -OutFile $p; & $p"
echo.
pause
