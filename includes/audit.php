<?php
/**
 * Log audit — mencatat siapa mengubah data apa.
 *
 * Tabel `log_aktivitas` tidak ada di skema asli (sik.sql), jadi dibuat di sini
 * dengan pola yang sama seperti tabel `antrian`: CREATE TABLE IF NOT EXISTS
 * membuat instalasi lama ikut naik versi sendiri tanpa menjalankan ulang wizard.
 *
 * Catatan: tabel `tracker` pada skema asli hanya mencatat login, bukan perubahan
 * data, jadi tidak bisa dipakai untuk keperluan audit.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function audit_bootstrap(): void
{
    static $selesai = false;
    if ($selesai) {
        return;
    }
    $selesai = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `log_aktivitas` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `waktu` datetime NOT NULL DEFAULT current_timestamp(),
              `pengguna` varchar(50) NOT NULL,
              `peran` varchar(20) NOT NULL DEFAULT '',
              `modul` varchar(40) NOT NULL,
              `aksi` varchar(20) NOT NULL,
              `tabel` varchar(64) NOT NULL,
              `kunci` varchar(120) DEFAULT NULL,
              `keterangan` varchar(255) DEFAULT NULL,
              `alamat_ip` varchar(45) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_log_waktu` (`waktu`),
              KEY `idx_log_modul` (`modul`,`waktu`),
              KEY `idx_log_pengguna` (`pengguna`,`waktu`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci"
        );
    } catch (Throwable) {
        // Sudah ada atau tidak punya hak DDL — pemanggil tidak boleh gagal
        // hanya karena pencatatan audit.
        return;
    }
}

/**
 * Catat satu perubahan data. Kegagalan pencatatan tidak boleh membatalkan aksi
 * utama pengguna, jadi seluruhnya dibungkus try/catch.
 *
 * @param string $modul  Nama modul, mis. "pasien".
 * @param string $aksi   save|delete|bayar|panggil|...
 * @param string $tabel  Tabel yang diubah.
 * @param string $kunci  Nilai kunci baris (No. RM, no_rawat, ...).
 */
function audit_catat(string $modul, string $aksi, string $tabel, string $kunci = '', string $keterangan = ''): void
{
    try {
        audit_bootstrap();
        $user = function_exists('auth_user') ? auth_user() : null;
        db_exec(
            'INSERT INTO log_aktivitas (pengguna, peran, modul, aksi, `tabel`, kunci, keterangan, alamat_ip)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                (string)($user['username'] ?? 'sistem'),
                (string)($user['role'] ?? ''),
                substr($modul, 0, 40),
                substr($aksi, 0, 20),
                substr($tabel, 0, 64),
                $kunci !== '' ? substr($kunci, 0, 120) : null,
                $keterangan !== '' ? substr($keterangan, 0, 255) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    } catch (Throwable $e) {
        error_log('[SIMRS] audit gagal: ' . $e->getMessage());
    }
}

/**
 * Label aksi untuk tampilan (badge).
 */
function audit_aksi_label(string $aksi): string
{
    return [
        'save' => 'Simpan',
        'create' => 'Tambah',
        'delete' => 'Hapus',
        'bayar' => 'Bayar',
        'panggil' => 'Panggil',
        'masuk' => 'Masuk',
        'pulang' => 'Pulang',
        'cetak' => 'Cetak',
    ][$aksi] ?? ucfirst($aksi);
}

/**
 * Warna badge per aksi.
 */
function audit_aksi_kelas(string $aksi): string
{
    return [
        'delete' => 'text-bg-danger',
        'create' => 'text-bg-success',
        'save' => 'text-bg-primary',
        'bayar' => 'text-bg-info',
    ][$aksi] ?? 'text-bg-secondary';
}
