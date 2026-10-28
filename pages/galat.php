<?php
/**
 * Halaman galat terakhir (500) — sengaja berdiri sendiri.
 *
 * Dipanggil index.php saat sebuah halaman melempar exception. Halaman ini
 * TIDAK memakai database, sesi, header.php, maupun berkas aset, karena justru
 * hal-hal itulah yang sering menjadi penyebab galat.
 *
 * Sebelumnya index.php mengalihkan (302) ke HTTP_REFERER saat galat. Pada
 * pemuatan halaman biasa HTTP_REFERER adalah halaman itu sendiri, sehingga
 * galat apa pun berubah menjadi pengalihan tanpa henti
 * (ERR_TOO_MANY_REDIRECTS) dan pesan aslinya tersembunyi. Sekarang galat
 * ditampilkan langsung supaya penyebabnya terlihat.
 */
declare(strict_types=1);

http_response_code(500);

// Variabel disiapkan index.php; nilai cadangan dipakai bila berkas ini dibuka sendiri.
$pesan = $galatPesan ?? 'Terjadi kesalahan yang tidak terduga.';
$berkas = $galatBerkas ?? '';
$baris = $galatBaris ?? 0;
$kelas = $galatKelas ?? 'Error';
$namaApp = defined('APP_NAME') ? APP_NAME : 'SIMRS';
$versiApp = defined('APP_VERSION') ? APP_VERSION : '';

// Tampilkan lokasi berkas hanya untuk pengguna lokal (localhost), bukan di server publik.
$host = $_SERVER['HTTP_HOST'] ?? '';
$hostTanpaPort = strtolower((string)preg_replace('/:\d+$/', '', $host));
$lokal = in_array($hostTanpaPort, ['localhost', '127.0.0.1', '::1'], true)
    || str_ends_with($hostTanpaPort, '.local')
    || str_ends_with($hostTanpaPort, '.test');
$tampilkanLokasi = $lokal && $berkas !== '';

$hint = null;
if (stripos($pesan, 'could not find driver') !== false) {
    $hint = 'Ekstensi <code>pdo_mysql</code> belum aktif. Buka <code>php.ini</code>, hapus tanda <code>;</code> di depan <code>extension=pdo_mysql</code>, lalu mulai ulang Apache.';
} elseif (stripos($pesan, 'Unknown database') !== false) {
    $hint = 'Database belum ada atau namanya berbeda. Sesuaikan <code>DB_NAME</code> di <code>config.php</code>, atau impor <code>database/sik.sql</code>.';
} elseif (stripos($pesan, 'Access denied') !== false) {
    $hint = 'Nama pengguna atau kata sandi MySQL salah. Periksa <code>DB_USER</code> dan <code>DB_PASS</code> di <code>config.php</code>.';
} elseif (stripos($pesan, 'Connection refused') !== false || stripos($pesan, 'No such host') !== false) {
    $hint = 'Server MySQL tidak berjalan atau alamatnya salah. Jalankan MySQL/MariaDB, lalu periksa <code>DB_HOST</code> dan <code>DB_PORT</code> di <code>config.php</code>.';
} elseif (stripos($pesan, "doesn't exist") !== false || stripos($pesan, 'Base table or view not found') !== false) {
    $hint = 'Tabel yang dibutuhkan belum ada. Impor <code>database/sik.sql</code> ke database tersebut.';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kesalahan | <?= htmlspecialchars($namaApp, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root { color-scheme: light dark; }
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 1.5rem;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #f1f5f9; color: #0f172a;
    }
    .kartu {
      width: 100%; max-width: 640px;
      background: #fff; border: 1px solid #e2e8f0;
      border-radius: 1rem; overflow: hidden;
      box-shadow: 0 18px 50px rgba(15, 40, 90, .12);
    }
    .kartu::before { content: ''; display: block; height: 5px; background: linear-gradient(90deg, #dc2626, #f97316); }
    .isi { padding: 2rem; }
    .ikon {
      width: 56px; height: 56px; border-radius: 1rem;
      display: flex; align-items: center; justify-content: center;
      background: #fee2e2; color: #b91c1c; font-size: 1.7rem;
      margin-bottom: 1rem;
    }
    h1 { margin: 0 0 .35rem; font-size: 1.35rem; }
    .sub { margin: 0 0 1.25rem; color: #64748b; font-size: .95rem; }
    .pesan {
      background: #0f172a; color: #f8fafc;
      border-radius: .6rem; padding: .9rem 1rem;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: .85rem; line-height: 1.5;
      overflow-x: auto; white-space: pre-wrap; word-break: break-word;
      margin: 0;
    }
    .lokasi { margin: .6rem 0 0; font-size: .8rem; color: #64748b; font-family: ui-monospace, Menlo, Consolas, monospace; }
    .hint {
      margin: 1.1rem 0 0; padding: .85rem 1rem;
      background: #fffbeb; border: 1px solid #fde68a; border-radius: .6rem;
      font-size: .9rem; line-height: 1.5;
    }
    .hint strong { display: block; margin-bottom: .25rem; }
    code { background: rgba(15, 23, 42, .08); padding: .1em .35em; border-radius: .3em; font-size: .9em; }
    .aksi { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: .6rem; }
    a.tombol {
      display: inline-block; text-decoration: none;
      padding: .6rem 1.1rem; border-radius: .55rem;
      font-size: .92rem; font-weight: 600;
      background: #1565c0; color: #fff;
    }
    a.tombol.sekunder { background: #e2e8f0; color: #0f172a; }
    .kaki { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; font-size: .78rem; color: #94a3b8; }
    @media (prefers-color-scheme: dark) {
      body { background: #0b1220; color: #e2e8f0; }
      .kartu { background: #111c2e; border-color: #1e2d45; }
      .sub, .lokasi { color: #94a3b8; }
      .ikon { background: rgba(220, 38, 38, .18); color: #fca5a5; }
      .hint { background: rgba(250, 204, 21, .1); border-color: rgba(250, 204, 21, .3); }
      code { background: rgba(255, 255, 255, .1); }
      a.tombol.sekunder { background: #1e2d45; color: #e2e8f0; }
      .kaki { border-top-color: #1e2d45; }
    }
  </style>
</head>
<body>
  <div class="kartu">
    <div class="isi">
      <div class="ikon" aria-hidden="true">&#9888;</div>
      <h1>Aplikasi gagal memuat halaman</h1>
      <p class="sub">
        Halaman ini ditampilkan langsung, bukan dialihkan, supaya penyebabnya terlihat
        dan tidak terjadi pengalihan tanpa henti.
      </p>
      <pre class="pesan"><?= htmlspecialchars($kelas . ': ' . $pesan, ENT_QUOTES, 'UTF-8') ?></pre>
      <?php if ($tampilkanLokasi): ?>
        <p class="lokasi"><?= htmlspecialchars($berkas . ':' . $baris, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <?php if ($hint !== null): ?>
        <div class="hint"><strong>Kemungkinan penyebab</strong><?= $hint ?></div>
      <?php endif; ?>
      <div class="aksi">
        <a class="tombol" href="index.php?page=dashboard">Coba lagi</a>
        <a class="tombol sekunder" href="index.php?page=diagnostik">Buka diagnostik</a>
        <a class="tombol sekunder" href="index.php?page=install">Buka wizard instalasi</a>
      </div>
      <p class="kaki">
        <?= htmlspecialchars($namaApp . ($versiApp !== '' ? ' v' . $versiApp : ''), ENT_QUOTES, 'UTF-8') ?>
        &middot; PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
  </div>
</body>
</html>
