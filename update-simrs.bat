@echo off
setlocal EnableExtensions
title SIMRS Web - Perbarui Aplikasi

rem ====================================================================
rem  SIMRS Web - pembaru aplikasi
rem
rem  Berkas ini mengunduh versi terbaru dari GitHub lalu menimpanya ke
rem  folder aplikasi ini. Berkas yang TIDAK diganggu gugat:
rem
rem    config.php   kredensial database Anda digabungkan ke config baru,
rem                 jadi host, user, dan sandi tetap sama
rem    database\    dump sik.sql bawaan, tidak pernah ditimpa
rem    backup\      salinan berkas lama sebelum diperbarui
rem    .git\        bila folder ini hasil "git clone"
rem
rem  Cara pakai:
rem    - Klik dua kali berkas ini, ATAU
rem    - Jalankan dari halaman login: tombol "Perbarui aplikasi"
rem
rem  Sebelum menimpa, seluruh isi folder dicadangkan ke backup\.
rem  Bila hasilnya bermasalah, jalankan kembali dan pilih menu PULIHKAN.
rem ====================================================================

rem --- Alamat sumber pembaruan (ubah bila memakai fork sendiri) ---
set "REPO=antono4/SIMRS"
set "BRANCH=main"

set "ROOT=%~dp0"
set "BACKUPROOT=%ROOT%backup"
set "WORK=%TEMP%\simrs_update"
set "ZIPFILE=%WORK%\simrs.zip"
set "EXTRACTDIR=%WORK%\isi"
set "ERRFILE=%TEMP%\simrs_update_error.txt"
set "OUTFILE=%TEMP%\simrs_update_out.txt"

cd /d "%ROOT%"

echo.
echo ============================================================
echo   SIMRS Web - Perbarui Aplikasi
echo ============================================================
echo.
echo   Folder aplikasi : %ROOT%
echo   Sumber          : https://github.com/%REPO% (cabang %BRANCH%)
echo.

rem ---------- 0. Menu ----------
echo   Pilihan:
echo     1. Perbarui sekarang (unduh versi terbaru dari GitHub)
echo     2. Pulihkan dari cadangan terakhir
echo     3. Keluar
echo.
set "PILIH="
set /p "PILIH=Pilih 1/2/3: "
if "%PILIH%"=="2" goto :pulihkan
if "%PILIH%"=="3" goto :batal
if not "%PILIH%"=="1" goto :batal

rem ---------- 1. Periksa prasyarat ----------
where powershell >nul 2>&1
if errorlevel 1 (
  echo.
  echo [X] PowerShell tidak ditemukan.
  echo     Windows 10/11 seharusnya sudah punya. Bila tidak ada, unduh
  echo     pembaruan secara manual dari:
  echo     https://github.com/%REPO%/archive/refs/heads/%BRANCH%.zip
  echo.
  pause
  exit /b 1
)
echo [1/7] PowerShell tersedia.

rem Cari PHP: dipakai untuk menggabungkan config.php (kredensial Anda).
set "PHPBIN="
if exist "C:\xampp\php\php.exe" set "PHPBIN=C:\xampp\php\php.exe"
if not defined PHPBIN if exist "D:\xampp\php\php.exe" set "PHPBIN=D:\xampp\php\php.exe"
if not defined PHPBIN if exist "E:\xampp\php\php.exe" set "PHPBIN=E:\xampp\php\php.exe"
if not defined PHPBIN for /f "delims=" %%i in ('where php 2^>nul') do if not defined PHPBIN set "PHPBIN=%%i"
if not defined PHPBIN (
  echo.
  echo [X] php.exe tidak ditemukan.
  echo     Pembaruan perlu PHP untuk memindahkan kredensial database Anda ke
  echo     config.php yang baru. Pastikan XAMPP terpasang, atau tambahkan
  echo     folder C:\xampp\php ke PATH Windows.
  echo.
  pause
  exit /b 1
)
echo [2/7] PHP ditemukan: %PHPBIN%

if not exist "%ROOT%index.php" (
  echo.
  echo [X] index.php tidak ditemukan di folder ini.
  echo     Simpan berkas ini di dalam folder SIMRS lalu jalankan lagi.
  echo.
  pause
  exit /b 1
)
if not exist "%ROOT%config.php" (
  echo.
  echo [X] config.php tidak ditemukan di folder ini.
  echo     Jalankan wizard instalasi lebih dulu:
  echo     http://localhost/SIMRS/index.php?page=install
  echo.
  pause
  exit /b 1
)
echo [3/7] Folder aplikasi dikenali.

rem ---------- 2. Cadangkan dulu ----------
for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss" 2^>nul') do set "STAMP=%%i"
if not defined STAMP set "STAMP=manual"
set "BACKUPDIR=%BACKUPROOT%\%STAMP%"

echo [4/7] Mencadangkan berkas lama ke backup\%STAMP% ...
if not exist "%BACKUPDIR%" mkdir "%BACKUPDIR%" >nul 2>&1
if not exist "%BACKUPDIR%" (
  echo.
  echo [X] Tidak dapat membuat folder cadangan: %BACKUPDIR%
  echo     Pastikan folder aplikasi dapat ditulis.
  echo.
  pause
  exit /b 1
)

robocopy "%ROOT%" "%BACKUPDIR%" /E /XD .git backup /NFL /NDL /NJH /NJS /NP /R:1 /W:1 >nul 2>&1
if errorlevel 8 (
  echo.
  echo [X] Pencadangan gagal. Tidak ada perubahan dilakukan.
  echo.
  pause
  exit /b 1
)
echo       Cadangan selesai.

rem ---------- 3. Unduh ----------
if exist "%WORK%" rd /s /q "%WORK%" >nul 2>&1
mkdir "%EXTRACTDIR%" >nul 2>&1

set "ZIPURL=https://github.com/%REPO%/archive/refs/heads/%BRANCH%.zip"
echo [5/7] Mengunduh versi terbaru...
echo       %ZIPURL%
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $ProgressPreference = 'SilentlyContinue'; try { Invoke-WebRequest -Uri '%ZIPURL%' -OutFile '%ZIPFILE%' -UseBasicParsing } catch { Write-Host $_.Exception.Message; exit 1 }" 2>"%ERRFILE%"

if not exist "%ZIPFILE%" goto :unduh_gagal
for %%A in ("%ZIPFILE%") do set "ZIPSIZE=%%~zA"
if not defined ZIPSIZE goto :unduh_gagal
if %ZIPSIZE% LSS 1000 goto :unduh_gagal
echo       Terunduh: %ZIPSIZE% byte.

rem ---------- 4. Buka arsip ----------
echo [6/7] Membuka arsip...
set "TAREXE=%SystemRoot%\System32\tar.exe"
if exist "%TAREXE%" (
  "%TAREXE%" -xf "%ZIPFILE%" -C "%EXTRACTDIR%" >nul 2>&1
) else (
  powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "Expand-Archive -Path '%ZIPFILE%' -DestinationPath '%EXTRACTDIR%' -Force" >nul 2>&1
)

set "SRC="
for /d %%d in ("%EXTRACTDIR%\*") do if not defined SRC set "SRC=%%d"
if not defined SRC goto :buka_gagal
if not exist "%SRC%\index.php" goto :buka_gagal
if not exist "%SRC%\config.php" goto :buka_gagal
echo       Isi arsip siap.

rem ---------- 5. Gabungkan config ----------
rem config.php memuat kredensial database sekaligus kebijakan aplikasi.
rem Nilai milik pengguna disalin ke config baru lewat skrip PHP.
set "MERGE=%SRC%\tools\merge-config.php"
set "CFGNEW=%SRC%\config.php"
set "CFGOLD=%BACKUPDIR%\config.php"

if not exist "%MERGE%" goto :merge_gagal
if not exist "%CFGOLD%" goto :merge_gagal

"%PHPBIN%" "%MERGE%" "%CFGOLD%" "%CFGNEW%" "%CFGNEW%" >"%OUTFILE%" 2>&1
if errorlevel 1 goto :merge_gagal
type "%OUTFILE%"

rem ---------- 6. Timpa berkas aplikasi ----------
echo [7/7] Menimpa berkas aplikasi...
echo.
echo       DIPERTAHANKAN : config.php (sudah digabungkan), database\, backup\, .git\
echo       Diperbarui    : berkas aplikasi lainnya
echo.

robocopy "%SRC%" "%ROOT%" /E /XD .git backup database /XF config.php /NFL /NDL /NJH /NJS /NP /R:1 /W:1 >nul 2>&1
set "RC=%ERRORLEVEL%"
if %RC% GEQ 8 goto :timpa_gagal

rem config.php hasil gabungan dipasang setelah robocopy, agar tidak ikut dilewati.
copy /y "%CFGNEW%" "%ROOT%config.php" >nul 2>&1
if errorlevel 1 goto :config_gagal

rem Bersihkan sisa unduhan
rd /s /q "%WORK%" >nul 2>&1

rem ---------- 7. Verifikasi ----------
echo.
echo       Memeriksa hasil...
set "VERIF=0"
if not exist "%ROOT%index.php" goto :hasil
if not exist "%ROOT%config.php" goto :hasil
if not exist "%ROOT%includes\helpers.php" goto :hasil
if not exist "%ROOT%VERSION" goto :hasil
set "VERIF=1"

:hasil
rem Pastikan config gabungan masih dapat dimuat dan kredensial terbaca.
rem Catatan: hasil PHP ditulis ke berkas lalu dibaca dengan "set /p". Cara ini
rem dipakai karena for /f dengan tanda kutip bersarang merusak parsing cmd.
set "CFGOK=0"
if "%VERIF%"=="1" (
  "%PHPBIN%" -r "require '%ROOT%config.php'; echo DB_NAME;" >"%OUTFILE%" 2>nul
  set "CEKDB="
  set /p "CEKDB="<"%OUTFILE%"
  if defined CEKDB set "CFGOK=1"
)

set "VER="
set /p "VER="<"%ROOT%VERSION"
if not defined VER set "VER=(tidak terbaca)"

echo.
echo ============================================================
if "%VERIF%"=="1" (
  echo   PEMBARUAN SELESAI
) else (
  echo   PEMBARUAN SELESAI - DENGAN CATATAN
)
echo ============================================================
echo.
echo   Versi terpasang      : %VER%
echo   Cadangan berkas lama : backup\%STAMP%
echo.

if "%CFGOK%"=="1" (
  echo   config.php dimuat ulang dengan baik, kredensial database Anda utuh.
) else (
  echo   [!] config.php hasil gabungan gagal dimuat.
  echo       Pulihkan sekarang: jalankan berkas ini lagi, pilih menu 2.
)

if "%VERIF%"=="1" (
  echo.
  echo   Langkah berikutnya:
  echo     1. Muat ulang halaman aplikasi di peramban (Ctrl+F5).
  echo     2. Periksa versi di http://localhost/SIMRS/index.php?page=diagnostik
  echo     3. Perubahan skema database diterapkan otomatis saat halaman
  echo        yang membutuhkannya dibuka - tidak perlu impor ulang.
) else (
  echo.
  echo   [!] Ada berkas inti yang tidak ditemukan setelah pembaruan.
  echo       Jalankan berkas ini lagi dan pilih menu 2 untuk memulihkan.
)

echo.
set "BUKA="
set /p "BUKA=Buka aplikasi sekarang? Y/N: "
if /i "%BUKA%"=="Y" start "" "http://localhost/SIMRS/"
echo.
pause
exit /b 0

rem ====================================================================
rem  Menu 2 - pulihkan dari cadangan
rem ====================================================================
:pulihkan
echo.
if not exist "%BACKUPROOT%" (
  echo [X] Belum ada cadangan di folder backup\.
  echo.
  pause
  exit /b 1
)

echo   Cadangan yang tersedia:
echo.
set "N=0"
for /f "delims=" %%d in ('dir /b /ad /o-n "%BACKUPROOT%" 2^>nul') do (
  set /a N+=1
  echo     %%d
)
if %N%==0 (
  echo     (kosong)
  echo.
  pause
  exit /b 1
)
echo.
set "PIL="
set /p "PIL=Ketik nama cadangan yang ingin dipulihkan: "
if not defined PIL goto :batal

if not exist "%BACKUPROOT%\%PIL%\index.php" (
  echo.
  echo [X] Cadangan "%PIL%" tidak ditemukan atau tidak berisi index.php.
  echo.
  pause
  exit /b 1
)

echo.
echo   Akan memulihkan dari: backup\%PIL%
echo.
echo   Berkas aplikasi akan dikembalikan ke kondisi saat cadangan dibuat.
echo   config.php Anda saat ini TIDAK ditimpa (kredensial tetap utuh).
echo   Kondisi sekarang juga dicadangkan dulu, jadi pemulihan bisa dibatalkan.
echo.
set "YA="
set /p "YA=Y/N: "
if /i not "%YA%"=="Y" goto :batal

rem Cadangkan dulu kondisi sekarang supaya pemulihan pun bisa dibatalkan.
for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss" 2^>nul') do set "STAMP2=%%i"
if not defined STAMP2 set "STAMP2=sebelum-pulih"
if not exist "%BACKUPROOT%\%STAMP2%" mkdir "%BACKUPROOT%\%STAMP2%" >nul 2>&1
robocopy "%ROOT%" "%BACKUPROOT%\%STAMP2%" /E /XD .git backup /NFL /NDL /NJH /NJS /NP /R:1 /W:1 >nul 2>&1

robocopy "%BACKUPROOT%\%PIL%" "%ROOT%" /E /XD .git backup /XF config.php /NFL /NDL /NJH /NJS /NP /R:1 /W:1 >nul 2>&1
if errorlevel 8 (
  echo.
  echo [X] Pemulihan gagal. Cadangan kondisi sekarang ada di backup\%STAMP2%.
  echo.
  pause
  exit /b 1
)

echo.
echo ============================================================
echo   PEMULIHAN SELESAI
echo ============================================================
echo.
echo   Dipulihkan dari : backup\%PIL%
echo   Kondisi sebelum pemulihan disimpan di : backup\%STAMP2%
echo.
echo   Muat ulang halaman aplikasi di peramban (Ctrl+F5).
echo.
pause
exit /b 0

rem ====================================================================
rem  Jalur galat
rem ====================================================================
:unduh_gagal
echo.
echo [X] Unduhan gagal atau berkas kosong.
if exist "%ERRFILE%" (
  echo     Pesan dari PowerShell:
  type "%ERRFILE%"
)
echo.
echo     Yang sering jadi penyebab:
echo       - Tidak ada koneksi internet
echo       - Proxy/firewall kantor memblokir github.com
echo       - Alamat repositori salah: %ZIPURL%
echo.
echo     Alternatif: unduh manual lalu timpa sendiri
echo       https://github.com/%REPO%/archive/refs/heads/%BRANCH%.zip
echo.
echo     Berkas lama Anda tidak diubah. Cadangan tetap ada di backup\%STAMP%.
echo.
pause
exit /b 1

:buka_gagal
echo.
echo [X] Isi arsip tidak seperti yang diharapkan.
echo     Berkas lama Anda tidak diubah.
echo     Cadangan tetap ada di backup\%STAMP%.
echo.
pause
exit /b 1

:timpa_gagal
echo.
echo [X] Penimpaan berkas gagal (kode robocopy %RC%).
echo     Sebagian berkas mungkin sudah berubah.
echo     Jalankan berkas ini lagi dan pilih menu 2 untuk memulihkan
echo     dari cadangan backup\%STAMP%.
echo.
pause
exit /b 1

:merge_gagal
echo.
echo [X] Gagal menggabungkan config.php.
echo     Pembaruan DIBATALKAN sebelum ada berkas yang ditimpa, sehingga
echo     instalasi Anda masih utuh seperti semula.
if exist "%OUTFILE%" type "%OUTFILE%"
echo.
echo     Yang sering jadi penyebab:
echo       - tools\merge-config.php tidak ada di arsip (arsip tidak lengkap)
echo       - config.php lama tidak ada di cadangan
echo       - php.exe tidak dapat dijalankan
echo.
echo     Cadangan tetap ada di backup\%STAMP%.
echo.
pause
exit /b 1

:config_gagal
echo.
echo [X] config.php hasil gabungan gagal dipasang.
echo     Berkas aplikasi lain sudah diperbarui, tetapi config.php lama
echo     masih terpasang - jadi aplikasi tetap dapat dibuka.
echo.
echo     Coba jalankan berkas ini lagi, atau salin manual:
echo       dari : %CFGNEW%
echo       ke   : %ROOT%config.php
echo.
echo     Bila ragu, pulihkan seluruhnya: jalankan berkas ini lagi,
echo     pilih menu 2, lalu pilih cadangan backup\%STAMP%.
echo.
pause
exit /b 1

:batal
echo.
echo Dibatalkan. Tidak ada perubahan.
echo.
pause
exit /b 0
