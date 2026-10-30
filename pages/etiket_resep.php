<?php
// Cetak etiket obat dari sebuah resep — satu etiket per obat.
declare(strict_types=1);

require_once __DIR__ . '/../includes/kunjungan.php';
require_once __DIR__ . '/../includes/cetak.php';

$noRawat = $_GET['no_rawat'] ?? '';
$noResep = $_GET['resep'] ?? '';
$kunjungan = $noRawat !== '' ? kunjungan_load($noRawat) : null;

if (!$kunjungan || $noResep === '') {
    flash_set('danger', 'Resep tidak ditemukan.');
    redirect(url('registrasi'));
}

$resep = db_row(
    'SELECT ro.*, d.nm_dokter FROM resep_obat ro LEFT JOIN dokter d ON d.kd_dokter = ro.kd_dokter WHERE ro.no_resep = ?',
    [$noResep]
);
$items = db_all(
    'SELECT rd.jml, rd.aturan_pakai, db.nama_brng, db.kode_brng
     FROM resep_dokter rd JOIN databarang db ON db.kode_brng = rd.kode_brng
     WHERE rd.no_resep = ? ORDER BY db.nama_brng',
    [$noResep]
);

if (!$resep || !$items) {
    flash_set('danger', 'Resep belum memiliki obat.');
    redirect(url('resep', ['no_rawat' => $noRawat, 'resep' => $noResep]));
}

[$umur, $sttsUmur] = umur_dari($kunjungan['tgl_lahir']);

audit_catat('resep', 'cetak', 'resep_obat', $noResep, 'Cetak etiket obat');

cetak_mulai('Etiket Resep ' . $noResep, 'A5', url('resep', ['no_rawat' => $noRawat, 'resep' => $noResep]));
?>
    <div class="doc-title">
      <h2>Etiket Obat</h2>
      <div class="sub">Resep <?= e($noResep) ?> &middot; <?= e(tgl_indo((string)$resep['tgl_perawatan'])) ?></div>
    </div>

    <dl class="meta">
      <dt>Nama Pasien</dt><dd><?= e($kunjungan['nm_pasien']) ?> (<?= e($kunjungan['no_rkm_medis']) ?>)</dd>
      <dt>Umur</dt><dd><?= e($umur . ' ' . $sttsUmur) ?></dd>
      <dt>Poliklinik</dt><dd><?= e($kunjungan['nm_poli']) ?></dd>
      <dt>Dokter</dt><dd><?= e($resep['nm_dokter'] ?? $kunjungan['nm_dokter']) ?></dd>
    </dl>

    <?php foreach ($items as $i): ?>
      <div class="box">
        <h4>Etiket</h4>
        <dl class="meta" style="margin-bottom:0">
          <dt>Nama Obat</dt><dd><?= e($i['nama_brng']) ?></dd>
          <dt>Jumlah</dt><dd><?= (float)$i['jml'] ?></dd>
          <dt>Aturan Pakai</dt><dd><?= e($i['aturan_pakai'] ?: '-') ?></dd>
        </dl>
      </div>
    <?php endforeach; ?>

    <div class="sign">
      <div>Pasien<div class="line"><?= e($kunjungan['nm_pasien']) ?></div></div>
      <div>Petugas Farmasi<div class="line"><?= e((string)(auth_user()['nama'] ?? auth_user()['username'] ?? '-')) ?></div></div>
    </div>
<?php cetak_selesai(); ?>
