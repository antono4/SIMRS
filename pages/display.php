<?php
/**
 * Layar Display Antrian — halaman publik untuk monitor ruang tunggu.
 *
 * Sengaja tidak memakai includes/header.php: halaman ini tampil penuh tanpa
 * sidebar/navbar, dibuka lama di TV, dan tidak boleh memuat aset berat.
 *
 * PRIVASI: halaman ini publik (tanpa login), jadi hanya nomor antrian dan nama
 * poliklinik yang dikirim ke peramban. Nama pasien dan No. RM tidak pernah
 * dimasukkan ke respons HTML maupun JSON.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/antrian.php';

$tgl = $_GET['tgl'] ?? date('Y-m-d');
$format = $_GET['format'] ?? '';

if ($format === 'panggil' || $format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if ($format === 'panggil') {
        // Ringkas: hanya panggilan terakhir, untuk memicu bel + suara di peramban.
        $p = antrian_panggilan_terakhir($tgl);
        echo json_encode([
            'nomor' => $p !== null ? antrian_nomor_tampil((int)$p['nomor'], (string)($p['kd_poli'] ?? '')) : null,
            'poli' => $p['nm_poli'] ?? null,
            'pada' => $p['dipanggil_pada'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return;
    }

    $panggilan = antrian_panggilan_terakhir($tgl);
    $daftar = [];
    foreach (antrian_ringkasan_aktif($tgl) as $p) {
        $daftar[] = [
            'poli' => to_utf8($p['nm_poli']),
            'warna' => poli_warna((string)$p['kd_poli']),
            'awalan' => poli_awalan((string)$p['nm_poli'], (string)$p['kd_poli']),
            'dipanggil' => $p['nomor_dipanggil'] !== null ? antrian_nomor_tampil((int)$p['nomor_dipanggil'], (string)$p['kd_poli']) : null,
            'berikutnya' => array_map(fn($n) => antrian_nomor_tampil((int)$n, (string)$p['kd_poli']), $p['berikutnya']),
            'menunggu' => (int)$p['menunggu'],
        ];
    }
    echo json_encode([
        'tgl' => $tgl,
        'panggilan' => $panggilan !== null ? [
            'nomor' => antrian_nomor_tampil((int)$panggilan['nomor'], (string)($panggilan['kd_poli'] ?? '')),
            'poli' => to_utf8($panggilan['nm_poli']),
            'pada' => $panggilan['dipanggil_pada'],
        ] : null,
        'daftar' => $daftar,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return;
}

$namaRs = setting_nama_rs();
$panggilan = antrian_panggilan_terakhir($tgl);
// Layar TV hanya menampilkan poliklinik yang benar-benar ada antriannya hari ini —
// 22 kartu kosong membuat layar sulit dibaca dari jauh.
$ringkasan = antrian_ringkasan_aktif($tgl);
$adaAntrian = $ringkasan !== [];
$nomorDipanggil = $panggilan !== null ? antrian_nomor_tampil((int)$panggilan['nomor'], (string)($panggilan['kd_poli'] ?? '')) : null;
?>
<!doctype html>
<html lang="id" data-bs-theme="dark">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>Display Antrian | <?= e($namaRs) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('favicon.svg') ?>" />
  <style>
    :root {
      --disp-bg-1: #061024;
      --disp-bg-2: #0b3d91;
      --disp-bg-3: #071a3a;
      --disp-fg: #eaf2ff;
      --disp-muted: #9db4d6;
      --disp-accent: #00d1ff;
      --disp-gold: #ffc233;
      --disp-line: rgba(157, 180, 214, .22);
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      color: var(--disp-fg);
      background: radial-gradient(circle at 20% 0%, var(--disp-bg-2) 0%, var(--disp-bg-3) 45%, var(--disp-bg-1) 100%);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .disp-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 1rem 1.6rem;
      border-bottom: 1px solid var(--disp-line);
      background: rgba(6, 16, 36, .55);
    }
    .disp-brand { display: flex; align-items: center; gap: .8rem; min-width: 0; }
    .disp-brand .logo {
      width: 3rem; height: 3rem; flex: none;
      display: grid; place-items: center;
      border-radius: .85rem;
      background: linear-gradient(135deg, var(--disp-accent), #1565c0);
      font-size: 1.5rem;
    }
    .disp-brand h1 {
      margin: 0; font-size: clamp(1.1rem, 2.1vw, 1.9rem); font-weight: 700;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .disp-brand p { margin: 0; color: var(--disp-muted); font-size: clamp(.7rem, 1vw, .95rem); }
    .disp-clock { text-align: right; flex: none; }
    .disp-clock .jam { font-size: clamp(1.6rem, 3.4vw, 3rem); font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
    .disp-clock .tanggal { color: var(--disp-muted); font-size: clamp(.7rem, 1vw, .95rem); }

    .disp-main { flex: 1; display: grid; grid-template-columns: 1.15fr 1fr; gap: 1rem; padding: 1rem 1.6rem; min-height: 0; }

    .disp-now {
      border-radius: 1.2rem;
      border: 2px solid rgba(0, 209, 255, .35);
      background: linear-gradient(160deg, rgba(0, 209, 255, .16), rgba(11, 61, 145, .28));
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 1rem; text-align: center; min-height: 0;
    }
    .disp-now .label { color: var(--disp-accent); letter-spacing: .28em; text-transform: uppercase; font-size: clamp(.65rem, 1.1vw, 1rem); font-weight: 600; }
    .disp-now .nomor {
      /* Nomor kini memuat kode poliklinik ("INT-007"), jadi ukurannya lebih kecil
         dari sebelumnya supaya tetap muat satu baris di layar TV. */
      font-size: clamp(2.6rem, 10.5vw, 9.5rem); font-weight: 800; line-height: 1;
      font-variant-numeric: tabular-nums;
      color: #fff; text-shadow: 0 0 42px rgba(0, 209, 255, .55);
      margin: .1em 0;
    }
    .disp-now .poli { font-size: clamp(1.1rem, 2.6vw, 2.4rem); font-weight: 600; color: var(--disp-gold); }
    .disp-now .menunggu { color: var(--disp-muted); font-size: clamp(.75rem, 1.1vw, 1.05rem); margin-top: .5rem; }
    .disp-now .kosong { color: var(--disp-muted); font-size: clamp(1rem, 2vw, 1.6rem); }

    .disp-list { display: flex; flex-direction: column; min-height: 0; }
    .disp-list h2 { margin: 0 0 .5rem; font-size: clamp(.75rem, 1.1vw, 1rem); letter-spacing: .18em; text-transform: uppercase; color: var(--disp-muted); }
    .disp-cards { flex: 1; display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: .6rem; overflow: auto; align-content: start; }
    .disp-card {
      border: 1px solid var(--disp-line); border-radius: .8rem;
      background: rgba(255, 255, 255, .045);
      padding: .65rem .85rem;
    }
    .disp-card.aktif { border-color: rgba(255, 194, 51, .6); background: rgba(255, 194, 51, .12); }
    .disp-card .nm { display: flex; align-items: center; gap: .6rem; font-weight: 600; font-size: clamp(.85rem, 1.3vw, 1.15rem); margin-bottom: .25rem; }
    /* Pemisah warna per poliklinik — disalin dari simrs.css karena halaman ini
       sengaja tidak memuat CSS aplikasi. Warna tetap sama dengan modul Antrian. */
    .sk-poli-mark {
      display: inline-flex; align-items: center; justify-content: center;
      width: 2.6rem; height: 2.6rem; flex: none; border-radius: 999px;
      background: var(--sk-poli, #0e7490); color: #fff;
      font-size: 1.15rem; font-weight: 700; line-height: 1;
    }
    .disp-card.aktif .sk-poli-mark { box-shadow: 0 0 0 3px rgba(255, 194, 51, .55); }
    .disp-card .baris { display: flex; align-items: baseline; gap: .55rem; }
    .disp-card .angka { font-size: clamp(1.5rem, 2.8vw, 2.4rem); font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
    .disp-card .angka.kosong { color: var(--disp-muted); font-size: 1.4rem; }
    .disp-card .kecil { color: var(--disp-muted); font-size: clamp(.65rem, .9vw, .85rem); }
    .disp-card .next { color: var(--disp-muted); font-size: clamp(.68rem, .95vw, .9rem); margin-top: .3rem; }
    .disp-card .next b { color: var(--disp-fg); }

    .disp-footer {
      display: flex; align-items: center; justify-content: space-between; gap: 1rem;
      padding: .55rem 1.6rem; border-top: 1px solid var(--disp-line);
      background: rgba(6, 16, 36, .6); font-size: clamp(.68rem, .95vw, .9rem); color: var(--disp-muted);
    }
    .disp-ticker { flex: 1; overflow: hidden; white-space: nowrap; }
    .disp-ticker span { display: inline-block; padding-left: 100%; animation: ticker 28s linear infinite; }
    @keyframes ticker { from { transform: translateX(0); } to { transform: translateX(-100%); } }
    .disp-btn {
      flex: none; cursor: pointer; border: 1px solid var(--disp-line); border-radius: .5rem;
      background: rgba(255, 255, 255, .07); color: var(--disp-fg);
      padding: .35rem .7rem; font: inherit; font-size: .85rem;
    }
    .disp-btn[aria-pressed="true"] { border-color: var(--disp-accent); color: var(--disp-accent); }
    .disp-status { flex: none; }
    @media (max-width: 900px) { .disp-main { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <header class="disp-header">
    <div class="disp-brand">
      <div class="logo"><i class="bi bi-hospital" aria-hidden="true">🏥</i></div>
      <div>
        <h1><?= e($namaRs) ?></h1>
        <p>Layar Antrian Poliklinik</p>
      </div>
    </div>
    <div class="disp-clock">
      <div class="jam" id="jam"><?= e(date('H:i')) ?></div>
      <div class="tanggal" id="tanggal"><?= e(tgl_indo($tgl)) ?></div>
    </div>
  </header>

  <main class="disp-main">
    <section class="disp-now" aria-label="Nomor yang dipanggil">
      <div class="label">Nomor Dipanggil</div>
      <div class="nomor" id="nomorDipanggil" aria-live="assertive" aria-atomic="true"><?= $nomorDipanggil !== null ? e($nomorDipanggil) : '---' ?></div>
      <div class="poli" id="poliDipanggil"><?= e($panggilan['nm_poli'] ?? '') ?></div>
      <div class="menunggu" id="infoMenunggu">
        <?php if ($panggilan === null): ?>
          <span class="kosong">Belum ada nomor yang dipanggil</span>
        <?php elseif ($panggilan['dipanggil_pada'] !== null): ?>
          Dipanggil pukul <?= e(date('H:i', strtotime((string)$panggilan['dipanggil_pada']))) ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="disp-list">
      <h2>Antrian per Poliklinik</h2>
      <div class="disp-cards" id="daftarPoli">
        <?php foreach ($ringkasan as $r): ?>
          <?php $dipanggil = $r['nomor_dipanggil']; ?>
          <article class="disp-card<?= $dipanggil !== null ? ' aktif' : '' ?>" style="--sk-poli: <?= e(poli_warna((string)$r['kd_poli'])) ?>">
            <div class="nm"><span class="sk-poli-mark sk-poli-mark-lg"><?= e(poli_awalan((string)$r['nm_poli'], (string)$r['kd_poli'])) ?></span><?= e($r['nm_poli']) ?></div>
            <div class="baris">
              <?php if ($dipanggil !== null): ?>
                <span class="angka"><?= e(antrian_nomor_tampil((int)$dipanggil, (string)$r['kd_poli'])) ?></span>
              <?php else: ?>
                <span class="angka kosong">&mdash;</span>
              <?php endif; ?>
              <span class="kecil"><?= (int)$r['menunggu'] ?> menunggu</span>
            </div>
            <div class="next">
              Berikutnya:
              <?php if ($r['berikutnya']): ?>
                <?php foreach ($r['berikutnya'] as $i => $n): ?><?= $i > 0 ? ', ' : '' ?><b><?= e(antrian_nomor_tampil((int)$n, (string)$r['kd_poli'])) ?></b><?php endforeach; ?>
              <?php else: ?>
                &mdash;
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$adaAntrian): ?>
          <div class="disp-card"><div class="nm">Belum ada antrian hari ini</div>
            <div class="next">Nomor dibuat otomatis saat registrasi kunjungan.</div></div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="disp-footer">
    <div class="disp-ticker"><span id="ticker">Selamat datang. Mohon menunggu nomor antrian Anda dipanggil. Harap perhatikan nomor dan poliklinik tujuan Anda.</span></div>
    <button class="disp-btn" id="tombolSuara" type="button" aria-pressed="false">🔇 Suara Mati</button>
    <div class="disp-status" id="statusMuat">&nbsp;</div>
  </footer>

<script>
(function () {
  'use strict';
  const URL_DATA = <?= json_encode(url('display', ['tgl' => $tgl, 'format' => 'json']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const URL_PANGGIL = <?= json_encode(url('display', ['tgl' => $tgl, 'format' => 'panggil']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const INTERVAL = 3000;

  const elNomor = document.getElementById('nomorDipanggil');
  const elPoli = document.getElementById('poliDipanggil');
  const elInfo = document.getElementById('infoMenunggu');
  const elDaftar = document.getElementById('daftarPoli');
  const elStatus = document.getElementById('statusMuat');
  const elSuara = document.getElementById('tombolSuara');
  const elJam = document.getElementById('jam');
  const elTanggal = document.getElementById('tanggal');

  let suaraAktif = false;
  let panggilanTerakhir = <?= json_encode($panggilan['dipanggil_pada'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  let audioCtx = null;
  let pertamaKali = true;

  // ---- Jam dinding ----
  function perbaruiJam() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    elJam.textContent = p(d.getHours()) + ':' + p(d.getMinutes());
    const hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][d.getDay()];
    const bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][d.getMonth()];
    elTanggal.textContent = hari + ', ' + d.getDate() + ' ' + bulan + ' ' + d.getFullYear();
  }
  perbaruiJam();
  setInterval(perbaruiJam, 10000);

  // ---- Bel: dua nada pendek lewat WebAudio, tanpa berkas aset ----
  function bunyiBel() {
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      audioCtx = audioCtx || new Ctx();
      if (audioCtx.state === 'suspended') audioCtx.resume();
      [880, 1180].forEach(function (freq, i) {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        const mulai = audioCtx.currentTime + i * 0.28;
        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, mulai);
        gain.gain.exponentialRampToValueAtTime(0.28, mulai + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, mulai + 0.26);
        osc.connect(gain).connect(audioCtx.destination);
        osc.start(mulai);
        osc.stop(mulai + 0.3);
      });
    } catch (e) { /* audio bukan fitur kritis */ }
  }

  // ---- Suara panggilan (Text-to-Speech Bahasa Indonesia) ----
  function ucapkan(nomor, poli) {
    try {
      if (!('speechSynthesis' in window)) return;
      // Nomor tampil sebagai "INT-007" (kode poli + nomor urut). Mesin TTS akan
      // membacanya sebagai satu kata yang aneh, jadi pisahkan: kodenya dibaca
      // sebagai kata, angkanya dieja angka.
      const pisah = String(nomor).match(/^([A-Za-z0-9]+)-(.*)$/);
      const teksNomor = pisah
        ? pisah[1] + ' nomor ' + pisah[2].split('').join(' ')
        : String(nomor).split('').join(' ');
      const teks = 'Nomor antrian ' + teksNomor + ', silakan menuju ' + poli;
      const u = new SpeechSynthesisUtterance(teks);
      u.lang = 'id-ID';
      u.rate = 0.95;
      speechSynthesis.speak(u);
    } catch (e) { /* TTS opsional */ }
  }

  elSuara.addEventListener('click', function () {
    suaraAktif = !suaraAktif;
    elSuara.setAttribute('aria-pressed', suaraAktif ? 'true' : 'false');
    elSuara.textContent = suaraAktif ? '🔊 Suara Aktif' : '🔇 Suara Mati';
    if (suaraAktif) {
      bunyiBel();               // membuka izin audio peramban
      ucapkan('nol', 'ruang tunggu');
    } else if ('speechSynthesis' in window) {
      speechSynthesis.cancel();
    }
  });

  function render(data) {
    const p = data.panggilan;
    if (p) {
      elNomor.textContent = p.nomor;
      elPoli.textContent = p.poli || '';
      elInfo.textContent = 'Dipanggil pukul ' + String(p.pada || '').slice(11, 16);
    } else {
      elNomor.textContent = '---';
      elPoli.textContent = '';
      elInfo.innerHTML = '<span class="kosong">Belum ada nomor yang dipanggil</span>';
    }
    const kartu = (data.daftar || []).map(function (r) {
      const aktif = r.dipanggil ? ' aktif' : '';
      const angka = r.dipanggil ? '<span class="angka">' + r.dipanggil + '</span>'
                                : '<span class="angka kosong">&mdash;</span>';
      const next = (r.berikutnya && r.berikutnya.length)
        ? r.berikutnya.map(function (n) { return '<b>' + n + '</b>'; }).join(', ')
        : '&mdash;';
      // Warna hanya dipakai bila benar-benar heksadesimal, supaya tidak bisa
      // menyelipkan CSS/HTML lewat kolom poliklinik.
      const warna = /^#[0-9a-f]{6}$/i.test(r.warna || '') ? r.warna : '#0e7490';
      return '<article class="disp-card' + aktif + '" style="--sk-poli: ' + warna + '">' +
             '<div class="nm"><span class="sk-poli-mark sk-poli-mark-lg"></span><span class="nm-teks"></span></div>' +
             '<div class="baris">' + angka + '<span class="kecil">' + r.menunggu + ' menunggu</span></div>' +
             '<div class="next">Berikutnya: ' + next + '</div></article>';
    });
    // Hari tanpa antrian: tetap tampilkan keterangan, jangan biarkan layar kosong.
    elDaftar.innerHTML = kartu.length
      ? kartu.join('')
      : '<div class="disp-card"><div class="nm"><span class="nm-teks"></span></div>' +
        '<div class="next">Nomor dibuat otomatis saat registrasi kunjungan.</div></div>';
    // Nama poliklinik dan huruf awalan diisi lewat textContent agar tidak bisa
    // disalahgunakan sebagai HTML.
    const nodes = elDaftar.querySelectorAll('.disp-card .nm-teks');
    const marks = elDaftar.querySelectorAll('.disp-card .sk-poli-mark');
    (data.daftar || []).forEach(function (r, i) {
      if (nodes[i]) nodes[i].textContent = r.poli || '';
      if (marks[i]) marks[i].textContent = r.awalan || '';
    });
    if (!kartu.length && nodes[0]) nodes[0].textContent = 'Belum ada antrian hari ini';
  }

  async function muat() {
    try {
      const res = await fetch(URL_DATA, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      render(await res.json());
      elStatus.textContent = '';
    } catch (e) {
      elStatus.textContent = 'Gagal memuat — mencoba lagi…';
    }
  }

  async function cekPanggilan() {
    try {
      const res = await fetch(URL_PANGGIL, { cache: 'no-store' });
      if (!res.ok) return;
      const p = await res.json();
      if (p.pada && p.pada !== panggilanTerakhir) {
        panggilanTerakhir = p.pada;
        if (!pertamaKali && suaraAktif) { bunyiBel(); ucapkan(p.nomor || '', p.poli || ''); }
      }
    } catch (e) { /* diamkan; polling berikutnya mencoba lagi */ }
    pertamaKali = false;
  }

  muat();
  cekPanggilan();
  setInterval(muat, INTERVAL);
  setInterval(cekPanggilan, INTERVAL);
})();
</script>
</body>
</html>
