# AGENTS.md — SIMRS Web

Aplikasi web PHP (AdminLTE 4 + MySQL) hasil pengembangan ulang SIMRS (Java).

## Fakta Penting
- Database: `sik` di MariaDB lokal, user `root` tanpa password, charset `latin1` (skema asli sistem, 1.182 tabel).
- Skema sumber: `/workspace/simrs-src/sik.sql` (clone SIMRS ada di `/workspace/simrs-src`).
- Login kompatibel sistem: tabel `admin`/`user`, `AES_DECRYPT(kolom,'nur')`. Akun bawaan: admin/admin.
- Menjalankan aplikasi: `cd /workspace/project && php -S 0.0.0.0:12000` (work host port 12000).
- MariaDB dijalankan dengan `sudo service mariadb start`; akses root via `sudo mysql`.

## Konvensi Kode
- Routing: `index.php?page=<nama>`; modul di `pages/<nama>.php`; daftarkan di array `$routes`.
- Layout: `includes/header.php` + `includes/footer.php` (butuh `$pageTitle`, opsional `$pageScripts`).
- DB: helper `db_all/db_row/db_val/db_exec` di `includes/db.php` (PDO prepared statements).
- Pola modul: blok POST handler di atas (act=save/delete/status) → redirect; blok `action=form`; blok daftar.
- Logika registrasi mengikuti sistem: no_rawat `yyyy/mm/dd/NNNNNN`, no_reg urut per dokter/tanggal,
  stts_daftar Baru/Lama, biaya dari `poliklinik.registrasi(lama)`, umur Th/Bl/Hr.
- Tabel `pasien` punya FK ke `penjab.kd_pj` — kd_pj harus valid.
- Modul klinis per kunjungan (tindakan, diagnosa, resep, kasir) memakai `includes/kunjungan.php`
  (`kunjungan_load` + `kunjungan_card`) dan diakses lewat `?page=<modul>&no_rawat=<no>`.
- Tindakan: tarif diambil dari `jns_perawatan` difilter kd_poli & kd_pj kunjungan (fallback semua aktif);
  simpan ke `rawat_jl_dr` (dokter) / `rawat_jl_pr` (petugas) dengan `stts_bayar='Belum'`.
- Kamar inap: masuk → kamar ISI + reg_periksa Ranap/Dirawat; pulang → lama & ttl_biaya dihitung,
  kamar DIBERSIHKAN; penanda aktif: `tgl_keluar='0000-00-00'`.
- Kasir: bayar = transaksi (nota_jalan + status_bayar Sudah + stts_bayar tindakan Sudah).
- User level: `includes/authz.php` (`PAGE_PERMISSIONS` halaman→kolom izin tabel user, `PERMISSION_CATALOG`,
  `auth_can`, `auth_level_label`). Enforcement di `index.php` (403) + filter menu di `header.php`.
  Izin disimpan di sesi saat login → perubahan izin baru berlaku setelah login ulang.
- Modul Pengguna (`pages/users.php`): INSERT user harus mengisi SEMUA kolom izin ('false' lalu timpa)
  karena ~151 kolom pertama tabel user tidak punya default.
- Nama RS: `includes/settings.php` (`setting_rs`, `setting_nama_rs`, `setting_update`) membaca tabel
  `setting`; dipakai di header (brand/title), footer, dan login. Edit via `pages/pengaturan.php`
  (izin `pegawai_admin`).
- Tema siang/malam: skrip inline di `<head>` membaca `localStorage['sk-theme']` (default light) →
  `data-bs-theme` di `<html>`; tombol `#themeToggle` + ikon `#themeIcon` di navbar & login.
