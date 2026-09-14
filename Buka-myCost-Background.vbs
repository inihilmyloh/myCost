Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' 1. Cek dan Jalankan MySQL jika belum berjalan
Set objWMIService = GetObject("winmgmts:\\.\root\cimv2")
Set colProcesses = objWMIService.ExecQuery("Select * from Win32_Process Where Name = 'mysqld.exe'")

If colProcesses.Count = 0 Then
    If fso.FileExists("D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe") Then
        WshShell.Run """D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe""", 0, False
        WScript.Sleep 1500
    End If
End If

' 2. Jalankan Server Laravel myCost pada Port 7777 di Background (Silent / Tanpa Jendela Hitam)
WshShell.Run "cmd /c cd /d d:\laragon\www\myCost && php artisan serve --host=0.0.0.0 --port=7777", 0, False

' 3. Buka Browser ke Port 7777
WScript.Sleep 1500
WshShell.Run "http://localhost:7777"
