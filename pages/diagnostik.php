<?php
// Halaman diagnostik mandiri — cek semua prasyarat aplikasi di lingkungan server
// Akses: index.php?page=diagnostik (tanpa login agar bisa dipakai saat masalah)
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$hasil = [];
function cek(string $label, bool $ok, string $detail = ''): void
{
    global $hasil;
    $hasil[] = [$label, $ok, $detail];
}

// 1. PHP
cek('Versi PHP ≥ 8.0', version_compare(PHP_VERSION, '8.0', '>='), PHP_VERSION);

// 2. Ekstensi
foreach (['pdo_mysql', 'session', 'json', 'openssl', 'pcre'] as $ext) {
    cek("Ekstensi $ext", extension_loaded($ext));
}
cek('Ekstensi mbstring (opsional, untuk karakter aksen)', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'terpasang' : 'tidak ada — fallback aktif');

// 3. Sesi
$sesiPath = session_save_path() ?: sys_get_temp_dir();
cek('Folder sesi dapat ditulis', is_writable($sesiPath), $sesiPath);

// 4. config.php dapat ditulis (untuk wizard)
cek('config.php dapat ditulis (wizard)', is_writable(__DIR__ . '/../config.php'));

// 5. Koneksi DB
$dbOk = false;
$dbMsg = '';
try {
    $pdo = db(true);
    $dbOk = true;
    $dbMsg = 'terhubung';
} catch (Throwable $e) {
    $dbMsg = $e->getMessage();
}
cek('Koneksi MySQL', $dbOk, $dbMsg);

// 6. Database & tabel kritis
if ($dbOk) {
    foreach (['user', 'admin', 'pasien', 'setting', 'dokter', 'poliklinik'] as $t) {
        $ada = (int)db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?", [DB_NAME, $t]) > 0;
        cek("Tabel `$t` ada", $ada);
    }
    $jml = (int)db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?", [DB_NAME]);
    cek('Jumlah tabel database', $jml > 0, number_format($jml) . ' tabel');
}

// 7. Aset
foreach (['assets/bootstrap/css/bootstrap.min.css', 'assets/adminlte/css/adminlte.min.css', 'assets/icons/css/bootstrap-icons.min.css'] as $a) {
    cek("Aset $a", is_file(__DIR__ . '/../' . $a));
}

// 8. BASE_PATH terdeteksi
cek('BASE_PATH terdeteksi', true, BASE_URL);

$semua = array_reduce($hasil, fn($c, $h) => $c && $h[1], true);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Diagnostik | SIMRS Web</title>
  <link rel="stylesheet" href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('icons/css/bootstrap-icons.min.css') ?>" />
</head>
<body class="bg-body-tertiary">
<div class="container py-4" style="max-width:760px">
  <div class="card shadow-sm">
    <div class="card-header <?= $semua ? 'bg-success' : 'bg-warning' ?> text-white">
      <h5 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Diagnostik SIMRS Web</h5>
    </div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead><tr><th>Pemeriksaan</th><th style="width:120px">Status</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($hasil as [$label, $ok, $detail]): ?>
            <tr>
              <td><?= htmlspecialchars($label) ?></td>
              <td><?= $ok ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">GAGAL</span>' ?></td>
              <td><small><?= htmlspecialchars($detail) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex gap-2">
      <a href="<?= BASE_URL ?>index.php?page=install" class="btn btn-primary"><i class="bi bi-gear me-1"></i>Wizard Instalasi</a>
      <a href="<?= BASE_URL ?>index.php?page=login" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
    </div>
  </div>
  <p class="text-muted small mt-3">Hapus/amanahkan berkas ini (pages/diagnostik.php) setelah instalasi berjalan baik.</p>
</div>
</body>
</html>
<?php
return;
