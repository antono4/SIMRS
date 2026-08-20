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
            <small class="text-muted"><?= e($v['nm_poli']) ?> &middot; <?= e($v['nm_dokter']) ?></small><br />
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
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('registrasi', ['tgl' => $v['tgl_registrasi']])) ?>"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
      </div>
    </div>
    <?php
}
