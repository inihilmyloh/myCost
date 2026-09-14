@echo off
title myCost Financial Manager - Port 7777
echo ========================================================
echo       MENJALANKAN myCost (PORT: 7777)
echo ========================================================

:: 1. Cek dan Jalankan MySQL jika belum aktif
echo [1/3] Memeriksa status database MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo       - MySQL sudah aktif.
) else (
    echo       - Menjalankan MySQL di background...
    if exist "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" (
        start "" /B "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe"
        timeout /t 2 /nobreak >nul
    )
)

:: 2. Buka Browser
echo [2/3] Membuka browser ke http://localhost:7777 ...
timeout /t 1 /nobreak >nul
start http://localhost:7777

:: 3. Jalankan Server Laravel
echo [3/3] Server myCost aktif pada port 7777...
echo       Bisa diakses di laptop maupun HP via Wi-Fi!
echo.
echo JANGAN TUTUP JENDELA INI SAAT MENGGUNAKAN APLIKASI
echo (Tekan Ctrl+C jika ingin mematikan server)
echo --------------------------------------------------------
cd /d "d:\laragon\www\myCost"
php artisan serve --host=0.0.0.0 --port=7777
pause
