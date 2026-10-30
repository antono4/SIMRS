<?php
/**
 * Kerangka halaman cetak — kop surat, tombol Cetak, dan gaya @media print.
 *
 * Dipakai halaman cetak mandiri (kartu berobat, etiket resep, tiket antrian,
 * ringkasan rekam medis) supaya gaya dan kopnya seragam. Halaman billing punya
 * gayanya sendiri karena tata letaknya sudah ada lebih dulu.
 *
 * Halaman cetak tidak memakai includes/header.php: tampilannya lembar kertas,
 * bukan panel AdminLTE.
 */
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/**
 * Buka lembar cetak. Panggil cetak_selesai() di akhir halaman.
 *
 * @param string $judul      Judul dokumen (jadi <title>).
 * @param string $ukuran     Ukuran kertas: A4, A5, atau A6.
 * @param string $kembaliUrl Alamat tombol "Kembali" (kosong = tombol disembunyikan).
 * @param string $aksiKanan  HTML tambahan di toolbar (mis. tombol kedua).
 */
function cetak_mulai(string $judul, string $ukuran = 'A4', string $kembaliUrl = '', string $aksiKanan = ''): void
{
    $ukuran = in_array($ukuran, ['A4', 'A5', 'A6'], true) ? $ukuran : 'A4';
    $rs = setting_rs();
    $namaRs = $rs['nama_instansi'] ?? setting_nama_rs();
    $alamat = array_filter([
        $rs['alamat_instansi'] ?? '',
        $rs['kabupaten'] ?? '',
        $rs['propinsi'] ?? '',
    ], static fn($v) => trim((string)$v) !== '');
    $kontak = array_filter([
        $rs['kontak'] ?? '',
        $rs['email'] ?? '',
    ], static fn($v) => trim((string)$v) !== '');
    $kontakTeks = implode(' · ', $kontak);
    $inisial = nama_inisial((string)$namaRs) ?: 'RS';
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title><?= e($judul) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style>
  @font-face { font-family: 'Inter'; font-weight: 400; font-style: normal; font-display: swap;
    src: url('<?= asset('fonts/inter-latin-400-normal.woff2') ?>') format('woff2'); }
  @font-face { font-family: 'Inter'; font-weight: 600; font-style: normal; font-display: swap;
    src: url('<?= asset('fonts/inter-latin-600-normal.woff2') ?>') format('woff2'); }
  @font-face { font-family: 'Inter'; font-weight: 700; font-style: normal; font-display: swap;
    src: url('<?= asset('fonts/inter-latin-700-normal.woff2') ?>') format('woff2'); }

  :root { --ink: #111827; --muted: #6b7280; --line: #cbd5e1; --soft: #f1f5f9; --brand: #0b3d91; }
  * { box-sizing: border-box; }
  body { margin: 0; padding: 24px 16px 64px; background: #e5e7eb; color: var(--ink);
    font: 13px/1.5 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
  .sheet { width: 210mm; max-width: 100%; margin: 0 auto; padding: 14mm; background: #fff;
    border-radius: 6px; box-shadow: 0 10px 30px rgba(15,23,42,.18); }
  .sheet.a5 { width: 148mm; }
  .sheet.a6 { width: 105mm; }
  .toolbar { width: 210mm; max-width: 100%; margin: 0 auto 14px; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar a, .toolbar button { font: inherit; font-weight: 600; padding: 8px 14px; border-radius: 6px;
    cursor: pointer; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; }
  .toolbar button.primary { background: var(--brand); border-color: var(--brand); color: #fff; }

  .kop { display: flex; gap: 14px; align-items: flex-start; border-bottom: 2.5px solid var(--ink); padding-bottom: 10px; }
  .kop .mark { width: 44px; height: 44px; flex: none; border-radius: 10px; background: var(--brand); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 21px; font-weight: 700; }
  .kop h1 { margin: 0; font-size: 18px; }
  .kop p { margin: 2px 0 0; color: var(--muted); font-size: 11.5px; }
  .doc-title { text-align: center; margin: 16px 0 14px; }
  .doc-title h2 { margin: 0; font-size: 15px; letter-spacing: 2px; text-transform: uppercase; }
  .doc-title .sub { color: var(--muted); font-size: 11.5px; margin-top: 3px; }

  table { width: 100%; border-collapse: collapse; }
  thead th { background: var(--soft); border-bottom: 1.5px solid var(--line); text-align: left;
    padding: 7px 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
  tbody td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
  .num { text-align: right; white-space: nowrap; }
  dl.meta { display: grid; grid-template-columns: auto 1fr; gap: 3px 14px; margin: 0 0 14px; }
  dl.meta dt { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .4px; padding-top: 2px; }
  dl.meta dd { margin: 0; font-weight: 600; }
  .box { border: 1px solid var(--line); border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; }
  .box h4 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); }
  .sign { display: flex; justify-content: space-between; margin-top: 34px; gap: 20px; }
  .sign div { width: 42%; text-align: center; color: var(--muted); font-size: 11.5px; }
  .sign .line { margin-top: 52px; border-top: 1px solid var(--ink); padding-top: 4px; color: var(--ink); font-weight: 600; }
  .muted { color: var(--muted); }
  .center { text-align: center; }

  @media print {
    @page { size: <?= $ukuran ?> portrait; margin: 10mm; }
    body { background: #fff; padding: 0; }
    .toolbar { display: none !important; }
    .sheet, .sheet.a5, .sheet.a6 { width: auto; max-width: none; margin: 0; padding: 0; border-radius: 0; box-shadow: none; }
    tbody tr { break-inside: avoid; }
    .sign, .box { break-inside: avoid; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <?php if ($kembaliUrl !== ''): ?>
      <a href="<?= e($kembaliUrl) ?>">&larr; Kembali</a>
    <?php endif; ?>
    <?= $aksiKanan ?>
    <button type="button" class="primary" onclick="window.print()">Cetak</button>
  </div>
  <div class="sheet <?= strtolower($ukuran) ?>">
    <div class="kop">
      <div class="mark"><?= e($inisial) ?></div>
      <div>
        <h1><?= e((string)$namaRs) ?></h1>
        <p><?= e(implode(', ', $alamat)) ?><?= $kontakTeks !== '' ? ' &middot; ' . e($kontakTeks) : '' ?></p>
      </div>
    </div>
    <?php
}

function cetak_selesai(): void
{
    ?>
  </div>
</body>
</html>
    <?php
}
