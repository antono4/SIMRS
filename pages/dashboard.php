<?php
// Dashboard: ringkasan statistik SIMRS
declare(strict_types=1);

$pageTitle = 'Dashboard';

$totalPasien = (int)db_val('SELECT COUNT(*) FROM pasien');
$totalDokter = (int)db_val("SELECT COUNT(*) FROM dokter WHERE status='1'");
$totalPoli = (int)db_val("SELECT COUNT(*) FROM poliklinik WHERE status='1'");
$kunjunganHariIni = (int)db_val('SELECT COUNT(*) FROM reg_periksa WHERE tgl_registrasi = CURDATE()');
$ralanHariIni = (int)db_val("SELECT COUNT(*) FROM reg_periksa WHERE tgl_registrasi = CURDATE() AND status_lanjut='Ralan'");
$ranapHariIni = (int)db_val("SELECT COUNT(*) FROM reg_periksa WHERE tgl_registrasi = CURDATE() AND status_lanjut='Ranap'");

// Kunjungan 14 hari terakhir
$kunjunganPerHari = db_all(
    "SELECT tgl_registrasi AS tgl, COUNT(*) AS jml
     FROM reg_periksa
     WHERE tgl_registrasi >= CURDATE() - INTERVAL 13 DAY
     GROUP BY tgl_registrasi ORDER BY tgl_registrasi"
);
$labelsHari = [];
$dataHari = [];
$mapHari = [];
foreach ($kunjunganPerHari as $r) {
    $mapHari[$r['tgl']] = (int)$r['jml'];
}
for ($i = 13; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-{$i} day"));
    $labelsHari[] = date('d/m', strtotime($tgl));
    $dataHari[] = $mapHari[$tgl] ?? 0;
}

// Kunjungan per poli bulan ini
$perPoli = db_all(
    "SELECT pl.nm_poli, COUNT(*) AS jml
     FROM reg_periksa rp JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
     WHERE MONTH(rp.tgl_registrasi) = MONTH(CURDATE()) AND YEAR(rp.tgl_registrasi) = YEAR(CURDATE())
     GROUP BY pl.nm_poli ORDER BY jml DESC LIMIT 8"
);

// Kunjungan terakhir
$terakhir = db_all(
    "SELECT rp.no_rawat, rp.tgl_registrasi, rp.jam_reg, p.nm_pasien, pl.nm_poli, d.nm_dokter, rp.stts
     FROM reg_periksa rp
     JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
     JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
     JOIN dokter d ON d.kd_dokter = rp.kd_dokter
     ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC LIMIT 8"
);

$pageScripts = '<script src="assets/chartjs/chart.umd.js"></script>
<script>
new Chart(document.getElementById("chartKunjungan"), {
  type: "line",
  data: { labels: ' . json_encode($labelsHari) . ',
    datasets: [{ label: "Kunjungan", data: ' . json_encode($dataHari) . ', borderColor: "#0d6efd", backgroundColor: "rgba(13,110,253,.15)", fill: true, tension: .35 }] },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
new Chart(document.getElementById("chartPoli"), {
  type: "bar",
  data: { labels: ' . json_encode(array_column($perPoli, 'nm_poli')) . ',
    datasets: [{ label: "Kunjungan", data: ' . json_encode(array_map('intval', array_column($perPoli, 'jml'))) . ', backgroundColor: "#198754" }] },
  options: { indexAxis: "y", responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>';

require __DIR__ . '/../includes/header.php';
?>
<div class="row">
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-primary">
      <div class="inner"><h3><?= number_format($totalPasien) ?></h3><p>Total Pasien</p></div>
      <i class="small-box-icon bi bi-people"></i>
      <a href="<?= e(url('pasien')) ?>" class="small-box-footer link-light">Lihat <i class="bi bi-arrow-right-circle"></i></a>
    </div>
  </div>
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-success">
      <div class="inner"><h3><?= number_format($kunjunganHariIni) ?></h3><p>Kunjungan Hari Ini</p></div>
      <i class="small-box-icon bi bi-clipboard2-pulse"></i>
      <a href="<?= e(url('registrasi')) ?>" class="small-box-footer link-light">Lihat <i class="bi bi-arrow-right-circle"></i></a>
    </div>
  </div>
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-warning">
      <div class="inner"><h3><?= number_format($totalDokter) ?></h3><p>Dokter Aktif</p></div>
      <i class="small-box-icon bi bi-heart-pulse"></i>
      <a href="<?= e(url('dokter')) ?>" class="small-box-footer link-dark">Lihat <i class="bi bi-arrow-right-circle"></i></a>
    </div>
  </div>
  <div class="col-lg-3 col-6">
    <div class="small-box text-bg-danger">
      <div class="inner"><h3><?= number_format($totalPoli) ?></h3><p>Poliklinik Aktif</p></div>
      <i class="small-box-icon bi bi-hospital"></i>
      <a href="<?= e(url('poliklinik')) ?>" class="small-box-footer link-light">Lihat <i class="bi bi-arrow-right-circle"></i></a>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-lg-7">
    <div class="card mb-4">
      <div class="card-header"><h3 class="card-title">Kunjungan 14 Hari Terakhir</h3></div>
      <div class="card-body"><canvas id="chartKunjungan" height="120"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-4">
      <div class="card-header"><h3 class="card-title">Kunjungan per Poli (Bulan Ini)</h3></div>
      <div class="card-body"><canvas id="chartPoli" height="180"></canvas></div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header"><h3 class="card-title">Registrasi Terakhir</h3></div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <thead><tr><th>No. Rawat</th><th>Tanggal</th><th>Pasien</th><th>Poli</th><th>Dokter</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($terakhir as $row): ?>
            <tr>
              <td><?= e($row['no_rawat']) ?></td>
              <td><?= e(tgl_indo($row['tgl_registrasi'])) ?> <?= e(substr((string)$row['jam_reg'], 0, 5)) ?></td>
              <td><?= e($row['nm_pasien']) ?></td>
              <td><?= e($row['nm_poli']) ?></td>
              <td><?= e($row['nm_dokter']) ?></td>
              <td><?= badge_status($row['stts'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$terakhir): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada registrasi.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card text-bg-primary mb-4">
      <div class="card-header"><h3 class="card-title">Ringkasan Hari Ini</h3></div>
      <div class="card-body">
        <p class="mb-1"><i class="bi bi-person-walking me-2"></i>Rawat Jalan: <strong><?= $ralanHariIni ?></strong></p>
        <p class="mb-1"><i class="bi bi-hospital me-2"></i>Rawat Inap: <strong><?= $ranapHariIni ?></strong></p>
        <hr />
        <p class="mb-0 small">Database: <code>sik</code> (<?= number_format((int)db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='sik'")) ?> tabel, kompatibel SIMRS)</p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
