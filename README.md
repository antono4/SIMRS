<h1 align="center">SIMRS Web</h1>

<p align="center">
  <strong>Sistem Informasi Manajemen Rumah Sakit</strong><br>
  PHP &middot; MySQL/MariaDB &middot; AdminLTE 4 &middot; Bootstrap 5.3
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL%20%2F%20MariaDB-8.0%2B%20%7C%2010.4%2B-4479A1?logo=mysql&logoColor=white">
  <img alt="AdminLTE" src="https://img.shields.io/badge/AdminLTE-4-2b8cbe">
  <img alt="Bootstrap" src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white">
  <img alt="License" src="https://img.shields.io/badge/License-MIT-green">
</p>

<p align="center">
  <img src="docs/images/dashboard.png" alt="Dashboard SIMRS Web" width="820">
</p>

---

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
| **Tindakan medis** | Pencatatan tindakan dokter dan petugas dengan tarif otomatis |
| **Diagnosa & resep** | Diagnosa ICD-10 dan resep obat per kunjungan |
| **Rawat inap** | Check-in/check-out kamar, perhitungan lama inap dan biaya |
| **Kasir** | Pembayaran kunjungan, penerbitan nota, dan pembatalan pembayaran |
| **Laporan** | Laporan kunjungan per periode, per poliklinik, per dokter, per penjamin |
| **Hak akses** | Izin per modul untuk setiap pengguna (diterapkan di server, bukan hanya di menu) |
| **Tema siang/malam** | Pilihan tema yang disimpan di browser (`localStorage`) |
| **Instalasi mandiri** | Wizard instalasi dan halaman diagnostik lingkungan |

## 📸 Tangkapan Layar

Semua gambar di bawah diambil dari aplikasi yang benar-benar berjalan dengan data contoh.

### Login

<p align="center"><img src="docs/images/login.png" alt="Halaman login" width="720"></p>

### Dashboard — tema terang dan tema gelap

<p align="center">
  <img src="docs/images/dashboard.png" alt="Dashboard tema terang" width="440">
  <img src="docs/images/dashboard_dark.png" alt="Dashboard tema gelap" width="440">
</p>

### Modul utama

<p align="center">
  <img src="docs/images/registrasi.png" alt="Registrasi kunjungan" width="440">
  <img src="docs/images/tindakan.png" alt="Tindakan medis" width="440">
</p>

<p align="center">
  <img src="docs/images/kamarinap.png" alt="Rawat inap" width="440">
  <img src="docs/images/kasir.png" alt="Kasir / billing" width="440">
</p>

### Data & laporan

<p align="center">
  <img src="docs/images/pasien.png" alt="Data pasien" width="440">
  <img src="docs/images/laporan.png" alt="Laporan kunjungan" width="440">
</p>

### Manajemen pengguna

<p align="center"><img src="docs/images/users.png" alt="Manajemen pengguna" width="720"></p>

## 🏗️ Arsitektur

<p align="center">
  <img src="docs/images/architecture.svg" alt="Arsitektur SIMRS Web" width="820">
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
  <img src="docs/images/erd.svg" alt="Diagram relasi inti basis data" width="880">
</p>

Skema `sik` berisi **51 tabel** dengan **54 relasi foreign key**. Tabel `reg_periksa`
menjadi pusat data: setiap kunjungan mengacu ke satu pasien, satu dokter, satu
poliklinik, dan satu penjamin. Data klinis dan keuangan (tindakan, diagnosa, resep,
kamar inap, nota) menggantung pada `no_rawat` kunjungan tersebut.

## 🗂️ Struktur Proyek

```
SIMRS/
├── index.php                 # Front controller: routing, autentikasi, otorisasi
├── config.php                # Konfigurasi database, sesi, dan identitas aplikasi
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
│   ├── tindakan.php          #   Tindakan dokter & petugas
│   ├── diagnosa.php          #   Diagnosa ICD-10
│   ├── resep.php             #   Resep obat
│   ├── kamarinap.php         #   Rawat inap
│   ├── kasir.php             #   Kasir & pembayaran
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
│   ├── settings.php          #   Identitas rumah sakit
│   ├── header.php            #   Kerangka tampilan atas
│   └── footer.php            #   Kerangka tampilan bawah
├── assets/                   # Aset statis (AdminLTE, Bootstrap, ikon, Chart.js)
├── database/
│   └── sik.sql               # Skema + data contoh (51 tabel)
├── docs/images/              # Gambar dokumentasi (diagram & tangkapan layar)
└── html/                     # Pratinjau statis (mockup tanpa backend)
```

## 🚀 Instalasi

### Kebutuhan

- PHP **8.1+** dengan ekstensi `pdo_mysql`, `mbstring`, dan `gd`
- MySQL **8.0+** atau MariaDB **10.4+**
- Web server (Apache/Nginx) atau server bawaan PHP untuk pengembangan

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

### Akun contoh

| Peran | Pengguna | Kata sandi | Sumber |
|-------|----------|-----------|--------|
| Administrator | `admin` | `admin` | Tabel `admin` |
| Pengguna/petugas | — | — | Tabel `user` |

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
| Tindakan | `?page=tindakan` | Tindakan dokter dan petugas |
| Diagnosa | `?page=diagnosa` | Diagnosa ICD-10 per kunjungan |
| Resep | `?page=resep` | Resep obat per kunjungan |
| Kamar Inap | `?page=kamarinap` | Masuk/pulang rawat inap |
| Kasir | `?page=kasir` | Pembayaran dan nota kunjungan |
| Laporan | `?page=laporan` | Laporan kunjungan per periode |
| Pengguna | `?page=users` | Manajemen pengguna dan izin modul |
| Pengaturan | `?page=pengaturan` | Identitas rumah sakit |
| Akun | `?page=akun` | Profil dan ganti kata sandi |
| Instalasi | `?page=install` | Wizard instalasi (tanpa login) |
| Diagnostik | `?page=diagnostik` | Diagnostik lingkungan (tanpa login) |

## 🛠️ Teknologi

- **PHP 8.1+** — `declare(strict_types=1)`, PDO *prepared statements*
- **MySQL / MariaDB** — skema `sik` dengan charset `latin1`
- **AdminLTE 4** + **Bootstrap 5.3** — kerangka antarmuka
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
