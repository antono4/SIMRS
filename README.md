# SIMRS Khanza Web (PHP + AdminLTE 4 + MySQL)

Pengembangan ulang [SIMRS-Khanza](https://github.com/mas-elkhanza/SIMRS-Khanza) (aplikasi desktop Java Swing) menjadi **satu aplikasi web** berbasis **PHP murni**, **UI AdminLTE 4 (Bootstrap 5)**, dan **database MySQL** — tetap memakai skema database asli `sik` sehingga **kompatibel penuh** dengan database SIMRS Khanza yang sudah ada.

## Fitur

| Modul | Keterangan |
|---|---|
| Login | Kompatibel tabel `admin`/`user` Khanza (AES key `nur`). Akun bawaan: `admin` / `admin` |
| User Level / Hak Akses | Izin per modul memakai kolom `enum('true','false')` pada tabel `user` Khanza; superuser (tabel `admin`) akses penuh; menu otomatis disaring, akses URL tanpa izin ditolak (403) |
| Manajemen Pengguna | CRUD pengguna terenkripsi AES + pengaturan hak akses per modul |
| Pengaturan Instansi | Edit nama RS, alamat, kabupaten, propinsi, kontak, email (tabel `setting`); nama RS tampil di sidebar, judul, login, dan footer |
| Skin Siang/Malam | Tombol di navbar & halaman login; default siang; pilihan tersimpan di `localStorage` (`sk-theme`) dan diterapkan sebelum render (tanpa kedip) |
| Dashboard | Statistik pasien, kunjungan, dokter, poli + grafik Chart.js |
| Master Pasien | CRUD lengkap, pencarian, paginasi, No. RM otomatis, umur otomatis |
| Master Dokter | CRUD + relasi spesialis |
| Master Poliklinik | CRUD + tarif registrasi baru/lama |
| Master Penjab | CRUD cara bayar/penanggung jawab |
| Master Tarif Perawatan | CRUD tarif tindakan (`jns_perawatan`) per kategori, poli, dan cara bayar |
| Registrasi | Kunjungan rawat jalan/inap dengan logika ala Khanza (lihat di bawah) |
| Tindakan/Perawatan | Tindakan dokter & perawat per kunjungan (`rawat_jl_dr`, `rawat_jl_pr`) dengan tarif otomatis sesuai poli & cara bayar |
| Diagnosa ICD-10 | Pencarian 40.000+ diagnosa (`penyakit`), prioritas otomatis, status penyakit baru/lama |
| Resep Obat | Buat resep (format `yyyymmddNNNN`), tambah obat dari `databarang`, aturan pakai, penyerahan obat |
| Kamar Inap | Masuk kamar (kamar kosong → ISI, kunjungan jadi Ranap/Dirawat), pulangkan (lama & biaya otomatis, kamar → DIBERSIHKAN), kelola status kamar |
| Kasir / Billing | Daftar tagihan semua kunjungan + rincian per kunjungan (registrasi + tindakan + obat + kamar), proses pembayaran dengan penerbitan `nota_jalan`, pembatalan pembayaran |
| Laporan | Kunjungan per periode: per poli, per dokter, distribusi cara bayar |

## Logika bisnis yang direplikasi dari SIMRS Khanza

- **No. Rawat**: format `yyyy/mm/dd/NNNNNN` (nomor urut 6 digit per tanggal)
- **No. Urut (no_reg)**: urutan 3 digit per dokter per tanggal
- **Status daftar**: `Baru` bila pasien belum pernah berkunjung, `Lama` bila sudah
- **Status poli**: `Baru`/`Lama` dihitung per poliklinik
- **Biaya registrasi**: `poliklinik.registrasi` (pasien baru) atau `poliklinik.registrasilama` (pasien lama)
- **Umur daftar**: dihitung dari `tgl_lahir` dengan satuan `Th`/`Bl`/`Hr`
- **Autentikasi**: `AES_DECRYPT(..., 'nur')` pada tabel `admin` dan `user`, sama seperti aplikasi Java
- **Otorisasi**: halaman dipetakan ke kolom izin tabel `user` (`includes/authz.php` — `PAGE_PERMISSIONS`); izin dimuat ke sesi saat login; perubahan izin berlaku setelah login ulang
- **No. resep**: format `yyyymmddNNNN` (urutan 4 digit per tanggal)
- **No. nota jalan**: format `yyyy/mm/dd/RJNNN` (urutan 3 digit per tanggal)
- **Tagihan kasir**: agregat biaya registrasi + tindakan dokter/perawat + obat resep (harga `databarang.ralan`) + biaya kamar inap; pembayaran menandai `status_bayar`/`stts_bayar` menjadi Sudah dalam satu transaksi

## Struktur Proyek

```
├── index.php            # Front controller / router (?page=...)
├── config.php           # Konfigurasi DB & aplikasi
├── includes/
│   ├── db.php           # Koneksi PDO MySQL + helper query
│   ├── auth.php         # Autentikasi kompatibel Khanza
│   ├── helpers.php      # Helper (escape, flash, paginasi, umur, dll)
│   ├── header.php       # Layout AdminLTE 4 (navbar + sidebar)
│   └── footer.php
├── pages/               # Modul: login, dashboard, pasien, dokter,
│                        # poliklinik, penjab, registrasi, laporan
└── assets/              # AdminLTE 4.8.4, Bootstrap 5.3, Bootstrap Icons,
                         # Chart.js 4, OverlayScrollbars (semua lokal)
```

## Instalasi

1. Siapkan PHP 8.x (`php-cli`, `php-mysql`) dan MySQL/MariaDB.
2. Buat database dan impor skema asli Khanza:
   ```bash
   mysql -e "CREATE DATABASE sik CHARACTER SET latin1 COLLATE latin1_swedish_ci"
   mysql sik < sik.sql   # dari repo SIMRS-Khanza (1.182 tabel)
   ```
3. Sesuaikan kredensial di `config.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
4. Tambahkan akun admin (bila database kosong):
   ```sql
   INSERT INTO admin VALUES (AES_ENCRYPT('admin','nur'), AES_ENCRYPT('admin','nur'));
   ```
5. Jalankan:
   ```bash
   php -S 0.0.0.0:12000 -t /path/ke/proyek
   ```
   atau arahkan document root Apache/Nginx ke direktori proyek.

## Catatan

- Charset database memakai `latin1` sesuai skema asli Khanza.
- Semua aset frontend disajikan lokal (tanpa CDN).
- Modul dapat dikembangkan lebih lanjut (tindakan, resep, kasir, kamar inap, dsb.) dengan pola yang sama: tambah file di `pages/` dan daftarkan di `$routes` pada `index.php`.
