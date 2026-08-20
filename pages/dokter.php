<?php
// Modul Master Dokter — tabel dokter skema sik
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';
$perPage = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $kd = $_POST['kd_dokter'] ?? '';
        $dipakai = (int)db_val('SELECT COUNT(*) FROM reg_periksa WHERE kd_dokter = ?', [$kd]);
        if ($dipakai > 0) {
            flash_set('danger', "Dokter $kd tidak dapat dihapus karena dipakai di $dipakai registrasi.");
        } else {
            db_exec('DELETE FROM dokter WHERE kd_dokter = ?', [$kd]);
            flash_set('success', "Dokter $kd berhasil dihapus.");
        }
        redirect(url('dokter'));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $kd = trim($_POST['kd_dokter'] ?? '');
        $data = [
            'nm_dokter' => trim($_POST['nm_dokter'] ?? ''),
            'jk' => $_POST['jk'] ?? 'L',
            'tmp_lahir' => trim($_POST['tmp_lahir'] ?? ''),
            'tgl_lahir' => $_POST['tgl_lahir'] ?: null,
            'gol_drh' => $_POST['gol_drh'] ?? '-',
            'agama' => strtoupper(trim($_POST['agama'] ?? '-')),
            'almt_tgl' => trim($_POST['almt_tgl'] ?? ''),
            'no_telp' => trim($_POST['no_telp'] ?? ''),
            'email' => trim($_POST['email'] ?? '-'),
            'stts_nikah' => $_POST['stts_nikah'] ?? 'BELUM MENIKAH',
            'kd_sps' => $_POST['kd_sps'] ?? '-',
            'alumni' => trim($_POST['alumni'] ?? ''),
            'no_ijn_praktek' => trim($_POST['no_ijn_praktek'] ?? ''),
            'status' => $_POST['status'] ?? '1',
        ];
        if ($data['nm_dokter'] === '') {
            flash_set('danger', 'Nama dokter wajib diisi.');
            redirect(url('dokter', ['action' => 'form'] + ($isEdit ? ['kd' => $kd] : [])));
        }
        if ($isEdit) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            $data['kd_dokter'] = $kd;
            db_exec("UPDATE dokter SET $set WHERE kd_dokter = :kd_dokter", $data);
            flash_set('success', "Data dokter $kd diperbarui.");
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                // FK dokter_ibfk_3: kd_dokter wajib terdaftar sebagai pegawai.nik — buat otomatis bila belum ada
                $adaPegawai = (int)db_val('SELECT COUNT(*) FROM pegawai WHERE nik = ?', [$kd]);
                if ($adaPegawai === 0) {
                    db_exec(
                        'INSERT INTO pegawai
                         (nik, nama, jk, jbtn, jnj_jabatan, kode_kelompok, kode_resiko, kode_emergency, departemen, bidang,
                          stts_wp, stts_kerja, npwp, pendidikan, gapok, tmp_lahir, tgl_lahir, alamat, kota, mulai_kerja,
                          ms_kerja, indexins, bpd, rekening, stts_aktif, wajibmasuk, pengurang, indek, mulai_kontrak,
                          cuti_diambil, dankes, photo, no_ktp)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        [
                            $kd, $data['nm_dokter'], $data['jk'] === 'P' ? 'Wanita' : 'Pria',
                            $data['kd_sps'] !== '-' && $data['kd_sps'] !== '' ? 'DOKTER SPESIALIS' : 'DOKTER',
                            // Nilai FK harus ada di tabel referensi
                            'DIRU', 'KP', 'IV', 'III', 'DOK', '-',
                            '-', '-', '-', '-', 0,
                            $data['tmp_lahir'] !== '' ? $data['tmp_lahir'] : '-',
                            $data['tgl_lahir'] ?: date('Y-m-d'),
                            $data['almt_tgl'] !== '' ? $data['almt_tgl'] : '-', '-', date('Y-m-d'),
                            '<1', '-', 'T', '-', 'AKTIF', 0, 0, 0, '1900-01-01',
                            0, 0, '-', '-',
                        ]
                    );
                }
                $data['kd_dokter'] = $kd;
                $cols = implode(', ', array_keys($data));
                $marks = ':' . implode(', :', array_keys($data));
                db_exec("INSERT INTO dokter ($cols) VALUES ($marks)", $data);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            flash_set('success', "Dokter baru ($kd) ditambahkan" . ($adaPegawai === 0 ? ' sekaligus sebagai pegawai.' : '.'));
        }
        redirect(url('dokter'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Dokter';
    $kd = $_GET['kd'] ?? null;
    $dokter = $kd ? db_row('SELECT * FROM dokter WHERE kd_dokter = ?', [$kd]) : null;
    if (!$dokter) {
        $last = db_val("SELECT kd_dokter FROM dokter WHERE kd_dokter REGEXP '^D[0-9]+$' ORDER BY CAST(SUBSTRING(kd_dokter,2) AS UNSIGNED) DESC LIMIT 1");
        $next = 'D' . str_pad((string)((int)substr((string)$last, 1) + 1), 4, '0', STR_PAD_LEFT);
        $dokter = [
            'kd_dokter' => $next, 'nm_dokter' => '', 'jk' => 'L', 'tmp_lahir' => '', 'tgl_lahir' => '',
            'gol_drh' => '-', 'agama' => 'ISLAM', 'almt_tgl' => '', 'no_telp' => '', 'email' => '',
            'stts_nikah' => 'BELUM MENIKAH', 'kd_sps' => '-', 'alumni' => '', 'no_ijn_praktek' => '', 'status' => '1',
        ];
    }
    $spesialis = db_all('SELECT kd_sps, nm_sps FROM spesialis ORDER BY nm_sps');
    require __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('dokter')) ?>" class="card card-primary">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $kd ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $kd ? 'Ubah Dokter' : 'Dokter Baru' ?></h3></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Kode Dokter</label>
            <input class="form-control" name="kd_dokter" value="<?= e($dokter['kd_dokter']) ?>" <?= $kd ? 'readonly' : '' ?> required />
            <?php if (!$kd): ?>
              <div class="form-text">Otomatis didaftarkan juga sebagai pegawai (NIK) bila belum ada.</div>
            <?php endif; ?>
          </div>
          <div class="col-md-5">
            <label class="form-label">Nama Dokter *</label>
            <input class="form-control" name="nm_dokter" value="<?= e($dokter['nm_dokter']) ?>" required />
          </div>
          <div class="col-md-4">
            <label class="form-label">Spesialis</label>
            <select class="form-select" name="kd_sps">
              <?php foreach ($spesialis as $s): ?>
                <option value="<?= e($s['kd_sps']) ?>" <?= $dokter['kd_sps'] === $s['kd_sps'] ? 'selected' : '' ?>><?= e($s['nm_sps']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Jenis Kelamin</label>
            <select class="form-select" name="jk">
              <option value="L" <?= $dokter['jk'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= $dokter['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tempat Lahir</label>
            <input class="form-control" name="tmp_lahir" value="<?= e($dokter['tmp_lahir']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Tanggal Lahir</label>
            <input class="form-control" type="date" name="tgl_lahir" value="<?= e($dokter['tgl_lahir']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Gol. Darah</label>
            <select class="form-select" name="gol_drh">
              <?php foreach (['-', 'A', 'B', 'O', 'AB'] as $g): ?>
                <option <?= $dokter['gol_drh'] === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Agama</label>
            <input class="form-control" name="agama" value="<?= e($dokter['agama']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Status Nikah</label>
            <select class="form-select" name="stts_nikah">
              <?php foreach (['BELUM MENIKAH', 'MENIKAH', 'JANDA', 'DUDHA', 'JOMBLO'] as $s): ?>
                <option <?= $dokter['stts_nikah'] === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">No. Telp</label>
            <input class="form-control" name="no_telp" value="<?= e($dokter['no_telp']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" value="<?= e($dokter['email']) ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Alamat</label>
            <input class="form-control" name="almt_tgl" value="<?= e($dokter['almt_tgl']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Alumni</label>
            <input class="form-control" name="alumni" value="<?= e($dokter['alumni']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">No. Ijin Praktek</label>
            <input class="form-control" name="no_ijn_praktek" value="<?= e($dokter['no_ijn_praktek']) ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="1" <?= $dokter['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
              <option value="0" <?= $dokter['status'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('dokter')) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Data Dokter';
$q = trim($_GET['q'] ?? '');
$hal = max(1, (int)($_GET['hal'] ?? 1));
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE d.nm_dokter LIKE ? OR d.kd_dokter LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$total = (int)db_val("SELECT COUNT(*) FROM dokter d $where", $params);
$offset = ($hal - 1) * $perPage;
$rows = db_all(
    "SELECT d.*, s.nm_sps FROM dokter d LEFT JOIN spesialis s ON s.kd_sps = d.kd_sps
     $where ORDER BY d.kd_dokter LIMIT $perPage OFFSET $offset",
    $params
);
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h3 class="card-title mb-0">Daftar Dokter (<?= number_format($total) ?>)</h3>
      <div class="d-flex gap-2">
        <form class="d-flex" method="get" action="index.php">
          <input type="hidden" name="page" value="dokter" />
          <input class="form-control form-control-sm me-2" name="q" placeholder="Cari nama / kode" value="<?= e($q) ?>" />
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= e(url('dokter', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Dokter Baru</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Kode</th><th>Nama Dokter</th><th>Spesialis</th><th>L/P</th><th>No. Telp</th><th>Status</th><th style="width:130px">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['kd_dokter']) ?></span></td>
            <td><?= e($r['nm_dokter']) ?></td>
            <td><?= e($r['nm_sps'] ?? '-') ?></td>
            <td><?= e($r['jk']) ?></td>
            <td><?= e($r['no_telp']) ?></td>
            <td><?= $r['status'] === '1' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('dokter', ['action' => 'form', 'kd' => $r['kd_dokter']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('dokter')) ?>" class="d-inline" onsubmit="return confirm('Hapus dokter <?= e($r['kd_dokter']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="kd_dokter" value="<?= e($r['kd_dokter']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-end"><?= paginate($total, $perPage, $hal, 'dokter', ['q' => $q]) ?></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
