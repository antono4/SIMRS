<?php
/**
 * Konfigurasi inti SIMRS Web (PHP + AdminLTE 4 + MySQL).
 */
declare(strict_types=1);

if (defined('SIMRS_CONFIG_LOADED')) {
    return; // mencegah duplikasi konstan/sesi bila dimuat ulang
}
define('SIMRS_CONFIG_LOADED', true);

// ---- Koneksi Database ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'sik');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'latin1'); // skema asli memakai latin1

// ---- Identitas Aplikasi ----
define('APP_NAME', 'SIMRS Web');
define('APP_VERSION', '2.0.0');

// ---- Prasyarat versi PHP ----
// APP_MIN_PHP  : versi terendah yang masih didukung (8.2 menerima perbaikan
//                keamanan sampai 31 Des 2026; 8.0/8.1 sudah habis masa dukungan).
// APP_TESTED_PHP: cabang versi tertinggi yang sudah diuji. Versi di atas ini
//                biasanya tetap jalan, tetapi belum diverifikasi.
define('APP_MIN_PHP', '8.2');
define('APP_TESTED_PHP', '8.5');

// ---- Base path dinamis: root (/), subfolder XAMPP (/SIMRS), dsb. ----
$__scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE_PATH', rtrim($__scriptDir, '/') === '' ? '' : rtrim($__scriptDir, '/'));
define('BASE_URL', BASE_PATH . '/');
unset($__scriptDir);

// ---- Kunci AES bawaan sistem (tabel admin/user) ----
define('AES_KEY', 'nur');

// ---- Sesi ----
session_name('SIMRSWEB');
session_start();

// ---- Zona waktu ----
date_default_timezone_set('Asia/Jakarta');
