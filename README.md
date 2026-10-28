<h1 align="center">SIMRS Web</h1>

<p align="center">
  <strong>Sistem Informasi Manajemen Rumah Sakit</strong><br>
  PHP &middot; MySQL/MariaDB &middot; AdminLTE 4 &middot; Bootstrap 5.3
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL%20%2F%20MariaDB-8.0%2B%20%7C%2010.4%2B-4479A1?logo=mysql&logoColor=white">
  <img alt="AdminLTE" src="https://img.shields.io/badge/AdminLTE-4-2b8cbe">
  <img alt="Bootstrap" src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white">
  <img alt="License" src="https://img.shields.io/badge/License-MIT-green">
</p>

<p align="center">
  <img src="docs/images/dashboard.png" alt="Dashboard SIMRS Web" width="820">
</p>

---

## 🧭 Daftar Isi

- [Tentang](#-tentang)
- [Fitur Utama](#-fitur-utama)
- [Tangkapan Layar](#-tangkapan-layar)
- [Antrian & Layar Display](#-antrian--layar-display)
- [Arsitektur](#️-arsitektur)
- [Model Data](#️-model-data)
- [Struktur Proyek](#️-struktur-proyek)
- [Instalasi](#-instalasi)
- [Daftar Modul](#-daftar-modul)
- [Teknologi](#️-teknologi)
- [Pratinjau Statis](#️-pratinjau-statis)
- [Catatan Repositori](#-catatan-repositori)
- [Lisensi](#-lisensi)
- [Kontak](#-kontak)

## 📖 Tentang

**SIMRS Web** adalah penulisan ulang aplikasi SIMRS (aslinya berbasis Java/desktop) menjadi
aplikasi web berbasis PHP dan MySQL/MariaDB. Aplikasi ini memakai skema database asli
SIMRS (`sik`) sehingga data yang sudah ada tetap kompatibel.

Pencarian cepat: aplikasi ini **bukan** situs statis. Aplikasi berjalan di atas PHP dan
membaca/menulis ke database `sik`. Untuk mencobanya, lihat bagian [Instalasi](#-instalasi).

## ✨ Fitur Utama

| Fitur | Keterangan |
|-------|-----------|
| **Dashboard** | Ringkasan jumlah pasien, dokter, poliklinik, dan kunjungan hari ini |
| **Grafik kunjungan** | Tren kunjungan 14 hari terakhir dan kunjungan per poliklinik (Chart.js) |
| **Master data** | Pasien, dokter, poliklinik, penjamin, dan tarif perawatan |
| **Registrasi kunjungan** | Pendaftaran rawat jalan & rawat inap, nomor rekam medis otomatis |
| **Antrian poliklinik** | Nomor antrian otomatis per poliklinik saat registrasi, pemanggilan, dan pemantauan status |
| **Pemisah antrian** | Satu warna tetap + huruf awalan per poliklinik, sama di modul Antrian dan layar display |
| **Layar display** | Layar TV ruang tunggu (tanpa login) dengan nomor dipanggil, bel, dan pengumuman suara |
| **Tindakan medis** | Pencatatan tindakan dokter dan petugas dengan tarif otomatis |
| **Diagnosa & resep** | Diagnosa ICD-10 dan resep obat per kunjungan |
| **Rawat inap** | Check-in/check-out kamar, perhitungan lama inap dan biaya |
| **Kasir** | Pembayaran kunjungan, penerbitan nota, dan pembatalan pembayaran |
| **Cetak billing** | Nota kunjungan siap cetak: kop instansi, rincian tarif, total, dan terbilang; langsung ke A4 atau PDF |
| **Laporan** | Laporan kunjungan per periode, per poliklinik, per dokter, per penjamin |
| **Hak akses** | Izin per modul untuk setiap pengguna (diterapkan di server, bukan hanya di menu) |
| **Tema siang/malam** | Pilihan tema yang disimpan di browser (`localStorage`), dioptimalkan agar tetap kontras di kedua tema |
| **Instalasi mandiri** | Wizard instalasi dan halaman diagnostik lingkungan |

## 📸 Tangkapan Layar

Semua gambar di bawah diambil dari aplikasi yang benar-benar berjalan dengan data
contoh bawaan (`database/sik.sql`) — bukan mockup atau gambar rancangan.

### Masuk

<p align="center"><img src="docs/images/login.png" alt="Halaman masuk" width="720"></p>

### Dashboard — tema terang dan tema gelap

<p align="center">
  <img src="docs/images/dashboard.png" alt="Dashboard tema terang" width="410">
  <img src="docs/images/dashboard_dark.png" alt="Dashboard tema gelap" width="410">
</p>

### Registrasi & pelayanan

<p align="center">
  <img src="docs/images/registrasi.png" alt="Registrasi kunjungan" width="410">
  <img src="docs/images/tindakan.png" alt="Tindakan medis" width="410">
</p>

<p align="center">
  <img src="docs/images/kamarinap.png" alt="Rawat inap" width="410">
  <img src="docs/images/kasir.png" alt="Kasir / pembayaran" width="410">
</p>

### Cetak billing

Halaman nota mandiri, terpisah dari tata letak aplikasi, sehingga siap dicetak
langsung ke kertas A4 atau disimpan sebagai PDF lewat dialog cetak peramban.

<p align="center"><img src="docs/images/billing.png" alt="Cetak billing: nota kunjungan siap cetak A4" width="720"></p>

### Data klinis

<p align="center">
  <img src="docs/images/diagnosa.png" alt="Diagnosa ICD-10 per kunjungan" width="410">
  <img src="docs/images/resep.png" alt="Resep obat per kunjungan" width="410">
</p>

### Data master

<p align="center">
  <img src="docs/images/pasien.png" alt="Master data pasien" width="410">
  <img src="docs/images/dokter.png" alt="Master data dokter" width="410">
</p>

<p align="center">
  <img src="docs/images/poliklinik.png" alt="Master poliklinik" width="410">
  <img src="docs/images/penjab.png" alt="Master penjamin" width="410">
</p>

<p align="center"><img src="docs/images/perawatan.png" alt="Master tarif perawatan" width="720"></p>

### Laporan

<p align="center"><img src="docs/images/laporan.png" alt="Laporan kunjungan per periode" width="720"></p>

### Administrasi & pengguna

<p align="center">
  <img src="docs/images/users.png" alt="Manajemen pengguna dan izin modul" width="410">
  <img src="docs/images/admin.png" alt="Halaman administrator" width="410">
</p>

<p align="center">
  <img src="docs/images/akun.png" alt="Profil dan ganti kata sandi" width="410">
  <img src="docs/images/pengaturan.png" alt="Identitas rumah sakit" width="410">
</p>

### Pemasangan & diagnostik

<p align="center">
  <img src="docs/images/install.png" alt="Wizard instalasi" width="410">
  <img src="docs/images/diagnostik.png" alt="Diagnostik lingkungan" width="410">
</p>

### Antrian & layar display

<p align="center">
  <img src="docs/images/antrian.png" alt="Modul antrian poliklinik" width="410">
  <img src="docs/images/display.png" alt="Layar display antrian ruang tunggu" width="410">
</p>

## 🎫 Antrian & Layar Display

Nomor antrian dibuat **otomatis** saat registrasi kunjungan, jadi petugas loket tidak perlu
mengambil nomor terpisah. Nomor berjalan per poliklinik, per hari, mulai dari `001`.

<p align="center">
  <img src="docs/images/alur-antrian.svg" alt="Alur antrian: nomor dibuat saat registrasi, dipanggil petugas, tampil di layar TV" width="820">
</p>

Modul `?page=antrian` dipakai petugas untuk memanggil dan memantau:

| Aksi | Kegunaan |
|------|----------|
| **Panggil Berikutnya** | Memanggil nomor `Menunggu` terkecil pada satu poliklinik |
| **Panggil** | Memanggil satu nomor tertentu |
| **Panggil ulang** | Memanggil lagi nomor yang sama (pasien belum hadir) |
| **Selesai / Lewati / Batal** | Menutup antrian tanpa memanggil |

Halaman `?page=display` adalah layar untuk TV ruang tunggu. Halaman ini **tanpa login**,
tampil penuh satu layar, dan menyegarkan dirinya setiap 3 detik:

- nomor yang sedang dipanggil dalam ukuran besar, beserta poliklinik tujuan;
- ringkasan per poliklinik: nomor terakhir dipanggil dan nomor berikutnya;
- bel dan pengumuman suara Bahasa Indonesia (`SpeechSynthesis`), yang aktif setelah
  tombol **Suara** ditekan — peramban memblokir audio sebelum ada interaksi pengguna.

### Pemisah antrian per poliklinik

Setiap poliklinik punya satu **warna tetap** dan **huruf awalan** yang tampil sama di
seluruh aplikasi: kartu grup di modul Antrian, tiap baris antrian, kartu informasi
kunjungan, dan kartu poliklinik di layar display. Jadi pasien bisa mencocokkan warna
pada kartu antriannya dengan kartu poliklinik di layar tanpa harus membaca nama poli.

Warna diberikan berdasarkan urutan `kd_poli`, sehingga selama jumlah poliklinik tidak
melebihi jumlah warna (36), tidak ada dua poliklinik yang berbagi warna — termasuk
poliklinik nonaktif yang masih tersimpan di basis data. Palet disusun dengan algoritma
jarak warna terbesar memakai jarak persepsi CIELAB, supaya warna yang berdampingan di
layar selalu terlihat jelas berbeda, dan semua warna diuji kontras ≥ 4,6 terhadap teks
putih (WCAG AA).

> **Privasi.** Layar display bersifat publik, jadi halaman ini **hanya** mengirim nomor
> antrian, nama poliklinik, warna, dan huruf awalan. Nama pasien dan No. Rekam Medis
> tidak pernah dikirim ke peramban, baik di HTML maupun di respons JSON.

### Menjalankan layar display

Buka `?page=display` di peramban TV, lalu tekan tombol **Suara** sekali. Untuk mode
kios, jalankan peramban dengan `--kiosk` dan arahkan ke URL tersebut.

## 🏗️ Arsitektur

<p align="center">
  <img src="docs/images/architecture.svg" alt="Arsitektur SIMRS Web" width="800">
</p>

Alur permintaan:

1. Browser mengirim permintaan ke **front controller** `index.php?page=<nama>`.
2. `index.php` memetakan nama halaman ke berkas di `pages/` melalui array `$routes`,
   lalu menjalankan pemeriksaan autentikasi (`auth_require`) dan otorisasi (`auth_can`).
   Permintaan tanpa izin dijawab dengan halaman **403**.
3. Modul di `pages/` memakai fungsi bersama dari `includes/` (koneksi database,
   helper, sesi, dan komponen tampilan `header.php` + `footer.php`).
4. Semua akses database melewati PDO *prepared statements* (`db_all`, `db_row`,
   `db_val`, `db_exec`) di `includes/db.php`.

## 🗃️ Model Data

<p align="center">
  <img src="docs/images/erd.svg" alt="Diagram relasi inti basis data" width="820">
</p>

Skema `sik` berisi **52 tabel** dengan **55 relasi foreign key**. Tabel `reg_periksa`
menjadi pusat data: setiap kunjungan mengacu ke satu pasien, satu dokter, satu
poliklinik, dan satu penjamin. Data klinis dan keuangan (tindakan, diagnosa, resep,
kamar inap, nota) menggantung pada `no_rawat` kunjungan tersebut.

Diagram berikut merangkum perjalanan satu kunjungan, dari pendaftaran sampai nota
siap cetak, supaya jelas modul mana yang menulis data ke mana:

<p align="center">
  <img src="docs/images/alur-kunjungan.svg" alt="Siklus satu kunjungan pasien: registrasi, pelayanan, kasir, nota" width="820">
</p>

## 🗂️ Struktur Proyek

<p align="center">
  <img src="docs/images/struktur-proyek.svg" alt="Struktur berkas: akar proyek, pages/, includes/, dan berkas lain" width="820">
</p>

```
SIMRS/
├── index.php                 # Front controller: routing, autentikasi, otorisasi
├── config.php                # Konfigurasi database, sesi, dan identitas aplikasi
├── install-database.bat      # Pemasang database untuk Windows/XAMPP (klik dua kali)
├── .htaccess                 # Aturan server
├── pages/                    # Modul halaman (satu berkas per halaman)
│   ├── dashboard.php         #   Ringkasan statistik & grafik
│   ├── login.php             #   Form login
│   ├── pasien.php            #   Master pasien
│   ├── dokter.php            #   Master dokter
│   ├── poliklinik.php        #   Master poliklinik
│   ├── penjab.php            #   Master penjamin
│   ├── perawatan.php         #   Master tarif perawatan
│   ├── registrasi.php        #   Registrasi kunjungan
│   ├── antrian.php           #   Antrian poliklinik (panggil & pantau)
│   ├── display.php           #   Layar display antrian (publik)
│   ├── tindakan.php          #   Tindakan dokter & petugas
│   ├── diagnosa.php          #   Diagnosa ICD-10
│   ├── resep.php             #   Resep obat
│   ├── kamarinap.php         #   Rawat inap
│   ├── kasir.php             #   Kasir & pembayaran
│   ├── billing.php           #   Cetak billing / nota (mandiri, siap cetak A4)
│   ├── laporan.php           #   Laporan kunjungan
│   ├── users.php             #   Manajemen pengguna & izin
│   ├── pengaturan.php        #   Pengaturan identitas rumah sakit
│   ├── akun.php              #   Profil pengguna
│   ├── install.php           #   Wizard instalasi
│   ├── diagnostik.php        #   Diagnostik lingkungan
│   ├── admin.php             #   Halaman administrator
│   ├── logout.php            #   Keluar
│   ├── 403.php · 404.php     #   Halaman galat
├── includes/                 # Kode bersama
│   ├── db.php                #   Koneksi PDO + helper query
│   ├── auth.php              #   Autentikasi (AES_DECRYPT)
│   ├── authz.php             #   Otorisasi per modul
│   ├── helpers.php           #   Fungsi bantu (url, flash, redirect)
│   ├── kunjungan.php         #   Komponen data kunjungan
│   ├── antrian.php           #   Logika antrian (nomor, panggil, ringkasan)
│   ├── settings.php          #   Identitas rumah sakit
│   ├── header.php            #   Kerangka tampilan atas
│   └── footer.php            #   Kerangka tampilan bawah
├── assets/                   # Aset statis
│   ├── css/simrs.css         #   Lapisan tema (token warna, komponen, cetak)
│   ├── fonts/                #   Inter (dilokalkan, tanpa CDN)
│   ├── adminlte/ · bootstrap/ #  Kerangka antarmuka
│   ├── icons/ · chartjs/     #   Ikon & grafik
│   └── favicon.svg           #   Ikon situs
├── database/
│   └── sik.sql               # Skema + data contoh (52 tabel)
├── docs/images/              # Gambar dokumentasi (diagram & tangkapan layar)
└── html/                     # Pratinjau statis (mockup tanpa backend)
```

## 🚀 Instalasi

### Kebutuhan

- PHP **8.2+** — diuji pada PHP **8.2, 8.3, 8.4, dan 8.5** (terakhir: 8.5.12)
  - Ekstensi wajib: `pdo_mysql`, `json`, `session`, `openssl`, `pcre`
  - Ekstensi opsional: `mbstring` (memperbaiki tampilan karakter beraksen; ada
    fallback `iconv` dan konversi manual bila tidak ada)
- MySQL **8.0+** atau MariaDB **10.4+** (diuji pada MariaDB 11.8)
- Web server (Apache/Nginx) atau server bawaan PHP untuk pengembangan

> **Catatan versi PHP.** PHP 8.0 dan 8.1 sudah **habis masa dukungan** (tidak lagi
> menerima perbaikan keamanan), dan 8.2 berakhir **31 Desember 2026**. Karena itu
> versi minimum aplikasi ini adalah **8.2**. Kode ini memakai sintaksis yang sama
> dari 8.2 sampai 8.5, jadi menjalankannya di 8.5 hanya perlu menaikkan versi PHP —
> tidak ada perubahan kode. Halaman `?page=diagnostik` akan melaporkan bila versi
> PHP server di bawah minimum.

### Langkah

```bash
# 1. Clone repositori
git clone https://github.com/antono4/SIMRS.git
cd SIMRS

# 2. Impor skema + data contoh
mysql -u root -p < database/sik.sql

# 3. Sesuaikan kredensial database pada config.php
#    DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS

# 4. Jalankan server pengembangan
php -S localhost:8000

# 5. Buka di browser
#    http://localhost:8000/index.php?page=login
```

Konfigurasi bawaan di `config.php` mengarah ke database `sik` pada `127.0.0.1:3306`
dengan pengguna `root` tanpa kata sandi.

### Pemasangan di XAMPP (Windows)

Pengguna Windows tidak perlu mengetik perintah SQL. Klik dua kali
`install-database.bat` di folder aplikasi:

1. Mencari `mysql.exe` di `C:\xampp\mysql\bin` (atau lokasi XAMPP lain).
2. Meminta kata sandi MySQL (kosongkan bila bawaan).
3. Mengimpor `database\sik.sql` sehingga database `sik` muncul di
   <http://localhost/phpmyadmin>.
4. Memverifikasi hasilnya: jumlah tabel (harus 52) dan baris `poliklinik`.
5. Menawarkan membuka phpMyAdmin setelah selesai.

Aman dijalankan berulang kali: setiap tabel didahului `DROP TABLE IF EXISTS`,
jadi impor yang tadinya terhenti di tengah bisa dilanjutkan cukup dengan
menjalankannya lagi. Bila XAMPP dipasang di lokasi lain, ubah `MYSQL` di bagian
atas berkas. Alternatif tanpa berkas ini: buka phpMyAdmin, pilih tab **Import**,
lalu pilih `database/sik.sql`.

### Akun contoh

| Peran | Pengguna | Kata sandi | Sumber |
|-------|----------|-----------|--------|
| Administrator | `admin` | `admin` | Tabel `admin` |
| Pengguna/petugas | — | — | Tabel `user` |

Akun `admin` adalah superuser: semua modul terbuka. Akun dari tabel `user` dibatasi
oleh izin per modul, yang diatur di `?page=users` dan diperiksa di server (bukan
hanya disembunyikan dari menu). Izin disimpan di sesi saat login, jadi perubahan izin
baru berlaku setelah pengguna login ulang.

| Izin di `?page=users` | Membuka modul |
|-----------------------|---------------|
| Master Pasien | Pasien |
| Master Dokter | Dokter |
| Registrasi, Poliklinik & Penjab | Registrasi, Poliklinik, Penanggung Jawab, Antrian |
| Tarif Perawatan | Tarif Perawatan |
| Tindakan / Perawatan | Tindakan |
| Diagnosa ICD-10 | Diagnosa |
| Resep Obat | Resep |
| Kamar Inap | Kamar Inap |
| Kasir / Billing | Kasir, Cetak Billing |
| Laporan Kunjungan | Laporan |
| Manajemen Pengguna | Pengguna |

Halaman yang tidak butuh izin khusus: Dashboard, Akun Saya, dan layar display
(publik). Panel Administrator hanya terbuka untuk peran `admin`.

> **Penting untuk produksi:** ganti kredensial bawaan, gunakan pengguna database
> dengan hak minimum, aktifkan HTTPS, dan hapus `info.php` (menampilkan `phpinfo()`)
> serta `pages/diagnostik.php` sebelum dipublikasikan.

## 🧩 Daftar Modul

| Halaman | URL | Keterangan |
|---------|-----|-----------|
| Login | `?page=login` | Autentikasi dengan dekripsi AES pada tabel `admin`/`user` |
| Dashboard | `?page=dashboard` | Statistik dan grafik kunjungan |
| Pasien | `?page=pasien` | Master data pasien |
| Dokter | `?page=dokter` | Master data dokter |
| Poliklinik | `?page=poliklinik` | Master poliklinik dan tarif registrasi |
| Penanggung Jawab | `?page=penjab` | Master penjamin (umum/asuransi) |
| Tarif Perawatan | `?page=perawatan` | Master tarif tindakan |
| Registrasi | `?page=registrasi` | Pendaftaran kunjungan rawat jalan/inap |
| Antrian | `?page=antrian` | Nomor antrian per poliklinik, pemanggilan, dan pemantauan |
| Display Antrian | `?page=display` | Layar TV ruang tunggu (tanpa login) |
| Tindakan | `?page=tindakan` | Tindakan dokter dan petugas |
| Diagnosa | `?page=diagnosa` | Diagnosa ICD-10 per kunjungan |
| Resep | `?page=resep` | Resep obat per kunjungan |
| Kamar Inap | `?page=kamarinap` | Masuk/pulang rawat inap |
| Kasir | `?page=kasir` | Rincian tagihan, proses pembayaran, dan penerbitan nota |
| Cetak Billing | `?page=billing&no_rawat=...` | Nota kunjungan siap cetak (A4/PDF), memakai perhitungan yang sama dengan Kasir |
| Laporan | `?page=laporan` | Laporan kunjungan per periode |
| Pengguna | `?page=users` | Manajemen pengguna dan izin modul |
| Pengaturan | `?page=pengaturan` | Identitas rumah sakit |
| Akun | `?page=akun` | Profil dan ganti kata sandi |
| Administrator | `?page=admin` | Panel admin: statistik basis data, riwayat login, dan daftar superuser |
| Instalasi | `?page=install` | Wizard instalasi (tanpa login) |
| Diagnostik | `?page=diagnostik` | Diagnostik lingkungan (tanpa login) |

## 🛠️ Teknologi

- **PHP 8.2+** (diuji 8.2–8.5) — `declare(strict_types=1)`, PDO *prepared statements*
- **MySQL / MariaDB** — skema `sik` dengan charset `latin1`
- **AdminLTE 4** + **Bootstrap 5.3** — kerangka antarmuka
- **Lapisan tema sendiri** (`assets/css/simrs.css`) — token warna, radius,
  dan bayangan; tema siang/malam dibangun di atas variabel Bootstrap 5.3
- **Inter** (dilokalkan di `assets/fonts/`) — tipografi, tanpa CDN
- **Chart.js** — grafik pada dashboard dan laporan
- **Bootstrap Icons** — ikon antarmuka

## 🖼️ Pratinjau Statis

Folder [`html/`](./html) berisi mockup statis (HTML saja, tanpa backend) yang bisa
dibuka langsung di browser tanpa PHP atau database:

<p align="center"><img src="docs/images/mockup.png" alt="Pratinjau statis" width="720"></p>

## 📌 Catatan Repositori

- **Automasi penimpa README telah dihapus.** Sebelumnya ada beberapa workflow
  (`.github/workflows/C1.yml`, `C2.yml`, `C3.yml`, dan `generate-readme.yml`) yang
  menimpa `README.md` setiap 10 menit dengan templat generik, sehingga dokumentasi
  yang benar selalu hilang. Workflow tersebut dihapus pada perubahan ini agar README
  ini dapat dirawat secara manual.
- **GitHub Pages tidak aktif** untuk repositori ini, sehingga tidak ada demo daring.
  Untuk mencoba aplikasi, jalankan secara lokal (lihat [Instalasi](#-instalasi)).
- Repositori ini **tidak memiliki berkas `LICENSE`**. Lisensi yang ditampilkan pada
  lencana di atas bersifat sementara; tambahkan berkas `LICENSE` untuk menetapkannya.

## 📄 Lisensi

Belum ada berkas lisensi pada repositori ini. Tambahkan berkas `LICENSE` untuk
menentukan lisensi proyek secara resmi.

## 📬 Kontak

- GitHub: [@antono4](https://github.com/antono4)
- Repositori: [antono4/SIMRS](https://github.com/antono4/SIMRS)
