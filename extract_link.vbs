Dim fso, link, line, ts, WshShell
Set fso = CreateObject("Scripting.FileSystemObject")
Set WshShell = CreateObject("WScript.Shell")

Const RAW_LOG    = "C:\xampp\htdocs\TesdaBCAT-1.02\cloudflare.log"
Dim LINK_LOG     : LINK_LOG = WshShell.ExpandEnvironmentStrings("%USERPROFILE%") & "\Desktop\tunnel_link.log"
Dim DESKTOP_FILE : DESKTOP_FILE = WshShell.ExpandEnvironmentStrings("%USERPROFILE%") & "\Desktop\TesdaBCAT_Link.txt"

link = ""

If fso.FileExists(RAW_LOG) Then
    Dim f, content, lines, i, parts, j
    Set f = fso.OpenTextFile(RAW_LOG, 1)
    content = f.ReadAll
    f.Close

    lines = Split(content, vbLf)
    For i = 0 To UBound(lines)
        line = Trim(lines(i))
        If InStr(line, "trycloudflare.com") > 0 Then
            parts = Split(line, " ")
            For j = 0 To UBound(parts)
                If InStr(parts(j), "trycloudflare.com") > 0 Then
                    link = Replace(Trim(parts(j)), "|", "")
                    link = Trim(link)
                    ' Append the system root path so the copied link is ready to use
                    If link <> "" Then link = link & "/TesdaBCAT-1.02"
                    Exit For
                End If
            Next
            If link <> "" Then Exit For
        End If
    Next
End If

If link = "" Then WScript.Quit

' Only append if this URL isn't already the last logged entry
Dim lastLine
lastLine = ""
If fso.FileExists(LINK_LOG) Then
    Dim lf
    Set lf = fso.OpenTextFile(LINK_LOG, 1)
    Dim allLines
    allLines = Split(lf.ReadAll, vbNewLine)
    lf.Close
    Dim k
    For k = UBound(allLines) To 0 Step -1
        If Trim(allLines(k)) <> "" Then
            lastLine = allLines(k)
            Exit For
        End If
    Next
End If

If InStr(lastLine, link) > 0 Then WScript.Quit

ts = Now()
Dim logFile
Set logFile = fso.OpenTextFile(LINK_LOG, 8, True)
logFile.WriteLine link
logFile.Close

' Overwrite Desktop file with just the current link (easy copy-paste)
Dim deskFile
Set deskFile = fso.OpenTextFile(DESKTOP_FILE, 2, True)
deskFile.WriteLine "===== TESDA-BCAT Cloudflare Link ====="
deskFile.WriteLine ""
deskFile.WriteLine link
deskFile.WriteLine ""
deskFile.WriteLine "Generated: " & ts
deskFile.WriteLine "====================================="
deskFile.Close

Set fso = Nothing
Set WshShell = Nothing
