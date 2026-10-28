<?php
/**
 * Komponen bersama: informasi kunjungan untuk modul klinis (tindakan, diagnosa, resep, kasir).
 */
declare(strict_types=1);

function kunjungan_load(string $noRawat): ?array
{
    return db_row(
        "SELECT rp.*, p.nm_pasien, p.jk, p.tgl_lahir, pl.nm_poli, d.nm_dokter, pj.png_jawab
         FROM reg_periksa rp
         JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
         JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
         JOIN dokter d ON d.kd_dokter = rp.kd_dokter
         LEFT JOIN penjab pj ON pj.kd_pj = rp.kd_pj
         WHERE rp.no_rawat = ?",
        [$noRawat]
    );
}

/**
 * Rincian tagihan satu kunjungan: registrasi, tindakan, obat, dan kamar inap.
 * Dipakai bersama oleh modul Kasir dan halaman cetak billing supaya angkanya
 * tidak pernah berbeda antara layar dan kertas.
 *
 * @return list<array{uraian: string, jml: float|int, harga: float}>
 */
function kunjungan_rincian(array $kunjungan): array
{
    $noRawat = (string)$kunjungan['no_rawat'];
    $baru = ($kunjungan['stts_daftar'] ?? '') === 'Baru';

    $rincian = [[
        'uraian' => 'Biaya Registrasi (' . ($baru ? 'Pasien Baru' : 'Pasien Lama') . ')',
        'jml' => 1,
        'harga' => (float)$kunjungan['biaya_reg'],
    ]];

    $tindakan = db_all(
        "SELECT j.nm_perawatan AS uraian, COUNT(*) AS jml, SUM(r.biaya_rawat) AS harga FROM (
            SELECT kd_jenis_prw, biaya_rawat FROM rawat_jl_dr WHERE no_rawat = ?
            UNION ALL SELECT kd_jenis_prw, biaya_rawat FROM rawat_jl_pr WHERE no_rawat = ?
         ) r JOIN jns_perawatan j ON j.kd_jenis_prw = r.kd_jenis_prw
         GROUP BY j.nm_perawatan ORDER BY j.nm_perawatan",
        [$noRawat, $noRawat]
    );
    foreach ($tindakan as $t) {
        $rincian[] = ['uraian' => 'Tindakan: ' . $t['uraian'], 'jml' => (int)$t['jml'], 'harga' => (float)$t['harga']];
    }

    $obat = db_all(
        'SELECT db.nama_brng, SUM(rd.jml) AS jml, db.ralan FROM resep_dokter rd
         JOIN resep_obat ro ON ro.no_resep = rd.no_resep
         JOIN databarang db ON db.kode_brng = rd.kode_brng
         WHERE ro.no_rawat = ? GROUP BY db.nama_brng, db.ralan ORDER BY db.nama_brng',
        [$noRawat]
    );
    foreach ($obat as $o) {
        $rincian[] = ['uraian' => 'Obat: ' . $o['nama_brng'], 'jml' => (float)$o['jml'], 'harga' => (float)$o['jml'] * (float)$o['ralan']];
    }

    $inap = db_all(
        'SELECT ki.*, k.kelas FROM kamar_inap ki JOIN kamar k ON k.kd_kamar = ki.kd_kamar WHERE ki.no_rawat = ?',
        [$noRawat]
    );
    foreach ($inap as $i) {
        $lama = (float)$i['lama'] > 0 ? (float)$i['lama'] : 1;
        $biaya = (float)$i['ttl_biaya'] > 0 ? (float)$i['ttl_biaya'] : $lama * (float)$i['trf_kamar'];
        $rincian[] = ['uraian' => 'Kamar Inap ' . $i['kd_kamar'] . ' (' . $lama . ' hari)', 'jml' => $lama, 'harga' => $biaya];
    }

    return $rincian;
}

function kunjungan_total(array $rincian): float
{
    return (float)array_sum(array_column($rincian, 'harga'));
}

function kunjungan_card(array $v): void
{
    [$umur, $sttsUmur] = umur_dari($v['tgl_lahir']);
    ?>
    <div class="card card-outline card-primary mb-3">
      <div class="card-body py-2">
        <div class="row align-items-center">
          <div class="col-md-4">
            <strong><?= e($v['nm_pasien']) ?></strong>
            <span class="badge text-bg-secondary"><?= e($v['no_rkm_medis']) ?></span><br />
            <small class="text-muted"><?= e($v['jk']) ?> &middot; <?= $umur ?> <?= $sttsUmur ?> &middot; <?= e(tgl_indo($v['tgl_lahir'])) ?></small>
          </div>
          <div class="col-md-3">
            <small class="text-muted">No. Rawat</small><br /><strong><?= e($v['no_rawat']) ?></strong>
          </div>
          <div class="col-md-3">
            <small class="text-muted sk-poli-nama">
              <?= poli_penanda((string)$v['nm_poli'], (string)$v['kd_poli']) ?>
              <span><?= e($v['nm_poli']) ?> &middot; <?= e($v['nm_dokter']) ?></span>
            </small><br />
            <small class="text-muted"><?= e(tgl_indo($v['tgl_registrasi'])) ?> <?= e(substr((string)$v['jam_reg'], 0, 5)) ?> &middot; <?= e($v['png_jawab'] ?? '-') ?></small>
          </div>
          <div class="col-md-2 text-md-end">
            <?= badge_status($v['stts'] ?? '') ?><br />
            <small class="text-muted"><?= e($v['status_lanjut']) ?> &middot; <?= e($v['status_bayar']) ?></small>
          </div>
        </div>
        <div class="mt-2 border-top pt-2 d-flex flex-wrap gap-2">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('tindakan', ['no_rawat' => $v['no_rawat']])) ?>"><i class="bi bi-bandaid me-1"></i>Tindakan</a>
          <a class="btn btn-sm btn-outline-danger" href="<?= e(url('diagnosa', ['no_rawat' => $v['no_rawat']])) ?>"><i class="bi bi-clipboard2-pulse me-1"></i>Diagnosa</a>
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('resep', ['no_rawat' => $v['no_rawat']])) ?>"><i class="bi bi-capsule me-1"></i>Resep</a>
          <a class="btn btn-sm btn-outline-warning" href="<?= e(url('kasir', ['no_rawat' => $v['no_rawat']])) ?>"><i class="bi bi-cash-coin me-1"></i>Kasir</a>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('billing', ['no_rawat' => $v['no_rawat']])) ?>"><i class="bi bi-printer me-1"></i>Cetak Billing</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('registrasi', ['tgl' => $v['tgl_registrasi']])) ?>"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
      </div>
    </div>
    <?php
}
