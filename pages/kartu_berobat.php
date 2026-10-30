<?php
// Cetak kartu berobat pasien — halaman mandiri, siap dicetak (A5).
declare(strict_types=1);

require_once __DIR__ . '/../includes/cetak.php';

$rm = $_GET['rm'] ?? '';
$pasien = $rm !== '' ? db_row('SELECT * FROM pasien WHERE no_rkm_medis = ?', [$rm]) : null;

if (!$pasien) {
    flash_set('danger', 'Pasien tidak ditemukan.');
    redirect(url('pasien'));
}

$kunjungan = db_all(
    "SELECT rp.no_rawat, rp.tgl_registrasi, rp.jam_reg, pl.nm_poli, d.nm_dokter
     FROM reg_periksa rp
     JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
     JOIN dokter d ON d.kd_dokter = rp.kd_dokter
     WHERE rp.no_rkm_medis = ? AND rp.stts <> 'Batal'
     ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC LIMIT 5",
    [$rm]
);
$penjab = $pasien['kd_pj'] !== '-' ? db_val('SELECT png_jawab FROM penjab WHERE kd_pj = ?', [$pasien['kd_pj']]) : null;
[$umur, $sttsUmur] = umur_dari($pasien['tgl_lahir']);

audit_catat('pasien', 'cetak', 'pasien', $rm, 'Cetak kartu berobat');

cetak_mulai('Kartu Berobat ' . $pasien['no_rkm_medis'], 'A5', url('pasien'));
?>
    <div class="doc-title">
      <h2>Kartu Berobat</h2>
      <div class="sub">Kartu ini dibawa setiap kali berobat</div>
    </div>

    <dl class="meta">
      <dt>No. Rekam Medis</dt><dd><?= e($pasien['no_rkm_medis']) ?></dd>
      <dt>Nama Pasien</dt><dd><?= e($pasien['nm_pasien']) ?></dd>
      <dt>Jenis Kelamin</dt><dd><?= $pasien['jk'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></dd>
      <dt>Tempat / Tgl. Lahir</dt>
      <dd><?= e($pasien['tmp_lahir'] ?: '-') ?>, <?= e(tgl_indo($pasien['tgl_lahir'])) ?> (<?= e($umur . ' ' . $sttsUmur) ?>)</dd>
      <dt>NIK</dt><dd><?= e($pasien['no_ktp'] ?: '-') ?></dd>
      <dt>Alamat</dt><dd><?= e($pasien['alamat'] ?: '-') ?></dd>
      <dt>Telepon</dt><dd><?= e($pasien['no_tlp'] ?: '-') ?></dd>
      <dt>Cara Bayar</dt><dd><?= e($penjab ?? '-') ?></dd>
      <dt>No. Peserta</dt><dd><?= e($pasien['no_peserta'] ?: '-') ?></dd>
    </dl>

    <div class="box">
      <h4>5 Kunjungan Terakhir</h4>
      <?php if ($kunjungan): ?>
        <table>
          <thead><tr><th>Tanggal</th><th>Jam</th><th>Poliklinik</th><th>Dokter</th></tr></thead>
          <tbody>
            <?php foreach ($kunjungan as $k): ?>
              <tr>
                <td><?= e(tgl_indo($k['tgl_registrasi'])) ?></td>
                <td><?= e(substr((string)$k['jam_reg'], 0, 5)) ?></td>
                <td><?= e($k['nm_poli']) ?></td>
                <td><?= e($k['nm_dokter']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted mb-0">Belum ada riwayat kunjungan.</p>
      <?php endif; ?>
    </div>

    <p class="muted" style="font-size:11px">
      Kartu diterbitkan <?= e(tgl_indo(date('Y-m-d'))) ?>. Bila kartu hilang, tunjukkan identitas
      berfoto di loket pendaftaran untuk mendapat kartu pengganti.
    </p>

    <div class="sign">
      <div>Pasien<div class="line"><?= e($pasien['nm_pasien']) ?></div></div>
      <div>Petugas Pendaftaran<div class="line"><?= e((string)(auth_user()['nama'] ?? auth_user()['username'] ?? '-')) ?></div></div>
    </div>
<?php cetak_selesai(); ?>
