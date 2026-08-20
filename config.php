<?php
// Konfigurasi utama SIMRS Khanza Web (PHP + AdminLTE 4 + MySQL)
declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'sik');
define('DB_USER', 'sik');
define('DB_PASS', 'sikhanza');
define('DB_CHARSET', 'latin1'); // skema asli Khanza memakai latin1

define('APP_NAME', 'SIMRS Khanza Web');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/');

// Kunci AES bawaan Khanza untuk tabel admin/user (jangan diubah agar tetap kompatibel)
define('KHANZA_AES_KEY', 'nur');

session_name('SIMRSKHANZA');
session_start();

date_default_timezone_set('Asia/Jakarta');
