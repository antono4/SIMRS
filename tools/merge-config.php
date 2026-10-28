<?php
/**
 * merge-config.php — gabungkan konfigurasi milik pengguna ke config.php baru.
 *
 * Dipakai oleh update-simrs.bat. Masalah yang dipecahkan:
 * config.php memuat kredensial database, jadi berkas itu TIDAK boleh ditimpa
 * mentah-mentah. Tetapi config.php juga memuat kebijakan aplikasi (mis. versi
 * PHP minimum) yang HARUS ikut diperbarui. Karena itu berkas ini menyalin
 * hanya nilai milik pengguna dari config lama ke config baru.
 *
 * Pemakaian:
 *   php tools/merge-config.php <config-lama> <config-baru> <keluaran>
 *
 * Kunci yang dipertahankan (daftar putih):
 *   DB_HOST DB_PORT DB_NAME DB_USER DB_PASS DB_CHARSET AES_KEY
 * Kunci lain diambil dari config baru, sehingga pembaruan kebijakan aplikasi
 * (APP_MIN_PHP, APP_TESTED_PHP, logika versi) ikut terpakai.
 *
 * Keluaran: kode 0 bila berhasil, 1 bila gagal.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Skrip ini hanya untuk baris perintah.\n");
}

const KUNCI_DIPERTAHANKAN = [
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET', 'AES_KEY',
];

function pesan(string $teks): void
{
    fwrite(STDOUT, $teks . PHP_EOL);
}

function gagal(string $teks): void
{
    fwrite(STDERR, '[X] ' . $teks . PHP_EOL);
    exit(1);
}

/** Ambil semua define('NAMA', 'nilai'); dari isi config. */
function ambil_define(string $isi): array
{
    $hasil = [];
    if (preg_match_all(
        "/define\(\s*'([A-Z_][A-Z0-9_]*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)/",
        $isi,
        $m,
        PREG_SET_ORDER
    ) === false) {
        return $hasil;
    }
    foreach ($m as $satu) {
        $hasil[$satu[1]] = $satu[2];
    }
    return $hasil;
}

/** Ganti nilai define('NAMA', '...') di dalam isi config. */
function ganti_define(string $isi, string $nama, string $nilaiBaru): array
{
    $pola = "/(define\(\s*'" . preg_quote($nama, '/') . "'\s*,\s*')(?:[^'\\\\]|\\\\.)*(?='\s*\))/";
    $jumlah = 0;
    $baru = preg_replace($pola, '${1}' . str_replace(['\\', '$'], ['\\\\', '\\$'], $nilaiBaru), $isi, -1, $jumlah);
    return [$baru ?? $isi, $jumlah];
}

// ---- Argumen ----
$argv = $_SERVER['argv'] ?? [];
if (count($argv) < 4) {
    gagal('Pemakaian: php tools/merge-config.php <config-lama> <config-baru> <keluaran>');
}
[, $fileLama, $fileBaru, $fileKeluar] = $argv;

if (!is_file($fileLama)) {
    gagal("Config lama tidak ditemukan: $fileLama");
}
if (!is_file($fileBaru)) {
    gagal("Config baru tidak ditemukan: $fileBaru");
}

$isiLama = (string)file_get_contents($fileLama);
$isiBaru = (string)file_get_contents($fileBaru);

if ($isiLama === '' || $isiBaru === '') {
    gagal('Salah satu berkas config kosong.');
}

$defineLama = ambil_define($isiLama);
$defineBaru = ambil_define($isiBaru);

if (!isset($defineBaru['DB_NAME'])) {
    gagal('Config baru tidak memuat DB_NAME — arsip pembaruan tidak sah. Batalkan.');
}

// ---- Salin nilai milik pengguna ----
$hasil = $isiBaru;
$dipakai = [];
$dilewati = [];

foreach (KUNCI_DIPERTAHANKAN as $kunci) {
    if (!array_key_exists($kunci, $defineLama)) {
        continue; // pengguna belum punya kunci ini -> pakai nilai baru
    }
    if (!array_key_exists($kunci, $defineBaru)) {
        continue; // config baru tidak lagi memakai kunci ini
    }
    [$hasil, $jumlah] = ganti_define($hasil, $kunci, $defineLama[$kunci]);
    if ($jumlah > 0) {
        $dipakai[$kunci] = $defineLama[$kunci];
    } else {
        $dilewati[] = $kunci;
    }
}

// Jaring pengaman: kredensial pengguna harus benar-benar ikut terbawa.
$cek = ambil_define($hasil);
if (($cek['DB_NAME'] ?? null) !== ($defineLama['DB_NAME'] ?? null)) {
    gagal('Gagal memindahkan DB_NAME ke config baru. Tidak ada berkas yang ditulis.');
}

// Tulis ke berkas sementara lalu pindahkan, supaya config.php tidak pernah
// dalam keadaan separuh tertulis bila proses gagal di tengah jalan.
$sementara = $fileKeluar . '.tmp';
if (file_put_contents($sementara, $hasil) === false) {
    gagal("Tidak dapat menulis berkas sementara: $sementara");
}

if (!@rename($sementara, $fileKeluar)) {
    if (@copy($sementara, $fileKeluar) === false) {
        @unlink($sementara);
        gagal("Tidak dapat menulis: $fileKeluar");
    }
    @unlink($sementara);
}

pesan('      config.php digabungkan: kredensial lama dipertahankan.');
if ($dipakai !== []) {
    foreach ($dipakai as $kunci => $nilai) {
        $tampil = in_array($kunci, ['DB_PASS', 'AES_KEY'], true) ? '(disembunyikan)' : $nilai;
        pesan(sprintf('        %-10s = %s', $kunci, $tampil));
    }
}
if ($dilewati !== []) {
    pesan('        dilewati (tidak ada di config baru): ' . implode(', ', $dilewati));
}
pesan('        kebijakan aplikasi diambil dari versi baru (APP_MIN_PHP, versi, dll).');

exit(0);
