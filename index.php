<?php
// Front controller SIMRS Web
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/authz.php';
require __DIR__ . '/includes/settings.php';

// Instalasi dianggap selesai bila file config.php sudah dibuat wizard (ada penanda) — lihat install.php
$page = preg_replace('/[^a-z0-9_]/i', '', $_GET['page'] ?? 'dashboard');

// Wizard instalasi bisa diakses tanpa login
if ($page === 'install') {
    require __DIR__ . '/pages/install.php';
    return;
}

$routes = [
    'login' => 'login.php',
    'logout' => 'logout.php',
    'dashboard' => 'dashboard.php',
    'pasien' => 'pasien.php',
    'dokter' => 'dokter.php',
    'poliklinik' => 'poliklinik.php',
    'penjab' => 'penjab.php',
    'perawatan' => 'perawatan.php',
    'registrasi' => 'registrasi.php',
    'tindakan' => 'tindakan.php',
    'diagnosa' => 'diagnosa.php',
    'resep' => 'resep.php',
    'kamarinap' => 'kamarinap.php',
    'kasir' => 'kasir.php',
    'laporan' => 'laporan.php',
    'users' => 'users.php',
    'pengaturan' => 'pengaturan.php',
    'akun' => 'akun.php',
    'admin' => 'admin.php',
];

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = '404';
    $routes['404'] = '404.php';
}

// Halaman yang tidak memerlukan login
$publicPages = ['login', '404', '403'];

if (!in_array($page, $publicPages, true)) {
    auth_require();
    if (!auth_can($page)) {
        http_response_code(403);
        $page = '403';
        $routes['403'] = '403.php';
    }
}

$currentPage = $page;
try {
    require __DIR__ . '/pages/' . $routes[$page];
} catch (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        flash_set('danger', 'Terjadi kesalahan: ' . $e->getMessage());
        $back = $_SERVER['HTTP_REFERER'] ?? url('dashboard');
        redirect($back);
    }
    throw $e;
}
