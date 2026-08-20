<?php
// Modul Master Penanggung Jawab / Cara Bayar — tabel penjab skema sik
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'delete') {
        $kd = $_POST['kd_pj'] ?? '';
        $dipakai = (int)db_val('SELECT COUNT(*) FROM pasien WHERE kd_pj = ?', [$kd]);
        if ($dipakai > 0) {
            flash_set('danger', "Penanggung jawab $kd tidak dapat dihapus karena dipakai $dipakai pasien.");
        } else {
            db_exec('DELETE FROM penjab WHERE kd_pj = ?', [$kd]);
            flash_set('success', "Penanggung jawab $kd dihapus.");
        }
        redirect(url('penjab'));
    }
    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = strtoupper(trim($_POST['kd_pj'] ?? ''));
        $data = [
            'png_jawab' => trim($_POST['png_jawab'] ?? ''),
            'nama_perusahaan' => trim($_POST['nama_perusahaan'] ?? ''),
            'alamat_asuransi' => trim($_POST['alamat_asuransi'] ?? ''),
            'no_telp' => trim($_POST['no_telp'] ?? ''),
            'attn' => trim($_POST['attn'] ?? ''),
            'status' => $_POST['status'] ?? '1',
        ];
        if ($kd === '' || $data['png_jawab'] === '') {
            flash_set('danger', 'Kode dan nama penanggung jawab wajib diisi.');
            redirect(url('penjab', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if ($isEdit) {
            db_exec('UPDATE penjab SET png_jawab=?, nama_perusahaan=?, alamat_asuransi=?, no_telp=?, attn=?, status=? WHERE kd_pj=?',
                [$data['png_jawab'], $data['nama_perusahaan'], $data['alamat_asuransi'], $data['no_telp'], $data['attn'], $data['status'], $kd]);
            flash_set('success', "Penanggung jawab $kd diperbarui.");
        } else {
            db_exec('INSERT INTO penjab (kd_pj, png_jawab, nama_perusahaan, alamat_asuransi, no_telp, attn, status) VALUES (?,?,?,?,?,?,?)',
                [$kd, $data['png_jawab'], $data['nama_perusahaan'], $data['alamat_asuransi'], $data['no_telp'], $data['attn'], $data['status']]);
            flash_set('success', "Penanggung jawab $kd ditambahkan.");
        }
        redirect(url('penjab'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Penanggung Jawab';
    $kd = $_GET['kd'] ?? null;
    $pj = $kd ? db_row('SELECT * FROM penjab WHERE kd_pj = ?', [$kd]) : null;
    if (!$pj) {
        $pj = ['kd_pj' => '', 'png_jawab' => '', 'nama_perusahaan' => '', 'alamat_asuransi' => '', 'no_telp' => '', 'attn' => '', 'status' => '1'];
    }
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('penjab')) ?>" class="card card-primary col-lg-6">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Penanggung Jawab' : 'Penanggung Jawab Baru' ?></h3></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Kode (maks 3 karakter)</label>
          <input class="form-control" name="kd_pj" maxlength="3" value="<?= e($pj['kd_pj']) ?>" <?= $kd ? 'readonly' : '' ?> required />
        </div>
        <div class="mb-3">
          <label class="form-label">Nama Penanggung Jawab</label>
          <input class="form-control" name="png_jawab" value="<?= e($pj['png_jawab']) ?>" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Nama Perusahaan/Asuransi</label>
          <input class="form-control" name="nama_perusahaan" value="<?= e($pj['nama_perusahaan']) ?>" />
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat</label>
          <input class="form-control" name="alamat_asuransi" value="<?= e($pj['alamat_asuransi']) ?>" />
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">No. Telp</label>
            <input class="form-control" name="no_telp" value="<?= e($pj['no_telp']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Attn / Kontak</label>
            <input class="form-control" name="attn" value="<?= e($pj['attn']) ?>" />
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="1" <?= $pj['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
            <option value="0" <?= $pj['status'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('penjab')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Penanggung Jawab / Cara Bayar';
$rows = db_all('SELECT * FROM penjab ORDER BY kd_pj');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
      <h3 class="card-title mb-0">Daftar Penanggung Jawab (<?= count($rows) ?>)</h3>
      <a href="<?= e(url('penjab', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode</th><th>Nama</th><th>Perusahaan</th><th>No. Telp</th><th>Status</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kd_pj']) ?></span></td>
            <td><?= e($r['png_jawab']) ?></td>
            <td><?= e($r['nama_perusahaan']) ?></td>
            <td><?= e($r['no_telp']) ?></td>
            <td><?= $r['status'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('penjab', ['action' => 'form', 'kd' => $r['kd_pj']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('penjab')) ?>" class="d-inline" onsubmit="return confirm('Hapus penanggung jawab <?= e($r['kd_pj']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kd_pj" value="<?= e($r['kd_pj']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
