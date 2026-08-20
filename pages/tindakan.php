<?php
// Modul Tindakan/Perawatan Rawat Jalan — rawat_jl_dr & rawat_jl_pr
declare(strict_types=1);

require __DIR__ . '/../includes/kunjungan.php';

$noRawat = $_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '';
$kunjungan = $noRawat ? kunjungan_load($noRawat) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$kunjungan) {
        flash_set('danger', 'Kunjungan tidak ditemukan.');
        redirect(url('registrasi'));
    }
    $act = $_POST['act'] ?? '';

    if ($act === 'save') {
        $jenis = $_POST['jenis'] ?? 'dr';
        $prw = db_row("SELECT * FROM jns_perawatan WHERE kd_jenis_prw = ? AND status='1'", [$_POST['kd_jenis_prw'] ?? '']);
        if (!$prw) {
            flash_set('danger', 'Jenis perawatan tidak valid.');
            redirect(url('tindakan', ['no_rawat' => $noRawat]));
        }
        $tgl = $_POST['tgl_perawatan'] ?: date('Y-m-d');
        $jam = $_POST['jam_rawat'] ?: date('H:i:s');

        if ($jenis === 'dr') {
            $pelaksana = $_POST['kd_dokter'] ?? '';
            db_exec(
                'INSERT INTO rawat_jl_dr (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, material, bhp, tarif_tindakandr, kso, menejemen, biaya_rawat, stts_bayar)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$noRawat, $prw['kd_jenis_prw'], $pelaksana, $tgl, $jam,
                 (float)$prw['material'], (float)$prw['bhp'], (float)$prw['tarif_tindakandr'],
                 (float)$prw['kso'], (float)$prw['menejemen'], (float)$prw['total_byrdr'], 'Belum']
            );
        } else {
            $pelaksana = $_POST['nip'] ?? '';
            db_exec(
                'INSERT INTO rawat_jl_pr (no_rawat, kd_jenis_prw, nip, tgl_perawatan, jam_rawat, material, bhp, tarif_tindakanpr, kso, menejemen, biaya_rawat, stts_bayar)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$noRawat, $prw['kd_jenis_prw'], $pelaksana, $tgl, $jam,
                 (float)$prw['material'], (float)$prw['bhp'], (float)$prw['tarif_tindakanpr'],
                 (float)$prw['kso'], (float)$prw['menejemen'], (float)$prw['total_byrpr'], 'Belum']
            );
        }
        flash_set('success', 'Tindakan "' . $prw['nm_perawatan'] . '" ditambahkan.');
        redirect(url('tindakan', ['no_rawat' => $noRawat]));
    }

    if ($act === 'delete') {
        $jenis = $_POST['jenis'] ?? 'dr';
        if ($jenis === 'dr') {
            db_exec('DELETE FROM rawat_jl_dr WHERE no_rawat=? AND kd_jenis_prw=? AND kd_dokter=? AND tgl_perawatan=? AND jam_rawat=?',
                [$noRawat, $_POST['kd_jenis_prw'], $_POST['pelaksana'], $_POST['tgl'], $_POST['jam']]);
        } else {
            db_exec('DELETE FROM rawat_jl_pr WHERE no_rawat=? AND kd_jenis_prw=? AND nip=? AND tgl_perawatan=? AND jam_rawat=?',
                [$noRawat, $_POST['kd_jenis_prw'], $_POST['pelaksana'], $_POST['tgl'], $_POST['jam']]);
        }
        flash_set('success', 'Tindakan dihapus.');
        redirect(url('tindakan', ['no_rawat' => $noRawat]));
    }
}

if (!$kunjungan) {
    flash_set('danger', 'Kunjungan tidak ditemukan.');
    redirect(url('registrasi'));
}

$pageTitle = 'Tindakan / Perawatan';
$tindakanDr = db_all(
    'SELECT r.*, j.nm_perawatan, d.nm_dokter FROM rawat_jl_dr r
     JOIN jns_perawatan j ON j.kd_jenis_prw = r.kd_jenis_prw
     JOIN dokter d ON d.kd_dokter = r.kd_dokter
     WHERE r.no_rawat = ? ORDER BY r.tgl_perawatan, r.jam_rawat',
    [$noRawat]
);
$tindakanPr = db_all(
    'SELECT r.*, j.nm_perawatan, pt.nama AS nm_petugas FROM rawat_jl_pr r
     JOIN jns_perawatan j ON j.kd_jenis_prw = r.kd_jenis_prw
     JOIN petugas pt ON pt.nip = r.nip
     WHERE r.no_rawat = ? ORDER BY r.tgl_perawatan, r.jam_rawat',
    [$noRawat]
);
// Tarif mengikuti poli & cara bayar kunjungan; bila tak ada yang cocok, tampilkan semua tarif aktif
$sqlPerawatan = "SELECT j.kd_jenis_prw, j.nm_perawatan, j.total_byrdr, j.total_byrpr, k.nm_kategori
     FROM jns_perawatan j LEFT JOIN kategori_perawatan k ON k.kd_kategori = j.kd_kategori
     WHERE j.status='1' %s ORDER BY k.nm_kategori, j.nm_perawatan";
$perawatan = db_all(
    sprintf($sqlPerawatan, 'AND (j.kd_poli = ? OR j.kd_poli = "-") AND (j.kd_pj = ? OR j.kd_pj = "-")'),
    [$kunjungan['kd_poli'], $kunjungan['kd_pj']]
);
if (!$perawatan) {
    $perawatan = db_all(sprintf($sqlPerawatan, ''));
}
$dokterList = db_all("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter");
$petugasList = db_all("SELECT nip, nama FROM petugas WHERE status='1' ORDER BY nama");

require __DIR__ . '/../includes/header.php';
kunjungan_card($kunjungan);

$renderTabel = function (array $rows, string $jenis) use ($noRawat): void {
    $total = 0.0;
    ?>
    <table class="table table-striped table-hover mb-0">
      <thead><tr><th>Tanggal/Jam</th><th>Perawatan</th><th>Pelaksana</th><th class="text-end">Biaya</th><th>Bayar</th><th style="width:60px"></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $total += (float)$r['biaya_rawat']; ?>
          <tr>
            <td><?= e(tgl_indo($r['tgl_perawatan'])) ?> <?= e(substr((string)$r['jam_rawat'], 0, 5)) ?></td>
            <td><?= e($r['nm_perawatan']) ?></td>
            <td><?= e($jenis === 'dr' ? $r['nm_dokter'] : $r['nm_petugas']) ?></td>
            <td class="text-end">Rp <?= number_format((float)$r['biaya_rawat'], 0, ',', '.') ?></td>
            <td><?= $r['stts_bayar'] === 'Sudah' ? '<span class="badge text-bg-success">Sudah</span>' : '<span class="badge text-bg-warning">Belum</span>' ?></td>
            <td>
              <form method="post" action="<?= e(url('tindakan')) ?>" onsubmit="return confirm('Hapus tindakan ini?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="jenis" value="<?= $jenis ?>" />
                <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
                <input type="hidden" name="kd_jenis_prw" value="<?= e($r['kd_jenis_prw']) ?>" />
                <input type="hidden" name="pelaksana" value="<?= e($jenis === 'dr' ? $r['kd_dokter'] : $r['nip']) ?>" />
                <input type="hidden" name="tgl" value="<?= e($r['tgl_perawatan']) ?>" />
                <input type="hidden" name="jam" value="<?= e($r['jam_rawat']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">Belum ada tindakan.</td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($rows): ?>
        <tfoot><tr class="table-secondary"><th colspan="3">Total</th><th class="text-end">Rp <?= number_format($total, 0, ',', '.') ?></th><th colspan="2"></th></tr></tfoot>
      <?php endif; ?>
    </table>
    <?php
};
?>
<div class="row">
  <div class="col-lg-4">
    <form method="post" action="<?= e(url('tindakan')) ?>" class="card card-primary">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="no_rawat" value="<?= e($noRawat) ?>" />
      <div class="card-header"><h3 class="card-title">Tambah Tindakan</h3></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Jenis Pelaksana</label>
          <select class="form-select" name="jenis" id="jenisPelaksana">
            <option value="dr">Dokter</option>
            <option value="pr">Perawat/Petugas</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Jenis Perawatan</label>
          <select class="form-select" name="kd_jenis_prw" required>
            <?php $kat = null; ?>
            <?php foreach ($perawatan as $p): ?>
              <?php if ($p['nm_kategori'] !== $kat): $kat = $p['nm_kategori']; ?>
                <?php if ($kat !== null && $p !== $perawatan[0]): ?></optgroup><?php endif; ?>
                <optgroup label="<?= e($kat ?? 'Lainnya') ?>">
              <?php endif; ?>
              <option value="<?= e($p['kd_jenis_prw']) ?>">
                <?= e($p['nm_perawatan']) ?> (dr: Rp <?= number_format((float)$p['total_byrdr'], 0, ',', '.') ?> / pr: Rp <?= number_format((float)$p['total_byrpr'], 0, ',', '.') ?>)
              </option>
            <?php endforeach; ?>
            <?php if ($perawatan): ?></optgroup><?php endif; ?>
          </select>
        </div>
        <div class="mb-3" id="wrapDokter">
          <label class="form-label">Dokter</label>
          <select class="form-select" name="kd_dokter">
            <?php foreach ($dokterList as $d): ?>
              <option value="<?= e($d['kd_dokter']) ?>" <?= $kunjungan['kd_dokter'] === $d['kd_dokter'] ? 'selected' : '' ?>><?= e($d['nm_dokter']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3 d-none" id="wrapPetugas">
          <label class="form-label">Petugas</label>
          <select class="form-select" name="nip">
            <?php foreach ($petugasList as $pt): ?>
              <option value="<?= e($pt['nip']) ?>"><?= e($pt['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Tanggal</label>
            <input class="form-control" type="date" name="tgl_perawatan" value="<?= e(date('Y-m-d')) ?>" />
          </div>
          <div class="col-6">
            <label class="form-label">Jam</label>
            <input class="form-control" type="time" name="jam_rawat" value="<?= e(date('H:i')) ?>" />
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
      </div>
    </form>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Tindakan Dokter (<?= count($tindakanDr) ?>)</h3></div>
      <div class="card-body p-0 table-responsive"><?php $renderTabel($tindakanDr, 'dr'); ?></div>
    </div>
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Tindakan Perawat (<?= count($tindakanPr) ?>)</h3></div>
      <div class="card-body p-0 table-responsive"><?php $renderTabel($tindakanPr, 'pr'); ?></div>
    </div>
  </div>
</div>
<?php
$pageScripts = '<script>
document.getElementById("jenisPelaksana").addEventListener("change", function () {
  document.getElementById("wrapDokter").classList.toggle("d-none", this.value !== "dr");
  document.getElementById("wrapPetugas").classList.toggle("d-none", this.value !== "pr");
});
</script>';
require __DIR__ . '/../includes/footer.php';
