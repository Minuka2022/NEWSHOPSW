Dim fso, shell, scriptDir, xamppPath
Set fso   = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")

' Auto-detect XAMPP: script lives in htdocs\NEWSHOPSW, go up 2 levels
scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
xamppPath = fso.GetParentFolderName(fso.GetParentFolderName(scriptDir))

' ── helpers ────────────────────────────────────────────────────────────────

Function IsRunning(procName)
    IsRunning = False
    Dim p
    For Each p In GetObject("winmgmts:").ExecQuery( _
        "SELECT Name FROM Win32_Process WHERE Name='" & procName & "'")
        IsRunning = True : Exit Function
    Next
End Function

Function WaitForApache(maxSec)
    WaitForApache = False
    Dim i, http
    For i = 1 To maxSec * 2
        On Error Resume Next
        Set http = CreateObject("MSXML2.XMLHTTP")
        http.Open "GET", "http://localhost/", False
        http.Send
        If Err.Number = 0 And http.Status > 0 Then
            WaitForApache = True : Exit Function
        End If
        On Error GoTo 0
        WScript.Sleep 500
    Next
End Function

' ── main ───────────────────────────────────────────────────────────────────

Dim apacheUp, mysqlUp, startedSomething
apacheUp         = IsRunning("httpd.exe")
mysqlUp          = IsRunning("mysqld.exe")
startedSomething = False

If Not mysqlUp Then
    shell.Run "cmd /c """ & xamppPath & "\mysql_start.bat""", 0, False
    startedSomething = True
End If

If Not apacheUp Then
    shell.Run "cmd /c """ & xamppPath & "\apache_start.bat""", 0, False
    startedSomething = True
End If

If startedSomething Then
    ' Timed popup — auto-dismisses after 5 s (user can click OK to skip wait)
    shell.Popup "Starting ShopSW services, please wait...", 5, "ShopSW Manager", 64
    If Not WaitForApache(15) Then
        MsgBox "Apache did not respond after 15 s." & vbCrLf & _
               "Please check XAMPP manually.", vbExclamation, "ShopSW Manager"
        WScript.Quit
    End If
End If

shell.Run "http://localhost/NEWSHOPSW/index.php"
