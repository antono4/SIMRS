@echo off
setlocal EnableExtensions
title SIMRS Web - Instalasi Database

rem ====================================================================
rem  SIMRS Web - pemasang database untuk MySQL/MariaDB (XAMPP)
rem
rem  Klik dua kali berkas ini. Tidak perlu mengetik perintah SQL.
rem  Berkas ini mengimpor database\sik.sql sehingga database "sik"
rem  langsung muncul di http://localhost/phpmyadmin
rem
rem  Catatan: dump sik.sql selalu membuat database bernama "sik"
rem  (di dalamnya ada "USE sik"), jadi DB_NAME di bawah sebaiknya
rem  dibiarkan "sik".
rem ====================================================================

cd /d "%~dp0"

rem --- Ubah bila perlu (bawaan mengikuti config.php) ---
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_NAME=sik"
set "DB_USER=root"
set "DB_PASS="

set "SQLFILE=%~dp0database\sik.sql"
set "ERRFILE=%TEMP%\simrs_db_error.txt"
set "OUTFILE=%TEMP%\simrs_db_out.txt"

echo.
echo ============================================================
echo   SIMRS Web - Instalasi Database
echo ============================================================
echo.

rem ---------- 1. Cari mysql.exe ----------
set "MYSQL="
if exist "C:\xampp\mysql\bin\mysql.exe" set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not defined MYSQL if exist "D:\xampp\mysql\bin\mysql.exe" set "MYSQL=D:\xampp\mysql\bin\mysql.exe"
if not defined MYSQL if exist "E:\xampp\mysql\bin\mysql.exe" set "MYSQL=E:\xampp\mysql\bin\mysql.exe"
if not defined MYSQL if exist "%ProgramFiles%\xampp\mysql\bin\mysql.exe" set "MYSQL=%ProgramFiles%\xampp\mysql\bin\mysql.exe"
if not defined MYSQL if exist "%ProgramFiles(x86)%\xampp\mysql\bin\mysql.exe" set "MYSQL=%ProgramFiles(x86)%\xampp\mysql\bin\mysql.exe"
if not defined MYSQL for /f "delims=" %%i in ('where mysql 2^>nul') do if not defined MYSQL set "MYSQL=%%i"

if not defined MYSQL (
  echo [X] mysql.exe tidak ditemukan.
  echo.
  echo     Pastikan XAMPP terpasang, atau tambahkan folder
  echo     C:\xampp\mysql\bin ke PATH Windows.
  echo.
  pause
  exit /b 1
)
echo [1/5] Klien MySQL ditemukan:
echo       %MYSQL%

rem ---------- 2. Periksa berkas SQL ----------
if not exist "%SQLFILE%" (
  echo.
  echo [X] Berkas tidak ditemukan: database\sik.sql
  echo     Simpan berkas ini di dalam folder SIMRS lalu jalankan lagi.
  echo.
  pause
  exit /b 1
)
echo [2/5] Berkas SQL ditemukan: database\sik.sql

rem ---------- 3. Kredensial ----------
echo.
echo [3/5] Kredensial MySQL
echo       Server : %DB_HOST%:%DB_PORT%
echo       User   : %DB_USER%
echo.
set "DB_PASS="
set /p "DB_PASS=      Kata sandi MySQL - kosongkan bila tanpa sandi: "

set "MYSQLARGS=-h %DB_HOST% -P %DB_PORT% -u %DB_USER%"
set "MYSQL_PWD=%DB_PASS%"

"%MYSQL%" %MYSQLARGS% -e "SELECT 1;" >nul 2>"%ERRFILE%"
if errorlevel 1 (
  echo.
  echo [X] Tidak dapat terhubung ke MySQL.
  if exist "%ERRFILE%" type "%ERRFILE%"
  echo.
  echo     Yang sering jadi penyebab:
  echo       - MySQL/MariaDB belum di-Start di XAMPP Control Panel
  echo       - Kata sandi salah
  echo       - Port %DB_PORT% dipakai aplikasi lain
  echo.
  pause
  exit /b 1
)
echo       Terhubung ke MySQL.

rem ---------- 4. Konfirmasi ----------
echo.
echo [4/5] Siap mengimpor
echo       Database tujuan : %DB_NAME%
echo       Isi dump        : 52 tabel + data contoh
echo.
echo       Database %DB_NAME% dibuat bila belum ada. Tabel yang namanya
echo       sama akan ditimpa ulang, jadi aman dijalankan berulang.
echo.
set "LANJUT="
set /p "LANJUT=      Lanjutkan impor? Y/N: "
if /i not "%LANJUT%"=="Y" goto :batal

echo.
echo       Mengimpor... mohon tunggu, bisa 1-3 menit.
echo       Jangan tutup jendela ini.
echo.

"%MYSQL%" %MYSQLARGS% --default-character-set=latin1 < "%SQLFILE%"
if errorlevel 1 (
  echo.
  echo [X] Impor gagal. Pesan galat di atas.
  echo     Bila soal batas waktu, jalankan berkas ini sekali lagi.
  echo.
  pause
  exit /b 1
)

rem ---------- 5. Verifikasi ----------
"%MYSQL%" %MYSQLARGS% -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='%DB_NAME%';" >"%OUTFILE%" 2>nul
set "TABEL=0"
set /p "TABEL="<"%OUTFILE%"
if not defined TABEL set "TABEL=0"

"%MYSQL%" %MYSQLARGS% -N -B -e "SELECT COUNT(*) FROM %DB_NAME%.poliklinik;" >"%OUTFILE%" 2>nul
set "POLI=0"
set /p "POLI="<"%OUTFILE%"
if not defined POLI set "POLI=0"

echo.
echo [5/5] Verifikasi
echo       Tabel terbentuk       : %TABEL% dari 52
echo       Baris tabel poliklinik: %POLI%

if %TABEL% LSS 52 goto :kurang
if %POLI% LSS 1 goto :kurang

echo.
echo ============================================================
echo   BERHASIL
echo ============================================================
echo.
echo   Database "%DB_NAME%" sudah siap dan tampil di phpMyAdmin.
echo.
echo   Buka phpMyAdmin   : http://localhost/phpmyadmin
echo   Buka aplikasi     : http://localhost/SIMRS
echo   Wizard instalasi  : http://localhost/SIMRS/index.php?page=install
echo   Diagnostik        : http://localhost/SIMRS/index.php?page=diagnostik
echo.
set "BUKA="
set /p "BUKA=Buka phpMyAdmin sekarang? Y/N: "
if /i "%BUKA%"=="Y" start "" "http://localhost/phpmyadmin/"
echo.
pause
exit /b 0

:kurang
echo.
echo [X] Impor belum lengkap: %TABEL% dari 52 tabel, poliklinik %POLI% baris.
echo     Jalankan berkas ini sekali lagi - impor aman diulang.
echo     Bila tetap sama, buka:
echo     http://localhost/SIMRS/index.php?page=diagnostik
echo.
pause
exit /b 1

:batal
echo.
echo Dibatalkan. Tidak ada perubahan.
echo.
pause
exit /b 0
