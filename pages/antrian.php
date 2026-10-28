<?php
// Modul Antrian Poliklinik — pengambilan nomor, pemanggilan, dan pemantauan.
declare(strict_types=1);

require_once __DIR__ . '/../includes/antrian.php';
require_once __DIR__ . '/../includes/kunjungan.php';

$tgl = $_GET['tgl'] ?? date('Y-m-d');
$poliFilter = $_GET['poli'] ?? '';
$statusFilter = $_GET['status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $tgl = $_POST['tgl'] ?? date('Y-m-d');
    $poliFilter = $_POST['poli'] ?? '';
    $statusFilter = $_POST['f_status'] ?? '';
    $oleh = (string)(auth_user()['username'] ?? '');

    if ($act === 'ambil') {
        $noRawat = trim($_POST['no_rawat'] ?? '');
        $kdPoli = trim($_POST['kd_poli'] ?? '');
        $v = $noRawat !== '' ? kunjungan_load($noRawat) : null;
        if ($v === null || $kdPoli === '') {
            flash_set('danger', 'Kunjungan atau poliklinik tidak ditemukan.');
        } else {
            $a = antrian_ambil_nomor((string)$v['tgl_registrasi'], $kdPoli, $noRawat, $oleh);
            flash_set('success', 'Nomor ' . antrian_nomor_tampil((int)$a['nomor'], (string)($a['kd_poli'] ?? '')) . ' diberikan kepada ' . $v['nm_pasien'] . ' (' . ($a['nm_poli'] ?? $kdPoli) . ').');
        }
    } elseif ($act === 'panggil') {
        $a = antrian_panggil((int)($_POST['id'] ?? 0), $oleh);
        if ($a !== null) {
            flash_set('success', 'Memanggil nomor ' . antrian_nomor_tampil((int)$a['nomor'], (string)($a['kd_poli'] ?? '')) . ' — ' . ($a['nm_poli'] ?? '-') . '.');
        } else {
            flash_set('danger', 'Data antrian tidak ditemukan.');
        }
    } elseif ($act === 'berikutnya') {
        $kdPoli = trim($_POST['kd_poli'] ?? '');
        $a = antrian_panggil_berikutnya($tgl, $kdPoli, $oleh);
        if ($a !== null) {
            flash_set('success', 'Memanggil nomor berikutnya ' . antrian_nomor_tampil((int)$a['nomor'], (string)($a['kd_poli'] ?? '')) . ' — ' . ($a['nm_poli'] ?? $kdPoli) . '.');
        } else {
            flash_set('warning', 'Tidak ada pasien menunggu pada poliklinik tersebut.');
        }
    } elseif ($act === 'ulang') {
        $a = antrian_panggil_ulang((int)($_POST['id'] ?? 0), $oleh);
        if ($a !== null) {
            flash_set('success', 'Memanggil ulang nomor ' . antrian_nomor_tampil((int)$a['nomor'], (string)($a['kd_poli'] ?? '')) . '.');
        } else {
            flash_set('danger', 'Data antrian tidak ditemukan.');
        }
    } elseif ($act === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $_POST['status'] ?? '';
        $a = antrian_row($id);
        if ($a === null) {
            flash_set('danger', 'Data antrian tidak ditemukan.');
        } else {
            antrian_set_status($id, $st);
            flash_set('success', 'Nomor ' . antrian_nomor_tampil((int)$a['nomor'], (string)($a['kd_poli'] ?? '')) . ' ditandai ' . e($st) . '.');
        }
    }
    redirect(url('antrian', ['tgl' => $tgl, 'poli' => $poliFilter, 'status' => $statusFilter]));
}

$pageTitle = 'Antrian Poliklinik';
$stat = antrian_statistik($tgl);
$poliList = db_all("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
$rows = antrian_daftar($tgl, $poliFilter, $statusFilter);

// Kelompokkan per poliklinik agar petugas loket bisa memantau satu kolom per poli.
$perPoli = [];
foreach ($rows as $r) {
    $perPoli[$r['kd_poli']]['nama'] = $r['nm_poli'] ?? $r['kd_poli'];
    $perPoli[$r['kd_poli']]['warna'] = poli_warna((string)$r['kd_poli']);
    $perPoli[$r['kd_poli']]['baris'][] = $r;
}

$tanpaNomor = [];
if ($poliFilter !== '') {
    $tanpaNomor = antrian_kunjungan_tanpa_nomor($tgl, $poliFilter);
}

$pageActions = '<a class="btn btn-sm btn-outline-primary" href="' . e(url('display')) . '" target="_blank" rel="noopener">'
    . '<i class="bi bi-tv me-1"></i>Buka Layar Display</a>';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end" method="get" action="index.php">
      <input type="hidden" name="page" value="antrian" />
      <div class="col-sm-4 col-md-3">
        <label class="form-label mb-1 small">Tanggal</label>
        <input type="date" class="form-control form-control-sm" name="tgl" value="<?= e($tgl) ?>" />
      </div>
      <div class="col-sm-4 col-md-3">
        <label class="form-label mb-1 small">Poliklinik</label>
        <select class="form-select form-select-sm" name="poli">
          <option value="">Semua poliklinik</option>
          <?php foreach ($poliList as $p): ?>
            <option value="<?= e($p['kd_poli']) ?>" <?= $poliFilter === $p['kd_poli'] ? 'selected' : '' ?>><?= e($p['nm_poli']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-md-2">
        <label class="form-label mb-1 small">Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="">Semua status</option>
          <?php foreach (ANTRIAN_STATUS as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('antrian')) ?>">Hari ini</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-2 mb-3">
  <?php
  $kartu = [
      ['Total', $stat['total'], 'primary', 'bi-people'],
      ['Menunggu', $stat['menunggu'], 'warning', 'bi-hourglass-split'],
      ['Dipanggil', $stat['dipanggil'], 'info', 'bi-megaphone'],
      ['Selesai', $stat['selesai'], 'success', 'bi-check2-circle'],
      ['Terlewat / Batal', $stat['terlewat'], 'secondary', 'bi-slash-circle'],
  ];
  foreach ($kartu as [$label, $nilai, $warna, $ikon]): ?>
    <div class="col-6 col-md">
      <div class="card border-<?= e($warna) ?> h-100">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
          <div>
            <div class="text-body-secondary small"><?= e($label) ?></div>
            <div class="fs-4 fw-semibold"><?= (int)$nilai ?></div>
          </div>
          <i class="bi <?= e($ikon) ?> fs-3 text-<?= e($warna) ?>"></i>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($poliFilter !== '' && $tanpaNomor): ?>
  <div class="card card-outline card-warning mb-3">
    <div class="card-header">
      <h3 class="card-title">Kunjungan <?= e(tgl_indo($tgl)) ?> belum bernomor antrian (<?= count($tanpaNomor) ?>)</h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>No. Rawat</th><th>No. RM</th><th>Pasien</th><th>Jam</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($tanpaNomor as $k): ?>
            <tr>
              <td><code><?= e($k['no_rawat']) ?></code></td>
              <td><?= e($k['no_rkm_medis']) ?></td>
              <td><?= e($k['nm_pasien']) ?></td>
              <td><?= e(substr((string)$k['jam_reg'], 0, 5)) ?></td>
              <td class="text-end">
                <form method="post" action="<?= e(url('antrian')) ?>" class="d-inline">
                  <input type="hidden" name="act" value="ambil" />
                  <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                  <input type="hidden" name="poli" value="<?= e($poliFilter) ?>" />
                  <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>" />
                  <input type="hidden" name="no_rawat" value="<?= e($k['no_rawat']) ?>" />
                  <input type="hidden" name="kd_poli" value="<?= e($poliFilter) ?>" />
                  <button class="btn btn-sm btn-outline-warning"><i class="bi bi-ticket-perforated me-1"></i>Beri Nomor</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if (!$perPoli): ?>
  <div class="alert alert-info">
    Belum ada antrian pada <?= e(tgl_indo($tgl)) ?><?= $poliFilter !== '' ? ' untuk poliklinik yang dipilih' : '' ?>.
    Nomor otomatis dibuat saat registrasi kunjungan baru.
  </div>
<?php endif; ?>

<?php foreach ($perPoli as $kdPoli => $grup): ?>
  <div class="card mb-3 sk-poli-card" style="--sk-poli: <?= e($grup['warna']) ?>">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h3 class="card-title mb-0 sk-poli-nama">
        <?= poli_penanda($grup['nama'], (string)$kdPoli) ?>
        <?= e($grup['nama']) ?>
        <span class="badge text-bg-secondary"><?= count($grup['baris']) ?></span>
      </h3>
      <form method="post" action="<?= e(url('antrian')) ?>" class="d-inline">
        <input type="hidden" name="act" value="berikutnya" />
        <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
        <input type="hidden" name="poli" value="<?= e($poliFilter) ?>" />
        <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>" />
        <input type="hidden" name="kd_poli" value="<?= e($kdPoli) ?>" />
        <button class="btn btn-sm btn-primary"><i class="bi bi-megaphone me-1"></i>Panggil Berikutnya</button>
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:9rem">Nomor</th>
              <th>Pasien</th>
              <th class="d-none d-md-table-cell">No. Rawat</th>
              <th>Status</th>
              <th class="d-none d-lg-table-cell">Dipanggil</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($grup['baris'] as $r): ?>
            <tr>
              <td class="text-nowrap"><span class="fs-5 fw-semibold"><?= e(antrian_nomor_tampil((int)$r['nomor'], (string)($r['kd_poli'] ?? ''))) ?></span></td>
              <td>
                <span class="sk-poli-nama">
                  <?= poli_penanda((string)($r['nm_poli'] ?? $kdPoli), (string)$kdPoli) ?>
                  <span>
                    <?= e($r['nm_pasien'] ?? '-') ?>
                    <?php if (!empty($r['no_rkm_medis'])): ?>
                      <br /><small class="text-body-secondary"><?= e($r['no_rkm_medis']) ?></small>
                    <?php endif; ?>
                  </span>
                </span>
              </td>
              <td class="d-none d-md-table-cell"><code><?= e($r['no_rawat'] ?? '-') ?></code></td>
              <td><?= antrian_badge((string)$r['status']) ?></td>
              <td class="d-none d-lg-table-cell">
                <small class="text-body-secondary">
                  <?= $r['dipanggil_pada'] ? e(date('H:i', strtotime((string)$r['dipanggil_pada']))) . ' · ' . e((string)$r['dipanggil_oleh']) : '-' ?>
                </small>
              </td>
              <td class="text-end text-nowrap">
                <form method="post" action="<?= e(url('antrian')) ?>" class="d-inline">
                  <input type="hidden" name="act" value="panggil" />
                  <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                  <input type="hidden" name="poli" value="<?= e($poliFilter) ?>" />
                  <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <button class="btn btn-sm btn-outline-primary" title="Panggil"><i class="bi bi-megaphone"></i></button>
                </form>
                <form method="post" action="<?= e(url('antrian')) ?>" class="d-inline">
                  <input type="hidden" name="act" value="ulang" />
                  <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                  <input type="hidden" name="poli" value="<?= e($poliFilter) ?>" />
                  <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <button class="btn btn-sm btn-outline-info" title="Panggil ulang"><i class="bi bi-arrow-repeat"></i></button>
                </form>
                <form method="post" action="<?= e(url('antrian')) ?>" class="d-inline">
                  <input type="hidden" name="act" value="status" />
                  <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                  <input type="hidden" name="poli" value="<?= e($poliFilter) ?>" />
                  <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <button class="btn btn-sm btn-outline-success" name="status" value="Selesai" title="Selesai"><i class="bi bi-check2"></i></button>
                </form>
                <form method="post" action="<?= e(url('antrian')) ?>" class="d-inline">
                  <input type="hidden" name="act" value="status" />
                  <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                  <input type="hidden" name="poli" value="<?= e($poliFilter) ?>" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <button class="btn btn-sm btn-outline-secondary" name="status" value="Terlewat" title="Lewati"><i class="bi bi-slash-circle"></i></button>
                </form>
                <form method="post" action="<?= e(url('antrian')) ?>" class="d-inline">
                  <input type="hidden" name="act" value="status" />
                  <input type="hidden" name="tgl" value="<?= e($tgl) ?>" />
                  <input type="hidden" name="poli" value="<?= e($poliFilter) ?>" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <button class="btn btn-sm btn-outline-danger" name="status" value="Batal" title="Batalkan"><i class="bi bi-x-lg"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
