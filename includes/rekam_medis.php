<?php
/**
 * Rekam medis rawat jalan / rawat inap — catatan SOAP + tanda vital.
 *
 * Tabel `rekam_medis` tidak ada di skema asli (sik.sql) yang dipakai aplikasi
 * ini, jadi dibuat di sini dengan pola yang sama seperti tabel `antrian`:
 * CREATE TABLE IF NOT EXISTS membuat instalasi lama ikut naik versi sendiri.
 *
 * Pada SIMRS asli catatan ini tersebar di `pemeriksaan_ralan`/`pemeriksaan_ranap`;
 * kedua tabel itu tidak ada di sini, jadi dipakai satu tabel dengan penanda
 * `status_lanjut` (Ralan/Ranap) agar rawat jalan dan rawat inap berbagi formulir.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const REKAM_KESADARAN = ['Compos Mentis', 'Somnolence', 'Sopor', 'Soporocoma', 'Coma'];

function rekam_medis_bootstrap(): void
{
    static $selesai = false;
    if ($selesai) {
        return;
    }
    $selesai = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `rekam_medis` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `no_rawat` varchar(17) NOT NULL,
              `status_lanjut` enum('Ralan','Ranap') NOT NULL DEFAULT 'Ralan',
              `tgl_perawatan` date NOT NULL,
              `jam` time NOT NULL,
              `kd_dokter` varchar(20) DEFAULT NULL,
              `nip` varchar(20) DEFAULT NULL,
              `keluhan` text DEFAULT NULL,
              `pemeriksaan` text DEFAULT NULL,
              `penilaian` text DEFAULT NULL,
              `rencana` text DEFAULT NULL,
              `tensi` varchar(12) DEFAULT NULL,
              `nadi` varchar(12) DEFAULT NULL,
              `respirasi` varchar(12) DEFAULT NULL,
              `suhu` varchar(12) DEFAULT NULL,
              `spo2` varchar(12) DEFAULT NULL,
              `tinggi` varchar(12) DEFAULT NULL,
              `berat` varchar(12) DEFAULT NULL,
              `gcs` varchar(12) DEFAULT NULL,
              `kesadaran` varchar(20) DEFAULT NULL,
              `alergi` varchar(120) DEFAULT NULL,
              `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
              `diubah_pada` datetime DEFAULT NULL,
              `diubah_oleh` varchar(50) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_rekam_rawat` (`no_rawat`,`tgl_perawatan`,`jam`),
              KEY `idx_rekam_tgl` (`tgl_perawatan`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci"
        );
    } catch (Throwable) {
        // Sudah ada atau tanpa hak DDL — biarkan query berikutnya melaporkan.
        return;
    }
    // Foreign key opsional: kegagalan diabaikan agar tabel tetap berguna.
    try {
        $ada = (int)db_val(
            "SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE constraint_schema = ? AND table_name = 'rekam_medis' AND constraint_type = 'FOREIGN KEY'",
            [DB_NAME]
        );
        if ($ada === 0) {
            db()->exec(
                'ALTER TABLE `rekam_medis` ADD CONSTRAINT `rekam_medis_ibfk_1`
                 FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`)
                 ON DELETE CASCADE ON UPDATE CASCADE'
            );
        }
    } catch (Throwable) {
        // tanpa FK pun modul tetap berjalan
    }
}

/**
 * Catatan rekam medis satu kunjungan, terbaru lebih dulu.
 *
 * @return list<array<string,mixed>>
 */
function rekam_medis_daftar(string $noRawat): array
{
    rekam_medis_bootstrap();
    return db_all(
        'SELECT rm.*, d.nm_dokter, pt.nama AS nm_petugas
         FROM rekam_medis rm
         LEFT JOIN dokter d ON d.kd_dokter = rm.kd_dokter
         LEFT JOIN petugas pt ON pt.nip = rm.nip
         WHERE rm.no_rawat = ?
         ORDER BY rm.tgl_perawatan DESC, rm.jam DESC, rm.id DESC',
        [$noRawat]
    );
}

function rekam_medis_row(int $id): ?array
{
    rekam_medis_bootstrap();
    return db_row('SELECT * FROM rekam_medis WHERE id = ?', [$id]);
}

/**
 * Ambil satu catatan milik satu kunjungan; menolak id yang bukan miliknya.
 */
function rekam_medis_row_kunjungan(int $id, string $noRawat): ?array
{
    rekam_medis_bootstrap();
    return db_row('SELECT * FROM rekam_medis WHERE id = ? AND no_rawat = ?', [$id, $noRawat]);
}

/**
 * Ringkasan angka vital terbaru untuk ditampilkan di kartu kunjungan.
 *
 * @return array<string,mixed>|null
 */
function rekam_medis_terbaru(string $noRawat): ?array
{
    rekam_medis_bootstrap();
    return db_row(
        'SELECT * FROM rekam_medis WHERE no_rawat = ? ORDER BY tgl_perawatan DESC, jam DESC, id DESC LIMIT 1',
        [$noRawat]
    );
}

/**
 * Label ringkas tanda vital, mis. "TD 120/80 · N 88 · S 36.5°C".
 */
function rekam_medis_vital_ringkas(array $r): string
{
    $bagian = [];
    if (trim((string)($r['tensi'] ?? '')) !== '') {
        $bagian[] = 'TD ' . $r['tensi'];
    }
    if (trim((string)($r['nadi'] ?? '')) !== '') {
        $bagian[] = 'N ' . $r['nadi'];
    }
    if (trim((string)($r['respirasi'] ?? '')) !== '') {
        $bagian[] = 'RR ' . $r['respirasi'];
    }
    if (trim((string)($r['suhu'] ?? '')) !== '') {
        $bagian[] = 'S ' . $r['suhu'] . "\u{00B0}C";
    }
    if (trim((string)($r['spo2'] ?? '')) !== '') {
        $bagian[] = 'SpO2 ' . $r['spo2'] . '%';
    }
    return implode(' &middot; ', $bagian);
}

/**
 * Indeks massa tubuh (kg/m²) dari kolom tinggi (cm) dan berat (kg), bila keduanya
 * berupa angka. Mengembalikan null bila tidak bisa dihitung.
 */
function rekam_medis_imt(array $r): ?float
{
    $tinggi = (float)str_replace(',', '.', (string)($r['tinggi'] ?? ''));
    $berat = (float)str_replace(',', '.', (string)($r['berat'] ?? ''));
    if ($tinggi <= 0 || $berat <= 0) {
        return null;
    }
    $m = $tinggi / 100;
    return round($berat / ($m * $m), 2);
}

function rekam_medis_imt_label(float $imt): string
{
    return match (true) {
        $imt < 18.5 => 'Kurang',
        $imt < 25.0 => 'Normal',
        $imt < 30.0 => 'Berlebih',
        default => 'Obesitas',
    };
}
