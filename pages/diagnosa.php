<?php
// Modul Diagnosa ICD-10 — diagnosa_pasien & penyakit
declare(strict_types=1);

require __DIR__ . '/../includes/kunjungan.php';

$noRawat = $_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '';
$kunjungan = $noRawat ? kunjungan_load($noRawat) : null;
if (!$kunjungan) {
    flash_set('danger', 'Kunjungan tidak ditemukan.');
    redirect(url('registrasi'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'save') {
        $kdPenyakit = trim($_POST['kd_penyakit'] ?? '');
        $ada = db_row('SELECT kd_penyakit FROM penyakit WHERE kd_penyakit = ?', [$kdPenyakit]);
        if (!$ada) {
            flash_set('danger', "Kode diagnosa $kdPenyakit tidak ditemukan di master ICD-10.");
        } elseif (db_val('SELECT COUNT(*) FROM diagnosa_pasien WHERE no_rawat = ? AND kd_penyakit = ?', [$noRawat, $kdPenyakit]) > 0) {
            flash_set('warning', 'Diagnosa tersebut sudah tercatat untuk kunjungan ini.');
        } else {
            $prioritas = (int)db_val('SELECT COALESCE(MAX(prioritas),0) FROM diagnosa_pasien WHERE no_rawat = ?', [$noRawat]) + 1;
            db_exec(
                'INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas, status_penyakit) VALUES (?,?,?,?,?)',
                [$noRawat, $kdPenyakit, $kunjungan['status_lanjut'], $prioritas, $_POST['status_penyakit'] ?? 'Baru']
            );
            flash_set('success', "Diagnosa $kdPenyakit ditambahkan sebagai diagnosa ke-$prioritas.");
        }
        redirect(url('diagnosa', ['no_rawat' => $noRawat]));
    }

    if ($act === 'delete') {
        db_exec('DELETE FROM diagnosa_pasien WHERE no_rawat = ? AND kd_penyakit = ?', [$noRawat, $_POST['kd_penyakit']]);
        // Rapikan nomor prioritas
        $sisa = db_all('SELECT kd_penyakit FROM diagnosa_pasien WHERE no_rawat = ? ORDER BY prioritas', [$noRawat]);
        foreach ($sisa as $i => $s) {
            db_exec('UPDATE diagnosa_pasien SET prioritas = ? WHERE no_rawat = ? AND kd_penyakit = ?', [$i + 1, $noRawat, $s['kd_penyakit']]);
        }
        flash_set('success', 'Diagnosa dihapus.');
        redirect(url('diagnosa', ['no_rawat' => $noRawat]));
    }
}

$pageTitle = 'Diagnosa ICD-10';
$diagnosa = db_all(
    'SELECT dp.*, p.nm_penyakit FROM diagnosa_pasien dp
     JOIN penyakit p ON p.kd_penyakit = dp.kd_penyakit
     WHERE dp.no_rawat = ? ORDER BY dp.prioritas',
    [$noRawat]
);

$q = trim($_GET['q'] ?? '');
$hasilCari = [];
if ($q !== '') {
    $hasilCari = db_all(
        "SELECT kd_penyakit, nm_penyakit, ciri_ciri FROM penyakit
         WHERE kd_penyakit LIKE ? OR nm_penyakit LIKE ? ORDER BY kd_penyakit LIMIT 15",
        ["%$q%", "%$q%"]
    );
}

require __DIR__ . '/../includes/header.php';
kunjungan_card($kunjungan);
?>
<div class="row">
  <div class="col-lg-5">
    <div class="card card-danger">
      <div class="card-header"><h3 class="card-title">Cari Diagnosa ICD-10</h3></div>
      <div class="card-body">
        <form method="get" action="index.php" class="d-flex mb-3">
          <input type="hidden" name="page" value="diagnosa" />
          <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
          <input class="form-control me-2" name="q" placeholder="Kode / nama diagnosa (mis. A09, demam)" value="<?= e($q) ?>" />
          <button class="btn btn-danger"><i class="bi bi-search"></i></button>
        </form>
        <?php if ($q !== ''): ?>
          <?php foreach ($hasilCari as $h): ?>
            <form method="post" action="<?= e(url('diagnosa')) ?>" class="border rounded p-2 mb-2">
              <input type="hidden" name="act" value="save" />
              <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
              <input type="hidden" name="kd_penyakit" value="<?= e($h['kd_penyakit']) ?>" />
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <span class="badge text-bg-danger"><?= e($h['kd_penyakit']) ?></span>
                  <strong><?= e($h['nm_penyakit']) ?></strong><br />
                  <small class="text-muted"><?= e($h['ciri_ciri']) ?></small>
                </div>
                <div class="d-flex gap-1 align-items-center">
                  <select name="status_penyakit" class="form-select form-select-sm" style="width:90px">
                    <option>Baru</option>
                    <option>Lama</option>
                  </select>
                  <button class="btn btn-sm btn-danger"><i class="bi bi-plus-lg"></i></button>
                </div>
              </div>
            </form>
          <?php endforeach; ?>
          <?php if (!$hasilCari): ?>
            <p class="text-muted">Tidak ditemukan di master ICD-10 (<?= number_format((int)db_val('SELECT COUNT(*) FROM penyakit')) ?> diagnosa).</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted mb-0">Ketik kode atau nama diagnosa untuk mencari di master ICD-10.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Diagnosa Kunjungan Ini (<?= count($diagnosa) ?>)</h3></div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th style="width:70px">Prioritas</th><th>Kode</th><th>Diagnosa</th><th>Status Penyakit</th><th>Jenis</th><th style="width:60px"></th></tr></thead>
          <tbody>
            <?php foreach ($diagnosa as $d): ?>
              <tr>
                <td>
                  <span class="badge <?= (int)$d['prioritas'] === 1 ? 'text-bg-danger' : 'text-bg-secondary' ?>">
                    <?= (int)$d['prioritas'] ?><?= (int)$d['prioritas'] === 1 ? ' (Utama)' : '' ?>
                  </span>
                </td>
                <td><?= e($d['kd_penyakit']) ?></td>
                <td><?= e($d['nm_penyakit']) ?></td>
                <td><?= e($d['status_penyakit'] ?? '-') ?></td>
                <td><?= e($d['status']) ?></td>
                <td>
                  <form method="post" action="<?= e(url('diagnosa')) ?>" onsubmit="return confirm('Hapus diagnosa ini?')">
                    <input type="hidden" name="act" value="delete" />
                    <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
                    <input type="hidden" name="kd_penyakit" value="<?= e($d['kd_penyakit']) ?>" />
                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$diagnosa): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">Belum ada diagnosa.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
