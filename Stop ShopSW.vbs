Dim fso, shell, scriptDir, xamppPath
Set fso   = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")

scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
xamppPath = fso.GetParentFolderName(fso.GetParentFolderName(scriptDir))

shell.Run "cmd /c """ & xamppPath & "\apache_stop.bat""", 0, True
shell.Run "cmd /c """ & xamppPath & "\mysql_stop.bat""",  0, True

MsgBox "ShopSW stopped.", vbInformation, "ShopSW Manager"
