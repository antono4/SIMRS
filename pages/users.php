<?php
// Modul Manajemen Pengguna & Hak Akses — tabel user Khanza (id_user/password terenkripsi AES 'nur')
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';
$key = KHANZA_AES_KEY;

function user_columns(): array
{
    static $cols = null;
    if ($cols === null) {
        $cols = db_all(
            "SELECT column_name AS c FROM information_schema.columns
             WHERE table_schema = ? AND table_name = 'user' AND column_name NOT IN ('id_user','password')
             ORDER BY ordinal_position",
            [DB_NAME]
        );
        $cols = array_column($cols, 'c');
    }
    return $cols;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $granted = $_POST['perms'] ?? [];
        if (!is_array($granted)) {
            $granted = [];
        }
        // Batasi pada katalog izin yang dikenal aplikasi
        $granted = array_values(array_intersect($granted, array_keys(PERMISSION_CATALOG)));

        if ($username === '') {
            flash_set('danger', 'Nama pengguna wajib diisi.');
            redirect(url('users', ['action' => 'form']));
        }

        if ($isEdit) {
            $original = $_POST['original_username'] ?? '';
            $set = [];
            $params = [];
            foreach (PERMISSION_CATALOG as $flag => $label) {
                $set[] = "$flag = ?";
                $params[] = in_array($flag, $granted, true) ? 'true' : 'false';
            }
            $sql = 'UPDATE user SET ' . implode(', ', $set);
            if ($username !== $original) {
                $sql .= ', id_user = AES_ENCRYPT(?, ?)';
                $params[] = $username;
                $params[] = $key;
            }
            if ($password !== '') {
                $sql .= ', password = AES_ENCRYPT(?, ?)';
                $params[] = $password;
                $params[] = $key;
            }
            $sql .= ' WHERE CAST(AES_DECRYPT(id_user, ?) AS CHAR) = ?';
            $params[] = $key;
            $params[] = $original;
            db_exec($sql, $params);
            flash_set('success', "Pengguna $username diperbarui.");
        } else {
            if ($password === '') {
                flash_set('danger', 'Kata sandi wajib diisi untuk pengguna baru.');
                redirect(url('users', ['action' => 'form']));
            }
            $ada = db_val('SELECT COUNT(*) FROM user WHERE CAST(AES_DECRYPT(id_user, ?) AS CHAR) = ?', [$key, $username]);
            if ($ada > 0) {
                flash_set('danger', "Nama pengguna $username sudah dipakai.");
                redirect(url('users', ['action' => 'form']));
            }
            // Seluruh kolom izin diisi 'false', lalu timpa dengan izin yang dipilih
            $flags = [];
            $marks = [];
            $params = [];
            foreach (user_columns() as $col) {
                $flags[] = $col;
                $marks[] = '?';
                $params[] = in_array($col, $granted, true) ? 'true' : 'false';
            }
            $sql = 'INSERT INTO user (id_user, password, ' . implode(', ', $flags) . ')
                    VALUES (AES_ENCRYPT(?, ?), AES_ENCRYPT(?, ?), ' . implode(', ', $marks) . ')';
            db_exec($sql, array_merge([$username, $key, $password, $key], $params));
            flash_set('success', "Pengguna $username ditambahkan dengan " . count($granted) . ' hak akses.');
        }
        redirect(url('users'));
    }

    if ($act === 'delete') {
        $username = $_POST['username'] ?? '';
        if ($username === (auth_user()['username'] ?? '')) {
            flash_set('danger', 'Tidak dapat menghapus akun yang sedang digunakan.');
        } else {
            db_exec('DELETE FROM user WHERE CAST(AES_DECRYPT(id_user, ?) AS CHAR) = ?', [$key, $username]);
            flash_set('success', "Pengguna $username dihapus.");
        }
        redirect(url('users'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Pengguna';
    $username = $_GET['u'] ?? null;
    $row = null;
    if ($username) {
        $row = db_row(
            'SELECT * FROM user WHERE CAST(AES_DECRYPT(id_user, ?) AS CHAR) = ?',
            [$key, $username]
        );
        if (!$row) {
            flash_set('danger', 'Pengguna tidak ditemukan.');
            redirect(url('users'));
        }
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('users')) ?>" class="card card-primary col-lg-7">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $row ? 'edit' : 'add' ?>" />
      <input type="hidden" name="original_username" value="<?= e($username ?? '') ?>" />
      <div class="card-header"><h3 class="card-title"><?= $row ? 'Ubah Pengguna' : 'Pengguna Baru' ?></h3></div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Nama Pengguna *</label>
            <input class="form-control" name="username" value="<?= e($username ?? '') ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Kata Sandi <?= $row ? '(kosongkan bila tidak diubah)' : '*' ?></label>
            <input class="form-control" type="password" name="password" autocomplete="new-password" />
          </div>
        </div>
        <h5>Hak Akses Modul</h5>
        <p class="text-muted small">Centang modul yang boleh diakses pengguna ini. Izin disimpan di tabel <code>user</code> yang sama dengan SIMRS Khanza.</p>
        <div class="row">
          <?php foreach (PERMISSION_CATALOG as $flag => $label): ?>
            <div class="col-md-6">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="perms[]" value="<?= e($flag) ?>" id="perm_<?= e($flag) ?>"
                  <?= ($row[$flag] ?? 'false') === 'true' ? 'checked' : '' ?> />
                <label class="form-check-label" for="perm_<?= e($flag) ?>"><?= e($label) ?> <code class="small"><?= e($flag) ?></code></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('users')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

// Daftar pengguna
$pageTitle = 'Manajemen Pengguna';
$flagsSelect = implode(', ', array_keys(PERMISSION_CATALOG));
$rows = db_all("SELECT CAST(AES_DECRYPT(id_user, ?) AS CHAR) AS username, $flagsSelect FROM user ORDER BY username", [$key]);
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
      <h3 class="card-title mb-0">Daftar Pengguna (<?= count($rows) ?>)</h3>
      <a href="<?= e(url('users', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Pengguna Baru</a>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Nama Pengguna</th><th>Hak Akses Aktif</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $aktif = array_filter(array_keys(PERMISSION_CATALOG), fn($f) => ($r[$f] ?? 'false') === 'true'); ?>
          <tr>
            <td><i class="bi bi-person-badge me-1"></i><strong><?= e($r['username']) ?></strong></td>
            <td>
              <?php if ($aktif): ?>
                <?php foreach ($aktif as $f): ?>
                  <span class="badge text-bg-info me-1"><?= e(PERMISSION_CATALOG[$f]) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="badge text-bg-secondary">Tanpa hak akses</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('users', ['action' => 'form', 'u' => $r['username']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('users')) ?>" class="d-inline" onsubmit="return confirm('Hapus pengguna <?= e($r['username']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="username" value="<?= e($r['username']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="3" class="text-center text-muted py-4">Belum ada pengguna. Akun superuser memakai tabel <code>admin</code> dan tidak tampil di sini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted small">
    Superuser (tabel <code>admin</code>) selalu memiliki akses penuh. Pengguna tabel <code>user</code> dibatasi sesuai hak akses di atas &mdash; menu yang tidak diizinkan disembunyikan dan akses langsung via URL ditolak (403).
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
