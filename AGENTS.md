# AGENTS.md — SIMRS Web

Aplikasi web PHP (AdminLTE 4 + MySQL) hasil pengembangan ulang SIMRS (Java).

## Fakta Penting
- PHP: minimum **8.2** (`APP_MIN_PHP` di `config.php`), diuji pada **8.2/8.3/8.4/8.5** (`APP_TESTED_PHP`).
  Kode memakai sintaksis yang sama di rentang itu — naik ke 8.5 tidak perlu ubah kode.
  PHP 8.0/8.1 sudah EOL. Cek cepat: `?page=diagnostik`.
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
- Antrian: tabel `antrian` **tidak ada di skema asli** — dibuat oleh
  `antrian_bootstrap()` (`CREATE TABLE IF NOT EXISTS`), jadi instalasi lama naik versi sendiri;
  DDL-nya juga ditambahkan ke `database/sik.sql` untuk instalasi baru. Nomor urut per hari per
  poliklinik mulai dari 001, dialokasikan dalam transaksi + `SELECT ... FOR UPDATE` pada baris
  `poliklinik` (unique key `uq_antrian_hari_poli_nomor` sebagai jaring pengaman).
  **Yang ditampilkan selalu kode poli + nomor urut** (`INT-007`, `U9-007`) lewat
  `antrian_nomor_tampil($nomor, $kdPoli)` — karena nomor urut diulang di tiap poli, tanpa kode
  itu nomor dua poliklinik terlihat sama. Kode diambil dari `kd_poli` (`poli_kode()`, nol depan
  dibuang: `U0001` -> `U1`), **bukan** huruf pertama nama poli, yang bertabrakan
  ("Poliklinik Kandungan" dan "Poliklinik Kulit & Kelamin" sama-sama K). Selalu kirim `kd_poli`
  sebagai argumen kedua; sel kolom Nomor di `pages/antrian.php` memakai `text-nowrap`.
  Logika di `includes/antrian.php`; halaman `pages/antrian.php` (izin `registrasi`).
  Nomor dibuat otomatis di `pages/registrasi.php` saat registrasi disimpan.
- Layar display `pages/display.php` **publik** (ada di `$publicPages`, tanpa login) dan tidak
  memakai `includes/header.php` — tampil penuh satu layar untuk TV, polling JSON tiap 3 detik
  (`?page=display&format=json`, `&format=panggil`). **Jangan pernah** kirim nama pasien atau
  No. RM ke halaman ini; hanya nomor antrian + nama poliklinik. Hanya poliklinik yang punya
  antrian hari itu yang ditampilkan (`antrian_ringkasan_aktif`), dan daftar itu harus disaring
  di jalur JSON juga, bukan hanya di HTML awal.
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
- Master data tambahan (pola daftar + form + hapus yang sama seperti pasien/dokter):
  `pegawai` (izin `pegawai_admin`), `petugas` (`petugas`), `penyakit` + kategori ICD-10 (`penyakit`),
  `obat`/data barang (`obat`), `bangsal` + `kamar` (`kamar`), dan `referensi` (Data Referensi).
- Data Referensi (`includes/referensi.php` + `pages/referensi.php`) membuka tabel berbeda per grup
  (`REFERENSI_GRUP`: wilayah/sdm/pasien/medis/penunjang). Karena satu halaman menaungi banyak tabel,
  izinnya dicek per grup lewat `auth_can_perm()` — bukan `auth_can()` yang hanya menerima nama halaman.
  Variabelnya memakai awalan `$menuRef`, **bukan** `$menu`, karena `$menu` sudah dipakai sidebar di
  `includes/header.php` yang di-include setelah halaman (tabrakan ini pernah jadi bug).
- Kontras tema: warna Bootstrap bawaan tidak selalu lolos 4,5:1 sebagai teks. Token tombol
  `.btn-outline-*` diatur per tema di `assets/css/simrs.css` (mis. `warning` #ffc107 hanya 1,63:1 di
  tema terang; `primary` #0d6efd hanya 3,74:1 di tema gelap). Kartu `.small-box` dashboard memakai
  overlay `rgba(0,0,0,.22)` + `link-light`/`link-dark` per warna kartu, karena satu warna teks tidak
  cukup untuk keempat warna. Saat mengaudit kontras, **komposit dulu lapisan tembus pandang**
  (`getComputedStyle().backgroundColor` sering `rgba(...)`); mengabaikannya menghasilkan temuan palsu.
- Tata letak: toolbar daftar memakai `d-flex flex-wrap gap-2`, input pencarian `min-width:9rem`, dan
  `<select>` filter `max-width:11rem`. Tanpa batas itu `<select style="width:auto">` melebar mengikuti
  opsi terpanjang dan meluber di layar 375px.
