<?php
// Modul Resep Obat — resep_obat, resep_dokter, databarang
declare(strict_types=1);

require __DIR__ . '/../includes/kunjungan.php';

$noRawat = $_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '';
$kunjungan = $noRawat ? kunjungan_load($noRawat) : null;
if (!$kunjungan) {
    flash_set('danger', 'Kunjungan tidak ditemukan.');
    redirect(url('registrasi'));
}
$statusResep = strtolower($kunjungan['status_lanjut']); // ralan / ranap

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'buat') {
        // No. resep ala sistem: yyyymmdd + urutan 4 digit per tanggal
        $tgl = date('Y-m-d');
        $prefix = date('Ymd');
        $last = db_val('SELECT no_resep FROM resep_obat WHERE no_resep LIKE ? ORDER BY no_resep DESC LIMIT 1', [$prefix . '%']);
        $noResep = $prefix . str_pad((string)((int)substr((string)$last, -4) + 1), 4, '0', STR_PAD_LEFT);
        db_exec(
            'INSERT INTO resep_obat (no_resep, tgl_perawatan, jam, no_rawat, kd_dokter, tgl_peresepan, jam_peresepan, status, tgl_penyerahan, jam_penyerahan)
             VALUES (?,?,CURTIME(),?,?,?,CURTIME(),?,"0000-00-00","00:00:00")',
            [$noResep, $tgl, $noRawat, $kunjungan['kd_dokter'], $tgl, $statusResep]
        );
        flash_set('success', "Resep baru $noResep dibuat. Silakan tambahkan obat.");
        redirect(url('resep', ['no_rawat' => $noRawat, 'resep' => $noResep]));
    }

    if ($act === 'tambah_obat') {
        $noResep = $_POST['no_resep'] ?? '';
        $kodeBrng = $_POST['kode_brng'] ?? '';
        $jml = (float)($_POST['jml'] ?? 0);
        $aturan = trim($_POST['aturan_pakai'] ?? '');
        $obat = db_row("SELECT kode_brng FROM databarang WHERE kode_brng = ? AND status='1'", [$kodeBrng]);
        if (!$obat || $jml <= 0) {
            flash_set('danger', 'Obat tidak valid atau jumlah belum diisi.');
        } else {
            db_exec('INSERT INTO resep_dokter (no_resep, kode_brng, jml, aturan_pakai) VALUES (?,?,?,?)',
                [$noResep, $kodeBrng, $jml, $aturan]);
            flash_set('success', 'Obat ditambahkan ke resep.');
        }
        redirect(url('resep', ['no_rawat' => $noRawat, 'resep' => $noResep]));
    }

    if ($act === 'hapus_obat') {
        db_exec('DELETE FROM resep_dokter WHERE no_resep = ? AND kode_brng = ?', [$_POST['no_resep'], $_POST['kode_brng']]);
        flash_set('success', 'Obat dihapus dari resep.');
        redirect(url('resep', ['no_rawat' => $noRawat, 'resep' => $_POST['no_resep']]));
    }

    if ($act === 'hapus_resep') {
        db_exec('DELETE FROM resep_dokter WHERE no_resep = ?', [$_POST['no_resep']]);
        db_exec('DELETE FROM resep_obat WHERE no_resep = ?', [$_POST['no_resep']]);
        flash_set('success', 'Resep dihapus.');
        redirect(url('resep', ['no_rawat' => $noRawat]));
    }

    if ($act === 'serah') {
        db_exec('UPDATE resep_obat SET tgl_penyerahan = CURDATE(), jam_penyerahan = CURTIME() WHERE no_resep = ?', [$_POST['no_resep']]);
        flash_set('success', 'Obat ditandai sudah diserahkan.');
        redirect(url('resep', ['no_rawat' => $noRawat, 'resep' => $_POST['no_resep']]));
    }
}

$pageTitle = 'Resep Obat';
$resepList = db_all(
    'SELECT r.*, d.nm_dokter FROM resep_obat r JOIN dokter d ON d.kd_dokter = r.kd_dokter
     WHERE r.no_rawat = ? ORDER BY r.no_resep DESC',
    [$noRawat]
);
$resepAktif = $_GET['resep'] ?? ($resepList[0]['no_resep'] ?? null);

$q = trim($_GET['q'] ?? '');
$obatCari = [];
if ($q !== '') {
    $obatCari = db_all(
        "SELECT kode_brng, nama_brng, ralan FROM databarang
         WHERE status='1' AND (nama_brng LIKE ? OR kode_brng LIKE ?) ORDER BY nama_brng LIMIT 12",
        ["%$q%", "%$q%"]
    );
}

require __DIR__ . '/../includes/header.php';
kunjungan_card($kunjungan);
?>
<div class="row">
  <div class="col-lg-4">
    <div class="card card-success mb-3">
      <div class="card-header"><h3 class="card-title">Resep Kunjungan Ini</h3></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('resep')) ?>" class="mb-3">
          <input type="hidden" name="act" value="buat" />
          <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
          <button class="btn btn-success w-100"><i class="bi bi-plus-lg me-1"></i>Buat Resep Baru</button>
        </form>
        <div class="list-group">
          <?php foreach ($resepList as $r): ?>
            <a class="list-group-item list-group-item-action <?= $resepAktif === $r['no_resep'] ? 'active' : '' ?>"
               href="<?= e(url('resep', ['no_rawat' => $noRawat, 'resep' => $r['no_resep']])) ?>">
              <strong><?= e($r['no_resep']) ?></strong>
              <span class="badge <?= $r['tgl_penyerahan'] !== '0000-00-00' ? 'text-bg-success' : 'text-bg-warning' ?> float-end">
                <?= $r['tgl_penyerahan'] !== '0000-00-00' ? 'Diserahkan' : 'Diproses' ?>
              </span><br />
              <small><?= e(tgl_indo($r['tgl_peresepan'])) ?> &middot; <?= e($r['nm_dokter']) ?></small>
            </a>
          <?php endforeach; ?>
          <?php if (!$resepList): ?>
            <p class="text-muted mb-0">Belum ada resep.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <?php if ($resepAktif): ?>
      <?php
      $items = db_all(
          'SELECT rd.*, db.nama_brng, db.ralan FROM resep_dokter rd
           JOIN databarang db ON db.kode_brng = rd.kode_brng
           WHERE rd.no_resep = ? ORDER BY rd.kode_brng',
          [$resepAktif]
      );
      $header = db_row('SELECT * FROM resep_obat WHERE no_resep = ?', [$resepAktif]);
      $total = array_sum(array_map(fn($i) => (float)$i['jml'] * (float)$i['ralan'], $items));
      ?>
      <div class="card mb-3">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Resep <?= e($resepAktif) ?></h3>
            <div class="d-flex gap-2">
              <?php if ($header && $header['tgl_penyerahan'] === '0000-00-00'): ?>
                <form method="post" action="<?= e(url('resep')) ?>">
                  <input type="hidden" name="act" value="serah" />
                  <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
                  <input type="hidden" name="no_resep" value="<?= e($resepAktif) ?>" />
                  <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Serahkan Obat</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= e(url('resep')) ?>" onsubmit="return confirm('Hapus resep ini beserta isinya?')">
                <input type="hidden" name="act" value="hapus_resep" />
                <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
                <input type="hidden" name="no_resep" value="<?= e($resepAktif) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Obat</th><th class="text-end">Jml</th><th>Aturan Pakai</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th><th style="width:60px"></th></tr></thead>
            <tbody>
              <?php foreach ($items as $i): ?>
                <tr>
                  <td><?= e($i['nama_brng']) ?> <small class="text-muted">(<?= e($i['kode_brng']) ?>)</small></td>
                  <td class="text-end"><?= (float)$i['jml'] ?></td>
                  <td><?= e($i['aturan_pakai']) ?></td>
                  <td class="text-end">Rp <?= number_format((float)$i['ralan'], 0, ',', '.') ?></td>
                  <td class="text-end">Rp <?= number_format((float)$i['jml'] * (float)$i['ralan'], 0, ',', '.') ?></td>
                  <td>
                    <form method="post" action="<?= e(url('resep')) ?>" onsubmit="return confirm('Hapus obat ini?')">
                      <input type="hidden" name="act" value="hapus_obat" />
                      <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
                      <input type="hidden" name="no_resep" value="<?= e($resepAktif) ?>" />
                      <input type="hidden" name="kode_brng" value="<?= e($i['kode_brng']) ?>" />
                      <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$items): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Resep masih kosong.</td></tr>
              <?php endif; ?>
            </tbody>
            <?php if ($items): ?>
              <tfoot><tr class="table-secondary"><th colspan="4">Total</th><th class="text-end">Rp <?= number_format($total, 0, ',', '.') ?></th><th></th></tr></tfoot>
            <?php endif; ?>
          </table>
        </div>
      </div>
      <div class="card card-outline card-success">
        <div class="card-header"><h3 class="card-title">Tambah Obat</h3></div>
        <div class="card-body">
          <form method="get" action="index.php" class="d-flex mb-3">
            <input type="hidden" name="page" value="resep" />
            <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
            <input type="hidden" name="resep" value="<?= e($resepAktif) ?>" />
            <input class="form-control me-2" name="q" placeholder="Cari nama obat / kode" value="<?= e($q) ?>" />
            <button class="btn btn-success"><i class="bi bi-search"></i></button>
          </form>
          <?php foreach ($obatCari as $o): ?>
            <form method="post" action="<?= e(url('resep')) ?>" class="row g-2 align-items-center border rounded p-2 mb-2">
              <input type="hidden" name="act" value="tambah_obat" />
              <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
              <input type="hidden" name="no_resep" value="<?= e($resepAktif) ?>" />
              <input type="hidden" name="kode_brng" value="<?= e($o['kode_brng']) ?>" />
              <div class="col-md-5">
                <strong><?= e($o['nama_brng']) ?></strong><br />
                <small class="text-muted"><?= e($o['kode_brng']) ?> &middot; Rp <?= number_format((float)$o['ralan'], 0, ',', '.') ?></small>
              </div>
              <div class="col-md-2"><input class="form-control form-control-sm" type="number" step="0.5" min="0.5" name="jml" placeholder="Jml" required /></div>
              <div class="col-md-4"><input class="form-control form-control-sm" name="aturan_pakai" placeholder="Aturan pakai (3x1)" /></div>
              <div class="col-md-1"><button class="btn btn-sm btn-success w-100"><i class="bi bi-plus-lg"></i></button></div>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-info">Buat resep baru untuk mulai menambahkan obat.</div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
