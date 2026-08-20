<?php
// Laporan Kunjungan per periode
declare(strict_types=1);

$pageTitle = 'Laporan Kunjungan';
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

$perPoli = db_all(
    "SELECT pl.nm_poli, COUNT(*) AS jml,
            SUM(CASE WHEN rp.stts_daftar='Baru' THEN 1 ELSE 0 END) AS baru,
            SUM(CASE WHEN rp.stts_daftar='Lama' THEN 1 ELSE 0 END) AS lama,
            SUM(rp.biaya_reg) AS pendapatan
     FROM reg_periksa rp JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
     WHERE rp.tgl_registrasi BETWEEN ? AND ?
     GROUP BY pl.nm_poli ORDER BY jml DESC",
    [$dari, $sampai]
);

$perDokter = db_all(
    "SELECT d.nm_dokter, COUNT(*) AS jml
     FROM reg_periksa rp JOIN dokter d ON d.kd_dokter = rp.kd_dokter
     WHERE rp.tgl_registrasi BETWEEN ? AND ?
     GROUP BY d.nm_dokter ORDER BY jml DESC LIMIT 10",
    [$dari, $sampai]
);

$perBayar = db_all(
    "SELECT COALESCE(pj.png_jawab, rp.kd_pj) AS cara, COUNT(*) AS jml
     FROM reg_periksa rp LEFT JOIN penjab pj ON pj.kd_pj = rp.kd_pj
     WHERE rp.tgl_registrasi BETWEEN ? AND ?
     GROUP BY cara ORDER BY jml DESC",
    [$dari, $sampai]
);

$totalKunjungan = array_sum(array_column($perPoli, 'jml'));
$totalPendapatan = array_sum(array_map('floatval', array_column($perPoli, 'pendapatan')));

$pageScripts = '<script src="<?= asset('chartjs/chart.umd.js') ?>"></script>
<script>
new Chart(document.getElementById("chartBayar"), {
  type: "doughnut",
  data: { labels: ' . json_encode(array_column($perBayar, 'cara')) . ',
    datasets: [{ data: ' . json_encode(array_map('intval', array_column($perBayar, 'jml'))) . ',
      backgroundColor: ["#0d6efd","#198754","#ffc107","#dc3545","#6f42c1","#20c997","#fd7e14","#6c757d"] }] },
  options: { responsive: true, plugins: { legend: { position: "bottom" } } }
});
</script>';

require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-4">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="index.php">
      <input type="hidden" name="page" value="laporan" />
      <div class="col-auto">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="dari" value="<?= e($dari) ?>" />
      </div>
      <div class="col-auto">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="sampai" value="<?= e($sampai) ?>" />
      </div>
      <div class="col-auto">
        <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
      </div>
    </form>
  </div>
</div>
<div class="row">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header"><h3 class="card-title">Kunjungan per Poliklinik</h3></div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th>Poliklinik</th><th class="text-end">Baru</th><th class="text-end">Lama</th><th class="text-end">Total</th><th class="text-end">Pendapatan Registrasi</th></tr></thead>
          <tbody>
            <?php foreach ($perPoli as $r): ?>
              <tr>
                <td><?= e($r['nm_poli']) ?></td>
                <td class="text-end"><?= (int)$r['baru'] ?></td>
                <td class="text-end"><?= (int)$r['lama'] ?></td>
                <td class="text-end"><strong><?= (int)$r['jml'] ?></strong></td>
                <td class="text-end">Rp <?= number_format((float)$r['pendapatan'], 0, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$perPoli): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada kunjungan pada periode ini.</td></tr>
            <?php endif; ?>
          </tbody>
          <?php if ($perPoli): ?>
            <tfoot>
              <tr class="table-secondary">
                <th>Total</th>
                <th class="text-end"><?= (int)array_sum(array_column($perPoli, 'baru')) ?></th>
                <th class="text-end"><?= (int)array_sum(array_column($perPoli, 'lama')) ?></th>
                <th class="text-end"><?= (int)$totalKunjungan ?></th>
                <th class="text-end">Rp <?= number_format($totalPendapatan, 0, ',', '.') ?></th>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header"><h3 class="card-title">10 Dokter Tersibuk</h3></div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <thead><tr><th>Dokter</th><th class="text-end">Jumlah Kunjungan</th></tr></thead>
          <tbody>
            <?php foreach ($perDokter as $r): ?>
              <tr><td><?= e($r['nm_dokter']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$perDokter): ?>
              <tr><td colspan="2" class="text-center text-muted py-4">Tidak ada data.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header"><h3 class="card-title">Distribusi Cara Bayar</h3></div>
      <div class="card-body">
        <?php if ($perBayar): ?>
          <canvas id="chartBayar"></canvas>
        <?php else: ?>
          <p class="text-muted">Tidak ada data.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
