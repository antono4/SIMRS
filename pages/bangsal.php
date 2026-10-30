<?php
// Modul Master Bangsal — tabel bangsal skema sik
// kamar.kd_bangsal -> bangsal ON DELETE CASCADE, dan kamar_inap.kd_kamar ->
// kamar ON DELETE CASCADE. Jadi menghapus bangsal dapat menghapus kamar beserta
// seluruh riwayat rawat inap di dalamnya — penghapusan harus dicegah lebih dulu.
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $kd = $_POST['kd_bangsal'] ?? '';
        $kamar = (int)db_val('SELECT COUNT(*) FROM kamar WHERE kd_bangsal = ?', [$kd]);
        if ($kamar > 0) {
            $inap = (int)db_val(
                'SELECT COUNT(*) FROM kamar_inap ki JOIN kamar k ON k.kd_kamar = ki.kd_kamar WHERE k.kd_bangsal = ?',
                [$kd]
            );
            $pesan = "Bangsal $kd tidak dapat dihapus karena masih memiliki $kamar kamar";
            $pesan .= $inap > 0 ? " dengan $inap riwayat rawat inap." : '. Pindahkan atau hapus kamarnya lebih dulu.';
            flash_set('danger', $pesan);
        } else {
            $galat = db_coba(fn() => db_exec('DELETE FROM bangsal WHERE kd_bangsal = ?', [$kd]));
            if ($galat === null) {
                audit_catat('bangsal', 'delete', 'bangsal', $kd);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Bangsal $kd dihapus.");
        }
        redirect(url('bangsal'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = strtoupper(trim($_POST['kd_bangsal'] ?? ''));
        $nama = trim($_POST['nm_bangsal'] ?? '');
        if ($kd === '' || $nama === '') {
            flash_set('danger', 'Kode dan nama bangsal wajib diisi.');
            redirect(url('bangsal', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if (strlen($kd) > 5 || strlen($nama) > 30) {
            flash_set('danger', 'Kode bangsal maksimal 5 karakter dan nama maksimal 30 karakter.');
            redirect(url('bangsal', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        $status = $_POST['status'] ?? '1';
        if ($isEdit) {
            $galat = db_coba(fn() => db_exec('UPDATE bangsal SET nm_bangsal=?, status=? WHERE kd_bangsal=?', [$nama, $status, $kd]));
            if ($galat === null) {
                audit_catat('bangsal', 'save', 'bangsal', $kd, $nama);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Bangsal $kd diperbarui.");
        } else {
            $galat = db_coba(fn() => db_exec('INSERT INTO bangsal (kd_bangsal, nm_bangsal, status) VALUES (?,?,?)', [$kd, $nama, $status]));
            if ($galat === null) {
                audit_catat('bangsal', 'create', 'bangsal', $kd, $nama);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Bangsal $kd ditambahkan.");
        }
        redirect(url('bangsal'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Bangsal';
    $kd = $_GET['kd'] ?? null;
    $b = $kd ? db_row('SELECT * FROM bangsal WHERE kd_bangsal = ?', [$kd]) : null;
    if (!$b) {
        $b = ['kd_bangsal' => '', 'nm_bangsal' => '', 'status' => '1'];
    }
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('bangsal')) ?>" class="card card-primary col-lg-6">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Bangsal' : 'Bangsal Baru' ?></h3></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Kode Bangsal (maks 5 karakter)</label>
          <input class="form-control" name="kd_bangsal" maxlength="5" value="<?= e($b['kd_bangsal']) ?>" <?= $kd ? 'readonly' : '' ?> required />
        </div>
        <div class="mb-3">
          <label class="form-label">Nama Bangsal</label>
          <input class="form-control" name="nm_bangsal" maxlength="30" value="<?= e($b['nm_bangsal']) ?>" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="1" <?= $b['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
            <option value="0" <?= $b['status'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('bangsal')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Bangsal';
$rows = db_all(
    "SELECT b.*, COUNT(k.kd_kamar) AS jml_kamar,
            SUM(CASE WHEN k.status = 'ISI' THEN 1 ELSE 0 END) AS terisi
     FROM bangsal b LEFT JOIN kamar k ON k.kd_bangsal = b.kd_bangsal
     GROUP BY b.kd_bangsal, b.nm_bangsal, b.status ORDER BY b.kd_bangsal"
);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
      <h3 class="card-title mb-0">Daftar Bangsal (<?= count($rows) ?>)</h3>
      <a href="<?= e(url('bangsal', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Bangsal Baru</a>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode</th><th>Nama Bangsal</th><th class="text-end">Jumlah Kamar</th><th class="text-end">Terisi</th><th>Status</th><th style="width:180px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kd_bangsal']) ?></span></td>
            <td><?= e($r['nm_bangsal']) ?></td>
            <td class="text-end"><?= (int)$r['jml_kamar'] ?></td>
            <td class="text-end"><?= (int)$r['terisi'] ?></td>
            <td><?= $r['status'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-link link-primary" href="<?= e(url('kamar', ['bangsal' => $r['kd_bangsal']])) ?>"><i class="bi bi-door-open me-1"></i>Kamar</a>
              <a class="btn btn-sm btn-warning" href="<?= e(url('bangsal', ['action' => 'form', 'kd' => $r['kd_bangsal']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('bangsal')) ?>" class="d-inline" onsubmit="return confirm('Hapus bangsal <?= e($r['kd_bangsal']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kd_bangsal" value="<?= e($r['kd_bangsal']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada bangsal.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
