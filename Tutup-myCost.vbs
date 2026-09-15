Set WshShell = CreateObject("WScript.Shell")

' 1. Matikan PHP (Laravel) dan MySQL tanpa jendela CMD (Background)
' Angka 0 berarti hidden, True berarti tunggu sampai proses selesai
WshShell.Run "cmd /c taskkill /F /IM php.exe /T", 0, True
WshShell.Run "cmd /c taskkill /F /IM mysqld.exe /T", 0, True

' 2. Cari jendela browser yang tab-nya bernama "localhost" atau "myCost"
Dim tabDitemukan
tabDitemukan = WshShell.AppActivate("myCost - Pengelola") 

' Jika judul tabnya myCost, gunakan baris ini sebagai gantinya:
' tabDitemukan = WshShell.AppActivate("myCost") 

' 3. Jika tab ditemukan, beri jeda sebentar lalu kirim pintasan Ctrl + W
If tabDitemukan Then
    WScript.Sleep 300
    WshShell.SendKeys "^w"
End If