<?php
// Cetak ringkasan rekam medis satu kunjungan — halaman mandiri, siap dicetak.
declare(strict_types=1);

require_once __DIR__ . '/../includes/kunjungan.php';
require_once __DIR__ . '/../includes/rekam_medis.php';
require_once __DIR__ . '/../includes/cetak.php';

$noRawat = $_GET['no_rawat'] ?? '';
$kunjungan = $noRawat !== '' ? kunjungan_load($noRawat) : null;

if (!$kunjungan) {
    flash_set('danger', 'Kunjungan tidak ditemukan.');
    redirect(url('registrasi'));
}

$catatan = rekam_medis_daftar($noRawat);
$diagnosa = db_all(
    'SELECT dp.kd_penyakit, p.nm_penyakit, dp.prioritas, dp.status_penyakit
     FROM diagnosa_pasien dp JOIN penyakit p ON p.kd_penyakit = dp.kd_penyakit
     WHERE dp.no_rawat = ? ORDER BY dp.prioritas',
    [$noRawat]
);
[$umur, $sttsUmur] = umur_dari($kunjungan['tgl_lahir']);

audit_catat('rekam_medis', 'cetak', 'rekam_medis', $noRawat, 'Cetak ringkasan rekam medis');

cetak_mulai('Rekam Medis ' . $noRawat, 'A4', url('rekam_medis', ['no_rawat' => $noRawat]));
?>
    <div class="doc-title">
      <h2>Ringkasan Rekam Medis</h2>
      <div class="sub">No. Rawat <?= e($noRawat) ?></div>
    </div>

    <dl class="meta">
      <dt>Nama Pasien</dt><dd><?= e($kunjungan['nm_pasien']) ?> (<?= e($kunjungan['no_rkm_medis']) ?>)</dd>
      <dt>Jenis Kelamin</dt><dd><?= $kunjungan['jk'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></dd>
      <dt>Umur</dt><dd><?= e($umur . ' ' . $sttsUmur) ?></dd>
      <dt>Tanggal Kunjungan</dt><dd><?= e(tgl_indo((string)$kunjungan['tgl_registrasi'])) ?> <?= e(substr((string)$kunjungan['jam_reg'], 0, 5)) ?></dd>
      <dt>Poliklinik / Dokter</dt><dd><?= e($kunjungan['nm_poli']) ?> / <?= e($kunjungan['nm_dokter']) ?></dd>
      <dt>Jenis Layanan</dt><dd><?= e((string)$kunjungan['status_lanjut']) ?> &middot; <?= e((string)($kunjungan['png_jawab'] ?? '-')) ?></dd>
    </dl>

    <?php if ($diagnosa): ?>
      <div class="box">
        <h4>Diagnosa</h4>
        <table>
          <thead><tr><th style="width:60px">Prioritas</th><th>Kode</th><th>Diagnosa</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($diagnosa as $d): ?>
              <tr>
                <td><?= (int)$d['prioritas'] ?></td>
                <td><?= e($d['kd_penyakit']) ?></td>
                <td><?= e($d['nm_penyakit']) ?></td>
                <td><?= e((string)$d['status_penyakit']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!$catatan): ?>
      <p class="muted">Belum ada catatan rekam medis.</p>
    <?php endif; ?>

    <?php foreach ($catatan as $r): ?>
      <?php $imt = rekam_medis_imt($r); $vital = rekam_medis_vital_ringkas($r); ?>
      <div class="box">
        <h4><?= e(tgl_indo((string)$r['tgl_perawatan'])) ?> <?= e(substr((string)$r['jam'], 0, 5)) ?>
          &middot; <?= e((string)$r['status_lanjut']) ?></h4>
        <p class="muted" style="margin:0 0 8px; font-size:11.5px">
          <?= e($r['nm_dokter'] ?? '-') ?><?= $r['nm_petugas'] ? ' &middot; ' . e($r['nm_petugas']) : '' ?>
        </p>
        <?php if ($vital !== '' || $imt !== null || $r['gcs'] || $r['kesadaran'] || $r['alergi']): ?>
          <p style="margin:0 0 8px">
            <?php if ($vital !== ''): ?><strong>Tanda vital:</strong> <?= $vital ?><br /><?php endif; ?>
            <?php if ($r['gcs']): ?><strong>GCS:</strong> <?= e((string)$r['gcs']) ?><?= $r['kesadaran'] ? ' (' . e((string)$r['kesadaran']) . ')' : '' ?><br /><?php endif; ?>
            <?php if ($imt !== null): ?><strong>IMT:</strong> <?= e((string)$imt) ?> (<?= e(rekam_medis_imt_label($imt)) ?>)<br /><?php endif; ?>
            <?php if ($r['alergi']): ?><strong>Alergi:</strong> <?= e((string)$r['alergi']) ?><?php endif; ?>
          </p>
        <?php endif; ?>
        <table>
          <tbody>
            <?php
            $soap = ['keluhan' => 'S — Subjektif', 'pemeriksaan' => 'O — Objektif',
                     'penilaian' => 'A — Asesmen', 'rencana' => 'P — Plan'];
            foreach ($soap as $kolom => $label):
                if (trim((string)$r[$kolom]) === '') {
                    continue;
                } ?>
              <tr>
                <th style="width:130px; background:none; border-bottom:1px solid #eef2f7; text-transform:none; letter-spacing:0"><?= e($label) ?></th>
                <td style="white-space:pre-wrap"><?= e((string)$r[$kolom]) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>

    <div class="sign">
      <div>Pasien<div class="line"><?= e($kunjungan['nm_pasien']) ?></div></div>
      <div>Dokter Pemeriksa<div class="line"><?= e($kunjungan['nm_dokter']) ?></div></div>
    </div>
<?php cetak_selesai(); ?>
