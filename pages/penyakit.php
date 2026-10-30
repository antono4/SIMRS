<?php
// Modul Master Penyakit (ICD-10) & Kategori Penyakit — tabel penyakit,
// kategori_penyakit skema sik.
// penyakit memiliki 40.000+ baris, jadi daftar wajib dicari dan dipaginasi.
// diagnosa_pasien.kd_penyakit -> penyakit ON DELETE RESTRICT.
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';
const PENYAKIT_STATUS = ['Menular', 'Tidak Menular'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $kd = $_POST['kd_penyakit'] ?? '';
        $dipakai = (int)db_val('SELECT COUNT(*) FROM diagnosa_pasien WHERE kd_penyakit = ?', [$kd]);
        if ($dipakai > 0) {
            flash_set('danger', "Penyakit $kd tidak dapat dihapus karena dipakai di $dipakai diagnosa pasien.");
        } else {
            $galat = db_coba(fn() => db_exec('DELETE FROM penyakit WHERE kd_penyakit = ?', [$kd]));
            if ($galat === null) {
                audit_catat('penyakit', 'delete', 'penyakit', $kd);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Penyakit $kd dihapus.");
        }
        redirect(url('penyakit'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = trim($_POST['kd_penyakit'] ?? '');
        $nm = trim($_POST['nm_penyakit'] ?? '');
        $ktg = $_POST['kd_ktg'] ?? '';
        if ($kd === '' || $nm === '' || $ktg === '') {
            flash_set('danger', 'Kode, nama penyakit, dan kategori wajib diisi.');
            redirect(url('penyakit', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if (strlen($kd) > 15) {
            flash_set('danger', 'Kode penyakit maksimal 15 karakter.');
            redirect(url('penyakit', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if (strlen($nm) > 250) {
            flash_set('danger', 'Nama penyakit maksimal 250 karakter.');
            redirect(url('penyakit', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if (strlen(trim($_POST['keterangan'] ?? '')) > 60) {
            flash_set('danger', 'Keterangan maksimal 60 karakter.');
            redirect(url('penyakit', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        $data = [
            'nm_penyakit' => $nm,
            'ciri_ciri' => trim($_POST['ciri_ciri'] ?? ''),
            'keterangan' => trim($_POST['keterangan'] ?? ''),
            'kd_ktg' => $ktg,
            'status' => in_array($_POST['status'] ?? '', PENYAKIT_STATUS, true) ? $_POST['status'] : 'Tidak Menular',
            'validcode' => ($_POST['validcode'] ?? '1') === '1' ? '1' : '0',
            'accpdx' => ($_POST['accpdx'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'asterisk' => ($_POST['asterisk'] ?? '0') === '1' ? '1' : '0',
            'im' => ($_POST['im'] ?? '0') === '1' ? '1' : '0',
        ];
        if ($isEdit) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $params = array_values($data);
            $params[] = $kd;
            $galat = db_coba(fn() => db_exec("UPDATE penyakit SET $set WHERE kd_penyakit = ?", $params));
            if ($galat === null) {
                audit_catat('penyakit', 'save', 'penyakit', $kd);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Penyakit $kd diperbarui.");
        } else {
            $cols = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            $params = array_merge([$kd], array_values($data));
            $galat = db_coba(fn() => db_exec("INSERT INTO penyakit (kd_penyakit, $cols) VALUES (?, $marks)", $params));
            if ($galat === null) {
                audit_catat('penyakit', 'create', 'penyakit', $kd);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Penyakit $kd ditambahkan.");
        }
        redirect(url('penyakit', ['q' => $kd]));
    }

    if ($act === 'delete_ktg') {
        $kd = $_POST['kd_ktg'] ?? '';
        $dipakai = (int)db_val('SELECT COUNT(*) FROM penyakit WHERE kd_ktg = ?', [$kd]);
        if ($dipakai > 0) {
            flash_set('danger', "Kategori $kd tidak dapat dihapus karena dipakai di $dipakai penyakit.");
        } else {
            $galat = db_coba(fn() => db_exec('DELETE FROM kategori_penyakit WHERE kd_ktg = ?', [$kd]));
            if ($galat === null) {
                audit_catat('penyakit', 'delete', 'kategori_penyakit', $kd);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Kategori $kd dihapus.");
        }
        redirect(url('penyakit', ['action' => 'kategori']));
    }

    if ($act === 'save_ktg') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = trim($_POST['kd_ktg'] ?? '');
        $nm = trim($_POST['nm_kategori'] ?? '');
        if ($kd === '' || $nm === '') {
            flash_set('danger', 'Kode dan nama kategori wajib diisi.');
            redirect(url('penyakit', ['action' => 'kategori']));
        }
        if (strlen($kd) > 8 || strlen($nm) > 30) {
            flash_set('danger', 'Kode kategori maksimal 8 karakter dan nama maksimal 30 karakter.');
            redirect(url('penyakit', ['action' => 'kategori']));
        }
        $ciri = trim($_POST['ciri_umum'] ?? '');
        if (strlen($ciri) > 200) {
            flash_set('danger', 'Ciri umum maksimal 200 karakter.');
            redirect(url('penyakit', ['action' => 'kategori']));
        }
        if ($isEdit) {
            $galat = db_coba(fn() => db_exec('UPDATE kategori_penyakit SET nm_kategori=?, ciri_umum=? WHERE kd_ktg=?', [$nm, $ciri, $kd]));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Kategori $kd diperbarui.");
        } else {
            $galat = db_coba(fn() => db_exec('INSERT INTO kategori_penyakit (kd_ktg, nm_kategori, ciri_umum) VALUES (?,?,?)', [$kd, $nm, $ciri]));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Kategori $kd ditambahkan.");
        }
        redirect(url('penyakit', ['action' => 'kategori']));
    }
}

$kategoriList = db_all('SELECT kd_ktg, nm_kategori FROM kategori_penyakit ORDER BY kd_ktg');

if ($action === 'kategori') {
    $pageTitle = 'Kategori Penyakit';
    $rows = db_all(
        'SELECT k.*, (SELECT COUNT(*) FROM penyakit p WHERE p.kd_ktg = k.kd_ktg) AS jml
         FROM kategori_penyakit k ORDER BY k.kd_ktg'
    );
    $edit = $_GET['kd'] ?? null;
    $k = $edit ? db_row('SELECT * FROM kategori_penyakit WHERE kd_ktg = ?', [$edit]) : null;
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="row">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">Kategori Penyakit (<?= count($rows) ?>)</h3>
              <a href="<?= e(url('penyakit')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Daftar Penyakit</a>
            </div>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-striped mb-0">
              <thead><tr><th>Kode</th><th>Nama Kategori</th><th class="text-end">Jumlah Penyakit</th><th style="width:130px">Aksi</th></tr></thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><span class="badge text-bg-secondary"><?= e($r['kd_ktg']) ?></span></td>
                    <td><?= e($r['nm_kategori']) ?></td>
                    <td class="text-end"><?= (int)$r['jml'] ?></td>
                    <td>
                      <a class="btn btn-sm btn-warning" href="<?= e(url('penyakit', ['action' => 'kategori', 'kd' => $r['kd_ktg']])) ?>"><i class="bi bi-pencil"></i></a>
                      <form method="post" action="<?= e(url('penyakit')) ?>" class="d-inline" onsubmit="return confirm('Hapus kategori <?= e($r['kd_ktg']) ?>?')">
                        <input type="hidden" name="act" value="delete_ktg" />
                        <input type="hidden" name="kd_ktg" value="<?= e($r['kd_ktg']) ?>" />
                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada kategori.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <form method="post" action="<?= e(url('penyakit')) ?>" class="card card-primary">
          <input type="hidden" name="act" value="save_ktg" />
          <input type="hidden" name="mode" value="<?= $k ? 'edit' : 'add' ?>" />
          <div class="card-header"><h3 class="card-title"><?= $k ? 'Ubah Kategori' : 'Kategori Baru' ?></h3></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Kode Kategori (maks 8 karakter)</label>
              <input class="form-control" name="kd_ktg" maxlength="8" value="<?= e($k['kd_ktg'] ?? '') ?>" <?= $k ? 'readonly' : '' ?> required />
            </div>
            <div class="mb-3">
              <label class="form-label">Nama Kategori</label>
              <input class="form-control" name="nm_kategori" maxlength="30" value="<?= e($k['nm_kategori'] ?? '') ?>" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Ciri Umum (maks 200 karakter)</label>
              <textarea class="form-control" name="ciri_umum" maxlength="200" rows="3"><?= e($k['ciri_umum'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
            <?php if ($k): ?>
              <a href="<?= e(url('penyakit', ['action' => 'kategori'])) ?>" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

if ($action === 'form') {
    $pageTitle = 'Form Penyakit (ICD-10)';
    $kd = $_GET['kd'] ?? null;
    $p = $kd ? db_row('SELECT * FROM penyakit WHERE kd_penyakit = ?', [$kd]) : null;
    if (!$p) {
        $p = [
            'kd_penyakit' => '', 'nm_penyakit' => '', 'ciri_ciri' => '', 'keterangan' => '',
            'kd_ktg' => $kategoriList[0]['kd_ktg'] ?? '', 'status' => 'Tidak Menular',
            'validcode' => '1', 'accpdx' => 'N', 'asterisk' => '0', 'im' => '0',
        ];
    }
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('penyakit')) ?>" class="card card-primary col-lg-8">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Penyakit' : 'Penyakit Baru' ?></h3></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Kode ICD-10 (maks 15 karakter)</label>
            <input class="form-control" name="kd_penyakit" maxlength="15" value="<?= e($p['kd_penyakit']) ?>" <?= $kd ? 'readonly' : '' ?> required />
          </div>
          <div class="col-md-8">
            <label class="form-label">Nama Penyakit *</label>
            <input class="form-control" name="nm_penyakit" maxlength="250" value="<?= e($p['nm_penyakit']) ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Kategori *</label>
            <select class="form-select" name="kd_ktg" required>
              <option value="">-- pilih --</option>
              <?php foreach ($kategoriList as $k): ?>
                <option value="<?= e($k['kd_ktg']) ?>" <?= $p['kd_ktg'] === $k['kd_ktg'] ? 'selected' : '' ?>><?= e($k['nm_kategori']) ?> (<?= e($k['kd_ktg']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status Penularan</label>
            <select class="form-select" name="status">
              <?php foreach (PENYAKIT_STATUS as $s): ?>
                <option value="<?= e($s) ?>" <?= $p['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Keterangan (maks 60 karakter)</label>
            <input class="form-control" name="keterangan" maxlength="60" value="<?= e($p['keterangan']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Kode Valid</label>
            <select class="form-select" name="validcode">
              <option value="1" <?= $p['validcode'] === '1' ? 'selected' : '' ?>>Ya</option>
              <option value="0" <?= $p['validcode'] === '0' ? 'selected' : '' ?>>Tidak</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Accpdx</label>
            <select class="form-select" name="accpdx">
              <option value="N" <?= $p['accpdx'] === 'N' ? 'selected' : '' ?>>N</option>
              <option value="Y" <?= $p['accpdx'] === 'Y' ? 'selected' : '' ?>>Y</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Asterisk</label>
            <select class="form-select" name="asterisk">
              <option value="0" <?= $p['asterisk'] === '0' ? 'selected' : '' ?>>Tidak</option>
              <option value="1" <?= $p['asterisk'] === '1' ? 'selected' : '' ?>>Ya</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Imunisasi (IM)</label>
            <select class="form-select" name="im">
              <option value="0" <?= $p['im'] === '0' ? 'selected' : '' ?>>Tidak</option>
              <option value="1" <?= $p['im'] === '1' ? 'selected' : '' ?>>Ya</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Ciri-ciri</label>
            <textarea class="form-control" name="ciri_ciri" rows="3"><?= e($p['ciri_ciri']) ?></textarea>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('penyakit')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Penyakit (ICD-10)';
$q = trim($_GET['q'] ?? '');
$ktgFilter = trim($_GET['ktg'] ?? '');
$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (p.kd_penyakit LIKE ? OR p.nm_penyakit LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($ktgFilter !== '') {
    $where .= ' AND p.kd_ktg = ?';
    $params[] = $ktgFilter;
}
$total = (int)db_val("SELECT COUNT(*) FROM penyakit p $where", $params);
$perPage = 25;
$hal = max(1, (int)($_GET['hal'] ?? 1));
$offset = ($hal - 1) * $perPage;

// Ekspor CSV memakai seluruh hasil filter, bukan hanya halaman yang tampil.
// Tabel ini besar (puluhan ribu baris), jadi diambil bertahap.
if (($_GET['ekspor'] ?? '') === 'csv') {
    audit_catat('penyakit', 'cetak', 'penyakit', $q, 'Ekspor CSV ' . $total . ' baris');
    ekspor_csv(
        ekspor_nama('penyakit-icd10'),
        ['Kode ICD-10', 'Nama Penyakit', 'Kategori', 'Penularan', 'Keterangan'],
        ekspor_batch(
            "SELECT p.kd_penyakit, p.nm_penyakit, k.nm_kategori, p.status, p.keterangan
             FROM penyakit p LEFT JOIN kategori_penyakit k ON k.kd_ktg = p.kd_ktg
             $where ORDER BY p.kd_penyakit",
            $params
        )
    );
}

$rows = db_all(
    "SELECT p.kd_penyakit, p.nm_penyakit, p.status, p.keterangan, k.nm_kategori,
            (SELECT COUNT(*) FROM diagnosa_pasien dp WHERE dp.kd_penyakit = p.kd_penyakit) AS dipakai
     FROM penyakit p LEFT JOIN kategori_penyakit k ON k.kd_ktg = p.kd_ktg
     $where ORDER BY p.kd_penyakit LIMIT $perPage OFFSET $offset",
    $params
);
$filterParams = array_filter(['q' => $q, 'ktg' => $ktgFilter], fn($v) => $v !== '');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h3 class="card-title mb-0">Data Penyakit (<?= number_format($total, 0, ',', '.') ?>)</h3>
      <div class="d-flex flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
          <input type="hidden" name="page" value="penyakit" />
          <input class="form-control form-control-sm" style="min-width:9rem" name="q" placeholder="Cari kode ICD / nama" value="<?= e($q) ?>" />
          <select class="form-select form-select-sm" name="ktg" style="width:auto;max-width:11rem">
            <option value="">Semua kategori</option>
            <?php foreach ($kategoriList as $k): ?>
              <option value="<?= e($k['kd_ktg']) ?>" <?= $ktgFilter === $k['kd_ktg'] ? 'selected' : '' ?>><?= e($k['nm_kategori']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= e(url('penyakit', ['action' => 'kategori'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-tags me-1"></i>Kategori</a>
        <a href="<?= e(url('penyakit', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Penyakit Baru</a>
        <?= ekspor_tombol('penyakit', $filterParams) ?>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode ICD-10</th><th>Nama Penyakit</th><th>Kategori</th><th>Penularan</th><th>Keterangan</th><th class="text-end">Didiagnosa</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kd_penyakit']) ?></span></td>
            <td><?= e($r['nm_penyakit']) ?></td>
            <td><?= e($r['nm_kategori'] ?? '-') ?></td>
            <td><?= $r['status'] === 'Menular' ? '<span class="badge text-bg-danger">Menular</span>' : '<span class="badge text-bg-light text-dark">Tidak</span>' ?></td>
            <td><?= e($r['keterangan'] ?? '-') ?></td>
            <td class="text-end"><?= (int)$r['dipakai'] ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('penyakit', ['action' => 'form', 'kd' => $r['kd_penyakit']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('penyakit')) ?>" class="d-inline" onsubmit="return confirm('Hapus penyakit <?= e($r['kd_penyakit']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kd_penyakit" value="<?= e($r['kd_penyakit']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada penyakit yang cocok.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">Menampilkan <?= count($rows) ?> dari <?= number_format($total, 0, ',', '.') ?> baris</small>
      <?= paginate($total, $perPage, $hal, 'penyakit', $filterParams) ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
