<?php
// Modul Registrasi Kunjungan — tabel reg_periksa (logika mengikuti DlgReg SIMRS Khanza)
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'save') {
        $rm = trim($_POST['no_rkm_medis'] ?? '');
        $kdDokter = $_POST['kd_dokter'] ?? '';
        $kdPoli = $_POST['kd_poli'] ?? '';
        $kdPj = $_POST['kd_pj'] ?? '';
        $tgl = $_POST['tgl_registrasi'] ?: date('Y-m-d');
        $lanjut = $_POST['status_lanjut'] ?? 'Ralan';

        $pasien = db_row('SELECT * FROM pasien WHERE no_rkm_medis = ?', [$rm]);
        $poli = db_row('SELECT * FROM poliklinik WHERE kd_poli = ?', [$kdPoli]);
        if (!$pasien || !$poli || !$kdDokter || !$kdPj) {
            flash_set('danger', 'Pasien, poliklinik, dokter, dan cara bayar wajib dipilih.');
            redirect(url('registrasi', ['action' => 'form']));
        }

        // No. Rawat: yyyy/mm/dd/ + nomor urut 6 digit (ala Khanza)
        $prefix = date('Y/m/d', strtotime($tgl)) . '/';
        $last = db_val('SELECT no_rawat FROM reg_periksa WHERE no_rawat LIKE ? ORDER BY no_rawat DESC LIMIT 1', [$prefix . '%']);
        $noRawat = $prefix . str_pad((string)((int)substr((string)$last, -6) + 1), 6, '0', STR_PAD_LEFT);

        // No. urut per dokter per tanggal
        $lastReg = db_val('SELECT no_reg FROM reg_periksa WHERE kd_dokter = ? AND tgl_registrasi = ? ORDER BY CAST(no_reg AS UNSIGNED) DESC LIMIT 1', [$kdDokter, $tgl]);
        $noReg = str_pad((string)((int)$lastReg + 1), 3, '0', STR_PAD_LEFT);

        // Status daftar & status poli: Lama bila sudah pernah berkunjung
        $pernahDaftar = (int)db_val("SELECT COUNT(*) FROM reg_periksa WHERE no_rkm_medis = ? AND stts != 'Batal'", [$rm]) > 0;
        $pernahPoli = (int)db_val("SELECT COUNT(*) FROM reg_periksa WHERE no_rkm_medis = ? AND kd_poli = ? AND stts != 'Batal'", [$rm, $kdPoli]) > 0;
        $sttsDaftar = $pernahDaftar ? 'Lama' : 'Baru';
        $statusPoli = $pernahPoli ? 'Lama' : 'Baru';
        $biaya = $sttsDaftar === 'Baru' ? (float)$poli['registrasi'] : (float)$poli['registrasilama'];

        [$umur, $sttsUmur] = umur_dari($pasien['tgl_lahir']);

        db_exec(
            'INSERT INTO reg_periksa
             (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, p_jawab, almt_pj, hubunganpj,
              biaya_reg, stts, stts_daftar, status_lanjut, kd_pj, umurdaftar, sttsumur, status_bayar, status_poli)
             VALUES (?,?,?,CURTIME(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $noReg, $noRawat, $tgl, $kdDokter, $rm, $kdPoli,
                $pasien['namakeluarga'] ?: '-', $pasien['alamatpj'] ?: '-', $pasien['keluarga'] ?: 'DIRI SENDIRI',
                $biaya, 'Belum', $sttsDaftar, $lanjut, $kdPj, $umur, $sttsUmur, 'Belum Bayar', $statusPoli,
            ]
        );
        flash_set('success', "Registrasi berhasil. No. Rawat: $noRawat (No. Urut $noReg, pasien $sttsDaftar, biaya Rp " . number_format($biaya, 0, ',', '.') . ')');
        redirect(url('registrasi', ['tgl' => $tgl]));
    }

    if ($act === 'status') {
        $noRawat = $_POST['no_rawat'] ?? '';
        $stts = $_POST['stts'] ?? 'Belum';
        $allowed = ['Belum', 'Sudah', 'Batal', 'Berkas Diterima', 'Dirujuk', 'Meninggal', 'Dirawat', 'Pulang Paksa'];
        if (in_array($stts, $allowed, true)) {
            db_exec('UPDATE reg_periksa SET stts = ? WHERE no_rawat = ?', [$stts, $noRawat]);
            flash_set('success', "Status $noRawat menjadi $stts.");
        }
        redirect(url('registrasi', ['tgl' => $_POST['tgl'] ?? date('Y-m-d')]));
    }

    if ($act === 'delete') {
        $noRawat = $_POST['no_rawat'] ?? '';
        db_exec('DELETE FROM reg_periksa WHERE no_rawat = ?', [$noRawat]);
        flash_set('success', "Registrasi $noRawat dihapus.");
        redirect(url('registrasi', ['tgl' => $_POST['tgl'] ?? date('Y-m-d')]));
    }
}

if ($action === 'form') {
    $pageTitle = 'Registrasi Baru';
    $q = trim($_GET['q'] ?? '');
    $rm = $_GET['rm'] ?? '';
    $pasienDipilih = $rm ? db_row('SELECT * FROM pasien WHERE no_rkm_medis = ?', [$rm]) : null;
    $hasilCari = [];
    if ($q !== '') {
        $hasilCari = db_all(
            'SELECT no_rkm_medis, nm_pasien, tgl_lahir, alamat FROM pasien
             WHERE nm_pasien LIKE ? OR no_rkm_medis LIKE ? ORDER BY nm_pasien LIMIT 10',
            ["%$q%", "%$q%"]
        );
    }
    $dokterList = db_all("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter");
    $poliList = db_all("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli");
    $penjabList = db_all("SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab");
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="row">
      <div class="col-lg-5">
        <div class="card card-info">
          <div class="card-header"><h3 class="card-title">1. Cari Pasien</h3></div>
          <div class="card-body">
            <form method="get" action="index.php" class="d-flex mb-3">
              <input type="hidden" name="page" value="registrasi" />
              <input type="hidden" name="action" value="form" />
              <input class="form-control me-2" name="q" placeholder="Nama atau No. RM" value="<?= e($q) ?>" />
              <button class="btn btn-info"><i class="bi bi-search"></i></button>
            </form>
            <?php if ($q !== ''): ?>
              <div class="list-group">
                <?php foreach ($hasilCari as $h): ?>
                  <a class="list-group-item list-group-item-action" href="<?= e(url('registrasi', ['action' => 'form', 'rm' => $h['no_rkm_medis']])) ?>">
                    <strong><?= e($h['nm_pasien']) ?></strong>
                    <span class="badge text-bg-secondary float-end"><?= e($h['no_rkm_medis']) ?></span><br />
                    <small class="text-muted"><?= e(tgl_indo($h['tgl_lahir'])) ?> &middot; <?= e($h['alamat']) ?></small>
                  </a>
                <?php endforeach; ?>
                <?php if (!$hasilCari): ?>
                  <div class="text-muted">Tidak ditemukan. <a href="<?= e(url('pasien', ['action' => 'form'])) ?>">Daftarkan pasien baru</a>.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('registrasi')) ?>" class="card card-primary">
          <input type="hidden" name="act" value="save" />
          <input type="hidden" name="no_rkm_medis" value="<?= e($pasienDipilih['no_rkm_medis'] ?? '') ?>" />
          <div class="card-header"><h3 class="card-title">2. Data Registrasi</h3></div>
          <div class="card-body">
            <?php if ($pasienDipilih): ?>
              <div class="alert alert-success py-2">
                <strong><?= e($pasienDipilih['nm_pasien']) ?></strong> (<?= e($pasienDipilih['no_rkm_medis']) ?>)
                &mdash; <?= e($pasienDipilih['jk']) ?>, lahir <?= e(tgl_indo($pasienDipilih['tgl_lahir'])) ?>
              </div>
            <?php else: ?>
              <div class="alert alert-warning py-2">Silakan cari dan pilih pasien terlebih dahulu.</div>
            <?php endif; ?>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Tanggal Registrasi</label>
                <input class="form-control" type="date" name="tgl_registrasi" value="<?= e(date('Y-m-d')) ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Poliklinik</label>
                <select class="form-select" name="kd_poli">
                  <?php foreach ($poliList as $p): ?>
                    <option value="<?= e($p['kd_poli']) ?>"><?= e($p['nm_poli']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Dokter</label>
                <select class="form-select" name="kd_dokter">
                  <?php foreach ($dokterList as $d): ?>
                    <option value="<?= e($d['kd_dokter']) ?>"><?= e($d['nm_dokter']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Cara Bayar</label>
                <select class="form-select" name="kd_pj">
                  <?php foreach ($penjabList as $p): ?>
                    <option value="<?= e($p['kd_pj']) ?>" <?= ($pasienDipilih['kd_pj'] ?? '') === $p['kd_pj'] ? 'selected' : '' ?>><?= e($p['png_jawab']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Jenis Kunjungan</label>
                <select class="form-select" name="status_lanjut">
                  <option value="Ralan">Rawat Jalan</option>
                  <option value="Ranap">Rawat Inap</option>
                </select>
              </div>
            </div>
            <p class="text-muted small mt-3 mb-0">
              No. Rawat, No. Urut, status pasien baru/lama, dan biaya registrasi dihitung otomatis mengikuti aturan SIMRS Khanza.
            </p>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary" <?= $pasienDipilih ? '' : 'disabled' ?>><i class="bi bi-check-lg me-1"></i>Simpan Registrasi</button>
            <a href="<?= e(url('registrasi')) ?>" class="btn btn-outline-secondary">Kembali</a>
          </div>
        </form>
      </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

// Daftar registrasi per tanggal
$pageTitle = 'Registrasi Kunjungan';
$tgl = $_GET['tgl'] ?? date('Y-m-d');
$poliFilter = $_GET['poli'] ?? '';
$where = 'WHERE rp.tgl_registrasi = ?';
$params = [$tgl];
if ($poliFilter !== '') {
    $where .= ' AND rp.kd_poli = ?';
    $params[] = $poliFilter;
}
$rows = db_all(
    "SELECT rp.*, p.nm_pasien, pl.nm_poli, d.nm_dokter, pj.png_jawab
     FROM reg_periksa rp
     JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
     JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
     JOIN dokter d ON d.kd_dokter = rp.kd_dokter
     LEFT JOIN penjab pj ON pj.kd_pj = rp.kd_pj
     $where ORDER BY rp.jam_reg DESC",
    $params
);
$poliList = db_all('SELECT kd_poli, nm_poli FROM poliklinik ORDER BY nm_poli');
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h3 class="card-title mb-0">Kunjungan <?= e(tgl_indo($tgl)) ?> (<?= count($rows) ?>)</h3>
      <div class="d-flex gap-2 align-items-center">
        <form class="d-flex gap-2" method="get" action="index.php">
          <input type="hidden" name="page" value="registrasi" />
          <input type="date" class="form-control form-control-sm" name="tgl" value="<?= e($tgl) ?>" />
          <select class="form-select form-select-sm" name="poli">
            <option value="">Semua Poli</option>
            <?php foreach ($poliList as $p): ?>
              <option value="<?= e($p['kd_poli']) ?>" <?= $poliFilter === $p['kd_poli'] ? 'selected' : '' ?>><?= e($p['nm_poli']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button>
        </form>
        <a href="<?= e(url('registrasi', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Registrasi Baru</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr><th>No. Rawat</th><th>No. Urut</th><th>Jam</th><th>Pasien</th><th>Poli</th><th>Dokter</th><th>Cara Bayar</th><th class="text-end">Biaya</th><th>Status</th><th style="width:200px">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['no_rawat']) ?></span></td>
            <td><?= e($r['no_reg']) ?></td>
            <td><?= e(substr((string)$r['jam_reg'], 0, 5)) ?></td>
            <td><?= e($r['nm_pasien']) ?> <small class="text-muted">(<?= e($r['no_rkm_medis']) ?>)</small></td>
            <td><?= e($r['nm_poli']) ?></td>
            <td><?= e($r['nm_dokter']) ?></td>
            <td><?= e($r['png_jawab'] ?? '-') ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['biaya_reg'], 0, ',', '.') ?></td>
            <td><?= badge_status($r['stts'] ?? '') ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" title="Tindakan/Diagnosa/Resep" href="<?= e(url('tindakan', ['no_rawat' => $r['no_rawat']])) ?>"><i class="bi bi-bandaid"></i></a>
              <a class="btn btn-sm btn-outline-warning" title="Kasir/Billing" href="<?= e(url('kasir', ['no_rawat' => $r['no_rawat']])) ?>"><i class="bi bi-cash-coin"></i></a>
              <form method="post" action="<?= e(url('registrasi')) ?>" class="d-inline-flex gap-1">
                <input type="hidden" name="act" value="status" />
                <input type="hidden" name="no_rawat" value="<?= e($r['no_rawat']) ?>" />
                <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                <select name="stts" class="form-select form-select-sm" style="width:110px" onchange="this.form.submit()">
                  <?php foreach (['Belum', 'Sudah', 'Batal', 'Berkas Diterima', 'Dirujuk', 'Dirawat'] as $s): ?>
                    <option <?= $r['stts'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <form method="post" action="<?= e(url('registrasi')) ?>" class="d-inline" onsubmit="return confirm('Hapus registrasi <?= e($r['no_rawat']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="no_rawat" value="<?= e($r['no_rawat']) ?>" />
                <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada kunjungan pada tanggal ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
