<?php
/**
 * Front controller SIMRS Web — routing, autentikasi, otorisasi.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/antrian.php';
require_once __DIR__ . '/includes/ekspor.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/rekam_medis.php';

$page = preg_replace('/[^a-z0-9_]/i', '', $_GET['page'] ?? 'dashboard');

// Wizard instalasi & diagnostik bisa diakses tanpa login
if ($page === 'install') {
    require_once __DIR__ . '/pages/install.php';
    return;
}
if ($page === 'diagnostik') {
    require_once __DIR__ . '/pages/diagnostik.php';
    return;
}
if ($page === 'update') {
    require_once __DIR__ . '/pages/update.php';
    return;
}

$routes = [
    'login' => 'login.php',
    'logout' => 'logout.php',
    'dashboard' => 'dashboard.php',
    'pasien' => 'pasien.php',
    'dokter' => 'dokter.php',
    'pegawai' => 'pegawai.php',
    'petugas' => 'petugas.php',
    'poliklinik' => 'poliklinik.php',
    'penjab' => 'penjab.php',
    'perawatan' => 'perawatan.php',
    'penyakit' => 'penyakit.php',
    'obat' => 'obat.php',
    'bangsal' => 'bangsal.php',
    'kamar' => 'kamar.php',
    'referensi' => 'referensi.php',
    'registrasi' => 'registrasi.php',
    'antrian' => 'antrian.php',
    'display' => 'display.php',
    'tindakan' => 'tindakan.php',
    'diagnosa' => 'diagnosa.php',
    'resep' => 'resep.php',
    'rekam_medis' => 'rekam_medis.php',
    'kamarinap' => 'kamarinap.php',
    'kasir' => 'kasir.php',
    'billing' => 'billing.php',
    'kartu_berobat' => 'kartu_berobat.php',
    'tiket_antrian' => 'tiket_antrian.php',
    'etiket_resep' => 'etiket_resep.php',
    'cetak_rekam_medis' => 'cetak_rekam_medis.php',
    'log_aktivitas' => 'log_aktivitas.php',
    'laporan' => 'laporan.php',
    'users' => 'users.php',
    'pengaturan' => 'pengaturan.php',
    'akun' => 'akun.php',
    'admin' => 'admin.php',
];

// Halaman galat didaftarkan sebagai rute supaya ?page=403 / ?page=404
// menampilkan halaman yang benar, bukan selalu jatuh ke 404.
$routes['403'] = '403.php';
$routes['404'] = '404.php';

if (!isset($routes[$page])) {
    $page = '404';
}
if ($page === '404') {
    http_response_code(404);
} elseif ($page === '403') {
    http_response_code(403);
}

// Halaman yang tidak memerlukan login
// 'display' publik: layar TV ruang tunggu tidak punya sesi login.
// Halamannya sengaja tidak menampilkan identitas pasien.
$publicPages = ['login', '404', '403', 'display'];

if (!in_array($page, $publicPages, true)) {
    auth_require();
    if (!auth_can($page)) {
        http_response_code(403);
        $page = '403';
    }
}

$currentPage = $page;
try {
    require_once __DIR__ . '/pages/' . $routes[$page];
} catch (Throwable $e) {
    error_log(sprintf('[SIMRS] %s: %s di %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

    // Jangan pernah mengalihkan ke halaman lain di sini: HTTP_REFERER pada
    // pemuatan halaman biasa adalah halaman itu sendiri, sehingga galat apa pun
    // berubah menjadi pengalihan tanpa henti (ERR_TOO_MANY_REDIRECTS) sekaligus
    // menyembunyikan pesan aslinya. Tampilkan halaman galat 500 secara langsung.
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        $galatKelas = $e::class;
        $galatPesan = $e->getMessage();
        $galatBerkas = $e->getFile();
        $galatBaris = $e->getLine();
        require __DIR__ . '/pages/galat.php';
        exit;
    }

    if (PHP_SAPI !== 'cli') {
        // Header sudah terkirim (galat muncul setelah keluaran). Tidak bisa lagi
        // mengubah status/redirect — tampilkan peringatan di ujung halaman.
        echo '<div style="position:fixed;left:0;right:0;bottom:0;z-index:9999;background:#b91c1c;color:#fff;'
            . 'padding:.75rem 1rem;font:14px/1.4 system-ui,sans-serif">'
            . '<strong>Terjadi kesalahan:</strong> '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            . '</div>';
        exit;
    }

    throw $e;
}
