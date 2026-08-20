<?php
// Modul Admin: panel administrasi — riwayat login (tracker), info database, akun admin
declare(strict_types=1);

// Hanya superuser (role admin) yang boleh membuka
if ((auth_user()['role'] ?? '') !== 'admin') {
    http_response_code(403);
    $pageTitle = 'Akses Ditolak';
    require __DIR__ . '/403.php';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'tambah_admin') {
        $nama = trim($_POST['usere'] ?? '');
        $pass = (string)($_POST['passworde'] ?? '');
        if ($nama === '' || $pass === '') {
            flash_set('danger', 'Nama dan kata sandi admin wajib diisi.');
        } else {
            $ada = db_val('SELECT COUNT(*) FROM admin WHERE CAST(AES_DECRYPT(usere, ?) AS CHAR) = ?', [AES_KEY, $nama]);
            if ($ada > 0) {
                db_exec('UPDATE admin SET passworde = AES_ENCRYPT(?, ?) WHERE CAST(AES_DECRYPT(usere, ?) AS CHAR) = ?',
                    [$pass, AES_KEY, AES_KEY, $nama]);
                flash_set('success', "Kata sandi admin $nama diperbarui.");
            } else {
                db_exec('INSERT INTO admin (usere, passworde) VALUES (AES_ENCRYPT(?, ?), AES_ENCRYPT(?, ?))',
                    [$nama, AES_KEY, $pass, AES_KEY]);
                flash_set('success', "Admin $nama ditambahkan.");
            }
        }
        redirect(url('admin', ['tab' => 'admin']));
    }
    if ($act === 'hapus_admin') {
        $nama = $_POST['usere'] ?? '';
        if ($nama === (auth_user()['username'] ?? '')) {
            flash_set('danger', 'Tidak dapat menghapus akun admin yang sedang digunakan.');
        } elseif ((int)db_val('SELECT COUNT(*) FROM admin') <= 1) {
            flash_set('danger', 'Tidak dapat menghapus admin terakhir.');
        } else {
            db_exec('DELETE FROM admin WHERE CAST(AES_DECRYPT(usere, ?) AS CHAR) = ?', [AES_KEY, $nama]);
            flash_set('success', "Admin $nama dihapus.");
        }
        redirect(url('admin', ['tab' => 'admin']));
    }
}

$tab = $_GET['tab'] ?? 'tracker';
$pageTitle = 'Panel Administrator';

// Riwayat login (tracker)
$tracker = db_all(
    'SELECT t.nip, t.tgl_login, t.jam_login
     FROM tracker t ORDER BY t.tgl_login DESC, t.jam_login DESC LIMIT 100'
);
// Tracer SQL (audit query) bila diaktifkan aplikasi Java
$tracerSql = db_all('SELECT tanggal, usere, sqle FROM trackersql ORDER BY tanggal DESC LIMIT 50');
// Info database
$tables = db_all(
    "SELECT table_name, table_rows, data_length, index_length, engine
     FROM information_schema.tables WHERE table_schema = ? ORDER BY table_rows DESC LIMIT 20",
    [DB_NAME]
);
$ukuranDb = db_row(
    "SELECT SUM(data_length) AS data, SUM(index_length) AS idx, COUNT(*) AS jml
     FROM information_schema.tables WHERE table_schema = ?",
    [DB_NAME]
);
// Daftar admin
$adminList = db_all('SELECT CAST(AES_DECRYPT(usere, ?) AS CHAR) AS username FROM admin', [AES_KEY]);

require __DIR__ . '/../includes/header.php';
?>
<div class="row">
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-primary">
      <div class="inner"><h3><?= number_format((int)$ukuranDb['jml']) ?></h3><p>Tabel Database</p></div>
      <i class="small-box-icon bi bi-database"></i>
    </div>
  </div>
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-success">
      <div class="inner"><h3><?= number_format(((float)$ukuranDb['data'] + (float)$ukuranDb['idx']) / 1048576, 1) ?> MB</h3><p>Ukuran Data</p></div>
      <i class="small-box-icon bi bi-hdd"></i>
    </div>
  </div>
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-warning">
      <div class="inner"><h3><?= count($tracker) ?></h3><p>Riwayat Login</p></div>
      <i class="small-box-icon bi bi-clock-history"></i>
    </div>
  </div>
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-danger">
      <div class="inner"><h3><?= count($adminList) ?></h3><p>Superuser (Admin)</p></div>
      <i class="small-box-icon bi bi-shield-lock"></i>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-header p-0 pt-1">
    <ul class="nav nav-tabs">
      <li class="nav-item"><a class="nav-link <?= $tab === 'tracker' ? 'active' : '' ?>" href="<?= e(url('admin')) ?>"><i class="bi bi-clock-history me-1"></i>Riwayat Login</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab === 'tracer' ? 'active' : '' ?>" href="<?= e(url('admin', ['tab' => 'tracer'])) ?>"><i class="bi bi-journal-text me-1"></i>Audit Query</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab === 'database' ? 'active' : '' ?>" href="<?= e(url('admin', ['tab' => 'database'])) ?>"><i class="bi bi-database me-1"></i>Info Database</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab === 'admin' ? 'active' : '' ?>" href="<?= e(url('admin', ['tab' => 'admin'])) ?>"><i class="bi bi-shield-lock me-1"></i>Akun Admin</a></li>
    </ul>
  </div>
  <div class="card-body p-0">
    <?php if ($tab === 'tracker'): ?>
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th style="width:50px">No</th><th>Pengguna (NIP/ID)</th><th>Tanggal Login</th><th>Jam Login</th></tr></thead>
          <tbody>
            <?php foreach ($tracker as $i => $t): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><i class="bi bi-person-badge me-1"></i><?= e($t['nip']) ?></td>
                <td><?= e(tgl_indo($t['tgl_login'])) ?></td>
                <td><?= e($t['jam_login']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$tracker): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">Belum ada riwayat login.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($tab === 'tracer'): ?>
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>Query</th></tr></thead>
          <tbody>
            <?php foreach ($tracerSql as $t): ?>
              <tr>
                <td><?= e($t['tanggal']) ?></td>
                <td><?= e($t['usere']) ?></td>
                <td><code class="small"><?= e(mb_strimwidth((string)$t['sqle'], 0, 180, '…')) ?></code></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$tracerSql): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">Belum ada audit query (tabel trackersql diisi oleh aplikasi desktop ketika tracer diaktifkan).</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($tab === 'database'): ?>
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th>Tabel</th><th class="text-end">Perkiraan Baris</th><th class="text-end">Data</th><th class="text-end">Indeks</th><th>Engine</th></tr></thead>
          <tbody>
            <?php foreach ($tables as $t): ?>
              <tr>
                <td><code><?= e($t['table_name']) ?></code></td>
                <td class="text-end"><?= number_format((int)$t['table_rows']) ?></td>
                <td class="text-end"><?= number_format((float)$t['data_length'] / 1024, 1) ?> KB</td>
                <td class="text-end"><?= number_format((float)$t['index_length'] / 1024, 1) ?> KB</td>
                <td><?= e($t['engine']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="p-3">
        <div class="row">
          <div class="col-lg-7">
            <h6 class="fw-bold">Daftar Superuser (tabel <code>admin</code>)</h6>
            <div class="table-responsive">
              <table class="table table-sm table-striped mb-0">
                <tbody>
                  <?php foreach ($adminList as $a): ?>
                    <tr>
                      <td><i class="bi bi-shield-lock me-1"></i><?= e($a['username']) ?></td>
                      <td style="width:60px">
                        <form method="post" action="<?= e(url('admin')) ?>" onsubmit="return confirm('Hapus admin <?= e($a['username']) ?>?')">
                          <input type="hidden" name="act" value="hapus_admin" />
                          <input type="hidden" name="usere" value="<?= e($a['username']) ?>" />
                          <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-5">
            <h6 class="fw-bold">Tambah / Perbarui Admin</h6>
            <form method="post" action="<?= e(url('admin')) ?>">
              <input type="hidden" name="act" value="tambah_admin" />
              <div class="mb-2"><input class="form-control" name="usere" placeholder="Nama pengguna" required /></div>
              <div class="mb-2"><input class="form-control" type="password" name="passworde" placeholder="Kata sandi" required /></div>
              <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Simpan Admin</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
