<?php
// Modul Kasir Ralan — rincian tagihan & pembayaran kunjungan
declare(strict_types=1);

require_once __DIR__ . '/../includes/kunjungan.php';

$noRawat = $_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '';
$kunjungan = $noRawat ? kunjungan_load($noRawat) : null;

// Tanpa no_rawat: daftar kunjungan menunggu pembayaran
if (!$kunjungan && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pageTitle = 'Kasir / Billing';
    $tgl = $_GET['tgl'] ?? '';
    $where = "WHERE rp.stts != 'Batal'";
    $params = [];
    if ($tgl !== '') {
        $where .= ' AND rp.tgl_registrasi = ?';
        $params[] = $tgl;
    }
    $rows = db_all(
        "SELECT rp.no_rawat, rp.tgl_registrasi, rp.jam_reg, rp.status_bayar, p.nm_pasien, pl.nm_poli, pj.png_jawab,
                (rp.biaya_reg
                 + COALESCE((SELECT SUM(biaya_rawat) FROM rawat_jl_dr WHERE no_rawat = rp.no_rawat),0)
                 + COALESCE((SELECT SUM(biaya_rawat) FROM rawat_jl_pr WHERE no_rawat = rp.no_rawat),0)
                 + COALESCE((SELECT SUM(rd.jml * db2.ralan) FROM resep_dokter rd JOIN resep_obat ro ON ro.no_resep = rd.no_resep JOIN databarang db2 ON db2.kode_brng = rd.kode_brng WHERE ro.no_rawat = rp.no_rawat),0)
                 + COALESCE((SELECT SUM(ttl_biaya) FROM kamar_inap WHERE no_rawat = rp.no_rawat),0)
                ) AS tagihan
         FROM reg_periksa rp
         JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
         JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
         LEFT JOIN penjab pj ON pj.kd_pj = rp.kd_pj
         $where ORDER BY rp.status_bayar DESC, rp.tgl_registrasi DESC, rp.jam_reg DESC LIMIT 100",
        $params
    );
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <h3 class="card-title mb-0">Tagihan Kunjungan</h3>
          <form class="d-flex gap-2" method="get" action="index.php">
            <input type="hidden" name="page" value="kasir" />
            <input type="date" class="form-control form-control-sm" name="tgl" value="<?= e($tgl) ?>" />
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button>
          </form>
        </div>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead><tr><th>No. Rawat</th><th>Tanggal</th><th>Pasien</th><th>Poli</th><th>Cara Bayar</th><th class="text-end">Tagihan</th><th>Status</th><th style="width:90px"></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><span class="badge text-bg-secondary"><?= e($r['no_rawat']) ?></span></td>
                <td><?= e(tgl_indo($r['tgl_registrasi'])) ?></td>
                <td><?= e($r['nm_pasien']) ?></td>
                <td><?= e($r['nm_poli']) ?></td>
                <td><?= e($r['png_jawab'] ?? '-') ?></td>
                <td class="text-end">Rp <?= number_format((float)$r['tagihan'], 0, ',', '.') ?></td>
                <td><?= $r['status_bayar'] === 'Sudah Bayar' ? '<span class="badge text-bg-success">Sudah</span>' : '<span class="badge text-bg-warning">Belum</span>' ?></td>
                <td><a class="btn btn-sm btn-primary" href="<?= e(url('kasir', ['no_rawat' => $r['no_rawat']])) ?>"><i class="bi bi-cash-coin me-1"></i>Billing</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada kunjungan.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

if (!$kunjungan) {
    flash_set('danger', 'Kunjungan tidak ditemukan.');
    redirect(url('kasir'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'bayar') {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Nota jalan bila belum ada
            if (!db_row('SELECT no_rawat FROM nota_jalan WHERE no_rawat = ?', [$noRawat])) {
                $tgl = date('Y/m/d');
                $urut = (int)db_val('SELECT COUNT(*) FROM nota_jalan WHERE tanggal = CURDATE()') + 1;
                $noNota = $tgl . '/RJ' . str_pad((string)$urut, 3, '0', STR_PAD_LEFT);
                db_exec('INSERT INTO nota_jalan (no_rawat, no_nota, tanggal, jam) VALUES (?,?,CURDATE(),CURTIME())', [$noRawat, $noNota]);
            }
            db_exec("UPDATE reg_periksa SET status_bayar = 'Sudah Bayar' WHERE no_rawat = ?", [$noRawat]);
            db_exec("UPDATE rawat_jl_dr SET stts_bayar = 'Sudah' WHERE no_rawat = ?", [$noRawat]);
            db_exec("UPDATE rawat_jl_pr SET stts_bayar = 'Sudah' WHERE no_rawat = ?", [$noRawat]);
            $pdo->commit();
            flash_set('success', 'Pembayaran berhasil dicatat. Nota jalan diterbitkan.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        redirect(url('kasir', ['no_rawat' => $noRawat]));
    }
    if ($act === 'batal_bayar') {
        db_exec("UPDATE reg_periksa SET status_bayar = 'Belum Bayar' WHERE no_rawat = ?", [$noRawat]);
        db_exec("UPDATE rawat_jl_dr SET stts_bayar = 'Belum' WHERE no_rawat = ?", [$noRawat]);
        db_exec("UPDATE rawat_jl_pr SET stts_bayar = 'Belum' WHERE no_rawat = ?", [$noRawat]);
        db_exec('DELETE FROM nota_jalan WHERE no_rawat = ?', [$noRawat]);
        flash_set('success', 'Status pembayaran dibatalkan.');
        redirect(url('kasir', ['no_rawat' => $noRawat]));
    }
}

$pageTitle = 'Kasir / Billing';
$rincian = [];
$rincian[] = ['uraian' => 'Biaya Registrasi (' . ($kunjungan['stts_daftar'] === 'Baru' ? 'Pasien Baru' : 'Pasien Lama') . ')', 'jml' => 1, 'harga' => (float)$kunjungan['biaya_reg']];

$tindakan = db_all(
    "SELECT j.nm_perawatan AS uraian, COUNT(*) AS jml, SUM(r.biaya_rawat) AS harga FROM (
        SELECT kd_jenis_prw, biaya_rawat FROM rawat_jl_dr WHERE no_rawat = ?
        UNION ALL SELECT kd_jenis_prw, biaya_rawat FROM rawat_jl_pr WHERE no_rawat = ?
     ) r JOIN jns_perawatan j ON j.kd_jenis_prw = r.kd_jenis_prw
     GROUP BY j.nm_perawatan ORDER BY j.nm_perawatan",
    [$noRawat, $noRawat]
);
foreach ($tindakan as $t) {
    $rincian[] = ['uraian' => 'Tindakan: ' . $t['uraian'], 'jml' => (int)$t['jml'], 'harga' => (float)$t['harga']];
}

$obat = db_all(
    'SELECT db.nama_brng, SUM(rd.jml) AS jml, db.ralan FROM resep_dokter rd
     JOIN resep_obat ro ON ro.no_resep = rd.no_resep
     JOIN databarang db ON db.kode_brng = rd.kode_brng
     WHERE ro.no_rawat = ? GROUP BY db.nama_brng, db.ralan ORDER BY db.nama_brng',
    [$noRawat]
);
foreach ($obat as $o) {
    $rincian[] = ['uraian' => 'Obat: ' . $o['nama_brng'], 'jml' => (float)$o['jml'], 'harga' => (float)$o['jml'] * (float)$o['ralan']];
}

$inap = db_all(
    'SELECT ki.*, k.kelas FROM kamar_inap ki JOIN kamar k ON k.kd_kamar = ki.kd_kamar WHERE ki.no_rawat = ?',
    [$noRawat]
);
foreach ($inap as $i) {
    $lama = (float)$i['lama'] > 0 ? (float)$i['lama'] : 1;
    $biaya = (float)$i['ttl_biaya'] > 0 ? (float)$i['ttl_biaya'] : $lama * (float)$i['trf_kamar'];
    $rincian[] = ['uraian' => 'Kamar Inap ' . $i['kd_kamar'] . ' (' . $lama . ' hari)', 'jml' => $lama, 'harga' => $biaya];
}

$totalTagihan = array_sum(array_column($rincian, 'harga'));
$nota = db_row('SELECT * FROM nota_jalan WHERE no_rawat = ?', [$noRawat]);
$sudahBayar = $kunjungan['status_bayar'] === 'Sudah Bayar';

require_once __DIR__ . '/../includes/header.php';
kunjungan_card($kunjungan);
?>
<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Rincian Tagihan</h3></div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th style="width:50px">No</th><th>Uraian</th><th class="text-end">Jml</th><th class="text-end">Biaya</th></tr></thead>
          <tbody>
            <?php foreach ($rincian as $idx => $r): ?>
              <tr>
                <td><?= $idx + 1 ?></td>
                <td><?= e($r['uraian']) ?></td>
                <td class="text-end"><?= $r['jml'] ?></td>
                <td class="text-end">Rp <?= number_format($r['harga'], 0, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-primary"><th colspan="3">Total Tagihan</th><th class="text-end">Rp <?= number_format($totalTagihan, 0, ',', '.') ?></th></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card <?= $sudahBayar ? 'card-success' : 'card-warning' ?>">
      <div class="card-header"><h3 class="card-title">Pembayaran</h3></div>
      <div class="card-body">
        <p class="mb-2">Cara Bayar: <strong><?= e($kunjungan['png_jawab'] ?? '-') ?></strong></p>
        <p class="mb-2">Status:
          <?= $sudahBayar ? '<span class="badge text-bg-success">Sudah Bayar</span>' : '<span class="badge text-bg-warning">Belum Bayar</span>' ?>
        </p>
        <?php if ($nota): ?>
          <p class="mb-2">No. Nota: <strong><?= e($nota['no_nota']) ?></strong><br />
          <small class="text-muted"><?= e(tgl_indo($nota['tanggal'])) ?> <?= e(substr((string)$nota['jam'], 0, 5)) ?></small></p>
        <?php endif; ?>
        <h4 class="mt-3">Rp <?= number_format($totalTagihan, 0, ',', '.') ?></h4>
      </div>
      <div class="card-footer">
        <?php if (!$sudahBayar): ?>
          <form method="post" action="<?= e(url('kasir')) ?>" onsubmit="return confirm('Proses pembayaran kunjungan ini?')">
            <input type="hidden" name="act" value="bayar" />
            <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
            <button class="btn btn-success w-100"><i class="bi bi-cash-coin me-1"></i>Proses Pembayaran</button>
          </form>
        <?php else: ?>
          <form method="post" action="<?= e(url('kasir')) ?>" onsubmit="return confirm('Batalkan status pembayaran?')">
            <input type="hidden" name="act" value="batal_bayar" />
            <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
            <button class="btn btn-outline-danger w-100"><i class="bi bi-arrow-counterclockwise me-1"></i>Batalkan Pembayaran</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
