<?php
// Cetak tiket antrian poliklinik — untuk printer termal (lebar 80 mm).
declare(strict_types=1);

require_once __DIR__ . '/../includes/antrian.php';

$id = (int)($_GET['id'] ?? 0);
$a = $id > 0 ? antrian_row($id) : null;

if (!$a) {
    flash_set('danger', 'Nomor antrian tidak ditemukan.');
    redirect(url('antrian'));
}

$poli = db_row('SELECT nm_poli FROM poliklinik WHERE kd_poli = ?', [$a['kd_poli']]);
$nomor = antrian_nomor_tampil((int)$a['nomor'], (string)$a['kd_poli']);
$rs = setting_rs();
$namaRs = $rs['nama_instansi'] ?? setting_nama_rs();

audit_catat('antrian', 'cetak', 'antrian', (string)$a['id'], $nomor);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Tiket <?= e($nomor) ?></title>
<style>
  @page { size: 80mm auto; margin: 4mm; }
  body { margin: 0; padding: 12px; background: #e5e7eb;
    font: 12px/1.45 'Courier New', ui-monospace, monospace; color: #000; }
  .toolbar { max-width: 80mm; margin: 0 auto 12px; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar a, .toolbar button { font: inherit; font-weight: 600; padding: 7px 12px; border-radius: 6px;
    cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #111; text-decoration: none; }
  .toolbar button.primary { background: #0b3d91; border-color: #0b3d91; color: #fff; }
  .tiket { width: 80mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 6mm 4mm; text-align: center; }
  .tiket h1 { font-size: 13px; margin: 0 0 2px; }
  .tiket .sub { font-size: 10px; }
  .tiket hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
  .label { font-size: 10px; letter-spacing: 1px; }
  .nomor { font-size: 44px; font-weight: 700; line-height: 1.1; letter-spacing: 2px; }
  .poli { font-size: 13px; font-weight: 700; }
  .kecil { font-size: 10px; }
  @media print {
    body { background: #fff; padding: 0; }
    .toolbar { display: none !important; }
    .tiket { width: auto; padding: 0; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <a href="<?= e(url('antrian')) ?>">&larr; Kembali</a>
    <button type="button" class="primary" onclick="window.print()">Cetak</button>
  </div>
  <div class="tiket">
    <h1><?= e((string)$namaRs) ?></h1>
    <div class="sub">Tiket Antrian Poliklinik</div>
    <hr />
    <div class="label">NOMOR ANTRIAN</div>
    <div class="nomor"><?= e($nomor) ?></div>
    <div class="poli"><?= e($poli['nm_poli'] ?? $a['kd_poli']) ?></div>
    <hr />
    <div class="kecil">Tanggal: <?= e(tgl_indo((string)$a['tgl'])) ?></div>
    <div class="kecil">Jam ambil: <?= e(substr((string)$a['dibuat_pada'], 11, 5)) ?></div>
    <hr />
    <div class="kecil">
      Mohon menunggu nomor dipanggil.<br />
      Simpan tiket ini sampai dilayani.
    </div>
  </div>
</body>
</html>
