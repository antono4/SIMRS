<?php
// Halaman pembaru mandiri — unduh skrip pembaru tanpa perlu login.
//
// Halaman ini hanya MENGUNDUH update-simrs.bat; tidak ada berkas yang diubah
// dari sisi PHP. Pembaruan sesungguhnya dijalankan di komputer pengguna, karena
// hanya di sana tersedia akses tulis ke folder aplikasi dan perintah Windows.
//
// Akses: index.php?page=update
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

const UPDATE_REPO = 'antono4/SIMRS';
const UPDATE_BRANCH = 'main';

$batFile = __DIR__ . '/../update-simrs.bat';
$batAda = is_file($batFile);
$batUkuran = $batAda ? (int)filesize($batFile) : 0;
$batDiperbarui = $batAda ? date('d M Y H:i', (int)filemtime($batFile)) : '';

// ---- Unduh skrip pembaru ----
if (isset($_GET['unduh'])) {
    if (!$batAda) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Berkas update-simrs.bat tidak ditemukan di folder aplikasi.";
        return;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="update-simrs.bat"');
    header('Content-Length: ' . $batUkuran);
    header('Cache-Control: no-store');
    readfile($batFile);
    return;
}

// ---- Versi terbaru di GitHub (opsional; gagal pun halaman tetap tampil) ----
$apiUrl = 'https://api.github.com/repos/' . UPDATE_REPO . '/contents/VERSION?ref=' . UPDATE_BRANCH;
$versiRepo = null;
$versiPesan = '';

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 6,
        'ignore_errors' => true,
        'header' => "User-Agent: SIMRS-Web-Updater\r\nAccept: application/vnd.github.raw\r\n",
    ],
]);
$mentah = @file_get_contents($apiUrl, false, $ctx);
$kodeHttp = 0;
if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
    $kodeHttp = (int)$m[1];
}

if ($kodeHttp === 200 && is_string($mentah) && preg_match('/^\s*(\d+(?:\.\d+){1,3})\s*$/', $mentah, $mv) === 1) {
    $versiRepo = $mv[1];
} elseif ($kodeHttp === 404) {
    $versiPesan = 'Repositori belum punya berkas VERSION di cabang ' . UPDATE_BRANCH
        . '. Pembaruan tetap bisa dijalankan, tetapi nomor versinya tidak dapat dibandingkan.';
} else {
    $versiPesan = 'Tidak dapat menghubungi GitHub (tanpa internet, atau proxy/firewall memblokir). '
        . 'Pembaruan tetap bisa dijalankan dari berkas .bat.';
}

$versiKini = APP_VERSION;
$adaBaru = $versiRepo !== null && version_compare($versiRepo, $versiKini, '>');

$zipUrl = 'https://github.com/' . UPDATE_REPO . '/archive/refs/heads/' . UPDATE_BRANCH . '.zip';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Perbarui Aplikasi | <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('favicon.svg') ?>" />
  <link rel="stylesheet" href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('icons/css/bootstrap-icons.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('adminlte/css/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('css/simrs.css') ?>" />
</head>
<body class="bg-body-tertiary">
<div class="container py-4" style="max-width:820px">

  <div class="d-flex align-items-center gap-3 mb-4">
    <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
         style="width:56px;height:56px">
      <i class="bi bi-arrow-repeat fs-3"></i>
    </div>
    <div>
      <h1 class="h4 mb-0">Perbarui <?= e(APP_NAME) ?></h1>
      <p class="text-body-secondary mb-0">Unduh versi terbaru langsung dari repositori GitHub.</p>
    </div>
  </div>

  <!-- Status versi -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3 text-center">
        <div class="col-6 col-md-4">
          <div class="text-body-secondary small">Versi terpasang</div>
          <div class="fs-4 fw-bold">v<?= e($versiKini) ?></div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-body-secondary small">Versi terbaru di GitHub</div>
          <div class="fs-4 fw-bold">
            <?php if ($versiRepo !== null): ?>
              v<?= e($versiRepo) ?>
            <?php else: ?>
              <span class="text-body-secondary fs-6">tidak diketahui</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-body-secondary small">Status</div>
          <div class="fs-6 fw-bold pt-1">
            <?php if ($versiRepo === null): ?>
              <span class="badge text-bg-secondary">Tidak dapat dicek</span>
            <?php elseif ($adaBaru): ?>
              <span class="badge text-bg-warning text-dark">Pembaruan tersedia</span>
            <?php else: ?>
              <span class="badge text-bg-success">Sudah terbaru</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($versiPesan !== ''): ?>
        <div class="alert alert-secondary small mt-3 mb-0" role="alert">
          <i class="bi bi-info-circle me-1"></i><?= e($versiPesan) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tombol unduh -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h6 text-uppercase text-body-secondary">Langkah 1 — Unduh skrip pembaru</h2>
      <p>
        Skrip ini mengunduh versi terbaru dari GitHub, mencadangkan berkas lama ke
        folder <code>backup\</code>, lalu menimpanya. <code>config.php</code> dan
        database Anda tidak pernah diubah.
      </p>
      <?php if ($batAda): ?>
        <a href="<?= e(url('update', ['unduh' => '1'])) ?>" class="btn btn-primary btn-lg">
          <i class="bi bi-download me-1"></i>Unduh update-simrs.bat
        </a>
        <div class="text-body-secondary small mt-2">
          update-simrs.bat &middot; <?= number_format($batUkuran / 1024, 1) ?> KB
          &middot; diperbarui <?= e($batDiperbarui) ?>
        </div>
      <?php else: ?>
        <div class="alert alert-danger mb-0" role="alert">
          <i class="bi bi-exclamation-triangle me-1"></i>
          Berkas <code>update-simrs.bat</code> tidak ada di folder aplikasi ini.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Langkah pakai -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-transparent">
      <h2 class="h6 text-uppercase text-body-secondary mb-0">Langkah 2 — Jalankan skripnya</h2>
    </div>
    <div class="card-body">
      <ol class="mb-0 ps-3">
        <li class="mb-2">
          Letakkan <code>update-simrs.bat</code> di dalam folder aplikasi
          (folder yang berisi <code>index.php</code>), misalnya
          <code>C:\xampp\htdocs\SIMRS</code>.
        </li>
        <li class="mb-2">Klik dua kali berkas itu.</li>
        <li class="mb-2">Pilih menu <strong>1</strong> untuk memperbarui.</li>
        <li class="mb-2">Tunggu sampai muncul tulisan <em>PEMBARUAN SELESAI</em>.</li>
        <li>Muat ulang aplikasi di peramban dengan <kbd>Ctrl</kbd>+<kbd>F5</kbd>.</li>
      </ol>
    </div>
  </div>

  <!-- Catatan -->
  <div class="alert alert-warning" role="alert">
    <h3 class="h6 alert-heading"><i class="bi bi-shield-exclamation me-1"></i>Yang perlu diketahui</h3>
    <ul class="mb-0 small">
      <li>Skrip hanya berjalan di Windows (XAMPP). Di Linux/macOS gunakan
        <code>git pull</code>.</li>
      <li><code>config.php</code> — berisi kredensial database Anda — <strong>tidak
        pernah ditimpa</strong>, begitu juga <code>database\</code> dan
        <code>backup\</code>.</li>
      <li>Setiap pembaruan membuat cadangan baru. Bila hasilnya bermasalah,
        jalankan skrip lagi dan pilih menu <strong>2</strong> untuk memulihkan.</li>
      <li>Perubahan struktur database diterapkan otomatis saat halaman terkait
        dibuka; impor ulang tidak diperlukan.</li>
      <li>Halaman ini terbuka tanpa login. Hapus <code>pages/update.php</code> dan
        <code>update-simrs.bat</code> bila server Anda dapat diakses publik.</li>
    </ul>
  </div>

  <div class="d-flex flex-wrap gap-2">
    <a href="<?= e(url('login')) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-box-arrow-in-right me-1"></i>Login
    </a>
    <a href="<?= e(url('diagnostik')) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-clipboard2-pulse me-1"></i>Diagnostik
    </a>
    <a href="https://github.com/<?= e(UPDATE_REPO) ?>/releases" target="_blank"
       rel="noopener" class="btn btn-outline-secondary">
      <i class="bi bi-github me-1"></i>Repositori
    </a>
  </div>

  <p class="text-body-secondary small mt-4 mb-0">
    Berkas .bat bila diunduh manual:
    <a href="<?= e($zipUrl) ?>" target="_blank" rel="noopener">unduh arsip sumber (zip)</a>.
  </p>
</div>
</body>
</html>
<?php
return;
