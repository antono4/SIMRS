<?php
// Modul Rekam Medis (SOAP) — catatan per kunjungan, diakses lewat ?page=rekam_medis&no_rawat=...
declare(strict_types=1);

require_once __DIR__ . '/../includes/kunjungan.php';
require_once __DIR__ . '/../includes/rekam_medis.php';

$noRawat = $_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '';
$kunjungan = $noRawat ? kunjungan_load($noRawat) : null;
if (!$kunjungan) {
    flash_set('danger', 'Kunjungan tidak ditemukan.');
    redirect(url('registrasi'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'tgl_perawatan' => ($_POST['tgl_perawatan'] ?? '') ?: date('Y-m-d'),
            'jam' => ($_POST['jam'] ?? '') ?: date('H:i:s'),
            'kd_dokter' => ($_POST['kd_dokter'] ?? '') ?: null,
            'nip' => ($_POST['nip'] ?? '') ?: null,
            'keluhan' => trim($_POST['keluhan'] ?? ''),
            'pemeriksaan' => trim($_POST['pemeriksaan'] ?? ''),
            'penilaian' => trim($_POST['penilaian'] ?? ''),
            'rencana' => trim($_POST['rencana'] ?? ''),
            'tensi' => trim($_POST['tensi'] ?? ''),
            'nadi' => trim($_POST['nadi'] ?? ''),
            'respirasi' => trim($_POST['respirasi'] ?? ''),
            'suhu' => trim($_POST['suhu'] ?? ''),
            'spo2' => trim($_POST['spo2'] ?? ''),
            'tinggi' => trim($_POST['tinggi'] ?? ''),
            'berat' => trim($_POST['berat'] ?? ''),
            'gcs' => trim($_POST['gcs'] ?? ''),
            'kesadaran' => $_POST['kesadaran'] ?? '',
            'alergi' => trim($_POST['alergi'] ?? ''),
        ];

        if ($data['keluhan'] === '' && $data['pemeriksaan'] === '' && $data['penilaian'] === '' && $data['rencana'] === '') {
            flash_set('warning', 'Isi minimal salah satu: keluhan, pemeriksaan, penilaian, atau rencana.');
            redirect(url('rekam_medis', ['no_rawat' => $noRawat, 'action' => $id ? 'form' : 'baru', 'id' => $id ?: null]));
        }

        if ($id > 0) {
            $lama = rekam_medis_row_kunjungan($id, $noRawat);
            if (!$lama) {
                flash_set('danger', 'Catatan rekam medis tidak ditemukan pada kunjungan ini.');
                redirect(url('rekam_medis', ['no_rawat' => $noRawat]));
            }
            $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($data)));
            $params = array_values($data);
            $params[] = auth_user()['username'] ?? '';
            $params[] = $id;
            db_exec(
                "UPDATE rekam_medis SET $set, diubah_pada = NOW(), diubah_oleh = ? WHERE id = ?",
                $params
            );
            audit_catat('rekam_medis', 'save', 'rekam_medis', (string)$id, $kunjungan['nm_pasien']);
            flash_set('success', 'Catatan rekam medis diperbarui.');
        } else {
            $data['no_rawat'] = $noRawat;
            $data['status_lanjut'] = $kunjungan['status_lanjut'] === 'Ranap' ? 'Ranap' : 'Ralan';
            $cols = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            db_exec("INSERT INTO rekam_medis ($cols) VALUES ($marks)", array_values($data));
            audit_catat('rekam_medis', 'create', 'rekam_medis', $noRawat, $kunjungan['nm_pasien']);
            flash_set('success', 'Catatan rekam medis ditambahkan.');
        }
        redirect(url('rekam_medis', ['no_rawat' => $noRawat]));
    }

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $lama = rekam_medis_row_kunjungan($id, $noRawat);
        if (!$lama) {
            flash_set('danger', 'Catatan rekam medis tidak ditemukan pada kunjungan ini.');
        } else {
            db_exec('DELETE FROM rekam_medis WHERE id = ?', [$id]);
            audit_catat('rekam_medis', 'delete', 'rekam_medis', (string)$id, $kunjungan['nm_pasien']);
            flash_set('success', 'Catatan rekam medis dihapus.');
        }
        redirect(url('rekam_medis', ['no_rawat' => $noRawat]));
    }
}

$daftar = rekam_medis_daftar($noRawat);
$dokterList = db_all("SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter");
$petugasList = db_all('SELECT nip, nama FROM petugas ORDER BY nama');

$action = $_GET['action'] ?? 'list';

if ($action === 'form' || $action === 'baru') {
    $pageTitle = 'Form Rekam Medis';
    $id = (int)($_GET['id'] ?? 0);
    $catatan = $id > 0 ? rekam_medis_row_kunjungan($id, $noRawat) : null;
    if ($id > 0 && !$catatan) {
        flash_set('danger', 'Catatan rekam medis tidak ditemukan pada kunjungan ini.');
        redirect(url('rekam_medis', ['no_rawat' => $noRawat]));
    }
    if (!$catatan) {
        $catatan = [
            'id' => 0,
            'tgl_perawatan' => date('Y-m-d'),
            'jam' => date('H:i:s'),
            'kd_dokter' => $kunjungan['kd_dokter'],
            'nip' => '',
            'keluhan' => '', 'pemeriksaan' => '', 'penilaian' => '', 'rencana' => '',
            'tensi' => '', 'nadi' => '', 'respirasi' => '', 'suhu' => '', 'spo2' => '',
            'tinggi' => '', 'berat' => '', 'gcs' => '', 'kesadaran' => '', 'alergi' => '',
        ];
    }
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('rekam_medis')) ?>" class="card card-primary">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
      <input type="hidden" name="id" value="<?= (int)$catatan['id'] ?>" />
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><?= $catatan['id'] ? 'Ubah' : 'Catatan Baru' ?> — <?= e($kunjungan['nm_pasien']) ?></h3>
        <a href="<?= e(url('rekam_medis', ['no_rawat' => $noRawat])) ?>" class="btn btn-sm btn-outline-secondary">Batal</a>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label">Tanggal *</label>
            <input class="form-control" type="date" name="tgl_perawatan" value="<?= e((string)$catatan['tgl_perawatan']) ?>" required />
          </div>
          <div class="col-md-2">
            <label class="form-label">Jam *</label>
            <input class="form-control" type="time" step="1" name="jam" value="<?= e(substr((string)$catatan['jam'], 0, 8)) ?>" required />
          </div>
          <div class="col-md-4">
            <label class="form-label">Dokter</label>
            <select class="form-select" name="kd_dokter">
              <option value="">-- pilih --</option>
              <?php foreach ($dokterList as $d): ?>
                <option value="<?= e($d['kd_dokter']) ?>" <?= $catatan['kd_dokter'] === $d['kd_dokter'] ? 'selected' : '' ?>><?= e($d['nm_dokter']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Petugas / Perawat</label>
            <select class="form-select" name="nip">
              <option value="">-- pilih --</option>
              <?php foreach ($petugasList as $p): ?>
                <option value="<?= e($p['nip']) ?>" <?= $catatan['nip'] === $p['nip'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <h6 class="text-muted text-uppercase small mb-2">Tanda Vital</h6>
        <div class="row g-3 mb-3">
          <?php
          $vital = [
              'tensi' => ['Tekanan Darah', '120/80'],
              'nadi' => ['Nadi (x/mnt)', '88'],
              'respirasi' => ['Respirasi (x/mnt)', '20'],
              'suhu' => ['Suhu (°C)', '36.5'],
              'spo2' => ['SpO2 (%)', '98'],
              'tinggi' => ['Tinggi (cm)', '165'],
              'berat' => ['Berat (kg)', '60'],
              'gcs' => ['GCS', '15'],
          ];
          foreach ($vital as $nama => [$label, $contoh]): ?>
            <div class="col-6 col-md-3 col-xl">
              <label class="form-label"><?= e($label) ?></label>
              <input class="form-control" name="<?= e($nama) ?>" maxlength="12" placeholder="<?= e($contoh) ?>" value="<?= e((string)$catatan[$nama]) ?>" />
            </div>
          <?php endforeach; ?>
          <div class="col-6 col-md-3 col-xl">
            <label class="form-label">Kesadaran</label>
            <select class="form-select" name="kesadaran">
              <option value="">-- pilih --</option>
              <?php foreach (REKAM_KESADARAN as $k): ?>
                <option value="<?= e($k) ?>" <?= $catatan['kesadaran'] === $k ? 'selected' : '' ?>><?= e($k) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3 col-xl">
            <label class="form-label">Alergi</label>
            <input class="form-control" name="alergi" maxlength="120" placeholder="mis. penisilin" value="<?= e((string)$catatan['alergi']) ?>" />
          </div>
        </div>

        <h6 class="text-muted text-uppercase small mb-2">Catatan SOAP</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">S — Subjektif (Keluhan)</label>
            <textarea class="form-control" name="keluhan" rows="4"><?= e((string)$catatan['keluhan']) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">O — Objektif (Pemeriksaan)</label>
            <textarea class="form-control" name="pemeriksaan" rows="4"><?= e((string)$catatan['pemeriksaan']) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">A — Asesmen (Penilaian)</label>
            <textarea class="form-control" name="penilaian" rows="4"><?= e((string)$catatan['penilaian']) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">P — Plan (Rencana)</label>
            <textarea class="form-control" name="rencana" rows="4"><?= e((string)$catatan['rencana']) ?></textarea>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$pageTitle = 'Rekam Medis';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="row align-items-center">
      <div class="col-md-5">
        <strong><?= e($kunjungan['nm_pasien']) ?></strong>
        <span class="badge text-bg-secondary"><?= e($kunjungan['no_rkm_medis']) ?></span><br />
        <small class="text-muted"><?= e($kunjungan['jk']) ?> &middot; <?= e(tgl_indo($kunjungan['tgl_lahir'])) ?></small>
      </div>
      <div class="col-md-3">
        <small class="text-muted">No. Rawat</small><br /><strong><?= e($noRawat) ?></strong>
      </div>
      <div class="col-md-4 text-md-end">
        <?= badge_status((string)($kunjungan['stts'] ?? '')) ?>
        <small class="text-muted d-block"><?= e($kunjungan['status_lanjut']) ?> &middot; <?= e($kunjungan['nm_poli']) ?></small>
      </div>
    </div>
    <div class="mt-2 border-top pt-2 d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-primary" href="<?= e(url('rekam_medis', ['no_rawat' => $noRawat, 'action' => 'baru'])) ?>"><i class="bi bi-plus-lg me-1"></i>Catatan Baru</a>
      <a class="btn btn-sm btn-outline-primary" href="<?= e(url('tindakan', ['no_rawat' => $noRawat])) ?>"><i class="bi bi-bandaid me-1"></i>Tindakan</a>
      <a class="btn btn-sm btn-outline-danger" href="<?= e(url('diagnosa', ['no_rawat' => $noRawat])) ?>"><i class="bi bi-clipboard2-pulse me-1"></i>Diagnosa</a>
      <a class="btn btn-sm btn-outline-success" href="<?= e(url('resep', ['no_rawat' => $noRawat])) ?>"><i class="bi bi-capsule me-1"></i>Resep</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('kasir', ['no_rawat' => $noRawat])) ?>"><i class="bi bi-cash-coin me-1"></i>Kasir</a>
      <a class="btn btn-sm btn-outline-dark" href="<?= e(url('cetak_rekam_medis', ['no_rawat' => $noRawat])) ?>" target="_blank"><i class="bi bi-printer me-1"></i>Cetak</a>
    </div>
  </div>
</div>

<?php if (!$daftar): ?>
  <div class="card"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-journal-medical" style="font-size:2.5rem"></i>
    <p class="mb-0 mt-2">Belum ada catatan rekam medis untuk kunjungan ini.</p>
  </div></div>
<?php endif; ?>

<?php foreach ($daftar as $r): ?>
  <?php $imt = rekam_medis_imt($r); $vital = rekam_medis_vital_ringkas($r); ?>
  <div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h3 class="card-title mb-0">
          <?= e(tgl_indo((string)$r['tgl_perawatan'])) ?> <?= e(substr((string)$r['jam'], 0, 5)) ?>
          <span class="badge text-bg-light text-dark ms-1"><?= e((string)$r['status_lanjut']) ?></span>
        </h3>
        <small class="text-muted">
          <?= e($r['nm_dokter'] ?? '-') ?><?= $r['nm_petugas'] ? ' &middot; ' . e($r['nm_petugas']) : '' ?>
          <?php if ($r['diubah_pada']): ?>
            &middot; diubah <?= e($r['diubah_oleh'] ?? '-') ?>
          <?php endif; ?>
        </small>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-warning" href="<?= e(url('rekam_medis', ['no_rawat' => $noRawat, 'action' => 'form', 'id' => $r['id']])) ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" action="<?= e(url('rekam_medis')) ?>" onsubmit="return confirm('Hapus catatan ini?')">
          <input type="hidden" name="act" value="delete" />
          <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
          <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
    <div class="card-body">
      <?php if ($vital !== '' || $imt !== null || $r['kesadaran'] || $r['alergi']): ?>
        <div class="mb-3 small">
          <?php if ($vital !== ''): ?>
            <span class="badge text-bg-secondary me-1"><i class="bi bi-activity me-1"></i><?= $vital ?></span>
          <?php endif; ?>
          <?php if ($r['gcs']): ?>
            <span class="badge text-bg-secondary me-1">GCS <?= e((string)$r['gcs']) ?></span>
          <?php endif; ?>
          <?php if ($r['kesadaran']): ?>
            <span class="badge text-bg-secondary me-1"><?= e((string)$r['kesadaran']) ?></span>
          <?php endif; ?>
          <?php if ($imt !== null): ?>
            <span class="badge text-bg-info me-1">IMT <?= e((string)$imt) ?> (<?= e(rekam_medis_imt_label($imt)) ?>)</span>
          <?php endif; ?>
          <?php if ($r['alergi']): ?>
            <span class="badge text-bg-danger">Alergi: <?= e((string)$r['alergi']) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="row g-3">
        <?php
        $soap = ['keluhan' => ['S — Subjektif', 'primary'], 'pemeriksaan' => ['O — Objektif', 'info'],
                 'penilaian' => ['A — Asesmen', 'warning'], 'rencana' => ['P — Plan', 'success']];
        foreach ($soap as $kolom => [$label, $warna]):
            if (trim((string)$r[$kolom]) === '') {
                continue;
            } ?>
          <div class="col-md-6">
            <div class="border-start border-4 border-<?= $warna ?> ps-2">
              <div class="text-muted text-uppercase small"><?= e($label) ?></div>
              <div style="white-space:pre-wrap"><?= e((string)$r[$kolom]) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
