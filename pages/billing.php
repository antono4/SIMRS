<?php
// Cetak billing / nota kunjungan — halaman mandiri, siap dicetak.
declare(strict_types=1);

require_once __DIR__ . '/../includes/kunjungan.php';

$noRawat = $_GET['no_rawat'] ?? '';
$kunjungan = $noRawat !== '' ? kunjungan_load($noRawat) : null;

if (!$kunjungan) {
    flash_set('danger', 'Kunjungan tidak ditemukan.');
    redirect(url('kasir'));
}

$rincian = kunjungan_rincian($kunjungan);
$total = kunjungan_total($rincian);
$nota = db_row('SELECT * FROM nota_jalan WHERE no_rawat = ?', [$noRawat]);
$sudahBayar = ($kunjungan['status_bayar'] ?? '') === 'Sudah Bayar';
$rs = setting_rs();
[$umur, $sttsUmur] = umur_dari($kunjungan['tgl_lahir']);
$noNota = $nota['no_nota'] ?? '-';
$tanggal = $nota['tanggal'] ?? $kunjungan['tgl_registrasi'];
$jam = $nota['jam'] ?? $kunjungan['jam_reg'];

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Billing <?= e($noNota) ?> &middot; <?= e($kunjungan['nm_pasien']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style>
  @font-face { font-family: 'Inter'; font-weight: 400; font-style: normal; font-display: swap;
    src: url('<?= asset('fonts/inter-latin-400-normal.woff2') ?>') format('woff2'); }
  @font-face { font-family: 'Inter'; font-weight: 600; font-style: normal; font-display: swap;
    src: url('<?= asset('fonts/inter-latin-600-normal.woff2') ?>') format('woff2'); }
  @font-face { font-family: 'Inter'; font-weight: 700; font-style: normal; font-display: swap;
    src: url('<?= asset('fonts/inter-latin-700-normal.woff2') ?>') format('woff2'); }

  :root { --ink: #111827; --muted: #6b7280; --line: #cbd5e1; --soft: #f1f5f9; }
  * { box-sizing: border-box; }
  body {
    margin: 0; padding: 24px 16px 64px; background: #e5e7eb; color: var(--ink);
    font: 13px/1.5 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
  }
  .sheet {
    width: 210mm; max-width: 100%; margin: 0 auto; padding: 14mm 14mm 16mm;
    background: #fff; border-radius: 6px; box-shadow: 0 10px 30px rgba(15, 23, 42, .18);
  }
  .toolbar { width: 210mm; max-width: 100%; margin: 0 auto 14px; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar a, .toolbar button {
    font: inherit; font-weight: 600; padding: 8px 14px; border-radius: 6px; cursor: pointer;
    border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none;
  }
  .toolbar button.primary { background: #0b3d91; border-color: #0b3d91; color: #fff; }

  .kop { display: flex; gap: 14px; align-items: flex-start; border-bottom: 2.5px solid var(--ink); padding-bottom: 10px; }
  .kop .mark {
    width: 46px; height: 46px; flex: none; border-radius: 10px; background: #0b3d91; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700;
  }
  .kop h1 { margin: 0; font-size: 19px; letter-spacing: .2px; }
  .kop p { margin: 2px 0 0; color: var(--muted); font-size: 11.5px; }

  .doc-title { text-align: center; margin: 16px 0 14px; }
  .doc-title h2 { margin: 0; font-size: 15px; letter-spacing: 2px; text-transform: uppercase; }
  .doc-title .sub { color: var(--muted); font-size: 11.5px; margin-top: 3px; }

  .meta { display: flex; flex-wrap: wrap; gap: 4px 28px; margin-bottom: 14px; }
  .meta div { min-width: 200px; }
  .meta dt { color: var(--muted); font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; }
  .meta dd { margin: 0; font-weight: 600; }

  table { width: 100%; border-collapse: collapse; }
  thead th {
    background: var(--soft); border-bottom: 1.5px solid var(--line); text-align: left;
    padding: 7px 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
  }
  tbody td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
  tfoot td { padding: 8px; font-weight: 700; }
  tfoot tr.total td { border-top: 1.5px solid var(--line); border-bottom: 2.5px double var(--ink); font-size: 14px; }
  .num { text-align: right; white-space: nowrap; }
  .empty { text-align: center; color: var(--muted); padding: 14px; }

  .terbilang {
    margin-top: 12px; padding: 9px 12px; border: 1px dashed var(--line); border-radius: 6px;
    background: #fafbfc; font-style: italic;
  }
  .terbilang span { font-style: normal; color: var(--muted); font-size: 10.5px; text-transform: uppercase;
    letter-spacing: .5px; display: block; }

  .stamp {
    display: inline-block; margin-top: 10px; padding: 4px 12px; border-radius: 999px;
    font-size: 11.5px; font-weight: 700; border: 1.5px solid currentColor;
  }
  .stamp.paid { color: #15803d; }
  .stamp.unpaid { color: #b45309; }

  .sign { display: flex; justify-content: space-between; margin-top: 34px; gap: 20px; }
  .sign div { width: 42%; text-align: center; color: var(--muted); font-size: 11.5px; }
  .sign .line { margin-top: 52px; border-top: 1px solid var(--ink); padding-top: 4px; color: var(--ink); font-weight: 600; }
  .foot { margin-top: 22px; padding-top: 9px; border-top: 1px solid var(--line);
    color: var(--muted); font-size: 10.5px; display: flex; justify-content: space-between; gap: 12px; }

  @media print {
    @page { size: A4 portrait; margin: 12mm; }
    body { background: #fff; padding: 0; }
    .toolbar { display: none !important; }
    .sheet { width: auto; max-width: none; margin: 0; padding: 0; border-radius: 0; box-shadow: none; }
    tbody tr { break-inside: avoid; }
    .sign { break-inside: avoid; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <a href="<?= e(url('kasir', ['no_rawat' => $noRawat])) ?>">&larr; Kembali ke Kasir</a>
    <button type="button" class="primary" onclick="window.print()">Cetak</button>
  </div>

  <div class="sheet">
    <div class="kop">
      <div class="mark">+</div>
      <div>
        <h1><?= e($rs['nama_instansi'] ?? setting_nama_rs()) ?></h1>
        <p>
          <?php
          $alamat = array_filter([
              $rs['alamat_instansi'] ?? '',
              $rs['kabupaten'] ?? '',
              $rs['propinsi'] ?? '',
          ], fn($v) => trim((string)$v) !== '');
          echo e(implode(', ', $alamat));
          ?>
          <?php if (!empty($rs['kontak'])): ?> &middot; Telp <?= e($rs['kontak']) ?><?php endif; ?>
          <?php if (!empty($rs['email'])): ?> &middot; <?= e($rs['email']) ?><?php endif; ?>
        </p>
      </div>
    </div>

    <div class="doc-title">
      <h2><?= $sudahBayar ? 'Nota Pembayaran' : 'Rincian Tagihan' ?></h2>
      <div class="sub">Rawat Jalan / <?= e($kunjungan['nm_poli']) ?></div>
    </div>

    <dl class="meta">
      <div><dt>No. Nota</dt><dd><?= e($noNota) ?></dd></div>
      <div><dt>No. Rawat</dt><dd><?= e($noRawat) ?></dd></div>
      <div><dt>Tanggal</dt><dd><?= e(tgl_indo((string)$tanggal)) ?> <?= e(substr((string)$jam, 0, 5)) ?></dd></div>
      <div><dt>No. Rekam Medis</dt><dd><?= e($kunjungan['no_rkm_medis']) ?></dd></div>
      <div><dt>Nama Pasien</dt><dd><?= e($kunjungan['nm_pasien']) ?></dd></div>
      <div><dt>Jenis Kelamin / Umur</dt><dd><?= e($kunjungan['jk']) ?> &middot; <?= (int)$umur ?> <?= e($sttsUmur) ?></dd></div>
      <div><dt>Dokter</dt><dd><?= e($kunjungan['nm_dokter']) ?></dd></div>
      <div><dt>Cara Bayar</dt><dd><?= e($kunjungan['png_jawab'] ?? '-') ?></dd></div>
    </dl>

    <table>
      <thead>
        <tr>
          <th style="width:34px">No</th>
          <th>Uraian</th>
          <th class="num" style="width:60px">Jml</th>
          <th class="num" style="width:110px">Tarif</th>
          <th class="num" style="width:120px">Jumlah</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rincian as $i => $r): ?>
          <?php $jml = (float)$r['jml']; $harga = (float)$r['harga']; $tarif = $jml > 0 ? $harga / $jml : $harga; ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($r['uraian']) ?></td>
            <td class="num"><?= $jml == (int)$jml ? (int)$jml : rtrim(rtrim(number_format($jml, 2, ',', '.'), '0'), ',') ?></td>
            <td class="num"><?= number_format($tarif, 0, ',', '.') ?></td>
            <td class="num"><?= number_format($harga, 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rincian): ?>
          <tr><td colspan="5" class="empty">Tidak ada rincian tagihan.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="total">
          <td colspan="4">Total Tagihan</td>
          <td class="num">Rp <?= number_format($total, 0, ',', '.') ?></td>
        </tr>
      </tfoot>
    </table>

    <div class="terbilang">
      <span>Terbilang</span>
      <?= e(terbilang($total)) ?>
    </div>

    <div>
      <span class="stamp <?= $sudahBayar ? 'paid' : 'unpaid' ?>">
        <?= $sudahBayar ? 'LUNAS' : 'BELUM DIBAYAR' ?>
      </span>
    </div>

    <div class="sign">
      <div>
        Petugas Kasir
        <div class="line"><?= e(auth_user()['username'] ?? '................') ?></div>
      </div>
      <div>
        Pasien / Penanggung Jawab
        <div class="line"><?= e($kunjungan['nm_pasien']) ?></div>
      </div>
    </div>

    <div class="foot">
      <span>Dicetak <?= e(date('d/m/Y H:i')) ?> &middot; <?= e(APP_NAME) ?></span>
      <span>Simpan nota ini sebagai bukti pembayaran yang sah.</span>
    </div>
  </div>

  <?php if (!empty($_GET['auto'])): ?>
    <script>window.addEventListener('load', () => window.print());</script>
  <?php endif; ?>
</body>
</html>
