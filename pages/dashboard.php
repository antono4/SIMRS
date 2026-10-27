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
(function () {
  const css = getComputedStyle(document.documentElement);
  const v = (n, f) => (css.getPropertyValue(n) || f).trim();
  const grid = v("--sk-border", "#e2e8f0");
  const muted = v("--sk-muted", "#64748b");
  const surface = v("--sk-surface", "#fff");
  const brand = "#1565c0";
  const soft = "#00b4d8";
  const green = "#0f9d58";
  const amber = "#f59f00";

  Chart.defaults.font.family = v("--sk-font", "Inter, sans-serif");
  Chart.defaults.font.size = 11;
  Chart.defaults.color = muted;

  const line = document.getElementById("chartKunjungan");
  if (line) {
    const ctx = line.getContext("2d");
    const fill = ctx.createLinearGradient(0, 0, 0, 240);
    fill.addColorStop(0, "rgba(21,101,192,.30)");
    fill.addColorStop(1, "rgba(21,101,192,0)");
    new Chart(line, {
      type: "line",
      data: {
        labels: ' . json_encode($labelsHari) . ',
        datasets: [{
          label: "Kunjungan",
          data: ' . json_encode($dataHari) . ',
          borderColor: brand,
          backgroundColor: fill,
          borderWidth: 2.5,
          fill: true,
          tension: .38,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: brand,
          pointHoverBorderColor: surface,
          pointHoverBorderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, border: { display: false } },
          y: {
            beginAtZero: true,
            ticks: { precision: 0, padding: 6 },
            grid: { color: grid, drawTicks: false },
            border: { display: false }
          }
        }
      }
    });
  }

  const bar = document.getElementById("chartPoli");
  if (bar) {
    new Chart(bar, {
      type: "bar",
      data: {
        labels: ' . json_encode(array_column($perPoli, 'nm_poli')) . ',
        datasets: [{
          label: "Kunjungan",
          data: ' . json_encode(array_map('intval', array_column($perPoli, 'jml'))) . ',
          backgroundColor: soft,
          hoverBackgroundColor: brand,
          borderRadius: 6,
          maxBarThickness: 22
        }]
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { precision: 0, padding: 6 },
            grid: { color: grid, drawTicks: false },
            border: { display: false }
          },
          y: { grid: { display: false }, border: { display: false } }
        }
      }
    });
  }

  const donut = document.getElementById("chartStatus");
  if (donut) {
    new Chart(donut, {
      type: "doughnut",
      data: {
        labels: ["Rawat Jalan", "Rawat Inap"],
        datasets: [{
          data: [' . (int)$ralanHariIni . ', ' . (int)$ranapHariIni . '],
          backgroundColor: [brand, amber],
          borderColor: surface,
          borderWidth: 3,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "68%",
        plugins: {
          legend: {
            position: "bottom",
            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: "circle", padding: 14 }
          }
        }
      }
    });
  }
})();
</script>';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <?php
  $kartu = [
      ['label' => 'Total Pasien', 'nilai' => $totalPasien, 'ikon' => 'bi-people', 'warna' => 'primary', 'tautan' => url('pasien')],
      ['label' => 'Kunjungan Hari Ini', 'nilai' => $kunjunganHariIni, 'ikon' => 'bi-clipboard2-pulse', 'warna' => 'success', 'tautan' => url('registrasi')],
      ['label' => 'Dokter Aktif', 'nilai' => $totalDokter, 'ikon' => 'bi-heart-pulse', 'warna' => 'warning', 'tautan' => url('dokter')],
      ['label' => 'Poliklinik Aktif', 'nilai' => $totalPoli, 'ikon' => 'bi-hospital', 'warna' => 'info', 'tautan' => url('poliklinik')],
  ];
  ?>
  <?php foreach ($kartu as $k): ?>
    <div class="col-xl-3 col-md-6">
      <div class="small-box text-bg-<?= e($k['warna']) ?>">
        <div class="inner">
          <h3><?= number_format((int)$k['nilai']) ?></h3>
          <p><?= e($k['label']) ?></p>
        </div>
        <i class="small-box-icon bi <?= e($k['ikon']) ?>"></i>
        <a href="<?= e($k['tautan']) ?>" class="small-box-footer link-<?= $k['warna'] === 'warning' ? 'dark' : 'light' ?> d-flex justify-content-between align-items-center">
          <span>Lihat detail</span><i class="bi bi-arrow-right-circle"></i>
        </a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Kunjungan 14 Hari Terakhir</h3>
      </div>
      <div class="card-body"><div style="height:280px"><canvas id="chartKunjungan"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-pie-chart me-2 text-primary"></i>Komposisi Hari Ini</h3>
      </div>
      <div class="card-body">
        <div style="height:200px"><canvas id="chartStatus"></canvas></div>
        <hr />
        <div class="d-flex justify-content-between small">
          <span class="text-body-secondary">Total kunjungan</span>
          <strong><?= number_format($kunjunganHariIni) ?></strong>
        </div>
        <div class="d-flex justify-content-between small mt-1">
          <span class="text-body-secondary">Tabel inti <code>sik</code></span>
          <strong><?= number_format((int)db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='sik'")) ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Kunjungan per Poli (Bulan Ini)</h3>
      </div>
      <div class="card-body"><div style="height:300px"><canvas id="chartPoli"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Registrasi Terakhir</h3>
        <a href="<?= e(url('registrasi')) ?>" class="btn btn-sm btn-outline-primary">Semua</a>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Pasien</th><th>Poli</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($terakhir, 0, 6) as $row): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($row['nm_pasien']) ?></div>
                <div class="small text-body-secondary"><?= e($row['no_rawat']) ?> &middot; <?= e(tgl_indo($row['tgl_registrasi'])) ?> <?= e(substr((string)$row['jam_reg'], 0, 5)) ?></div>
              </td>
              <td class="small"><?= e($row['nm_poli']) ?></td>
              <td><?= badge_status($row['stts'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$terakhir): ?>
            <tr><td colspan="3" class="text-center text-body-secondary py-4">Belum ada registrasi.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
