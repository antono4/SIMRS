<?php
// Modul Master Poliklinik — tabel poliklinik skema sik
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'delete') {
        $kd = $_POST['kd_poli'] ?? '';
        $dipakai = (int)db_val('SELECT COUNT(*) FROM reg_periksa WHERE kd_poli = ?', [$kd]);
        if ($dipakai > 0) {
            flash_set('danger', "Poliklinik $kd tidak dapat dihapus karena dipakai di $dipakai registrasi.");
        } else {
            db_exec('DELETE FROM poliklinik WHERE kd_poli = ?', [$kd]);
            flash_set('success', "Poliklinik $kd dihapus.");
        }
        redirect(url('poliklinik'));
    }
    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = strtoupper(trim($_POST['kd_poli'] ?? ''));
        $data = [
            'nm_poli' => trim($_POST['nm_poli'] ?? ''),
            'registrasi' => (float)($_POST['registrasi'] ?? 0),
            'registrasilama' => (float)($_POST['registrasilama'] ?? 0),
            'status' => $_POST['status'] ?? '1',
        ];
        if ($kd === '' || $data['nm_poli'] === '') {
            flash_set('danger', 'Kode dan nama poliklinik wajib diisi.');
            redirect(url('poliklinik', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if ($isEdit) {
            db_exec('UPDATE poliklinik SET nm_poli=?, registrasi=?, registrasilama=?, status=? WHERE kd_poli=?',
                [$data['nm_poli'], $data['registrasi'], $data['registrasilama'], $data['status'], $kd]);
            flash_set('success', "Poliklinik $kd diperbarui.");
        } else {
            db_exec('INSERT INTO poliklinik (kd_poli, nm_poli, registrasi, registrasilama, status) VALUES (?,?,?,?,?)',
                [$kd, $data['nm_poli'], $data['registrasi'], $data['registrasilama'], $data['status']]);
            flash_set('success', "Poliklinik $kd ditambahkan.");
        }
        redirect(url('poliklinik'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Poliklinik';
    $kd = $_GET['kd'] ?? null;
    $poli = $kd ? db_row('SELECT * FROM poliklinik WHERE kd_poli = ?', [$kd]) : null;
    if (!$poli) {
        $poli = ['kd_poli' => '', 'nm_poli' => '', 'registrasi' => 0, 'registrasilama' => 0, 'status' => '1'];
    }
    require __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('poliklinik')) ?>" class="card card-primary col-lg-6">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Poliklinik' : 'Poliklinik Baru' ?></h3></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Kode Poli (maks 5 karakter)</label>
          <input class="form-control" name="kd_poli" maxlength="5" value="<?= e($poli['kd_poli']) ?>" <?= $kd ? 'readonly' : '' ?> required />
        </div>
        <div class="mb-3">
          <label class="form-label">Nama Poliklinik</label>
          <input class="form-control" name="nm_poli" value="<?= e($poli['nm_poli']) ?>" required />
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Biaya Registrasi Baru (Rp)</label>
            <input class="form-control" type="number" step="0.01" name="registrasi" value="<?= e((string)$poli['registrasi']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Biaya Registrasi Lama (Rp)</label>
            <input class="form-control" type="number" step="0.01" name="registrasilama" value="<?= e((string)$poli['registrasilama']) ?>" />
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="1" <?= $poli['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
            <option value="0" <?= $poli['status'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('poliklinik')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Poliklinik';
$rows = db_all('SELECT * FROM poliklinik ORDER BY kd_poli');
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
      <h3 class="card-title mb-0">Daftar Poliklinik (<?= count($rows) ?>)</h3>
      <a href="<?= e(url('poliklinik', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Poliklinik Baru</a>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode</th><th>Nama Poliklinik</th><th class="text-end">Reg. Baru</th><th class="text-end">Reg. Lama</th><th>Status</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kd_poli']) ?></span></td>
            <td><?= e($r['nm_poli']) ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['registrasi'], 0, ',', '.') ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['registrasilama'], 0, ',', '.') ?></td>
            <td><?= $r['status'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('poliklinik', ['action' => 'form', 'kd' => $r['kd_poli']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('poliklinik')) ?>" class="d-inline" onsubmit="return confirm('Hapus poliklinik <?= e($r['kd_poli']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kd_poli" value="<?= e($r['kd_poli']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
