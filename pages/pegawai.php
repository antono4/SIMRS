<?php
// Modul Master Pegawai — tabel pegawai skema sik
// Pegawai adalah induk dari dokter (dokter.kd_dokter -> pegawai.nik) dan
// petugas (petugas.nip -> pegawai.nik), keduanya ON DELETE RESTRICT.
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $nik = $_POST['nik'] ?? '';
        $dokter = (int)db_val('SELECT COUNT(*) FROM dokter WHERE kd_dokter = ?', [$nik]);
        $petugas = (int)db_val('SELECT COUNT(*) FROM petugas WHERE nip = ?', [$nik]);
        if ($dokter > 0 || $petugas > 0) {
            $sebab = [];
            if ($dokter > 0) {
                $sebab[] = 'data dokter';
            }
            if ($petugas > 0) {
                $sebab[] = 'data petugas';
            }
            flash_set('danger', "Pegawai $nik tidak dapat dihapus karena masih dipakai di " . implode(' dan ', $sebab) . '.');
        } else {
            $galat = db_coba(fn() => db_exec('DELETE FROM pegawai WHERE nik = ?', [$nik]));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Pegawai $nik dihapus.");
        }
        redirect(url('pegawai'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $nik = trim($_POST['nik'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        if ($nik === '' || $nama === '') {
            flash_set('danger', 'NIK dan nama pegawai wajib diisi.');
            redirect(url('pegawai', ['action' => 'form'] + ($isEdit ? ['nik' => $nik] : [])));
        }
        if (strlen($nik) > 20 || strlen($nama) > 50) {
            flash_set('danger', 'NIK maksimal 20 karakter dan nama maksimal 50 karakter.');
            redirect(url('pegawai', ['action' => 'form'] + ($isEdit ? ['nik' => $nik] : [])));
        }
        $tgl = static fn(string $k, string $d = '0000-00-00'): string => ($_POST[$k] ?? '') !== '' ? (string)$_POST[$k] : $d;
        $tglNull = static fn(string $k): ?string => ($_POST[$k] ?? '') !== '' ? (string)$_POST[$k] : null;
        $data = [
            'nama' => $nama,
            'jk' => $_POST['jk'] ?? 'Pria',
            'jbtn' => trim($_POST['jbtn'] ?? '') ?: '-',
            'jnj_jabatan' => $_POST['jnj_jabatan'] ?? '-',
            'kode_kelompok' => $_POST['kode_kelompok'] ?? '-',
            'kode_resiko' => $_POST['kode_resiko'] ?? '-',
            'kode_emergency' => $_POST['kode_emergency'] ?? '-',
            'departemen' => $_POST['departemen'] ?? '-',
            'bidang' => $_POST['bidang'] ?? '-',
            'stts_wp' => $_POST['stts_wp'] ?? '-',
            'stts_kerja' => $_POST['stts_kerja'] ?? '-',
            'npwp' => trim($_POST['npwp'] ?? '') ?: '-',
            'pendidikan' => $_POST['pendidikan'] ?? '-',
            'gapok' => (float)($_POST['gapok'] ?? 0),
            'tmp_lahir' => trim($_POST['tmp_lahir'] ?? '') ?: '-',
            'tgl_lahir' => $tgl('tgl_lahir'),
            'alamat' => trim($_POST['alamat'] ?? '') ?: '-',
            'kota' => trim($_POST['kota'] ?? '') ?: '-',
            'mulai_kerja' => $tgl('mulai_kerja'),
            'ms_kerja' => $_POST['ms_kerja'] ?? '<1',
            'indexins' => $_POST['indexins'] ?? '-',
            'bpd' => $_POST['bpd'] ?? 'T',
            'rekening' => trim($_POST['rekening'] ?? '') ?: '-',
            'stts_aktif' => $_POST['stts_aktif'] ?? 'AKTIF',
            'wajibmasuk' => (int)($_POST['wajibmasuk'] ?? 0),
            'pengurang' => (float)($_POST['pengurang'] ?? 0),
            'indek' => (int)($_POST['indek'] ?? 0),
            'mulai_kontrak' => $tglNull('mulai_kontrak'),
            'cuti_diambil' => (int)($_POST['cuti_diambil'] ?? 0),
            'dankes' => (float)($_POST['dankes'] ?? 0),
            'no_ktp' => trim($_POST['no_ktp'] ?? '') ?: '-',
        ];

        if ($isEdit) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $params = array_values($data);
            $params[] = $nik;
            $galat = db_coba(fn() => db_exec("UPDATE pegawai SET $set WHERE nik = ?", $params));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Pegawai $nik diperbarui.");
        } else {
            $cols = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            $params = array_merge([$nik], array_values($data));
            $galat = db_coba(fn() => db_exec("INSERT INTO pegawai (nik, $cols) VALUES (?, $marks)", $params));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "Pegawai $nik ditambahkan.");
        }
        redirect(url('pegawai'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Pegawai';
    $nik = $_GET['nik'] ?? null;
    $p = $nik ? db_row('SELECT * FROM pegawai WHERE nik = ?', [$nik]) : null;
    if (!$p) {
        $p = [
            'nik' => '', 'nama' => '', 'jk' => 'Pria', 'jbtn' => '', 'jnj_jabatan' => '-',
            'kode_kelompok' => '-', 'kode_resiko' => '-', 'kode_emergency' => '-', 'departemen' => '-',
            'bidang' => '-', 'stts_wp' => '-', 'stts_kerja' => '-', 'npwp' => '', 'pendidikan' => '-',
            'gapok' => 0, 'tmp_lahir' => '', 'tgl_lahir' => '', 'alamat' => '', 'kota' => '',
            'mulai_kerja' => '', 'ms_kerja' => '<1', 'indexins' => '-', 'bpd' => 'T', 'rekening' => '',
            'stts_aktif' => 'AKTIF', 'wajibmasuk' => 0, 'pengurang' => 0, 'indek' => 0,
            'mulai_kontrak' => '', 'cuti_diambil' => 0, 'dankes' => 0, 'no_ktp' => '',
        ];
    }
    $opsi = [
        'jnj_jabatan' => db_all('SELECT kode AS v, nama AS t FROM jnj_jabatan ORDER BY nama'),
        'kode_kelompok' => db_all('SELECT kode_kelompok AS v, nama_kelompok AS t FROM kelompok_jabatan ORDER BY kode_kelompok'),
        'kode_resiko' => db_all('SELECT kode_resiko AS v, nama_resiko AS t FROM resiko_kerja ORDER BY kode_resiko'),
        'kode_emergency' => db_all('SELECT kode_emergency AS v, nama_emergency AS t FROM emergency_index ORDER BY kode_emergency'),
        'departemen' => db_all('SELECT dep_id AS v, nama AS t FROM departemen ORDER BY dep_id'),
        'bidang' => db_all('SELECT nama AS v, nama AS t FROM bidang ORDER BY nama'),
        'stts_wp' => db_all('SELECT stts AS v, ktg AS t FROM stts_wp ORDER BY stts'),
        'stts_kerja' => db_all('SELECT stts AS v, ktg AS t FROM stts_kerja ORDER BY stts'),
        'pendidikan' => db_all('SELECT tingkat AS v, tingkat AS t FROM pendidikan ORDER BY tingkat'),
        'bpd' => db_all('SELECT namabank AS v, namabank AS t FROM bank ORDER BY namabank'),
        'indexins' => db_all('SELECT dep_id AS v, nama AS t FROM departemen ORDER BY dep_id'),
    ];
    require_once __DIR__ . '/../includes/header.php';
    $sel = static function (string $name, array $list, string $val, string $label): void { ?>
      <div class="col-md-4">
        <label class="form-label"><?= e($label) ?></label>
        <select class="form-select" name="<?= e($name) ?>">
          <?php foreach ($list as $o): ?>
            <option value="<?= e((string)$o['v']) ?>" <?= (string)$val === (string)$o['v'] ? 'selected' : '' ?>><?= e((string)$o['t']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php };
    ?>
    <form method="post" action="<?= e(url('pegawai')) ?>" class="card card-primary">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $nik ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $nik ? 'Ubah Pegawai' : 'Pegawai Baru' ?></h3></div>
      <div class="card-body">
        <h6 class="text-body-secondary text-uppercase small mb-2">Identitas</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">NIK (maks 20 karakter)</label>
            <input class="form-control" name="nik" maxlength="20" value="<?= e($p['nik']) ?>" <?= $nik ? 'readonly' : '' ?> required />
          </div>
          <div class="col-md-8">
            <label class="form-label">Nama Pegawai *</label>
            <input class="form-control" name="nama" maxlength="50" value="<?= e($p['nama']) ?>" required />
          </div>
          <div class="col-md-4">
            <label class="form-label">Jenis Kelamin</label>
            <select class="form-select" name="jk">
              <option value="Pria" <?= $p['jk'] === 'Pria' ? 'selected' : '' ?>>Pria</option>
              <option value="Wanita" <?= $p['jk'] === 'Wanita' ? 'selected' : '' ?>>Wanita</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">No. KTP</label>
            <input class="form-control" name="no_ktp" maxlength="20" value="<?= e($p['no_ktp']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">NPWP</label>
            <input class="form-control" name="npwp" maxlength="15" value="<?= e($p['npwp']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Tempat Lahir</label>
            <input class="form-control" name="tmp_lahir" maxlength="20" value="<?= e($p['tmp_lahir']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Tanggal Lahir</label>
            <input class="form-control" type="date" name="tgl_lahir" value="<?= e($p['tgl_lahir'] === '0000-00-00' ? '' : (string)$p['tgl_lahir']) ?>" />
          </div>
          <div class="col-md-8">
            <label class="form-label">Alamat</label>
            <input class="form-control" name="alamat" maxlength="60" value="<?= e($p['alamat']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Kota</label>
            <input class="form-control" name="kota" maxlength="20" value="<?= e($p['kota']) ?>" />
          </div>
        </div>

        <h6 class="text-body-secondary text-uppercase small mb-2 mt-4">Kepegawaian</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nama Jabatan</label>
            <input class="form-control" name="jbtn" maxlength="25" value="<?= e($p['jbtn']) ?>" placeholder="mis. Perawat, Dokter Umum" />
          </div>
          <?php
          $sel('jnj_jabatan', $opsi['jnj_jabatan'], (string)$p['jnj_jabatan'], 'Jenjang Jabatan');
          $sel('kode_kelompok', $opsi['kode_kelompok'], (string)$p['kode_kelompok'], 'Kelompok Jabatan');
          $sel('departemen', $opsi['departemen'], (string)$p['departemen'], 'Departemen');
          $sel('bidang', $opsi['bidang'], (string)$p['bidang'], 'Bidang');
          $sel('kode_resiko', $opsi['kode_resiko'], (string)$p['kode_resiko'], 'Indeks Resiko Kerja');
          $sel('kode_emergency', $opsi['kode_emergency'], (string)$p['kode_emergency'], 'Indeks Emergency');
          $sel('indexins', $opsi['indexins'], (string)$p['indexins'], 'Index Instalasi');
          $sel('stts_wp', $opsi['stts_wp'], (string)$p['stts_wp'], 'Status Wajib Pajak');
          $sel('stts_kerja', $opsi['stts_kerja'], (string)$p['stts_kerja'], 'Status Kerja');
          $sel('pendidikan', $opsi['pendidikan'], (string)$p['pendidikan'], 'Pendidikan');
          ?>
          <div class="col-md-4">
            <label class="form-label">Mulai Kerja</label>
            <input class="form-control" type="date" name="mulai_kerja" value="<?= e($p['mulai_kerja'] === '0000-00-00' ? '' : (string)$p['mulai_kerja']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Masa Kerja</label>
            <select class="form-select" name="ms_kerja">
              <?php foreach (['<1' => 'Kurang dari 1 tahun', 'PT' => 'Paruh waktu', 'FT>1' => 'Penuh waktu > 1 tahun'] as $v => $t): ?>
                <option value="<?= e($v) ?>" <?= $p['ms_kerja'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Mulai Kontrak</label>
            <input class="form-control" type="date" name="mulai_kontrak" value="<?= e(!$p['mulai_kontrak'] || $p['mulai_kontrak'] === '0000-00-00' ? '' : (string)$p['mulai_kontrak']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Status Kepegawaian</label>
            <select class="form-select" name="stts_aktif">
              <?php foreach (['AKTIF', 'CUTI', 'KELUAR', 'TENAGA LUAR', 'NON AKTIF'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $p['stts_aktif'] === $s ? 'selected' : '' ?>><?= e(ucwords(strtolower($s))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Wajib Masuk (hari/bulan)</label>
            <input class="form-control" type="number" name="wajibmasuk" value="<?= e((string)$p['wajibmasuk']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Cuti Diambil (hari)</label>
            <input class="form-control" type="number" name="cuti_diambil" value="<?= e((string)$p['cuti_diambil']) ?>" />
          </div>
        </div>

        <h6 class="text-body-secondary text-uppercase small mb-2 mt-4">Penggajian</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Gaji Pokok (Rp)</label>
            <input class="form-control" type="number" step="0.01" name="gapok" value="<?= e((string)$p['gapok']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Pengurang (Rp)</label>
            <input class="form-control" type="number" step="0.01" name="pengurang" value="<?= e((string)$p['pengurang']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Dana Kesehatan (Rp)</label>
            <input class="form-control" type="number" step="0.01" name="dankes" value="<?= e((string)$p['dankes']) ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Indeks</label>
            <input class="form-control" type="number" name="indek" value="<?= e((string)$p['indek']) ?>" />
          </div>
          <?php $sel('bpd', $opsi['bpd'], (string)$p['bpd'], 'Bank Penggajian'); ?>
          <div class="col-md-4">
            <label class="form-label">No. Rekening</label>
            <input class="form-control" name="rekening" maxlength="25" value="<?= e($p['rekening']) ?>" />
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('pegawai')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Pegawai';
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE p.nik LIKE ? OR p.nama LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$total = (int)db_val("SELECT COUNT(*) FROM pegawai p $where", $params);
$perPage = 25;
$hal = max(1, (int)($_GET['hal'] ?? 1));
$offset = ($hal - 1) * $perPage;
$rows = db_all(
    "SELECT p.nik, p.nama, p.jk, p.jbtn, p.stts_aktif, j.nama AS jenjang, d.nama AS dept,
            EXISTS(SELECT 1 FROM dokter dk WHERE dk.kd_dokter = p.nik) AS is_dokter
     FROM pegawai p
     LEFT JOIN jnj_jabatan j ON j.kode = p.jnj_jabatan
     LEFT JOIN departemen d ON d.dep_id = p.departemen
     $where ORDER BY p.nama LIMIT $perPage OFFSET $offset",
    $params
);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h3 class="card-title mb-0">Daftar Pegawai (<?= $total ?>)</h3>
      <div class="d-flex flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
          <input type="hidden" name="page" value="pegawai" />
          <input class="form-control form-control-sm" name="q" placeholder="Cari NIK / nama" value="<?= e($q) ?>" />
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= e(url('pegawai', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Pegawai Baru</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th>Status</th><th>Peran</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['nik']) ?></span></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= e($r['jbtn'] ?: '-') ?></td>
            <td><?= e($r['dept'] ?? '-') ?></td>
            <td><?= $r['stts_aktif'] === 'AKTIF' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">' . e($r['stts_aktif']) . '</span>' ?></td>
            <td><?= $r['is_dokter'] ? '<span class="badge text-bg-info">Dokter</span>' : '<span class="badge text-bg-light text-dark">Pegawai</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('pegawai', ['action' => 'form', 'nik' => $r['nik']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('pegawai')) ?>" class="d-inline" onsubmit="return confirm('Hapus pegawai <?= e($r['nik']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="nik" value="<?= e($r['nik']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pegawai.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-end">
      <?= paginate($total, $perPage, $hal, 'pegawai', $q !== '' ? ['q' => $q] : []) ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
