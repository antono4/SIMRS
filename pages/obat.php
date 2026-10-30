<?php
// Modul Master Data Barang / Obat — tabel databarang skema sik
// resep_dokter.kode_brng -> databarang ON DELETE CASCADE, jadi menghapus barang
// juga menghapus barisnya dari seluruh resep — penghapusan dicegah bila barang
// sudah pernah diresepkan. Barang yang tidak dipakai lagi dinonaktifkan (status=0).
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $kd = $_POST['kode_brng'] ?? '';
        $dipakai = (int)db_val('SELECT COUNT(*) FROM resep_dokter WHERE kode_brng = ?', [$kd]);
        if ($dipakai > 0) {
            flash_set('danger', "Barang $kd tidak dapat dihapus karena dipakai di $dipakai baris resep. Nonaktifkan barang bila sudah tidak dijual.");
        } else {
            $galat = db_coba(fn() => db_exec('DELETE FROM databarang WHERE kode_brng = ?', [$kd]));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Barang $kd dihapus.");
        }
        redirect(url('obat'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = strtoupper(trim($_POST['kode_brng'] ?? ''));
        $nama = trim($_POST['nama_brng'] ?? '');
        if ($kd === '' || $nama === '') {
            flash_set('danger', 'Kode dan nama barang wajib diisi.');
            redirect(url('obat', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if (strlen($kd) > 15) {
            flash_set('danger', 'Kode barang maksimal 15 karakter.');
            redirect(url('obat', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if (strlen($nama) > 80) {
            flash_set('danger', 'Nama barang maksimal 80 karakter.');
            redirect(url('obat', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        $data = [
            'nama_brng' => $nama,
            'kode_satbesar' => $_POST['kode_satbesar'] ?? '',
            'kode_sat' => $_POST['kode_sat'] ?? '',
            'letak_barang' => trim($_POST['letak_barang'] ?? '') ?: '-',
            'dasar' => (float)($_POST['dasar'] ?? 0),
            'h_beli' => (float)($_POST['h_beli'] ?? 0),
            'ralan' => (float)($_POST['ralan'] ?? 0),
            'kelas1' => (float)($_POST['kelas1'] ?? 0),
            'kelas2' => (float)($_POST['kelas2'] ?? 0),
            'kelas3' => (float)($_POST['kelas3'] ?? 0),
            'utama' => (float)($_POST['utama'] ?? 0),
            'vip' => (float)($_POST['vip'] ?? 0),
            'vvip' => (float)($_POST['vvip'] ?? 0),
            'beliluar' => (float)($_POST['beliluar'] ?? 0),
            'jualbebas' => (float)($_POST['jualbebas'] ?? 0),
            'karyawan' => (float)($_POST['karyawan'] ?? 0),
            'stokminimal' => (float)($_POST['stokminimal'] ?? 0),
            'kdjns' => $_POST['kdjns'] ?? '',
            'isi' => (float)($_POST['isi'] ?? 0),
            'kapasitas' => (float)($_POST['kapasitas'] ?? 0),
            'expire' => ($_POST['expire'] ?? '') !== '' ? (string)$_POST['expire'] : null,
            'status' => $_POST['status'] ?? '1',
            'kode_industri' => $_POST['kode_industri'] ?? '',
            'kode_kategori' => $_POST['kode_kategori'] ?? '',
            'kode_golongan' => $_POST['kode_golongan'] ?? '',
        ];
        if ($data['kode_satbesar'] === '' || $data['kode_sat'] === '' || $data['kdjns'] === ''
            || $data['kode_industri'] === '' || $data['kode_kategori'] === '' || $data['kode_golongan'] === '') {
            flash_set('danger', 'Satuan, jenis, industri, kategori, dan golongan barang wajib dipilih.');
            redirect(url('obat', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }

        if ($isEdit) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $params = array_values($data);
            $params[] = $kd;
            $galat = db_coba(fn() => db_exec("UPDATE databarang SET $set WHERE kode_brng = ?", $params));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Barang $kd diperbarui.");
        } else {
            $cols = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            $params = array_merge([$kd], array_values($data));
            $galat = db_coba(fn() => db_exec("INSERT INTO databarang (kode_brng, $cols) VALUES (?, $marks)", $params));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Barang $kd ditambahkan.");
        }
        redirect(url('obat'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Data Barang';
    $kd = $_GET['kd'] ?? null;
    $b = $kd ? db_row('SELECT * FROM databarang WHERE kode_brng = ?', [$kd]) : null;
    if (!$b) {
        $b = array_fill_keys([
            'kode_brng', 'nama_brng', 'kode_satbesar', 'kode_sat', 'letak_barang', 'dasar', 'h_beli',
            'ralan', 'kelas1', 'kelas2', 'kelas3', 'utama', 'vip', 'vvip', 'beliluar', 'jualbebas',
            'karyawan', 'stokminimal', 'kdjns', 'isi', 'kapasitas', 'expire', 'status',
            'kode_industri', 'kode_kategori', 'kode_golongan',
        ], '');
        $b['status'] = '1';
        $b['isi'] = '1';
    }
    $satuan = db_all('SELECT kode_sat, satuan FROM kodesatuan ORDER BY satuan');
    $jenis = db_all('SELECT kdjns, nama FROM jenis ORDER BY nama');
    $industri = db_all('SELECT kode_industri, nama_industri FROM industrifarmasi ORDER BY nama_industri');
    $kategori = db_all('SELECT kode, nama FROM kategori_barang ORDER BY nama');
    $golongan = db_all('SELECT kode, nama FROM golongan_barang ORDER BY nama');
    require_once __DIR__ . '/../includes/header.php';
    $sel = static function (string $name, array $list, string $val, string $label, string $vk, string $tk): void { ?>
      <div class="col-md-4">
        <label class="form-label"><?= e($label) ?></label>
        <select class="form-select" name="<?= e($name) ?>" required>
          <option value="">-- pilih --</option>
          <?php foreach ($list as $o): ?>
            <option value="<?= e((string)$o[$vk]) ?>" <?= (string)$val === (string)$o[$vk] ? 'selected' : '' ?>><?= e((string)$o[$tk]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php };
    $num = static function (string $name, string $label, string $val, string $step = '0.01'): void { ?>
      <div class="col-md-3">
        <label class="form-label"><?= e($label) ?></label>
        <input class="form-control" type="number" step="<?= e($step) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>" />
      </div>
    <?php };
    ?>
    <form method="post" action="<?= e(url('obat')) ?>" class="card card-primary">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Data Barang' : 'Data Barang Baru' ?></h3></div>
      <div class="card-body">
        <h6 class="text-body-secondary text-uppercase small mb-2">Identitas Barang</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Kode Barang (maks 15 karakter)</label>
            <input class="form-control" name="kode_brng" maxlength="15" value="<?= e($b['kode_brng']) ?>" <?= $kd ? 'readonly' : '' ?> required />
          </div>
          <div class="col-md-8">
            <label class="form-label">Nama Barang *</label>
            <input class="form-control" name="nama_brng" maxlength="80" value="<?= e($b['nama_brng']) ?>" required />
          </div>
          <?php
          $sel('kode_satbesar', $satuan, (string)$b['kode_satbesar'], 'Satuan Besar', 'kode_sat', 'satuan');
          $sel('kode_sat', $satuan, (string)$b['kode_sat'], 'Satuan Kecil', 'kode_sat', 'satuan');
          $sel('kdjns', $jenis, (string)$b['kdjns'], 'Jenis', 'kdjns', 'nama');
          $sel('kode_kategori', $kategori, (string)$b['kode_kategori'], 'Kategori', 'kode', 'nama');
          $sel('kode_golongan', $golongan, (string)$b['kode_golongan'], 'Golongan', 'kode', 'nama');
          $sel('kode_industri', $industri, (string)$b['kode_industri'], 'Industri Farmasi', 'kode_industri', 'nama_industri');
          ?>
          <div class="col-md-4">
            <label class="form-label">Letak Barang</label>
            <input class="form-control" name="letak_barang" maxlength="100" value="<?= e($b['letak_barang']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Isi per Satuan Besar</label>
            <input class="form-control" type="number" step="0.01" name="isi" value="<?= e((string)$b['isi']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Kapasitas</label>
            <input class="form-control" type="number" step="0.01" name="kapasitas" value="<?= e((string)$b['kapasitas']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Kadaluarsa</label>
            <input class="form-control" type="date" name="expire" value="<?= e((string)($b['expire'] ?? '')) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="1" <?= (string)$b['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
              <option value="0" <?= (string)$b['status'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
        </div>

        <h6 class="text-body-secondary text-uppercase small mb-2 mt-4">Harga &amp; Tarif (Rp)</h6>
        <div class="row g-3">
          <?php
          $num('dasar', 'Harga Dasar', (string)$b['dasar']);
          $num('h_beli', 'Harga Beli', (string)$b['h_beli']);
          $num('ralan', 'Rawat Jalan', (string)$b['ralan']);
          $num('kelas1', 'Kelas 1', (string)$b['kelas1']);
          $num('kelas2', 'Kelas 2', (string)$b['kelas2']);
          $num('kelas3', 'Kelas 3', (string)$b['kelas3']);
          $num('utama', 'Utama', (string)$b['utama']);
          $num('vip', 'VIP', (string)$b['vip']);
          $num('vvip', 'VVIP', (string)$b['vvip']);
          $num('beliluar', 'Beli Luar', (string)$b['beliluar']);
          $num('jualbebas', 'Jual Bebas', (string)$b['jualbebas']);
          $num('karyawan', 'Karyawan', (string)$b['karyawan']);
          $num('stokminimal', 'Stok Minimal', (string)$b['stokminimal'], '1');
          ?>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('obat')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Barang / Obat';
$q = trim($_GET['q'] ?? '');
$jenisFilter = trim($_GET['jenis'] ?? '');
$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (d.kode_brng LIKE ? OR d.nama_brng LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($jenisFilter !== '') {
    $where .= ' AND d.kdjns = ?';
    $params[] = $jenisFilter;
}
$total = (int)db_val("SELECT COUNT(*) FROM databarang d $where", $params);
$perPage = 25;
$hal = max(1, (int)($_GET['hal'] ?? 1));
$offset = ($hal - 1) * $perPage;
$rows = db_all(
    "SELECT d.kode_brng, d.nama_brng, d.h_beli, d.ralan, d.status, d.stokminimal,
            j.nama AS jenis, s.satuan, g.nama AS golongan, k.nama AS kategori,
            (SELECT COUNT(*) FROM resep_dokter rd WHERE rd.kode_brng = d.kode_brng) AS dipakai
     FROM databarang d
     LEFT JOIN jenis j ON j.kdjns = d.kdjns
     LEFT JOIN kodesatuan s ON s.kode_sat = d.kode_sat
     LEFT JOIN golongan_barang g ON g.kode = d.kode_golongan
     LEFT JOIN kategori_barang k ON k.kode = d.kode_kategori
     $where ORDER BY d.nama_brng LIMIT $perPage OFFSET $offset",
    $params
);
$jenisList = db_all('SELECT kdjns, nama FROM jenis ORDER BY nama');
$filterParams = array_filter(['q' => $q, 'jenis' => $jenisFilter], fn($v) => $v !== '');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h3 class="card-title mb-0">Data Barang (<?= $total ?>)</h3>
      <div class="d-flex flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
          <input type="hidden" name="page" value="obat" />
          <input class="form-control form-control-sm" style="min-width:9rem" name="q" placeholder="Cari kode / nama" value="<?= e($q) ?>" />
          <select class="form-select form-select-sm" name="jenis" style="width:auto;max-width:11rem">
            <option value="">Semua jenis</option>
            <?php foreach ($jenisList as $j): ?>
              <option value="<?= e($j['kdjns']) ?>" <?= $jenisFilter === $j['kdjns'] ? 'selected' : '' ?>><?= e($j['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= e(url('obat', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Barang Baru</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode</th><th>Nama Barang</th><th>Jenis</th><th>Satuan</th><th>Golongan</th><th class="text-end">Harga Beli</th><th class="text-end">Ralan</th><th class="text-end">Diresepkan</th><th>Status</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kode_brng']) ?></span></td>
            <td><?= e($r['nama_brng']) ?></td>
            <td><?= e($r['jenis'] ?? '-') ?></td>
            <td><?= e($r['satuan'] ?? '-') ?></td>
            <td><?= e($r['golongan'] ?? '-') ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['h_beli'], 0, ',', '.') ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['ralan'], 0, ',', '.') ?></td>
            <td class="text-end"><?= (int)$r['dipakai'] ?></td>
            <td><?= $r['status'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('obat', ['action' => 'form', 'kd' => $r['kode_brng']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('obat')) ?>" class="d-inline" onsubmit="return confirm('Hapus barang <?= e($r['kode_brng']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kode_brng" value="<?= e($r['kode_brng']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Tidak ada barang.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-end">
      <?= paginate($total, $perPage, $hal, 'obat', $filterParams) ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
