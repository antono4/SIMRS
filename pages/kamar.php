<?php
// Modul Master Kamar — tabel kamar skema sik
// kamar_inap.kd_kamar -> kamar ON DELETE CASCADE, jadi menghapus kamar juga
// menghapus seluruh riwayat rawat inap di kamar itu. Penghapusan dicegah bila
// riwayat masih ada; kamar yang tidak lagi dipakai dinonaktifkan (statusdata=0).
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

const KAMAR_STATUS = ['KOSONG', 'ISI', 'DIBERSIHKAN', 'DIBOOKING', 'PERBAIKAN'];
const KAMAR_KELAS = ['Kelas 1', 'Kelas 2', 'Kelas 3', 'Kelas Utama', 'Kelas VIP', 'Kelas VVIP'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $kd = $_POST['kd_kamar'] ?? '';
        $inap = (int)db_val('SELECT COUNT(*) FROM kamar_inap WHERE kd_kamar = ?', [$kd]);
        if ($inap > 0) {
            flash_set('danger', "Kamar $kd tidak dapat dihapus karena memiliki $inap riwayat rawat inap. Nonaktifkan kamar bila sudah tidak dipakai.");
        } else {
            $galat = db_coba(fn() => db_exec('DELETE FROM kamar WHERE kd_kamar = ?', [$kd]));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Kamar $kd dihapus.");
        }
        redirect(url('kamar'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = trim($_POST['kd_kamar'] ?? '');
        $kdBangsal = $_POST['kd_bangsal'] ?? '';
        if ($kd === '' || $kdBangsal === '') {
            flash_set('danger', 'Kode kamar dan bangsal wajib diisi.');
            redirect(url('kamar', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if (strlen($kd) > 15) {
            flash_set('danger', 'Kode kamar maksimal 15 karakter.');
            redirect(url('kamar', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        $status = in_array($_POST['status'] ?? '', KAMAR_STATUS, true) ? $_POST['status'] : 'KOSONG';
        $kelas = in_array($_POST['kelas'] ?? '', KAMAR_KELAS, true) ? $_POST['kelas'] : 'Kelas 3';
        $trf = (float)($_POST['trf_kamar'] ?? 0);
        $statusdata = ($_POST['statusdata'] ?? '1') === '1' ? '1' : '0';

        if ($isEdit) {
            // Kamar yang sedang terisi tidak boleh ditandai kosong lewat master data;
            // status ISI hanya boleh berubah lewat modul Kamar Inap.
            $aktif = (int)db_val("SELECT COUNT(*) FROM kamar_inap WHERE kd_kamar = ? AND tgl_keluar = '0000-00-00'", [$kd]);
            if ($aktif > 0 && $status !== 'ISI') {
                flash_set('danger', "Kamar $kd sedang terisi pasien, jadi statusnya tidak dapat diubah dari sini.");
                redirect(url('kamar', ['action' => 'form', 'kd' => $kd]));
            }
            $galat = db_coba(fn() => db_exec(
                'UPDATE kamar SET kd_bangsal=?, trf_kamar=?, status=?, kelas=?, statusdata=? WHERE kd_kamar=?',
                [$kdBangsal, $trf, $status, $kelas, $statusdata, $kd]
            ));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Kamar $kd diperbarui.");
        } else {
            $galat = db_coba(fn() => db_exec(
                'INSERT INTO kamar (kd_kamar, kd_bangsal, trf_kamar, status, kelas, statusdata) VALUES (?,?,?,?,?,?)',
                [$kd, $kdBangsal, $trf, $status, $kelas, $statusdata]
            ));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Kamar $kd ditambahkan.");
        }
        redirect(url('kamar'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Kamar';
    $kd = $_GET['kd'] ?? null;
    $k = $kd ? db_row('SELECT * FROM kamar WHERE kd_kamar = ?', [$kd]) : null;
    if (!$k) {
        $k = [
            'kd_kamar' => '', 'kd_bangsal' => $_GET['bangsal'] ?? '', 'trf_kamar' => 0,
            'status' => 'KOSONG', 'kelas' => 'Kelas 3', 'statusdata' => '1',
        ];
    }
    $bangsal = db_all("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal");
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('kamar')) ?>" class="card card-primary col-lg-6">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Kamar' : 'Kamar Baru' ?></h3></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Kode Kamar (maks 15 karakter)</label>
          <input class="form-control" name="kd_kamar" maxlength="15" value="<?= e($k['kd_kamar']) ?>" <?= $kd ? 'readonly' : '' ?> required />
        </div>
        <div class="mb-3">
          <label class="form-label">Bangsal *</label>
          <select class="form-select" name="kd_bangsal" required>
            <option value="">-- pilih --</option>
            <?php foreach ($bangsal as $b): ?>
              <option value="<?= e($b['kd_bangsal']) ?>" <?= $k['kd_bangsal'] === $b['kd_bangsal'] ? 'selected' : '' ?>><?= e($b['nm_bangsal']) ?> (<?= e($b['kd_bangsal']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Kelas</label>
            <select class="form-select" name="kelas">
              <?php foreach (KAMAR_KELAS as $kls): ?>
                <option value="<?= e($kls) ?>" <?= $k['kelas'] === $kls ? 'selected' : '' ?>><?= e($kls) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tarif Kamar / Hari (Rp)</label>
            <input class="form-control" type="number" step="0.01" name="trf_kamar" value="<?= e((string)$k['trf_kamar']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Status Kamar</label>
            <select class="form-select" name="status">
              <?php foreach (KAMAR_STATUS as $s): ?>
                <option value="<?= e($s) ?>" <?= $k['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(strtolower($s))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status Data</label>
            <select class="form-select" name="statusdata">
              <option value="1" <?= $k['statusdata'] === '1' ? 'selected' : '' ?>>Aktif (ditawarkan saat rawat inap)</option>
              <option value="0" <?= $k['statusdata'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('kamar')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Kamar';
$q = trim($_GET['q'] ?? '');
$bangsalFilter = trim($_GET['bangsal'] ?? '');
$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (k.kd_kamar LIKE ? OR b.nm_bangsal LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($bangsalFilter !== '') {
    $where .= ' AND k.kd_bangsal = ?';
    $params[] = $bangsalFilter;
}
$rows = db_all(
    "SELECT k.*, b.nm_bangsal, (SELECT COUNT(*) FROM kamar_inap ki WHERE ki.kd_kamar = k.kd_kamar) AS jml_inap
     FROM kamar k LEFT JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
     $where ORDER BY b.nm_bangsal, k.kd_kamar",
    $params
);
$bangsalList = db_all('SELECT kd_bangsal, nm_bangsal FROM bangsal ORDER BY nm_bangsal');
$badgeKamar = ['KOSONG' => 'text-bg-success', 'ISI' => 'text-bg-danger', 'DIBERSIHKAN' => 'text-bg-warning', 'DIBOOKING' => 'text-bg-info', 'PERBAIKAN' => 'text-bg-dark'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h3 class="card-title mb-0">Daftar Kamar (<?= count($rows) ?>)</h3>
      <div class="d-flex flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
          <input type="hidden" name="page" value="kamar" />
          <input class="form-control form-control-sm" style="min-width:9rem" name="q" placeholder="Cari kamar / bangsal" value="<?= e($q) ?>" />
          <select class="form-select form-select-sm" name="bangsal" style="width:auto;max-width:11rem">
            <option value="">Semua bangsal</option>
            <?php foreach ($bangsalList as $b): ?>
              <option value="<?= e($b['kd_bangsal']) ?>" <?= $bangsalFilter === $b['kd_bangsal'] ? 'selected' : '' ?>><?= e($b['nm_bangsal']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button>
        </form>
        <a href="<?= e(url('kamar', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Kamar Baru</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode Kamar</th><th>Bangsal</th><th>Kelas</th><th class="text-end">Tarif/Hari</th><th>Status</th><th>Data</th><th class="text-end">Riwayat Inap</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kd_kamar']) ?></span></td>
            <td><?= e($r['nm_bangsal'] ?? '-') ?></td>
            <td><?= e($r['kelas'] ?? '-') ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['trf_kamar'], 0, ',', '.') ?></td>
            <td><span class="badge <?= $badgeKamar[$r['status']] ?? 'text-bg-secondary' ?>"><?= e($r['status']) ?></span></td>
            <td><?= $r['statusdata'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td class="text-end"><?= (int)$r['jml_inap'] ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('kamar', ['action' => 'form', 'kd' => $r['kd_kamar']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('kamar')) ?>" class="d-inline" onsubmit="return confirm('Hapus kamar <?= e($r['kd_kamar']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kd_kamar" value="<?= e($r['kd_kamar']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada kamar.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
