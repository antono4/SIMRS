<?php
// Wizard Instalasi SIMRS Web — 5 langkah, tanpa login
declare(strict_types=1);

$step = max(1, min(5, (int)($_GET['step'] ?? $_POST['step'] ?? 1)));
$err = null;
$ok = null;

// ---- Pemrosesan tiap langkah (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';

    if ($do === 'cek') {
        $step = 2;
    } elseif ($do === 'simpan_config') {
        // Tulis kredensial DB baru ke config.php (wizard instalasi)
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = (string)($_POST['db_pass'] ?? '');
        $name = trim($_POST['db_name'] ?? 'sik');
        $cfg = file_get_contents(__DIR__ . '/../config.php');
        $cfg = preg_replace("/define\('DB_HOST', '[^']*'\);/", "define('DB_HOST', '" . addslashes($host) . "');", (string)$cfg);
        $cfg = preg_replace("/define\('DB_USER', '[^']*'\);/", "define('DB_USER', '" . addslashes($user) . "');", (string)$cfg);
        $cfg = preg_replace("/define\('DB_PASS', '[^']*'\);/", "define('DB_PASS', '" . addslashes($pass) . "');", (string)$cfg);
        $cfg = preg_replace("/define\('DB_NAME', '[^']*'\);/", "define('DB_NAME', '" . addslashes($name) . "');", (string)$cfg);
        if (file_put_contents(__DIR__ . '/../config.php', $cfg) === false) {
            $err = 'config.php tidak dapat ditulis. Atur izin tulis folder aplikasi.';
            $step = 2;
        } else {
            redirect('index.php?page=install&step=2');
        }
    } elseif ($do === 'db') {
        $step = 3;
    } elseif ($do === 'import') {
        $step = 4;
    } elseif ($do === 'simpan_akun') {
        $step = 5;
    } elseif ($do === 'selesai') {
        // tulis penanda instalasi selesai
        file_put_contents(__DIR__ . '/../storage_installed.lock', date('c'));
        redirect(url('login'));
    }
}

// ---- Langkah 2: uji koneksi DB dari config ----
$dbOk = false;
$dbErr = '';
$jumlahTabel = 0;
if ($step >= 2) {
    try {
        $pdoTest = db(true); // koneksi tanpa memilih database
        $stmt = $pdoTest->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?");
        $stmt->execute([DB_NAME]);
        $jumlahTabel = (int)$stmt->fetchColumn();
        $dbOk = true;
    } catch (Throwable $e) {
        $dbErr = $e->getMessage();
    }
}

// ---- Langkah 3: impor database/sik.sql bila diminta ----
// Impor per-pernyataan SQL dengan parser state yang aman terhadap komentar mysqldump
function import_sql_file(PDO $pdo, string $file): int
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('SET SESSION sql_mode = "NO_AUTO_VALUE_ON_ZERO"');
    $fh = fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Tidak dapat membuka berkas SQL.');
    }
    $buffer = '';
    $count = 0;
    $inBlockComment = false;
    while (($line = fgets($fh)) !== false) {
        $i = 0;
        $n = strlen($line);
        while ($i < $n) {
            if ($inBlockComment) {
                $end = strpos($line, '*/', $i);
                if ($end === false) { $i = $n; continue; }
                $inBlockComment = false;
                $i = $end + 2;
                continue;
            }
            // komentar baris: -- dan #
            if (substr($line, $i, 2) === '--' || $line[$i] === '#') { $i = $n; continue; }
            // blok komentar biasa /* ... */ — BUKAN /*! ... */ atau /*M! ... */ (executable di MySQL)
            if (substr($line, $i, 2) === '/*' && substr($line, $i, 3) !== '/*!' && substr($line, $i, 4) !== '/*M!') {
                $inBlockComment = true; $i += 2; continue;
            }
            $buffer .= $line[$i];
            if ($line[$i] === ';') {
                $sql = trim($buffer);
                // Buang CREATE DATABASE & USE dari dump — kita sudah mengaturnya secara eksplisit
                if ($sql !== '' && !preg_match('/^\s*(CREATE\s+DATABASE|USE\s)/i', $sql)) {
                    try { $pdo->exec($sql); } catch (Throwable) { /* abaikan: tabel sudah ada / data ganda */ }
                    $count++;
                }
                $buffer = '';
            }
            $i++;
        }
    }
    fclose($fh);
    $sisa = trim($buffer);
    if ($sisa !== '' && !preg_match('/^\s*(CREATE\s+DATABASE|USE\s)/i', $sisa)) {
        try { $pdo->exec($sisa); } catch (Throwable) {}
        $count++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return $count;
}

$importLog = '';
if ($step >= 3 && ($_GET['aksi'] ?? '') === 'impor') {
    $sqlFile = __DIR__ . '/../database/sik.sql';
    if (!is_file($sqlFile)) {
        $importLog = "Berkas database/sik.sql tidak ditemukan.";
    } else {
        try {
            // Koneksi TANPA database agar bisa membuat database sik bila belum ada
            $pdo = db(true); // koneksi tanpa database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET latin1 COLLATE latin1_swedish_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            $pernyataan = import_sql_file($pdo, $sqlFile);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?");
            $stmt->execute([DB_NAME]);
            $jumlahTabel = (int)$stmt->fetchColumn();
            $importLog = "Impor berhasil ($pernyataan pernyataan). Jumlah tabel: $jumlahTabel.";
        } catch (Throwable $e) {
            $importLog = 'Impor gagal: ' . $e->getMessage();
        }
    }
}

// ---- Langkah 4: buat akun admin + pengaturan RS ----
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'simpan_akun') {
    $nama = trim($_POST['admin_user'] ?? 'admin');
    $pass = (string)($_POST['admin_pass'] ?? 'admin');
    if ($nama === '' || $pass === '') {
        $err = 'Nama pengguna dan kata sandi admin wajib diisi.';
        $step = 4;
    } else {
        try {
            db_exec('DELETE FROM admin WHERE CAST(AES_DECRYPT(usere, ?) AS CHAR) = ?', [AES_KEY, $nama]);
            db_exec('INSERT INTO admin (usere, passworde) VALUES (AES_ENCRYPT(?, ?), AES_ENCRYPT(?, ?))', [$nama, AES_KEY, $pass, AES_KEY]);
            setting_update([
                'nama_instansi' => trim($_POST['nama_instansi'] ?? 'Rumah Sakit'),
                'alamat_instansi' => trim($_POST['alamat_instansi'] ?? ''),
                'kabupaten' => trim($_POST['kabupaten'] ?? ''),
                'propinsi' => trim($_POST['propinsi'] ?? ''),
                'kontak' => trim($_POST['kontak'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
            ]);
            $_SESSION['install_admin'] = ['user' => $nama, 'pass' => $pass];
            $step = 5;
        } catch (Throwable $e) {
            $err = 'Gagal menyimpan: ' . $e->getMessage();
            $step = 4;
        }
    }
}

$settingSekarang = $dbOk ? (setting_rs() ?? []) : [];
?>
<!doctype html>
<html lang="id">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Instalasi | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="<?= asset('icons/css/bootstrap-icons.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('adminlte/css/adminlte.min.css') ?>" />
  <style>
    body { background: linear-gradient(135deg, #0b3d91, #1565c0 45%, #00b4d8); min-height: 100vh; }
    .wizard-card { border: 0; border-radius: 1.25rem; box-shadow: 0 20px 60px rgba(15,40,90,.3); }
    .wizard-steps .step {
      width: 40px; height: 40px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: #dee2e6; color: #6c757d; font-weight: 700;
    }
    .wizard-steps .step.active { background: #0d6efd; color: #fff; }
    .wizard-steps .step.done { background: #198754; color: #fff; }
    .wizard-steps .line { flex: 1; height: 3px; background: #dee2e6; margin: 0 4px; align-self: center; }
    .wizard-steps .line.done { background: #198754; }
    .status-ok { color: #198754; font-weight: 600; }
    .status-bad { color: #dc3545; font-weight: 600; }
  </style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card wizard-card">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width:72px;height:72px;font-size:2rem">
              <i class="bi bi-gear-wide-connected"></i>
            </div>
            <h3 class="fw-bold mb-1">Instalasi <?= APP_NAME ?></h3>
            <p class="text-body-secondary mb-0">Sistem Informasi Manajemen Rumah Sakit</p>
          </div>

          <!-- Langkah -->
          <div class="wizard-steps d-flex align-items-center mb-4">
            <?php
            $labels = ['Selamat Datang', 'Koneksi Database', 'Impor Database', 'Akun & Instansi', 'Selesai'];
            foreach ($labels as $i => $label):
                $n = $i + 1;
                $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
            ?>
              <div class="text-center" style="min-width:0">
                <div class="step <?= $cls ?> mx-auto"><?= $n < $step ? '<i class="bi bi-check"></i>' : $n ?></div>
                <div class="small mt-1 <?= $n === $step ? 'fw-bold' : 'text-body-secondary' ?>" style="font-size:.72rem"><?= e($label) ?></div>
              </div>
              <?php if ($n < 5): ?><div class="line <?= $n < $step ? 'done' : '' ?>"></div><?php endif; ?>
            <?php endforeach; ?>
          </div>

          <?php if ($err): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e($err) ?></div>
          <?php endif; ?>
          <?php if ($ok): ?>
            <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= e($ok) ?></div>
          <?php endif; ?>

          <!-- LANGKAH 1 -->
          <?php if ($step === 1): ?>
            <h5 class="fw-bold">Selamat Datang</h5>
            <p>Wizard ini akan membantu Anda menyiapkan <?= APP_NAME ?> dalam beberapa langkah: memeriksa koneksi database, mengimpor skema, dan membuat akun administrator.</p>
            <ul class="mb-4">
              <li>PHP 8.x + ekstensi <code>PDO MySQL</code></li>
              <li>Server MySQL/MariaDB yang dapat diakses</li>
              <li>Berkas skema <code>database/sik.sql</code> (sudah disertakan)</li>
            </ul>
            <form method="post"><input type="hidden" name="do" value="cek" /><button class="btn btn-primary btn-lg w-100"><i class="bi bi-arrow-right-circle me-1"></i>Mulai Instalasi</button></form>

          <!-- LANGKAH 2 -->
          <?php elseif ($step === 2): ?>
            <h5 class="fw-bold">Koneksi Database</h5>
            <table class="table table-sm">
              <tr><td>Versi PHP</td><td class="<?= version_compare(PHP_VERSION, '8.0', '>=') ? 'status-ok' : 'status-bad' ?>"><?= PHP_VERSION ?></td></tr>
              <tr><td>Ekstensi PDO MySQL</td><td class="<?= extension_loaded('pdo_mysql') ? 'status-ok' : 'status-bad' ?>"><?= extension_loaded('pdo_mysql') ? 'Tersedia' : 'Tidak ada' ?></td></tr>
              <tr><td>Host / Database</td><td><?= e(DB_HOST) ?>:<?= e(DB_PORT) ?> / <code><?= e(DB_NAME) ?></code></td></tr>
              <tr><td>Koneksi</td>
                <td class="<?= $dbOk ? 'status-ok' : 'status-bad' ?>">
                  <?= $dbOk ? 'Berhasil' : 'Gagal — ' . e($dbErr) ?>
                </td>
              </tr>
              <?php if ($dbOk): ?>
                <tr><td>Jumlah Tabel</td><td><?= number_format($jumlahTabel) ?> tabel</td></tr>
              <?php endif; ?>
            </table>
            <details class="mb-3">
              <summary class="text-body-secondary" style="cursor:pointer"><i class="bi bi-sliders me-1"></i>Ubah kredensial database (disimpan ke <code>config.php</code>)</summary>
              <form method="post" class="row g-2 mt-2">
                <input type="hidden" name="do" value="simpan_config" />
                <div class="col-md-3"><input class="form-control form-control-sm" name="db_host" value="<?= e(DB_HOST) ?>" placeholder="Host" /></div>
                <div class="col-md-3"><input class="form-control form-control-sm" name="db_user" value="<?= e(DB_USER) ?>" placeholder="Pengguna" /></div>
                <div class="col-md-3"><input class="form-control form-control-sm" type="text" name="db_pass" value="<?= e(DB_PASS) ?>" placeholder="Kata sandi" /></div>
                <div class="col-md-2"><input class="form-control form-control-sm" name="db_name" value="<?= e(DB_NAME) ?>" placeholder="Database" /></div>
                <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Simpan</button></div>
              </form>
            </details>
            <div class="d-flex gap-2">
              <?php if ($dbOk): ?>
                <form method="post"><input type="hidden" name="do" value="db" /><button class="btn btn-primary"><i class="bi bi-arrow-right me-1"></i>Lanjut</button></form>
              <?php else: ?>
                <a href="index.php?page=install&step=2" class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Uji Ulang</a>
              <?php endif; ?>
              <a href="index.php?page=install&step=1" class="btn btn-outline-secondary">Kembali</a>
            </div>

          <!-- LANGKAH 3 -->
          <?php elseif ($step === 3): ?>
            <h5 class="fw-bold">Impor Database</h5>
            <?php if ($jumlahTabel > 0): ?>
              <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Database <code><?= e(DB_NAME) ?></code> sudah berisi <strong><?= number_format($jumlahTabel) ?></strong> tabel. Anda dapat melewati langkah ini, atau mengimpor ulang untuk memulai dari data awal.</div>
            <?php endif; ?>
            <?php if ($importLog !== ''): ?>
              <div class="alert <?= str_starts_with($importLog, 'Impor berhasil') ? 'alert-success' : 'alert-danger' ?> py-2"><?= e($importLog) ?></div>
            <?php endif; ?>
            <p class="text-body-secondary">Berkas yang diimpor: <code>database/sik.sql</code> (skema + data contoh, 1.182 tabel).</p>
            <div class="d-flex gap-2 flex-wrap">
              <a href="index.php?page=install&step=3&aksi=impor" class="btn btn-warning" onclick="return confirm('Impor database/sik.sql? Data yang ada akan ditimpa.')"><i class="bi bi-database-down me-1"></i>Impor Database</a>
              <form method="post" class="d-inline"><input type="hidden" name="do" value="import" /><button class="btn btn-primary"><i class="bi bi-arrow-right me-1"></i>Lanjut</button></form>
              <a href="index.php?page=install&step=2" class="btn btn-outline-secondary">Kembali</a>
            </div>

          <!-- LANGKAH 4 -->
          <?php elseif ($step === 4): ?>
            <h5 class="fw-bold">Akun Administrator & Identitas Rumah Sakit</h5>
            <form method="post">
              <input type="hidden" name="do" value="simpan_akun" />
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Nama Pengguna Admin *</label>
                  <input class="form-control" name="admin_user" value="admin" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Kata Sandi Admin *</label>
                  <input class="form-control" type="text" name="admin_pass" value="admin" required />
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Rumah Sakit</label>
                <input class="form-control" name="nama_instansi" value="<?= e($settingSekarang['nama_instansi'] ?? '') ?>" />
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Alamat</label>
                  <input class="form-control" name="alamat_instansi" value="<?= e($settingSekarang['alamat_instansi'] ?? '') ?>" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Kabupaten</label>
                  <input class="form-control" name="kabupaten" value="<?= e($settingSekarang['kabupaten'] ?? '') ?>" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Propinsi</label>
                  <input class="form-control" name="propinsi" value="<?= e($settingSekarang['propinsi'] ?? '') ?>" />
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan & Lanjut</button>
                <a href="index.php?page=install&step=3" class="btn btn-outline-secondary">Kembali</a>
              </div>
            </form>

          <!-- LANGKAH 5 -->
          <?php elseif ($step === 5): ?>
            <div class="text-center py-3">
              <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success mb-3" style="width:88px;height:88px;font-size:2.5rem">
                <i class="bi bi-check-lg"></i>
              </div>
              <h4 class="fw-bold">Instalasi Selesai!</h4>
              <p class="text-body-secondary"><?= APP_NAME ?> siap digunakan. Simpan baik-baik kredensial administrator Anda.</p>
              <div class="card bg-body-tertiary border-0 mx-auto mb-4" style="max-width:320px">
                <div class="card-body py-3">
                  <div class="d-flex justify-content-between"><span class="text-body-secondary">Pengguna</span><strong><?= e(($_SESSION['install_admin'] ?? [])['user'] ?? $_POST['admin_user'] ?? 'admin') ?></strong></div>
                  <div class="d-flex justify-content-between"><span class="text-body-secondary">Kata sandi</span><strong><?= e(($_SESSION['install_admin'] ?? [])['pass'] ?? $_POST['admin_pass'] ?? 'admin') ?></strong></div>
                </div>
              </div>
              <form method="post">
                <input type="hidden" name="do" value="selesai" />
                <button class="btn btn-success btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk ke Aplikasi</button>
              </form>
            </div>
          <?php endif; ?>

          <div class="text-center mt-4">
            <small class="text-body-secondary"><?= APP_NAME ?> v<?= APP_VERSION ?> — Wizard Instalasi</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?= asset('bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
<?php
// install.php mengelola output sendiri (tanpa layout aplikasi)
return;
