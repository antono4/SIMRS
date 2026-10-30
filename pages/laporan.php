<?php
/**
 * Laporan kunjungan, tindakan, diagnosa, rawat inap, dan obat.
 *
 * Setiap tab punya query sendiri dan hanya dijalankan saat tab itu dibuka,
 * supaya halaman tetap ringan pada data besar (mis. diagnosa puluhan ribu baris).
 * Semua query memakai prepared statement dengan rentang tanggal yang sama.
 */
declare(strict_types=1);

$pageTitle = 'Laporan';
// Tanggal tidak valid ditolak lebih awal; kalau tidak, perbandingan BETWEEN
// dengan string sembarang hanya menghasilkan laporan kosong tanpa penjelasan.
$tanggalSah = static fn(string $t): bool => $t !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) === 1
    && checkdate((int)substr($t, 5, 2), (int)substr($t, 8, 2), (int)substr($t, 0, 4)) === true;
$dari = (string)($_GET['dari'] ?? date('Y-m-01'));
$sampai = (string)($_GET['sampai'] ?? date('Y-m-d'));
if (!$tanggalSah($dari)) {
    $dari = date('Y-m-01');
}
if (!$tanggalSah($sampai)) {
    $sampai = date('Y-m-d');
}
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}
$tab = $_GET['tab'] ?? 'kunjungan';
$poli = trim($_GET['poli'] ?? '');
$periode = [$dari, $sampai];
$filterPoli = $poli !== '' ? ' AND rp.kd_poli = ?' : '';
$filterPoliP = $poli !== '' ? [$poli] : [];

$tabs = [
    'kunjungan' => ['label' => 'Kunjungan', 'ikon' => 'bi-people', 'izin' => 'kunjungan_ralan'],
    'tindakan' => ['label' => 'Tindakan & Tarif', 'ikon' => 'bi-clipboard2-check', 'izin' => 'tindakan_ralan'],
    'diagnosa' => ['label' => 'Diagnosa / Morbiditas', 'ikon' => 'bi-clipboard2-pulse', 'izin' => 'diagnosa_pasien'],
    'ranap' => ['label' => 'Rawat Inap', 'ikon' => 'bi-hospital', 'izin' => 'kamar_inap'],
    'obat' => ['label' => 'Resep & Obat', 'ikon' => 'bi-capsule', 'izin' => 'resep_obat'],
];
// Setiap tab hanya dibuka bila pengguna punya izin modul yang dilaporkannya,
// supaya laporan tidak jadi jalan pintas membaca data di luar haknya.
$tabs = array_filter($tabs, fn(array $t): bool => auth_can_perm($t['izin']));
if (!$tabs) {
    $pageTitle = 'Laporan';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="card"><div class="card-body text-center py-5">
      <i class="bi bi-shield-lock text-danger" style="font-size:3rem"></i>
      <h4 class="mt-3">Tidak ada laporan yang dapat Anda buka</h4>
      <p class="text-muted mb-0">Hubungi administrator untuk menambahkan izin modul terkait.</p>
    </div></div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}
if (!isset($tabs[$tab])) {
    $tab = array_key_first($tabs);
}

$poliList = db_all('SELECT kd_poli, nm_poli FROM poliklinik ORDER BY nm_poli');

$ringkas = [];
foreach (array_keys($tabs) as $kunci) {
    $ringkas[$kunci] = match ($kunci) {
        'kunjungan' => (int)db_val('SELECT COUNT(*) FROM reg_periksa WHERE tgl_registrasi BETWEEN ? AND ?', $periode),
        'tindakan' => (int)db_val(
            'SELECT (SELECT COUNT(*) FROM rawat_jl_dr WHERE tgl_perawatan BETWEEN ? AND ?)
                  + (SELECT COUNT(*) FROM rawat_jl_pr WHERE tgl_perawatan BETWEEN ? AND ?)',
            array_merge($periode, $periode)
        ),
        'diagnosa' => (int)db_val(
            'SELECT COUNT(*) FROM diagnosa_pasien dp JOIN reg_periksa rp ON rp.no_rawat = dp.no_rawat
             WHERE rp.tgl_registrasi BETWEEN ? AND ?',
            $periode
        ),
        'ranap' => (int)db_val('SELECT COUNT(*) FROM kamar_inap WHERE tgl_masuk BETWEEN ? AND ?', $periode),
        'obat' => (int)db_val('SELECT COUNT(*) FROM resep_obat WHERE tgl_perawatan BETWEEN ? AND ?', $periode),
    };
}

$data = [];

if ($tab === 'kunjungan') {
    $data['perPoli'] = db_all(
        "SELECT pl.nm_poli, COUNT(*) AS jml,
                SUM(CASE WHEN rp.stts_daftar='Baru' THEN 1 ELSE 0 END) AS baru,
                SUM(CASE WHEN rp.stts_daftar='Lama' THEN 1 ELSE 0 END) AS lama,
                SUM(CASE WHEN rp.status_lanjut='Ranap' THEN 1 ELSE 0 END) AS ranap,
                SUM(rp.biaya_reg) AS pendapatan
         FROM reg_periksa rp JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY pl.nm_poli ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
    $data['perDokter'] = db_all(
        "SELECT d.nm_dokter, COUNT(*) AS jml
         FROM reg_periksa rp JOIN dokter d ON d.kd_dokter = rp.kd_dokter
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY d.nm_dokter ORDER BY jml DESC LIMIT 10",
        array_merge($periode, $filterPoliP)
    );
    $data['perBayar'] = db_all(
        "SELECT COALESCE(pj.png_jawab, rp.kd_pj) AS cara, COUNT(*) AS jml
         FROM reg_periksa rp LEFT JOIN penjab pj ON pj.kd_pj = rp.kd_pj
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY cara ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
    $data['harian'] = db_all(
        "SELECT rp.tgl_registrasi AS tgl, COUNT(*) AS jml,
                SUM(CASE WHEN rp.status_lanjut='Ranap' THEN 1 ELSE 0 END) AS ranap
         FROM reg_periksa rp
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY rp.tgl_registrasi ORDER BY rp.tgl_registrasi",
        array_merge($periode, $filterPoliP)
    );
    $data['status'] = db_all(
        "SELECT rp.stts, COUNT(*) AS jml FROM reg_periksa rp
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY rp.stts ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
}

if ($tab === 'tindakan') {
    $data['perTindakan'] = db_all(
        "SELECT j.nm_perawatan, k.nm_kategori, COUNT(*) AS jml,
                SUM(t.biaya_rawat) AS total, SUM(t.tarif_tindakandr) AS jasa_dr,
                SUM(t.material + t.bhp) AS sarana
         FROM rawat_jl_dr t
         JOIN jns_perawatan j ON j.kd_jenis_prw = t.kd_jenis_prw
         LEFT JOIN kategori_perawatan k ON k.kd_kategori = j.kd_kategori
         JOIN reg_periksa rp ON rp.no_rawat = t.no_rawat
         WHERE t.tgl_perawatan BETWEEN ? AND ? $filterPoli
         GROUP BY j.nm_perawatan, k.nm_kategori ORDER BY jml DESC LIMIT 30",
        array_merge($periode, $filterPoliP)
    );
    $data['perPerawat'] = db_all(
        "SELECT pt.nama AS nm_petugas, COUNT(*) AS jml, SUM(t.biaya_rawat) AS total
         FROM rawat_jl_pr t JOIN petugas pt ON pt.nip = t.nip
         JOIN reg_periksa rp ON rp.no_rawat = t.no_rawat
         WHERE t.tgl_perawatan BETWEEN ? AND ? $filterPoli
         GROUP BY pt.nama ORDER BY jml DESC LIMIT 15",
        array_merge($periode, $filterPoliP)
    );
    $data['perKategori'] = db_all(
        "SELECT COALESCE(k.nm_kategori, '-') AS nm_kategori, COUNT(*) AS jml, SUM(t.biaya_rawat) AS total
         FROM rawat_jl_dr t
         JOIN jns_perawatan j ON j.kd_jenis_prw = t.kd_jenis_prw
         LEFT JOIN kategori_perawatan k ON k.kd_kategori = j.kd_kategori
         JOIN reg_periksa rp ON rp.no_rawat = t.no_rawat
         WHERE t.tgl_perawatan BETWEEN ? AND ? $filterPoli
         GROUP BY nm_kategori ORDER BY total DESC",
        array_merge($periode, $filterPoliP)
    );
    $data['belumBayar'] = db_row(
        "SELECT COUNT(*) AS jml, COALESCE(SUM(t.biaya_rawat),0) AS total
         FROM rawat_jl_dr t JOIN reg_periksa rp ON rp.no_rawat = t.no_rawat
         WHERE t.tgl_perawatan BETWEEN ? AND ? AND t.stts_bayar <> 'Sudah' $filterPoli",
        array_merge($periode, $filterPoliP)
    ) ?? ['jml' => 0, 'total' => 0];
}

if ($tab === 'diagnosa') {
    $data['teratas'] = db_all(
        "SELECT p.kd_penyakit, p.nm_penyakit, k.nm_kategori, COUNT(*) AS jml,
                SUM(CASE WHEN dp.status_penyakit='Baru' THEN 1 ELSE 0 END) AS baru
         FROM diagnosa_pasien dp
         JOIN reg_periksa rp ON rp.no_rawat = dp.no_rawat
         JOIN penyakit p ON p.kd_penyakit = dp.kd_penyakit
         LEFT JOIN kategori_penyakit k ON k.kd_ktg = p.kd_ktg
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY p.kd_penyakit, p.nm_penyakit, k.nm_kategori ORDER BY jml DESC LIMIT 30",
        array_merge($periode, $filterPoliP)
    );
    $data['perKategori'] = db_all(
        "SELECT COALESCE(k.nm_kategori, '-') AS nm_kategori, COUNT(*) AS jml
         FROM diagnosa_pasien dp
         JOIN reg_periksa rp ON rp.no_rawat = dp.no_rawat
         JOIN penyakit p ON p.kd_penyakit = dp.kd_penyakit
         LEFT JOIN kategori_penyakit k ON k.kd_ktg = p.kd_ktg
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY nm_kategori ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
    $data['menular'] = db_all(
        "SELECT p.nm_penyakit, COUNT(*) AS jml
         FROM diagnosa_pasien dp
         JOIN reg_periksa rp ON rp.no_rawat = dp.no_rawat
         JOIN penyakit p ON p.kd_penyakit = dp.kd_penyakit
         WHERE rp.tgl_registrasi BETWEEN ? AND ? AND p.status='Menular' $filterPoli
         GROUP BY p.nm_penyakit ORDER BY jml DESC LIMIT 15",
        array_merge($periode, $filterPoliP)
    );
    $data['jenisDiagnosa'] = db_all(
        "SELECT dp.status, COUNT(*) AS jml FROM diagnosa_pasien dp
         JOIN reg_periksa rp ON rp.no_rawat = dp.no_rawat
         WHERE rp.tgl_registrasi BETWEEN ? AND ? $filterPoli
         GROUP BY dp.status ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
}

if ($tab === 'ranap') {
    $data['perBangsal'] = db_all(
        "SELECT COALESCE(b.nm_bangsal, '-') AS nm_bangsal, COUNT(*) AS jml,
                COALESCE(SUM(ki.ttl_biaya),0) AS total, COALESCE(SUM(ki.lama),0) AS lama
         FROM kamar_inap ki
         JOIN kamar km ON km.kd_kamar = ki.kd_kamar
         LEFT JOIN bangsal b ON b.kd_bangsal = km.kd_bangsal
         JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
         WHERE ki.tgl_masuk BETWEEN ? AND ? $filterPoli
         GROUP BY nm_bangsal ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
    $data['pulang'] = db_all(
        "SELECT ki.stts_pulang, COUNT(*) AS jml, COALESCE(SUM(ki.ttl_biaya),0) AS total
         FROM kamar_inap ki JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
         WHERE ki.tgl_masuk BETWEEN ? AND ? $filterPoli
         GROUP BY ki.stts_pulang ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
    $data['perKelas'] = db_all(
        "SELECT COALESCE(km.kelas,'-') AS kelas, COUNT(*) AS jml, COALESCE(SUM(ki.ttl_biaya),0) AS total
         FROM kamar_inap ki
         JOIN kamar km ON km.kd_kamar = ki.kd_kamar
         JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
         WHERE ki.tgl_masuk BETWEEN ? AND ? $filterPoli
         GROUP BY kelas ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
}

if ($tab === 'obat') {
    $data['teratas'] = db_all(
        "SELECT d.nama_brng, s.satuan, SUM(rd.jml) AS jml, COUNT(*) AS kali,
                SUM(rd.jml * COALESCE(NULLIF(d.ralan,0), d.h_beli)) AS nilai
         FROM resep_dokter rd
         JOIN resep_obat ro ON ro.no_resep = rd.no_resep
         JOIN reg_periksa rp ON rp.no_rawat = ro.no_rawat
         JOIN databarang d ON d.kode_brng = rd.kode_brng
         LEFT JOIN kodesatuan s ON s.kode_sat = d.kode_sat
         WHERE ro.tgl_perawatan BETWEEN ? AND ? $filterPoli
         GROUP BY d.nama_brng, s.satuan ORDER BY jml DESC LIMIT 30",
        array_merge($periode, $filterPoliP)
    );
    $data['perJenis'] = db_all(
        "SELECT COALESCE(j.nama,'-') AS nama, COUNT(DISTINCT ro.no_resep) AS resep, SUM(rd.jml) AS jml
         FROM resep_dokter rd
         JOIN resep_obat ro ON ro.no_resep = rd.no_resep
         JOIN reg_periksa rp ON rp.no_rawat = ro.no_rawat
         JOIN databarang d ON d.kode_brng = rd.kode_brng
         LEFT JOIN jenis j ON j.kdjns = d.kdjns
         WHERE ro.tgl_perawatan BETWEEN ? AND ? $filterPoli
         GROUP BY nama ORDER BY jml DESC",
        array_merge($periode, $filterPoliP)
    );
    $data['perDokter'] = db_all(
        "SELECT dk.nm_dokter, COUNT(DISTINCT ro.no_resep) AS resep, COUNT(rd.kode_brng) AS item
         FROM resep_obat ro
         JOIN dokter dk ON dk.kd_dokter = ro.kd_dokter
         JOIN reg_periksa rp ON rp.no_rawat = ro.no_rawat
         LEFT JOIN resep_dokter rd ON rd.no_resep = ro.no_resep
         WHERE ro.tgl_perawatan BETWEEN ? AND ? $filterPoli
         GROUP BY dk.nm_dokter ORDER BY resep DESC LIMIT 15",
        array_merge($periode, $filterPoliP)
    );
    $data['ringkasResep'] = db_row(
        "SELECT COUNT(DISTINCT ro.no_resep) AS resep, COUNT(rd.kode_brng) AS item
         FROM resep_obat ro
         JOIN reg_periksa rp ON rp.no_rawat = ro.no_rawat
         LEFT JOIN resep_dokter rd ON rd.no_resep = ro.no_resep
         WHERE ro.tgl_perawatan BETWEEN ? AND ? $filterPoli",
        array_merge($periode, $filterPoliP)
    ) ?? ['resep' => 0, 'item' => 0];
}

$rp = static fn(float|int|string|null $n): string => 'Rp ' . number_format((float)$n, 0, ',', '.');

$pageScripts = '';
if ($tab === 'kunjungan') {
    $pageScripts = '<script src="assets/chartjs/chart.umd.js"></script><script>
(function () {
  const el = document.getElementById("chartBayar");
  if (el) new Chart(el, { type: "doughnut",
    data: { labels: ' . json_encode(array_column($data['perBayar'], 'cara')) . ',
      datasets: [{ data: ' . json_encode(array_map('intval', array_column($data['perBayar'], 'jml'))) . ',
        backgroundColor: ["#0d6efd","#198754","#ffc107","#dc3545","#6f42c1","#20c997","#fd7e14","#6c757d"] }] },
    options: { responsive: true, plugins: { legend: { position: "bottom" } } } });
  const h = document.getElementById("chartHarian");
  if (h) new Chart(h, { type: "bar",
    data: { labels: ' . json_encode(array_column($data['harian'], 'tgl')) . ',
      datasets: [
        { label: "Rawat Jalan", data: ' . json_encode(array_map(fn($r) => (int)$r['jml'] - (int)$r['ranap'], $data['harian'])) . ', backgroundColor: "#1565c0" },
        { label: "Rawat Inap", data: ' . json_encode(array_map(fn($r) => (int)$r['ranap'], $data['harian'])) . ', backgroundColor: "#00b4d8" }
      ] },
    options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true } } } });
})();
</script>';
}
if ($tab === 'diagnosa') {
    $pageScripts = '<script src="assets/chartjs/chart.umd.js"></script><script>
(function () {
  const el = document.getElementById("chartKategori");
  if (el) new Chart(el, { type: "bar",
    data: { labels: ' . json_encode(array_column($data['perKategori'], 'nm_kategori')) . ',
      datasets: [{ label: "Jumlah Diagnosa", data: ' . json_encode(array_map('intval', array_column($data['perKategori'], 'jml'))) . ', backgroundColor: "#dc3545" }] },
    options: { indexAxis: "y", responsive: true, plugins: { legend: { display: false } } } });
})();
</script>';
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="index.php">
      <input type="hidden" name="page" value="laporan" />
      <input type="hidden" name="tab" value="<?= e($tab) ?>" />
      <div class="col-auto">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="dari" value="<?= e($dari) ?>" />
      </div>
      <div class="col-auto">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="sampai" value="<?= e($sampai) ?>" />
      </div>
      <div class="col-auto">
        <label class="form-label mb-1">Poliklinik</label>
        <select class="form-select" name="poli">
          <option value="">Semua poliklinik</option>
          <?php foreach ($poliList as $pl): ?>
            <option value="<?= e($pl['kd_poli']) ?>" <?= $poli === $pl['kd_poli'] ? 'selected' : '' ?>><?= e($pl['nm_poli']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
      </div>
    </form>
  </div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
  <?php foreach ($tabs as $kunci => $t): ?>
    <li class="nav-item">
      <a class="nav-link <?= $kunci === $tab ? 'active' : '' ?>"
         href="<?= e(url('laporan', ['tab' => $kunci, 'dari' => $dari, 'sampai' => $sampai] + ($poli !== '' ? ['poli' => $poli] : []))) ?>">
        <i class="bi <?= e($t['ikon']) ?> me-1"></i><?= e($t['label']) ?>
        <span class="badge ms-1 <?= $kunci === $tab ? 'bg-body-secondary text-body-secondary' : 'text-bg-secondary' ?>"><?= number_format($ringkas[$kunci], 0, ',', '.') ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'kunjungan'): ?>
  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Kunjungan per Poliklinik</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Poliklinik</th><th class="text-end">Baru</th><th class="text-end">Lama</th><th class="text-end">Ranap</th><th class="text-end">Total</th><th class="text-end">Pendapatan Registrasi</th></tr></thead>
            <tbody>
              <?php foreach ($data['perPoli'] as $r): ?>
                <tr>
                  <td><?= e($r['nm_poli']) ?></td>
                  <td class="text-end"><?= (int)$r['baru'] ?></td>
                  <td class="text-end"><?= (int)$r['lama'] ?></td>
                  <td class="text-end"><?= (int)$r['ranap'] ?></td>
                  <td class="text-end"><strong><?= (int)$r['jml'] ?></strong></td>
                  <td class="text-end"><?= $rp($r['pendapatan']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$data['perPoli']): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada kunjungan pada periode ini.</td></tr>
              <?php endif; ?>
            </tbody>
            <?php if ($data['perPoli']): ?>
              <tfoot>
                <tr class="table-secondary">
                  <th>Total</th>
                  <th class="text-end"><?= (int)array_sum(array_column($data['perPoli'], 'baru')) ?></th>
                  <th class="text-end"><?= (int)array_sum(array_column($data['perPoli'], 'lama')) ?></th>
                  <th class="text-end"><?= (int)array_sum(array_column($data['perPoli'], 'ranap')) ?></th>
                  <th class="text-end"><?= (int)array_sum(array_column($data['perPoli'], 'jml')) ?></th>
                  <th class="text-end"><?= $rp(array_sum(array_map('floatval', array_column($data['perPoli'], 'pendapatan')))) ?></th>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Kunjungan Harian</h3></div>
        <div class="card-body">
          <?php if ($data['harian']): ?><canvas id="chartHarian" height="90"></canvas>
          <?php else: ?><p class="text-muted mb-0">Tidak ada data.</p><?php endif; ?>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">10 Dokter Tersibuk</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Dokter</th><th class="text-end">Jumlah Kunjungan</th></tr></thead>
            <tbody>
              <?php foreach ($data['perDokter'] as $r): ?>
                <tr><td><?= e($r['nm_dokter']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['perDokter']): ?><tr><td colspan="2" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Distribusi Cara Bayar</h3></div>
        <div class="card-body">
          <?php if ($data['perBayar']): ?><canvas id="chartBayar"></canvas>
          <?php else: ?><p class="text-muted mb-0">Tidak ada data.</p><?php endif; ?>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Status Kunjungan</h3></div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0">
            <tbody>
              <?php foreach ($data['status'] as $r): ?>
                <tr><td><?= badge_status((string)$r['stts']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['status']): ?><tr><td class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'tindakan'): ?>
  <div class="row mb-3">
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <p class="text-body-secondary mb-1">Total Biaya Tindakan Dokter</p>
        <h3 class="mb-0"><?= $rp(array_sum(array_map('floatval', array_column($data['perKategori'], 'total')))) ?></h3>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <p class="text-body-secondary mb-1">Tindakan Belum Dibayar</p>
        <h3 class="mb-0"><?= (int)$data['belumBayar']['jml'] ?> <small class="text-body-secondary fs-6">/ <?= $rp($data['belumBayar']['total']) ?></small></h3>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <p class="text-body-secondary mb-1">Kategori Tindakan Terpakai</p>
        <h3 class="mb-0"><?= count($data['perKategori']) ?></h3>
      </div></div>
    </div>
  </div>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title mb-0">30 Tindakan Terbanyak (Dokter)</h3></div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped mb-0">
        <thead><tr><th>Tindakan</th><th>Kategori</th><th class="text-end">Jumlah</th><th class="text-end">Jasa Dokter</th><th class="text-end">Sarana</th><th class="text-end">Total</th></tr></thead>
        <tbody>
          <?php foreach ($data['perTindakan'] as $r): ?>
            <tr>
              <td><?= e($r['nm_perawatan']) ?></td>
              <td><?= e($r['nm_kategori'] ?? '-') ?></td>
              <td class="text-end"><?= (int)$r['jml'] ?></td>
              <td class="text-end"><?= $rp($r['jasa_dr']) ?></td>
              <td class="text-end"><?= $rp($r['sarana']) ?></td>
              <td class="text-end"><strong><?= $rp($r['total']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$data['perTindakan']): ?><tr><td colspan="6" class="text-center text-muted py-4">Tidak ada tindakan pada periode ini.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-6">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Biaya Tindakan per Kategori</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Kategori</th><th class="text-end">Jumlah</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php foreach ($data['perKategori'] as $r): ?>
                <tr><td><?= e($r['nm_kategori']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td><td class="text-end"><?= $rp($r['total']) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['perKategori']): ?><tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">15 Petugas Tersibuk</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Petugas</th><th class="text-end">Jumlah</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php foreach ($data['perPerawat'] as $r): ?>
                <tr><td><?= e($r['nm_petugas']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td><td class="text-end"><?= $rp($r['total']) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['perPerawat']): ?><tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'diagnosa'): ?>
  <div class="row">
    <div class="col-lg-7">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">30 Diagnosa Terbanyak</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Kode</th><th>Diagnosa</th><th>Kategori</th><th class="text-end">Kasus</th><th class="text-end">Baru</th></tr></thead>
            <tbody>
              <?php foreach ($data['teratas'] as $r): ?>
                <tr>
                  <td><span class="badge text-bg-danger"><?= e($r['kd_penyakit']) ?></span></td>
                  <td><?= e($r['nm_penyakit']) ?></td>
                  <td><?= e($r['nm_kategori'] ?? '-') ?></td>
                  <td class="text-end"><strong><?= (int)$r['jml'] ?></strong></td>
                  <td class="text-end"><?= (int)$r['baru'] ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$data['teratas']): ?><tr><td colspan="5" class="text-center text-muted py-4">Tidak ada diagnosa pada periode ini.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">15 Penyakit Menular Terbanyak</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Penyakit</th><th class="text-end">Kasus</th></tr></thead>
            <tbody>
              <?php foreach ($data['menular'] as $r): ?>
                <tr><td><?= e($r['nm_penyakit']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['menular']): ?><tr><td colspan="2" class="text-center text-muted py-4">Tidak ada penyakit menular pada periode ini.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Kasus per Kategori Penyakit</h3></div>
        <div class="card-body">
          <?php if ($data['perKategori']): ?><canvas id="chartKategori" height="220"></canvas>
          <?php else: ?><p class="text-muted mb-0">Tidak ada data.</p><?php endif; ?>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Jenis Diagnosa</h3></div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0">
            <tbody>
              <?php foreach ($data['jenisDiagnosa'] as $r): ?>
                <tr><td><?= e($r['status']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['jenisDiagnosa']): ?><tr><td class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'ranap'): ?>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title mb-0">Rawat Inap per Bangsal</h3></div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped mb-0">
        <thead><tr><th>Bangsal</th><th class="text-end">Jumlah Masuk</th><th class="text-end">Total Hari</th><th class="text-end">Rata-rata Hari</th><th class="text-end">Total Biaya</th></tr></thead>
        <tbody>
          <?php foreach ($data['perBangsal'] as $r): ?>
            <tr>
              <td><?= e($r['nm_bangsal']) ?></td>
              <td class="text-end"><?= (int)$r['jml'] ?></td>
              <td class="text-end"><?= (int)$r['lama'] ?></td>
              <td class="text-end"><?= number_format((int)$r['lama'] / max(1, (int)$r['jml']), 1, ',', '.') ?></td>
              <td class="text-end"><strong><?= $rp($r['total']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$data['perBangsal']): ?><tr><td colspan="5" class="text-center text-muted py-4">Tidak ada rawat inap pada periode ini.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-6">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Status Pulang</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Status Pulang</th><th class="text-end">Jumlah</th><th class="text-end">Total Biaya</th></tr></thead>
            <tbody>
              <?php foreach ($data['pulang'] as $r): ?>
                <tr><td><?= e($r['stts_pulang']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td><td class="text-end"><?= $rp($r['total']) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['pulang']): ?><tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Rawat Inap per Kelas</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Kelas</th><th class="text-end">Jumlah</th><th class="text-end">Total Biaya</th></tr></thead>
            <tbody>
              <?php foreach ($data['perKelas'] as $r): ?>
                <tr><td><?= e($r['kelas']) ?></td><td class="text-end"><?= (int)$r['jml'] ?></td><td class="text-end"><?= $rp($r['total']) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['perKelas']): ?><tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'obat'): ?>
  <div class="row mb-3">
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <p class="text-body-secondary mb-1">Jumlah Resep</p>
        <h3 class="mb-0"><?= number_format((int)$data['ringkasResep']['resep'], 0, ',', '.') ?></h3>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <p class="text-body-secondary mb-1">Jumlah Item Obat</p>
        <h3 class="mb-0"><?= number_format((int)$data['ringkasResep']['item'], 0, ',', '.') ?></h3>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <p class="text-body-secondary mb-1">Nilai Obat (estimasi tarif Ralan)</p>
        <h3 class="mb-0"><?= $rp(array_sum(array_map('floatval', array_column($data['teratas'], 'nilai')))) ?></h3>
      </div></div>
    </div>
  </div>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title mb-0">30 Obat Terbanyak Diresepkan</h3></div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped mb-0">
        <thead><tr><th>Nama Obat</th><th>Satuan</th><th class="text-end">Jumlah</th><th class="text-end">Kali Diresepkan</th><th class="text-end">Estimasi Nilai</th></tr></thead>
        <tbody>
          <?php foreach ($data['teratas'] as $r): ?>
            <tr>
              <td><?= e($r['nama_brng']) ?></td>
              <td><?= e($r['satuan'] ?? '-') ?></td>
              <td class="text-end"><?= number_format((float)$r['jml'], 0, ',', '.') ?></td>
              <td class="text-end"><?= (int)$r['kali'] ?></td>
              <td class="text-end"><?= $rp($r['nilai']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$data['teratas']): ?><tr><td colspan="5" class="text-center text-muted py-4">Tidak ada resep pada periode ini.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-6">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Pemakaian per Jenis Barang</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Jenis</th><th class="text-end">Jumlah Resep</th><th class="text-end">Total Item</th></tr></thead>
            <tbody>
              <?php foreach ($data['perJenis'] as $r): ?>
                <tr><td><?= e($r['nama']) ?></td><td class="text-end"><?= (int)$r['resep'] ?></td><td class="text-end"><?= number_format((float)$r['jml'], 0, ',', '.') ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['perJenis']): ?><tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">15 Dokter Peresep</h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Dokter</th><th class="text-end">Jumlah Resep</th><th class="text-end">Item</th></tr></thead>
            <tbody>
              <?php foreach ($data['perDokter'] as $r): ?>
                <tr><td><?= e($r['nm_dokter']) ?></td><td class="text-end"><?= (int)$r['resep'] ?></td><td class="text-end"><?= (int)$r['item'] ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$data['perDokter']): ?><tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
