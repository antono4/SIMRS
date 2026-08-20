<?php
// Modul Kamar Inap — kamar_inap, kamar, bangsal
declare(strict_types=1);

require_once __DIR__ . '/../includes/kunjungan.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'masuk') {
        $noRawat = $_POST['no_rawat'] ?? '';
        $kdKamar = $_POST['kd_kamar'] ?? '';
        $kamar = db_row("SELECT * FROM kamar WHERE kd_kamar = ? AND status = 'KOSONG' AND statusdata='1'", [$kdKamar]);
        $sudah = db_val("SELECT COUNT(*) FROM kamar_inap WHERE no_rawat = ? AND tgl_keluar = '0000-00-00'", [$noRawat]);
        if (!$kamar) {
            flash_set('danger', 'Kamar tidak tersedia.');
        } elseif ($sudah > 0) {
            flash_set('warning', 'Pasien sudah memiliki kamar inap aktif.');
        } else {
            db_exec(
                'INSERT INTO kamar_inap (no_rawat, kd_kamar, trf_kamar, diagnosa_awal, diagnosa_akhir, tgl_masuk, jam_masuk, tgl_keluar, jam_keluar, lama, ttl_biaya, stts_pulang)
                 VALUES (?,?,?,?,"-",?,?, "0000-00-00","00:00:00",0,0,"-")',
                [$noRawat, $kdKamar, (float)$kamar['trf_kamar'], trim($_POST['diagnosa_awal'] ?? '-'),
                 ($_POST['tgl_masuk'] ?? '') ?: date('Y-m-d'), ($_POST['jam_masuk'] ?? '') ?: date('H:i:s')]
            );
            db_exec("UPDATE kamar SET status='ISI' WHERE kd_kamar = ?", [$kdKamar]);
            db_exec("UPDATE reg_periksa SET status_lanjut='Ranap', stts='Dirawat' WHERE no_rawat = ?", [$noRawat]);
            flash_set('success', "Pasien $noRawat masuk kamar $kdKamar.");
        }
        redirect(url('kamarinap'));
    }

    if ($act === 'pulang') {
        $noRawat = $_POST['no_rawat'] ?? '';
        $inap = db_row("SELECT * FROM kamar_inap WHERE no_rawat = ? AND tgl_keluar = '0000-00-00'", [$noRawat]);
        if (!$inap) {
            flash_set('danger', 'Data kamar inap aktif tidak ditemukan.');
            redirect(url('kamarinap'));
        }
        $tglKeluar = ($_POST['tgl_keluar'] ?? '') ?: date('Y-m-d');
        $jamKeluar = ($_POST['jam_keluar'] ?? '') ?: date('H:i:s');
        $lama = max(1, (int)((strtotime($tglKeluar) - strtotime($inap['tgl_masuk'])) / 86400));
        $ttl = $lama * (float)$inap['trf_kamar'];
        db_exec(
            'UPDATE kamar_inap SET tgl_keluar=?, jam_keluar=?, lama=?, ttl_biaya=?, diagnosa_akhir=?, stts_pulang=?
             WHERE no_rawat=? AND tgl_masuk=? AND jam_masuk=?',
            [$tglKeluar, $jamKeluar, $lama, $ttl, trim($_POST['diagnosa_akhir'] ?? '-'), $_POST['stts_pulang'] ?? 'Sembuh',
             $noRawat, $inap['tgl_masuk'], $inap['jam_masuk']]
        );
        db_exec("UPDATE kamar SET status='DIBERSIHKAN' WHERE kd_kamar = ?", [$inap['kd_kamar']]);
        db_exec("UPDATE reg_periksa SET stts='Sudah' WHERE no_rawat = ?", [$noRawat]);
        flash_set('success', "Pasien dipulangkan ($lama hari, biaya kamar Rp " . number_format($ttl, 0, ',', '.') . ').');
        redirect(url('kamarinap'));
    }

    if ($act === 'status_kamar') {
        $allowed = ['ISI', 'KOSONG', 'DIBERSIHKAN', 'DIBOOKING', 'PERBAIKAN'];
        $stts = $_POST['status'] ?? '';
        if (in_array($stts, $allowed, true)) {
            db_exec('UPDATE kamar SET status = ? WHERE kd_kamar = ?', [$stts, $_POST['kd_kamar']]);
            flash_set('success', 'Status kamar diperbarui.');
        }
        redirect(url('kamarinap', ['view' => 'kamar']));
    }
}

$pageTitle = 'Kamar Inap';
$view = $_GET['view'] ?? 'aktif';

$aktif = db_all(
    "SELECT ki.*, p.nm_pasien, k.kelas, b.nm_bangsal, rp.stts AS stts_reg
     FROM kamar_inap ki
     JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
     JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
     JOIN kamar k ON k.kd_kamar = ki.kd_kamar
     LEFT JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
     WHERE ki.tgl_keluar = '0000-00-00'
     ORDER BY ki.tgl_masuk DESC"
);

$kamarList = db_all(
    'SELECT k.*, b.nm_bangsal FROM kamar k LEFT JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
     WHERE k.statusdata = "1" ORDER BY b.nm_bangsal, k.kd_kamar'
);

$kamarKosong = array_filter($kamarList, fn($k) => $k['status'] === 'KOSONG');
$kunjunganRanap = db_all(
    "SELECT rp.no_rawat, p.nm_pasien, rp.tgl_registrasi FROM reg_periksa rp
     JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
     WHERE rp.stts != 'Batal'
       AND rp.no_rawat NOT IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar = '0000-00-00')
     ORDER BY rp.tgl_registrasi DESC LIMIT 50"
);

require_once __DIR__ . '/../includes/header.php';
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $view === 'aktif' ? 'active' : '' ?>" href="<?= e(url('kamarinap')) ?>">Pasien Dirawat (<?= count($aktif) ?>)</a></li>
  <li class="nav-item"><a class="nav-link <?= $view === 'kamar' ? 'active' : '' ?>" href="<?= e(url('kamarinap', ['view' => 'kamar'])) ?>">Status Kamar (<?= count($kamarList) ?>)</a></li>
  <li class="nav-item"><a class="nav-link <?= $view === 'masuk' ? 'active' : '' ?>" href="<?= e(url('kamarinap', ['view' => 'masuk'])) ?>"><i class="bi bi-plus-lg me-1"></i>Masuk Kamar</a></li>
</ul>

<?php if ($view === 'aktif'): ?>
  <div class="card">
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead><tr><th>No. Rawat</th><th>Pasien</th><th>Kamar</th><th>Bangsal</th><th>Kelas</th><th>Masuk</th><th class="text-end">Tarif/Hari</th><th style="width:280px">Pulangkan</th></tr></thead>
        <tbody>
          <?php foreach ($aktif as $a): ?>
            <tr>
              <td><span class="badge text-bg-secondary"><?= e($a['no_rawat']) ?></span></td>
              <td><?= e($a['nm_pasien']) ?></td>
              <td><strong><?= e($a['kd_kamar']) ?></strong></td>
              <td><?= e($a['nm_bangsal'] ?? '-') ?></td>
              <td><?= e($a['kelas'] ?? '-') ?></td>
              <td><?= e(tgl_indo($a['tgl_masuk'])) ?> <?= e(substr((string)$a['jam_masuk'], 0, 5)) ?></td>
              <td class="text-end">Rp <?= number_format((float)$a['trf_kamar'], 0, ',', '.') ?></td>
              <td>
                <form method="post" action="<?= e(url('kamarinap')) ?>" class="d-flex gap-1" onsubmit="return confirm('Pulangkan pasien ini?')">
                  <input type="hidden" name="act" value="pulang" />
                  <input type="hidden" name="no_rawat" value="<?= e($a['no_rawat']) ?>" />
                  <input type="date" name="tgl_keluar" class="form-control form-control-sm" style="width:130px" value="<?= e(date('Y-m-d')) ?>" />
                  <select name="stts_pulang" class="form-select form-select-sm" style="width:110px">
                    <?php foreach (['Sembuh', 'Membaik', 'Atas Persetujuan Dokter', 'Atas Permintaan Sendiri', 'Rujuk', 'Meninggal', 'Lain-lain'] as $s): ?>
                      <option><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-warning" title="Pulangkan"><i class="bi bi-box-arrow-right"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$aktif): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pasien yang sedang dirawat.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php elseif ($view === 'kamar'): ?>
  <div class="card">
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead><tr><th>Kamar</th><th>Bangsal</th><th>Kelas</th><th class="text-end">Tarif</th><th>Status</th><th style="width:170px">Ubah Status</th></tr></thead>
        <tbody>
          <?php foreach ($kamarList as $k): ?>
            <?php
            $badge = ['ISI' => 'text-bg-danger', 'KOSONG' => 'text-bg-success', 'DIBERSIHKAN' => 'text-bg-warning', 'DIBOOKING' => 'text-bg-info', 'PERBAIKAN' => 'text-bg-dark'][$k['status']] ?? 'text-bg-secondary';
            ?>
            <tr>
              <td><strong><?= e($k['kd_kamar']) ?></strong></td>
              <td><?= e($k['nm_bangsal'] ?? '-') ?></td>
              <td><?= e($k['kelas'] ?? '-') ?></td>
              <td class="text-end">Rp <?= number_format((float)$k['trf_kamar'], 0, ',', '.') ?></td>
              <td><span class="badge <?= $badge ?>"><?= e($k['status']) ?></span></td>
              <td>
                <form method="post" action="<?= e(url('kamarinap')) ?>" class="d-flex gap-1">
                  <input type="hidden" name="act" value="status_kamar" />
                  <input type="hidden" name="kd_kamar" value="<?= e($k['kd_kamar']) ?>" />
                  <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach (['ISI', 'KOSONG', 'DIBERSIHKAN', 'DIBOOKING', 'PERBAIKAN'] as $s): ?>
                      <option <?= $k['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <form method="post" action="<?= e(url('kamarinap')) ?>" class="card card-primary col-lg-6">
    <input type="hidden" name="act" value="masuk" />
    <div class="card-header"><h3 class="card-title">Masuk Kamar Inap</h3></div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Kunjungan (No. Rawat)</label>
        <select class="form-select" name="no_rawat">
          <?php foreach ($kunjunganRanap as $k): ?>
            <option value="<?= e($k['no_rawat']) ?>"><?= e($k['no_rawat']) ?> — <?= e($k['nm_pasien']) ?> (<?= e(tgl_indo($k['tgl_registrasi'])) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Kamar Kosong</label>
        <select class="form-select" name="kd_kamar">
          <?php foreach ($kamarKosong as $k): ?>
            <option value="<?= e($k['kd_kamar']) ?>"><?= e($k['kd_kamar']) ?> — <?= e($k['nm_bangsal'] ?? '') ?> (<?= e($k['kelas'] ?? '') ?>, Rp <?= number_format((float)$k['trf_kamar'], 0, ',', '.') ?>/hari)</option>
          <?php endforeach; ?>
        </select>
        <?php if (!$kamarKosong): ?>
          <div class="form-text text-danger">Tidak ada kamar kosong.</div>
        <?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label">Diagnosa Awal</label>
        <input class="form-control" name="diagnosa_awal" placeholder="Diagnosa awal masuk perawatan" />
      </div>
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label">Tanggal Masuk</label>
          <input class="form-control" type="date" name="tgl_masuk" value="<?= e(date('Y-m-d')) ?>" />
        </div>
        <div class="col-6">
          <label class="form-label">Jam Masuk</label>
          <input class="form-control" type="time" name="jam_masuk" value="<?= e(date('H:i')) ?>" />
        </div>
      </div>
    </div>
    <div class="card-footer">
      <button class="btn btn-primary" <?= $kamarKosong && $kunjunganRanap ? '' : 'disabled' ?>><i class="bi bi-check-lg me-1"></i>Simpan</button>
    </div>
  </form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
