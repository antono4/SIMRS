<?php
// Modul Master Tarif Perawatan/Tindakan — jns_perawatan & kategori_perawatan
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';
$perPage = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $kd = $_POST['kd_jenis_prw'] ?? '';
        $dipakai = (int)db_val('SELECT (SELECT COUNT(*) FROM rawat_jl_dr WHERE kd_jenis_prw = ?) + (SELECT COUNT(*) FROM rawat_jl_pr WHERE kd_jenis_prw = ?)', [$kd, $kd]);
        if ($dipakai > 0) {
            flash_set('danger', "Tarif $kd tidak dapat dihapus karena dipakai di $dipakai tindakan.");
        } else {
            db_exec('DELETE FROM jns_perawatan WHERE kd_jenis_prw = ?', [$kd]);
            flash_set('success', "Tarif $kd dihapus.");
        }
        redirect(url('perawatan'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = trim($_POST['kd_jenis_prw'] ?? '');
        $data = [
            'nm_perawatan' => trim($_POST['nm_perawatan'] ?? ''),
            'kd_kategori' => $_POST['kd_kategori'] ?? '-',
            'material' => (float)($_POST['material'] ?? 0),
            'bhp' => (float)($_POST['bhp'] ?? 0),
            'tarif_tindakandr' => (float)($_POST['tarif_tindakandr'] ?? 0),
            'tarif_tindakanpr' => (float)($_POST['tarif_tindakanpr'] ?? 0),
            'kso' => (float)($_POST['kso'] ?? 0),
            'menejemen' => (float)($_POST['menejemen'] ?? 0),
            'total_byrdr' => (float)($_POST['total_byrdr'] ?? 0),
            'total_byrpr' => (float)($_POST['total_byrpr'] ?? 0),
            'total_byrdrpr' => (float)($_POST['total_byrdrpr'] ?? 0),
            'kd_pj' => $_POST['kd_pj'] ?? '-',
            'kd_poli' => $_POST['kd_poli'] ?? '-',
            'status' => $_POST['status'] ?? '1',
        ];
        if ($kd === '' || $data['nm_perawatan'] === '') {
            flash_set('danger', 'Kode dan nama perawatan wajib diisi.');
            redirect(url('perawatan', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if ($isEdit) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            $data['kd_jenis_prw'] = $kd;
            db_exec("UPDATE jns_perawatan SET $set WHERE kd_jenis_prw = :kd_jenis_prw", $data);
            flash_set('success', "Tarif $kd diperbarui.");
        } else {
            $data['kd_jenis_prw'] = $kd;
            $cols = implode(', ', array_keys($data));
            $marks = ':' . implode(', :', array_keys($data));
            db_exec("INSERT INTO jns_perawatan ($cols) VALUES ($marks)", $data);
            flash_set('success', "Tarif $kd ditambahkan.");
        }
        redirect(url('perawatan'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Tarif Perawatan';
    $kd = $_GET['kd'] ?? null;
    $tarif = $kd ? db_row('SELECT * FROM jns_perawatan WHERE kd_jenis_prw = ?', [$kd]) : null;
    if (!$tarif) {
        $tarif = array_fill_keys(['kd_jenis_prw', 'nm_perawatan'], '') + [
            'kd_kategori' => '-', 'material' => 0, 'bhp' => 0, 'tarif_tindakandr' => 0, 'tarif_tindakanpr' => 0,
            'kso' => 0, 'menejemen' => 0, 'total_byrdr' => 0, 'total_byrpr' => 0, 'total_byrdrpr' => 0,
            'kd_pj' => '-', 'kd_poli' => '-', 'status' => '1',
        ];
    }
    $kategori = db_all('SELECT kd_kategori, nm_kategori FROM kategori_perawatan ORDER BY nm_kategori');
    $poliList = db_all('SELECT kd_poli, nm_poli FROM poliklinik ORDER BY nm_poli');
    $penjabList = db_all('SELECT kd_pj, png_jawab FROM penjab ORDER BY png_jawab');
    require __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('perawatan')) ?>" class="card card-primary">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Tarif' : 'Tarif Baru' ?></h3></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Kode Perawatan</label>
            <input class="form-control" name="kd_jenis_prw" maxlength="15" value="<?= e($tarif['kd_jenis_prw']) ?>" <?= $kd ? 'readonly' : '' ?> required />
          </div>
          <div class="col-md-5">
            <label class="form-label">Nama Perawatan *</label>
            <input class="form-control" name="nm_perawatan" value="<?= e($tarif['nm_perawatan']) ?>" required />
          </div>
          <div class="col-md-4">
            <label class="form-label">Kategori</label>
            <select class="form-select" name="kd_kategori">
              <?php foreach ($kategori as $k): ?>
                <option value="<?= e($k['kd_kategori']) ?>" <?= $tarif['kd_kategori'] === $k['kd_kategori'] ? 'selected' : '' ?>><?= e($k['nm_kategori']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php
          $tarifFields = [
              'material' => 'Material', 'bhp' => 'BHP', 'tarif_tindakandr' => 'Jasa Dokter', 'tarif_tindakanpr' => 'Jasa Perawat',
              'kso' => 'KSO', 'menejemen' => 'Manajemen', 'total_byrdr' => 'Total Tarif Dokter', 'total_byrpr' => 'Total Tarif Perawat',
              'total_byrdrpr' => 'Total Tarif Dokter+Perawat',
          ];
          foreach ($tarifFields as $name => $label): ?>
            <div class="col-md-4">
              <label class="form-label"><?= $label ?> (Rp)</label>
              <input class="form-control" type="number" step="0.01" name="<?= $name ?>" value="<?= e((string)$tarif[$name]) ?>" />
            </div>
          <?php endforeach; ?>
          <div class="col-md-4">
            <label class="form-label">Poliklinik</label>
            <select class="form-select" name="kd_poli">
              <?php foreach ($poliList as $p): ?>
                <option value="<?= e($p['kd_poli']) ?>" <?= $tarif['kd_poli'] === $p['kd_poli'] ? 'selected' : '' ?>><?= e($p['nm_poli']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cara Bayar</label>
            <select class="form-select" name="kd_pj">
              <?php foreach ($penjabList as $p): ?>
                <option value="<?= e($p['kd_pj']) ?>" <?= $tarif['kd_pj'] === $p['kd_pj'] ? 'selected' : '' ?>><?= e($p['png_jawab']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="1" <?= $tarif['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
              <option value="0" <?= $tarif['status'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('perawatan')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Tarif Perawatan';
$q = trim($_GET['q'] ?? '');
$hal = max(1, (int)($_GET['hal'] ?? 1));
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE j.nm_perawatan LIKE ? OR j.kd_jenis_prw LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$total = (int)db_val("SELECT COUNT(*) FROM jns_perawatan j $where", $params);
$offset = ($hal - 1) * $perPage;
$rows = db_all(
    "SELECT j.*, k.nm_kategori, pl.nm_poli, pj.png_jawab FROM jns_perawatan j
     LEFT JOIN kategori_perawatan k ON k.kd_kategori = j.kd_kategori
     LEFT JOIN poliklinik pl ON pl.kd_poli = j.kd_poli
     LEFT JOIN penjab pj ON pj.kd_pj = j.kd_pj
     $where ORDER BY k.nm_kategori, j.nm_perawatan LIMIT $perPage OFFSET $offset",
    $params
);
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h3 class="card-title mb-0">Daftar Tarif (<?= number_format($total) ?>)</h3>
      <div class="d-flex gap-2">
        <form class="d-flex" method="get" action="index.php">
          <input type="hidden" name="page" value="perawatan" />
          <input class="form-control form-control-sm me-2" name="q" placeholder="Cari nama / kode" value="<?= e($q) ?>" />
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= e(url('perawatan', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Tarif Baru</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode</th><th>Perawatan</th><th>Kategori</th><th>Poli</th><th>Cara Bayar</th><th class="text-end">Tarif Dokter</th><th class="text-end">Tarif Perawat</th><th>Status</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kd_jenis_prw']) ?></span></td>
            <td><?= e($r['nm_perawatan']) ?></td>
            <td><?= e($r['nm_kategori'] ?? '-') ?></td>
            <td><?= e($r['nm_poli'] ?? '-') ?></td>
            <td><?= e($r['png_jawab'] ?? '-') ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['total_byrdr'], 0, ',', '.') ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['total_byrpr'], 0, ',', '.') ?></td>
            <td><?= $r['status'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('perawatan', ['action' => 'form', 'kd' => $r['kd_jenis_prw']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('perawatan')) ?>" class="d-inline" onsubmit="return confirm('Hapus tarif <?= e($r['kd_jenis_prw']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kd_jenis_prw" value="<?= e($r['kd_jenis_prw']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-end"><?= paginate($total, $perPage, $hal, 'perawatan', ['q' => $q]) ?></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
