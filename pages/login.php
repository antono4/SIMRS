<?php
// Halaman login bergaya modern (split-screen, tema siang/malam)
declare(strict_types=1);

$error = null;
$errorDb = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    try {
        // Jangan logout sebelum verifikasi — cukup timpa sesi bila kredensial valid
        if (auth_attempt($username, $password)) {
            unset($_SESSION['flash']); // bersihkan flash lama (mis. pesan keluar)
            redirect(url('dashboard'));
        }
        $error = 'Nama pengguna atau kata sandi salah.';
    } catch (Throwable $e) {
        $error = 'Gagal terhubung ke database: ' . $e->getMessage();
        $errorDb = true;
    }
} elseif (auth_check()) {
    // Sesi valid → lanjut ke dashboard. Sesi usang/rusak dibersihkan agar tidak loop.
    $u = auth_user();
    if (is_array($u) && !empty($u['username']) && !empty($u['role'])) {
        redirect(url('dashboard'));
    }
    auth_logout();
}

$namaRs = setting_nama_rs();
try {
    $rs = setting_rs();
} catch (Throwable) {
    $rs = [];
}
$alamatRs = trim(($rs['alamat_instansi'] ?? '') . ' ' . ($rs['kabupaten'] ?? ''));
?>
<!doctype html>
<html lang="id">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Masuk | <?= e($namaRs) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#0b3d91" />
  <link rel="icon" type="image/svg+xml" href="<?= asset('favicon.svg') ?>" />
  <script>
    (function () {
      const theme = localStorage.getItem('sk-theme') === 'dark' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <link rel="stylesheet" href="<?= asset('icons/css/bootstrap-icons.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('adminlte/css/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('css/simrs.css') ?>" />
  <style>
    /* Halaman masuk — panel merek (kiri) + panel formulir (kanan). */
    body.login-split {
      min-height: 100vh;
      margin: 0;
    }

    /* --- Panel merek --- */
    .login-hero {
      background: var(--sk-gradient);
      position: relative;
      overflow: hidden;
      color: #fff;
      isolation: isolate;
    }
    .login-hero::before,
    .login-hero::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      z-index: -1;
    }
    .login-hero::before {
      width: 560px; height: 560px; top: -200px; right: -160px;
      background: radial-gradient(circle at 32% 32%, rgba(255, 255, 255, .10), rgba(255, 255, 255, 0) 70%);
    }
    .login-hero::after {
      width: 460px; height: 460px; bottom: -190px; left: -140px;
      background: radial-gradient(circle at 62% 42%, rgba(255, 255, 255, .07), rgba(255, 255, 255, 0) 72%);
    }
    .login-hero .hero-grid {
      position: absolute;
      inset: 0;
      z-index: -1;
      opacity: .16;
      background-image:
        linear-gradient(rgba(255, 255, 255, .7) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, .7) 1px, transparent 1px);
      background-size: 48px 48px;
      -webkit-mask-image: radial-gradient(circle at 38% 42%, #000 0%, transparent 76%);
      mask-image: radial-gradient(circle at 38% 42%, #000 0%, transparent 76%);
    }
    .login-hero .hero-icon {
      width: 92px; height: 92px;
      border-radius: 26px;
      background: rgba(4, 20, 46, .32);
      border: 1px solid rgba(255, 255, 255, .28);
      -webkit-backdrop-filter: blur(8px);
      backdrop-filter: blur(8px);
      display: flex; align-items: center; justify-content: center;
      font-size: 2.9rem;
      box-shadow: 0 16px 40px rgba(0, 0, 0, .28);
    }
    .login-hero h1 { letter-spacing: -.02em; }
    .hero-feature {
      display: flex; align-items: center; gap: .7rem;
      padding: .62rem .85rem;
      border-radius: .8rem;
      background: rgba(4, 20, 46, .22);
      border: 1px solid rgba(255, 255, 255, .14);
      font-size: .92rem;
      line-height: 1.3;
      transition: background-color .2s ease, transform .2s ease;
    }
    .hero-feature:hover {
      background: rgba(255, 255, 255, .16);
      transform: translateX(3px);
    }
    .hero-feature i { font-size: 1.05rem; flex: 0 0 auto; }

    /* --- Panel formulir --- */
    .login-panel {
      display: flex; align-items: center; justify-content: center;
      padding: 2rem 1rem;
    }
    .login-card-modern {
      position: relative;
      width: 100%; max-width: 430px;
      border: 1px solid var(--sk-border);
      border-radius: 1.25rem;
      overflow: hidden;
      box-shadow: 0 22px 60px rgba(15, 40, 90, .16);
      animation: rise .55s cubic-bezier(.22, .8, .3, 1) both;
    }
    /* garis aksen merek di tepi atas kartu */
    .login-card-modern::before {
      content: '';
      position: absolute;
      inset: 0 0 auto 0;
      height: 5px;
      background: var(--sk-gradient);
    }
    @keyframes rise {
      from { opacity: 0; transform: translateY(26px); }
      to { opacity: 1; transform: translateY(0); }
    }
    [data-bs-theme="dark"] .login-card-modern {
      box-shadow: 0 22px 60px rgba(0, 0, 0, .6);
    }
    .login-badge {
      width: 76px; height: 76px;
      display: inline-flex; align-items: center; justify-content: center;
      border-radius: 1.4rem;
      background: var(--sk-gradient);
      color: #fff;
      font-size: 2.1rem;
      box-shadow: 0 12px 26px rgba(21, 101, 192, .38);
    }
    .btn-login {
      background: var(--sk-gradient);
      border: 0;
      color: #fff;
      font-weight: 600;
      letter-spacing: .01em;
      transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .btn-login:hover,
    .btn-login:focus {
      color: #fff;
      transform: translateY(-1px);
      filter: brightness(1.06);
      box-shadow: 0 12px 26px rgba(21, 101, 192, .42);
    }
    .theme-fab {
      position: fixed; top: 1rem; right: 1rem; z-index: 10;
      width: 44px; height: 44px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: var(--sk-surface);
      border: 1px solid var(--sk-border);
      color: var(--sk-text);
      box-shadow: var(--sk-shadow-sm);
    }
    .theme-fab:hover { color: var(--sk-brand); }

    @media (prefers-reduced-motion: reduce) {
      .login-card-modern { animation: none; }
    }
  </style>
</head>
<body class="login-split">
<button class="btn theme-fab" id="themeToggle" title="Ganti tema siang/malam" aria-label="Ganti tema siang/malam">
  <i class="bi bi-moon-stars" id="themeIcon"></i>
</button>
<div class="container-fluid g-0">
  <div class="row g-0 min-vh-100">
    <!-- Panel hero -->
    <div class="col-lg-7 d-none d-lg-flex login-hero align-items-center">
      <span class="hero-grid" aria-hidden="true"></span>
      <div class="px-5 position-relative" style="z-index:1">
        <div class="hero-icon mb-4"><i class="bi bi-hospital"></i></div>
        <h1 class="display-5 fw-bold mb-2"><?= e($namaRs) ?></h1>
        <p class="lead opacity-75 mb-4">Sistem Informasi Manajemen Rumah Sakit<br />terpadu, cepat, dan mudah digunakan.</p>
        <?php if ($alamatRs !== ''): ?>
          <p class="mb-4 opacity-75"><i class="bi bi-geo-alt me-2"></i><?= e($alamatRs) ?></p>
        <?php endif; ?>
        <div class="d-flex flex-column gap-2 mt-4">
          <div class="hero-feature"><i class="bi bi-clipboard2-pulse"></i><span>Pendaftaran, tindakan, diagnosa &amp; resep dalam satu alur</span></div>
          <div class="hero-feature"><i class="bi bi-hospital"></i><span>Manajemen kamar inap &amp; kasir terintegrasi</span></div>
          <div class="hero-feature"><i class="bi bi-shield-check"></i><span>Hak akses bertingkat yang aman</span></div>
        </div>
      </div>
    </div>
    <!-- Panel form -->
    <div class="col-lg-5 login-panel bg-body-tertiary">
      <div class="card login-card-modern">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="login-badge mb-3"><i class="bi bi-hospital"></i></div>
            <h4 class="fw-bold mb-1"><?= e($namaRs) ?></h4>
            <p class="text-body-secondary mb-0">Masuk untuk memulai sesi Anda</p>
          </div>
          <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center py-2" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              <div>
                <?= e($error) ?>
                <?php if ($errorDb): ?>
                  <br /><a href="<?= e(url('install')) ?>" class="alert-link">Buka wizard instalasi</a> untuk menyiapkan database.
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          <form method="post" action="<?= e(url('login')) ?>">
            <div class="form-floating mb-3">
              <input id="username" name="username" type="text" class="form-control" placeholder="Nama Pengguna" required autofocus />
              <label for="username"><i class="bi bi-person me-1"></i>Nama Pengguna</label>
            </div>
            <div class="form-floating mb-4">
              <input id="password" name="password" type="password" class="form-control" placeholder="Kata Sandi" required />
              <label for="password"><i class="bi bi-lock me-1"></i>Kata Sandi</label>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-login btn-lg text-white">
                <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
              </button>
            </div>
          </form>
          <hr class="my-4" />
          <p class="text-center text-body-secondary small mb-0">
            Akun bawaan <code>admin</code> / <code>admin</code><br />
            Kompatibel tabel <code>admin</code> &amp; <code>user</code> sistem
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?= asset('bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script>
  (function () {
    const icon = document.getElementById('themeIcon');
    const apply = (theme) => {
      document.documentElement.setAttribute('data-bs-theme', theme);
      if (icon) icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    };
    apply(localStorage.getItem('sk-theme') === 'dark' ? 'dark' : 'light');
    document.getElementById('themeToggle')?.addEventListener('click', () => {
      const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('sk-theme', next);
      apply(next);
    });
  })();
</script>
</body>
</html>
