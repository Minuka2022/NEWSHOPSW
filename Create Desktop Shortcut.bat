@echo off
setlocal

set "VBS=%~dp0Start ShopSW.vbs"
set "ICON=C:\xampp\xampp-control.exe"

powershell -NoProfile -Command ^
  "$ws = New-Object -ComObject WScript.Shell;" ^
  "$sc = $ws.CreateShortcut([Environment]::GetFolderPath('Desktop') + '\ShopSW Manager.lnk');" ^
  "$sc.TargetPath  = 'wscript.exe';" ^
  "$sc.Arguments   = '\"%VBS%\"';" ^
  "$sc.IconLocation = '%ICON%,0';" ^
  "$sc.Description  = 'Launch ShopSW Manager';" ^
  "$sc.Save()"

echo Shortcut created on Desktop!
pause
