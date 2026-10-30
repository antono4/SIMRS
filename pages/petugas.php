<?php
// Modul Master Petugas/Paramedis — tabel petugas skema sik
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $nip = $_POST['nip'] ?? '';
        // petugas.nip dipakai rawat_jl_pr (tindakan petugas).
        $tindakan = (int)db_val('SELECT COUNT(*) FROM rawat_jl_pr WHERE nip = ?', [$nip]);
        if ($tindakan > 0) {
            flash_set('danger', "Petugas $nip tidak dapat dihapus karena masih dipakai di $tindakan tindakan.");
        } else {
            $galat = db_coba(fn() => db_exec('DELETE FROM petugas WHERE nip = ?', [$nip]));
            if ($galat === null) {
                audit_catat('petugas', 'delete', 'petugas', $nip);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Petugas $nip dihapus.");
        }
        redirect(url('petugas'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $nip = trim($_POST['nip'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        if ($nip === '' || $nama === '') {
            flash_set('danger', 'NIP dan nama petugas wajib diisi.');
            redirect(url('petugas', ['action' => 'form'] + ($isEdit ? ['nip' => $nip] : [])));
        }
        if (strlen($nip) > 20) {
            flash_set('danger', 'NIP maksimal 20 karakter.');
            redirect(url('petugas', ['action' => 'form'] + ($isEdit ? ['nip' => $nip] : [])));
        }
        if (strlen($nama) > 50) {
            flash_set('danger', 'Nama petugas maksimal 50 karakter.');
            redirect(url('petugas', ['action' => 'form'] + ($isEdit ? ['nip' => $nip] : [])));
        }
        if (strlen(trim($_POST['alamat'] ?? '')) > 60) {
            flash_set('danger', 'Alamat maksimal 60 karakter.');
            redirect(url('petugas', ['action' => 'form'] + ($isEdit ? ['nip' => $nip] : [])));
        }
        $data = [
            'nama' => $nama,
            'jk' => $_POST['jk'] ?? 'L',
            'tmp_lahir' => trim($_POST['tmp_lahir'] ?? '') ?: '-',
            'tgl_lahir' => ($_POST['tgl_lahir'] ?? '') ?: '0000-00-00',
            'gol_darah' => $_POST['gol_darah'] ?? '-',
            'agama' => trim($_POST['agama'] ?? '') ?: '-',
            'stts_nikah' => $_POST['stts_nikah'] ?? 'BELUM MENIKAH',
            'alamat' => trim($_POST['alamat'] ?? '') ?: '-',
            'kd_jbtn' => $_POST['kd_jbtn'] ?? '',
            'no_telp' => trim($_POST['no_telp'] ?? '') ?: '-',
            'email' => trim($_POST['email'] ?? '') ?: '-',
            'status' => $_POST['status'] ?? '1',
        ];
        if ($data['kd_jbtn'] === '') {
            flash_set('danger', 'Jabatan wajib dipilih.');
            redirect(url('petugas', ['action' => 'form'] + ($isEdit ? ['nip' => $nip] : [])));
        }

        if ($isEdit) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $params = array_values($data);
            $params[] = $nip;
            $galat = db_coba(fn() => db_exec("UPDATE petugas SET $set WHERE nip = ?", $params));
            if ($galat === null) {
                audit_catat('petugas', 'save', 'petugas', $nip);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Petugas $nip diperbarui.");
        } else {
            $cols = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            $params = array_merge([$nip], array_values($data));
            $galat = db_coba(fn() => db_exec("INSERT INTO petugas (nip, $cols) VALUES (?, $marks)", $params));
            if ($galat === null) {
                audit_catat('petugas', 'create', 'petugas', $nip);
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Petugas $nip ditambahkan.");
        }
        redirect(url('petugas'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Petugas';
    $nip = $_GET['nip'] ?? null;
    $petugas = $nip ? db_row('SELECT * FROM petugas WHERE nip = ?', [$nip]) : null;
    if (!$petugas) {
        $petugas = [
            'nip' => '', 'nama' => '', 'jk' => 'L', 'tmp_lahir' => '', 'tgl_lahir' => '',
            'gol_darah' => '-', 'agama' => 'ISLAM', 'stts_nikah' => 'BELUM MENIKAH', 'alamat' => '',
            'kd_jbtn' => '', 'no_telp' => '', 'email' => '', 'status' => '1',
        ];
    }
    $jabatan = db_all('SELECT kd_jbtn, nm_jbtn FROM jabatan ORDER BY nm_jbtn');
    $agamaList = ['ISLAM', 'KRISTEN', 'KATOLIK', 'HINDU', 'BUDHA', 'KONGHUCU', 'LAINNYA'];
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('petugas')) ?>" class="card card-primary col-lg-8">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $nip ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $nip ? 'Ubah Petugas' : 'Petugas Baru' ?></h3></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">NIP (maks 20 karakter)</label>
            <input class="form-control" name="nip" maxlength="20" value="<?= e($petugas['nip']) ?>" <?= $nip ? 'readonly' : '' ?> required />
          </div>
          <div class="col-md-8">
            <label class="form-label">Nama Petugas *</label>
            <input class="form-control" name="nama" maxlength="50" value="<?= e($petugas['nama']) ?>" required />
          </div>
          <div class="col-md-4">
            <label class="form-label">Jabatan *</label>
            <select class="form-select" name="kd_jbtn" required>
              <option value="">-- pilih --</option>
              <?php foreach ($jabatan as $j): ?>
                <option value="<?= e($j['kd_jbtn']) ?>" <?= $petugas['kd_jbtn'] === $j['kd_jbtn'] ? 'selected' : '' ?>><?= e($j['nm_jbtn']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Jenis Kelamin</label>
            <select class="form-select" name="jk">
              <option value="L" <?= $petugas['jk'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= $petugas['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Golongan Darah</label>
            <select class="form-select" name="gol_darah">
              <?php foreach (['-', 'A', 'B', 'O', 'AB'] as $g): ?>
                <option value="<?= $g ?>" <?= $petugas['gol_darah'] === $g ? 'selected' : '' ?>><?= $g === '-' ? 'Belum diketahui' : $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tempat Lahir</label>
            <input class="form-control" name="tmp_lahir" maxlength="20" value="<?= e($petugas['tmp_lahir']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Tanggal Lahir</label>
            <input class="form-control" type="date" name="tgl_lahir" value="<?= e($petugas['tgl_lahir'] === '0000-00-00' ? '' : $petugas['tgl_lahir']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Agama</label>
            <select class="form-select" name="agama">
              <?php foreach ($agamaList as $a): ?>
                <option value="<?= $a ?>" <?= strtoupper((string)$petugas['agama']) === $a ? 'selected' : '' ?>><?= ucfirst(strtolower($a)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status Nikah</label>
            <select class="form-select" name="stts_nikah">
              <?php foreach (['BELUM MENIKAH', 'MENIKAH', 'JANDA', 'DUDHA', 'JOMBLO'] as $s): ?>
                <option value="<?= $s ?>" <?= $petugas['stts_nikah'] === $s ? 'selected' : '' ?>><?= ucwords(strtolower($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Alamat (maks 60 karakter)</label>
            <input class="form-control" name="alamat" maxlength="60" value="<?= e($petugas['alamat']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">No. Telepon (maks 13)</label>
            <input class="form-control" name="no_telp" maxlength="13" value="<?= e($petugas['no_telp']) ?>" />
          </div>
          <div class="col-md-8">
            <label class="form-label">Email (maks 70 karakter)</label>
            <input class="form-control" type="email" name="email" maxlength="70" value="<?= e($petugas['email']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="1" <?= $petugas['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
              <option value="0" <?= $petugas['status'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('petugas')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Petugas';
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE p.nip LIKE ? OR p.nama LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$total = (int)db_val("SELECT COUNT(*) FROM petugas p $where", $params);
$perPage = 25;
$hal = max(1, (int)($_GET['hal'] ?? 1));
$offset = ($hal - 1) * $perPage;

// Ekspor CSV memakai seluruh hasil filter, bukan hanya halaman yang tampil.
if (($_GET['ekspor'] ?? '') === 'csv') {
    audit_catat('petugas', 'cetak', 'petugas', $q, 'Ekspor CSV ' . $total . ' baris');
    ekspor_csv(
        ekspor_nama('petugas'),
        ['NIP', 'Nama Petugas', 'L/P', 'Jabatan', 'Tempat Lahir', 'Tgl. Lahir', 'Telepon', 'Email', 'Status'],
        ekspor_batch(
            "SELECT p.nip, p.nama, p.jk, j.nm_jbtn, p.tmp_lahir, p.tgl_lahir, p.no_telp, p.email, p.status
             FROM petugas p LEFT JOIN jabatan j ON j.kd_jbtn = p.kd_jbtn
             $where ORDER BY p.nama",
            $params
        )
    );
}

$rows = db_all(
    "SELECT p.*, j.nm_jbtn FROM petugas p LEFT JOIN jabatan j ON j.kd_jbtn = p.kd_jbtn
     $where ORDER BY p.nama LIMIT $perPage OFFSET $offset",
    $params
);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h3 class="card-title mb-0">Daftar Petugas (<?= $total ?>)</h3>
      <div class="d-flex flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
          <input type="hidden" name="page" value="petugas" />
          <input class="form-control form-control-sm" name="q" placeholder="Cari NIP / nama" value="<?= e($q) ?>" />
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= e(url('petugas', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Petugas Baru</a>
        <?= ekspor_tombol('petugas', $q !== '' ? ['q' => $q] : []) ?>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>NIP</th><th>Nama</th><th>Jabatan</th><th>JK</th><th>Telepon</th><th>Status</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['nip']) ?></span></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= e($r['nm_jbtn'] ?? '-') ?></td>
            <td><?= e($r['jk'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></td>
            <td><?= e($r['no_telp']) ?></td>
            <td><?= $r['status'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('petugas', ['action' => 'form', 'nip' => $r['nip']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('petugas')) ?>" class="d-inline" onsubmit="return confirm('Hapus petugas <?= e($r['nip']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="nip" value="<?= e($r['nip']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada petugas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-end">
      <?= paginate($total, $perPage, $hal, 'petugas', $q !== '' ? ['q' => $q] : []) ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
