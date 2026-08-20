<?php
// Konfigurasi utama SIMRS Web (PHP + AdminLTE 4 + MySQL)
declare(strict_types=1);

if (defined('SIMRS_CONFIG_LOADED')) {
    return; // mencegah duplikasi konstan/sesi bila dimuat ulang
}
define('SIMRS_CONFIG_LOADED', true);

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'sik');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'latin1'); // skema asli memakai latin1

define('APP_NAME', 'SIMRS Web');
define('APP_VERSION', '1.0.0');

// Base path dinamis: mendukung root (/), subfolder XAMPP (/SIMRS), dsb.
define('BASE_PATH', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') === '' ? '' : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));
define('BASE_URL', BASE_PATH . '/');

// Kunci AES bawaan sistem untuk tabel admin/user (jangan diubah agar tetap kompatibel)
define('AES_KEY', 'nur');

session_name('SIMRSWEB');
session_start();

date_default_timezone_set('Asia/Jakarta');
