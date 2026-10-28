<?php
/**
 * Antrian poliklinik — pengambilan nomor, pemanggilan, dan ringkasan untuk layar display.
 *
 * Tabel `antrian` tidak ada di skema asli (sik.sql), jadi dibuat di sini.
 * CREATE TABLE IF NOT EXISTS membuat instalasi lama ikut naik versi sendiri
 * tanpa perlu menjalankan ulang wizard instalasi.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const ANTRIAN_STATUS = ['Menunggu', 'Dipanggil', 'Terlewat', 'Selesai', 'Batal'];

function antrian_bootstrap(): void
{
    static $selesai = false;
    if ($selesai) {
        return;
    }
    $selesai = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `antrian` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `tgl` date NOT NULL,
              `kd_poli` char(5) NOT NULL,
              `nomor` int(11) NOT NULL,
              `no_rawat` varchar(17) DEFAULT NULL,
              `status` enum('Menunggu','Dipanggil','Terlewat','Selesai','Batal') NOT NULL DEFAULT 'Menunggu',
              `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
              `dipanggil_pada` datetime DEFAULT NULL,
              `dipanggil_oleh` varchar(50) DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_antrian_hari_poli_nomor` (`tgl`,`kd_poli`,`nomor`),
              KEY `idx_antrian_urut` (`tgl`,`kd_poli`,`status`,`nomor`),
              KEY `idx_antrian_panggil` (`tgl`,`status`,`dipanggil_pada`),
              KEY `no_rawat` (`no_rawat`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci"
        );
    } catch (Throwable) {
        // Tabel sudah ada atau pengguna tidak punya hak DDL — query berikutnya
        // akan melaporkan masalah sebenarnya.
        return;
    }
    // Foreign key opsional: tabel tetap berguna tanpanya, jadi kegagalan diabaikan.
    try {
        $ada = (int)db_val(
            "SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE constraint_schema = ? AND table_name = 'antrian' AND constraint_type = 'FOREIGN KEY'",
            [DB_NAME]
        );
        if ($ada === 0) {
            db()->exec(
                'ALTER TABLE `antrian` ADD CONSTRAINT `antrian_ibfk_1`
                 FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`)
                 ON DELETE CASCADE ON UPDATE CASCADE'
            );
        }
    } catch (Throwable) {
        // reg_periksa belum ada / hak DDL kurang — lewati.
    }
}

/**
 * Nomor antrian siap tampil: kode poliklinik + nomor urut, mis. "INT-007".
 *
 * Kode poli ikut ditampilkan karena nomor urut diulang dari 1 di setiap
 * poliklinik — tanpa kode itu, nomor 007 di dua poli berbeda terlihat sama.
 * Bila kode poli kosong, hanya nomornya yang ditampilkan.
 */
function antrian_nomor_tampil(int $nomor, string $kdPoli = ''): string
{
    $angka = str_pad((string)$nomor, 3, '0', STR_PAD_LEFT);
    $kode = $kdPoli !== '' ? poli_kode($kdPoli) : '';
    return $kode === '' ? $angka : $kode . '-' . $angka;
}

function antrian_badge(string $status): string
{
    $map = [
        'Menunggu' => 'text-bg-warning',
        'Dipanggil' => 'text-bg-primary',
        'Terlewat' => 'text-bg-secondary',
        'Selesai' => 'text-bg-success',
        'Batal' => 'text-bg-danger',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'text-bg-secondary') . '">' . e($status) . '</span>';
}

/**
 * Ambil nomor antrian baru (per hari, per poliklinik, mulai dari 1).
 *
 * Pengambilan nomor dikunci lewat baris `poliklinik` (SELECT ... FOR UPDATE) supaya
 * dua loket yang menyimpan bersamaan tidak mendapat nomor yang sama; unique key
 * `uq_antrian_hari_poli_nomor` menjadi jaring pengaman terakhir.
 */
function antrian_ambil_nomor(string $tgl, string $kdPoli, ?string $noRawat = null, ?string $oleh = null): array
{
    antrian_bootstrap();
    $pdo = db();
    $terakhir = null;

    for ($coba = 1; $coba <= 5; $coba++) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('SELECT kd_poli FROM poliklinik WHERE kd_poli = ? FOR UPDATE')->execute([$kdPoli]);
            $nomor = (int)db_val(
                'SELECT COALESCE(MAX(nomor), 0) + 1 FROM antrian WHERE tgl = ? AND kd_poli = ?',
                [$tgl, $kdPoli]
            );
            db_exec(
                'INSERT INTO antrian (tgl, kd_poli, nomor, no_rawat, status, dibuat_pada)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [$tgl, $kdPoli, $nomor, $noRawat, 'Menunggu']
            );
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            return antrian_row($id) ?? [];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $terakhir = $e;
            // 23000 = pelanggaran unique key (nomor sudah dipakai) → ulangi.
            if ($e->getCode() !== '23000') {
                break;
            }
            usleep(50000);
        }
    }

    throw $terakhir ?? new RuntimeException('Gagal mengambil nomor antrian.');
}

function antrian_row(int $id): ?array
{
    antrian_bootstrap();
    return db_row(
        'SELECT a.*, pl.nm_poli, p.nm_pasien, p.no_rkm_medis
         FROM antrian a
         LEFT JOIN poliklinik pl ON pl.kd_poli = a.kd_poli
         LEFT JOIN reg_periksa rp ON rp.no_rawat = a.no_rawat
         LEFT JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
         WHERE a.id = ?',
        [$id]
    );
}

function antrian_daftar(string $tgl, string $kdPoli = '', string $status = ''): array
{
    antrian_bootstrap();
    $where = ['a.tgl = ?'];
    $params = [$tgl];
    if ($kdPoli !== '') {
        $where[] = 'a.kd_poli = ?';
        $params[] = $kdPoli;
    }
    if ($status !== '' && in_array($status, ANTRIAN_STATUS, true)) {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    return db_all(
        'SELECT a.*, pl.nm_poli, p.nm_pasien, p.no_rkm_medis, rp.jam_reg
         FROM antrian a
         LEFT JOIN poliklinik pl ON pl.kd_poli = a.kd_poli
         LEFT JOIN reg_periksa rp ON rp.no_rawat = a.no_rawat
         LEFT JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY a.kd_poli, a.nomor',
        $params
    );
}

/** Baris antrian yang belum selesai untuk satu kunjungan (dipakai saat registrasi). */
function antrian_untuk_rawat(string $noRawat): ?array
{
    antrian_bootstrap();
    return db_row(
        "SELECT a.*, pl.nm_poli FROM antrian a
         LEFT JOIN poliklinik pl ON pl.kd_poli = a.kd_poli
         WHERE a.no_rawat = ? AND a.status NOT IN ('Batal')
         ORDER BY a.id DESC LIMIT 1",
        [$noRawat]
    );
}

function antrian_panggil(int $id, string $oleh): ?array
{
    antrian_bootstrap();
    db_exec(
        "UPDATE antrian SET status = 'Dipanggil', dipanggil_pada = NOW(), dipanggil_oleh = ?
         WHERE id = ?",
        [$oleh, $id]
    );
    return antrian_row($id);
}

/** Panggil nomor Menunggu terkecil berikutnya pada satu poliklinik. */
function antrian_panggil_berikutnya(string $tgl, string $kdPoli, string $oleh): ?array
{
    antrian_bootstrap();
    $row = db_row(
        "SELECT id FROM antrian
         WHERE tgl = ? AND kd_poli = ? AND status = 'Menunggu'
         ORDER BY nomor LIMIT 1",
        [$tgl, $kdPoli]
    );
    if ($row === null) {
        return null;
    }
    return antrian_panggil((int)$row['id'], $oleh);
}

function antrian_set_status(int $id, string $status): void
{
    antrian_bootstrap();
    if (!in_array($status, ANTRIAN_STATUS, true)) {
        return;
    }
    db_exec('UPDATE antrian SET status = ? WHERE id = ?', [$status, $id]);
}

/** Panggil ulang: perbarui waktu panggil supaya muncul kembali di layar display. */
function antrian_panggil_ulang(int $id, string $oleh): ?array
{
    antrian_bootstrap();
    db_exec(
        "UPDATE antrian SET status = 'Dipanggil', dipanggil_pada = NOW(), dipanggil_oleh = ?
         WHERE id = ?",
        [$oleh, $id]
    );
    return antrian_row($id);
}

/**
 * Ringkasan per poliklinik aktif untuk satu tanggal.
 * "Dipanggil" = panggilan terakhir (dipanggil_pada terbaru), bukan nomor terbesar,
 * supaya pemanggilan ulang nomor lama tetap tampil di layar display.
 */
function antrian_ringkasan(string $tgl): array
{
    antrian_bootstrap();
    $poli = db_all(
        "SELECT pl.kd_poli, pl.nm_poli,
                COALESCE(SUM(a.status = 'Menunggu'), 0)  AS menunggu,
                COALESCE(SUM(a.status = 'Dipanggil'), 0) AS dipanggil,
                COALESCE(SUM(a.status = 'Selesai'), 0)   AS selesai,
                COALESCE(SUM(a.status IN ('Terlewat','Batal')), 0) AS terlewat,
                COUNT(a.id) AS total
         FROM poliklinik pl
         LEFT JOIN antrian a ON a.kd_poli = pl.kd_poli AND a.tgl = ?
         WHERE pl.status = '1'
         GROUP BY pl.kd_poli, pl.nm_poli
         ORDER BY pl.nm_poli",
        [$tgl]
    );

    $terakhir = [];
    foreach (db_all(
        "SELECT a.kd_poli, a.nomor, a.dipanggil_pada
         FROM antrian a
         WHERE a.tgl = ? AND a.status = 'Dipanggil'
         ORDER BY a.dipanggil_pada DESC, a.nomor DESC",
        [$tgl]
    ) as $r) {
        $terakhir[$r['kd_poli']] ??= $r;
    }

    $menunggu = [];
    foreach (db_all(
        "SELECT kd_poli, nomor FROM antrian
         WHERE tgl = ? AND status = 'Menunggu' ORDER BY kd_poli, nomor",
        [$tgl]
    ) as $r) {
        $menunggu[$r['kd_poli']][] = (int)$r['nomor'];
    }

    foreach ($poli as &$p) {
        $kd = $p['kd_poli'];
        $p['nomor_dipanggil'] = isset($terakhir[$kd]) ? (int)$terakhir[$kd]['nomor'] : null;
        $p['dipanggil_pada'] = $terakhir[$kd]['dipanggil_pada'] ?? null;
        $p['berikutnya'] = array_slice($menunggu[$kd] ?? [], 0, 3);
    }
    unset($p);

    return $poli;
}

/**
 * Ringkasan yang hanya berisi poliklinik dengan antrian pada tanggal tersebut.
 * Layar TV menampilkan daftar ini; 22 poliklinik kosong membuat layar sulit dibaca.
 */
function antrian_ringkasan_aktif(string $tgl): array
{
    return array_values(array_filter(
        antrian_ringkasan($tgl),
        fn(array $r): bool => (int)$r['total'] > 0
    ));
}

/** Panggilan terakhir di seluruh poliklinik — untuk nomor besar di layar display. */
function antrian_panggilan_terakhir(string $tgl): ?array
{
    antrian_bootstrap();
    return db_row(
        "SELECT a.nomor, a.kd_poli, a.dipanggil_pada, pl.nm_poli
         FROM antrian a
         LEFT JOIN poliklinik pl ON pl.kd_poli = a.kd_poli
         WHERE a.tgl = ? AND a.status = 'Dipanggil'
         ORDER BY a.dipanggil_pada DESC, a.nomor DESC
         LIMIT 1",
        [$tgl]
    );
}

function antrian_statistik(string $tgl): array
{
    antrian_bootstrap();
    $r = db_row(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'Menunggu'), 0)  AS menunggu,
                COALESCE(SUM(status = 'Dipanggil'), 0) AS dipanggil,
                COALESCE(SUM(status = 'Selesai'), 0)   AS selesai,
                COALESCE(SUM(status IN ('Terlewat','Batal')), 0) AS terlewat
         FROM antrian WHERE tgl = ?",
        [$tgl]
    ) ?? [];
    return array_map('intval', $r ?: ['total' => 0, 'menunggu' => 0, 'dipanggil' => 0, 'selesai' => 0, 'terlewat' => 0]);
}

/**
 * Kunjungan hari ini pada satu poliklinik yang belum punya nomor antrian —
 * dipakai modul Antrian untuk memberi nomor secara manual (pasien sudah
 * terlanjur registrasi sebelum fitur ini dipakai).
 */
function antrian_kunjungan_tanpa_nomor(string $tgl, string $kdPoli): array
{
    antrian_bootstrap();
    return db_all(
        "SELECT rp.no_rawat, rp.no_reg, rp.jam_reg, p.nm_pasien, p.no_rkm_medis
         FROM reg_periksa rp
         JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
         WHERE rp.tgl_registrasi = ? AND rp.kd_poli = ?
           AND rp.stts NOT IN ('Batal')
           AND NOT EXISTS (
             SELECT 1 FROM antrian a
             WHERE a.no_rawat = rp.no_rawat AND a.status <> 'Batal'
           )
         ORDER BY rp.jam_reg, rp.no_reg",
        [$tgl, $kdPoli]
    );
}
