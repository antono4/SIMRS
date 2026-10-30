<?php
/**
 * Data referensi (tabel acuan skema sik) — satu modul untuk seluruh tabel
 * master kecil: wilayah, SDM, farmasi, dan data penunjang pasien.
 *
 * Definisi tabel sengaja dikumpulkan di sini supaya penambahan tabel acuan baru
 * cukup satu baris. Penjaga penghapusan TIDAK ditulis per tabel: tabel mana pun
 * yang menunjuk ke baris tersebut dibaca langsung dari information_schema,
 * sehingga tabel acuan yang baru dipakai modul lain otomatis ikut terjaga.
 */
declare(strict_types=1);

const REFERENSI_GRUP = [
    'wilayah' => ['label' => 'Wilayah', 'ikon' => 'bi-geo-alt', 'izin' => 'pasien'],
    'pasien' => ['label' => 'Data Penunjang Pasien', 'ikon' => 'bi-person-vcard', 'izin' => 'pasien'],
    'sdm' => ['label' => 'Kepegawaian', 'ikon' => 'bi-person-badge', 'izin' => 'pegawai_admin'],
    'farmasi' => ['label' => 'Farmasi & Barang', 'ikon' => 'bi-capsule', 'izin' => 'obat'],
    'medis' => ['label' => 'Medis', 'ikon' => 'bi-clipboard2-pulse', 'izin' => 'dokter'],
];

const REFERENSI_TABEL = [
    // --- Wilayah ---
    'propinsi' => [
        'grup' => 'wilayah', 'tabel' => 'propinsi', 'label' => 'Propinsi',
        'pk' => 'kd_prop', 'auto' => true, 'urut' => 'nm_prop',
        'kolom' => [
            ['nama' => 'nm_prop', 'label' => 'Nama Propinsi', 'tipe' => 'teks', 'maks' => 30, 'wajib' => true],
        ],
    ],
    'kabupaten' => [
        'grup' => 'wilayah', 'tabel' => 'kabupaten', 'label' => 'Kabupaten / Kota',
        'pk' => 'kd_kab', 'auto' => true, 'urut' => 'nm_kab',
        'kolom' => [
            ['nama' => 'nm_kab', 'label' => 'Nama Kabupaten / Kota', 'tipe' => 'teks', 'maks' => 60, 'wajib' => true],
        ],
    ],
    'kecamatan' => [
        'grup' => 'wilayah', 'tabel' => 'kecamatan', 'label' => 'Kecamatan',
        'pk' => 'kd_kec', 'auto' => true, 'urut' => 'nm_kec',
        'kolom' => [
            ['nama' => 'nm_kec', 'label' => 'Nama Kecamatan', 'tipe' => 'teks', 'maks' => 60, 'wajib' => true],
        ],
    ],
    'kelurahan' => [
        'grup' => 'wilayah', 'tabel' => 'kelurahan', 'label' => 'Kelurahan / Desa',
        'pk' => 'kd_kel', 'auto' => true, 'urut' => 'nm_kel',
        'kolom' => [
            ['nama' => 'nm_kel', 'label' => 'Nama Kelurahan / Desa', 'tipe' => 'teks', 'maks' => 60, 'wajib' => true],
        ],
    ],

    // --- Data penunjang pasien ---
    'suku_bangsa' => [
        'grup' => 'pasien', 'tabel' => 'suku_bangsa', 'label' => 'Suku Bangsa',
        'pk' => 'id', 'auto' => true, 'urut' => 'nama_suku_bangsa',
        'kolom' => [
            ['nama' => 'nama_suku_bangsa', 'label' => 'Nama Suku Bangsa', 'tipe' => 'teks', 'maks' => 30],
        ],
    ],
    'bahasa_pasien' => [
        'grup' => 'pasien', 'tabel' => 'bahasa_pasien', 'label' => 'Bahasa',
        'pk' => 'id', 'auto' => true, 'urut' => 'nama_bahasa',
        'kolom' => [
            ['nama' => 'nama_bahasa', 'label' => 'Nama Bahasa', 'tipe' => 'teks', 'maks' => 30],
        ],
    ],
    'cacat_fisik' => [
        'grup' => 'pasien', 'tabel' => 'cacat_fisik', 'label' => 'Cacat Fisik',
        'pk' => 'id', 'auto' => true, 'urut' => 'nama_cacat',
        'kolom' => [
            ['nama' => 'nama_cacat', 'label' => 'Nama Cacat Fisik', 'tipe' => 'teks', 'maks' => 30, 'wajib' => true],
        ],
    ],
    'perusahaan_pasien' => [
        'grup' => 'pasien', 'tabel' => 'perusahaan_pasien', 'label' => 'Perusahaan Pasien',
        'pk' => 'kode_perusahaan', 'auto' => false, 'urut' => 'nama_perusahaan',
        'kolom' => [
            ['nama' => 'nama_perusahaan', 'label' => 'Nama Perusahaan', 'tipe' => 'teks', 'maks' => 70],
            ['nama' => 'alamat', 'label' => 'Alamat', 'tipe' => 'teks', 'maks' => 100],
            ['nama' => 'kota', 'label' => 'Kota', 'tipe' => 'teks', 'maks' => 40],
            ['nama' => 'no_telp', 'label' => 'No. Telepon', 'tipe' => 'teks', 'maks' => 27],
        ],
    ],

    // --- Kepegawaian ---
    'jabatan' => [
        'grup' => 'sdm', 'tabel' => 'jabatan', 'label' => 'Jabatan',
        'pk' => 'kd_jbtn', 'auto' => false, 'urut' => 'nm_jbtn',
        'kolom' => [
            ['nama' => 'nm_jbtn', 'label' => 'Nama Jabatan', 'tipe' => 'teks', 'maks' => 25],
        ],
    ],
    'jnj_jabatan' => [
        'grup' => 'sdm', 'tabel' => 'jnj_jabatan', 'label' => 'Jenjang Jabatan',
        'pk' => 'kode', 'auto' => false, 'urut' => 'nama',
        'kolom' => [
            ['nama' => 'nama', 'label' => 'Nama Jenjang', 'tipe' => 'teks', 'maks' => 50, 'wajib' => true],
            ['nama' => 'tnj', 'label' => 'Tunjangan (Rp)', 'tipe' => 'desimal'],
            ['nama' => 'indek', 'label' => 'Indeks', 'tipe' => 'angka'],
        ],
    ],
    'kelompok_jabatan' => [
        'grup' => 'sdm', 'tabel' => 'kelompok_jabatan', 'label' => 'Kelompok Jabatan',
        'pk' => 'kode_kelompok', 'auto' => false, 'urut' => 'kode_kelompok',
        'kolom' => [
            ['nama' => 'nama_kelompok', 'label' => 'Nama Kelompok', 'tipe' => 'teks', 'maks' => 100],
            ['nama' => 'indek', 'label' => 'Indeks', 'tipe' => 'angka'],
        ],
    ],
    'departemen' => [
        'grup' => 'sdm', 'tabel' => 'departemen', 'label' => 'Departemen / Instalasi',
        'pk' => 'dep_id', 'auto' => false, 'urut' => 'dep_id',
        'kolom' => [
            ['nama' => 'nama', 'label' => 'Nama Departemen', 'tipe' => 'teks', 'maks' => 25, 'wajib' => true],
        ],
    ],
    'bidang' => [
        'grup' => 'sdm', 'tabel' => 'bidang', 'label' => 'Bidang',
        'pk' => 'nama', 'auto' => false, 'urut' => 'nama',
        'kolom' => [],
    ],
    'resiko_kerja' => [
        'grup' => 'sdm', 'tabel' => 'resiko_kerja', 'label' => 'Indeks Resiko Kerja',
        'pk' => 'kode_resiko', 'auto' => false, 'urut' => 'kode_resiko',
        'kolom' => [
            ['nama' => 'nama_resiko', 'label' => 'Nama Resiko', 'tipe' => 'teks', 'maks' => 100],
            ['nama' => 'indek', 'label' => 'Indeks', 'tipe' => 'angka'],
        ],
    ],
    'emergency_index' => [
        'grup' => 'sdm', 'tabel' => 'emergency_index', 'label' => 'Indeks Emergency',
        'pk' => 'kode_emergency', 'auto' => false, 'urut' => 'kode_emergency',
        'kolom' => [
            ['nama' => 'nama_emergency', 'label' => 'Nama Emergency', 'tipe' => 'teks', 'maks' => 100],
            ['nama' => 'indek', 'label' => 'Indeks', 'tipe' => 'angka'],
        ],
    ],
    'stts_kerja' => [
        'grup' => 'sdm', 'tabel' => 'stts_kerja', 'label' => 'Status Kerja',
        'pk' => 'stts', 'auto' => false, 'urut' => 'stts',
        'kolom' => [
            ['nama' => 'ktg', 'label' => 'Keterangan', 'tipe' => 'teks', 'maks' => 20, 'wajib' => true],
            ['nama' => 'indek', 'label' => 'Indeks', 'tipe' => 'angka', 'wajib' => true],
            ['nama' => 'hakcuti', 'label' => 'Hak Cuti (hari)', 'tipe' => 'angka', 'wajib' => true],
        ],
    ],
    'stts_wp' => [
        'grup' => 'sdm', 'tabel' => 'stts_wp', 'label' => 'Status Wajib Pajak',
        'pk' => 'stts', 'auto' => false, 'urut' => 'stts',
        'kolom' => [
            ['nama' => 'ktg', 'label' => 'Keterangan', 'tipe' => 'teks', 'maks' => 50, 'wajib' => true],
        ],
    ],
    'pendidikan' => [
        'grup' => 'sdm', 'tabel' => 'pendidikan', 'label' => 'Pendidikan',
        'pk' => 'tingkat', 'auto' => false, 'urut' => 'tingkat',
        'kolom' => [
            ['nama' => 'indek', 'label' => 'Indeks', 'tipe' => 'angka', 'wajib' => true],
            ['nama' => 'gapok1', 'label' => 'Gaji Pokok Awal (Rp)', 'tipe' => 'desimal', 'wajib' => true],
            ['nama' => 'kenaikan', 'label' => 'Kenaikan (Rp)', 'tipe' => 'desimal', 'wajib' => true],
            ['nama' => 'maksimal', 'label' => 'Maksimal (Rp)', 'tipe' => 'angka', 'wajib' => true],
        ],
    ],
    'bank' => [
        'grup' => 'sdm', 'tabel' => 'bank', 'label' => 'Bank',
        'pk' => 'namabank', 'auto' => false, 'urut' => 'namabank',
        'kolom' => [],
    ],

    // --- Farmasi & barang ---
    'jenis' => [
        'grup' => 'farmasi', 'tabel' => 'jenis', 'label' => 'Jenis Barang',
        'pk' => 'kdjns', 'auto' => false, 'urut' => 'nama',
        'kolom' => [
            ['nama' => 'nama', 'label' => 'Nama Jenis', 'tipe' => 'teks', 'maks' => 30, 'wajib' => true],
            ['nama' => 'keterangan', 'label' => 'Keterangan', 'tipe' => 'teks', 'maks' => 50, 'wajib' => true],
        ],
    ],
    'kategori_barang' => [
        'grup' => 'farmasi', 'tabel' => 'kategori_barang', 'label' => 'Kategori Barang',
        'pk' => 'kode', 'auto' => false, 'urut' => 'nama',
        'kolom' => [
            ['nama' => 'nama', 'label' => 'Nama Kategori', 'tipe' => 'teks', 'maks' => 30],
        ],
    ],
    'golongan_barang' => [
        'grup' => 'farmasi', 'tabel' => 'golongan_barang', 'label' => 'Golongan Barang',
        'pk' => 'kode', 'auto' => false, 'urut' => 'nama',
        'kolom' => [
            ['nama' => 'nama', 'label' => 'Nama Golongan', 'tipe' => 'teks', 'maks' => 30],
        ],
    ],
    'kodesatuan' => [
        'grup' => 'farmasi', 'tabel' => 'kodesatuan', 'label' => 'Satuan Barang',
        'pk' => 'kode_sat', 'auto' => false, 'urut' => 'satuan',
        'kolom' => [
            ['nama' => 'satuan', 'label' => 'Nama Satuan', 'tipe' => 'teks', 'maks' => 30],
        ],
    ],
    'industrifarmasi' => [
        'grup' => 'farmasi', 'tabel' => 'industrifarmasi', 'label' => 'Industri Farmasi',
        'pk' => 'kode_industri', 'auto' => false, 'urut' => 'nama_industri',
        'kolom' => [
            ['nama' => 'nama_industri', 'label' => 'Nama Industri', 'tipe' => 'teks', 'maks' => 50],
            ['nama' => 'alamat', 'label' => 'Alamat', 'tipe' => 'teks', 'maks' => 50],
            ['nama' => 'kota', 'label' => 'Kota', 'tipe' => 'teks', 'maks' => 20],
            ['nama' => 'no_telp', 'label' => 'No. Telepon', 'tipe' => 'teks', 'maks' => 20],
        ],
    ],

    // --- Medis ---
    'spesialis' => [
        'grup' => 'medis', 'tabel' => 'spesialis', 'label' => 'Spesialis',
        'pk' => 'kd_sps', 'auto' => false, 'urut' => 'nm_sps',
        'kolom' => [
            ['nama' => 'nm_sps', 'label' => 'Nama Spesialis', 'tipe' => 'teks', 'maks' => 30],
        ],
    ],
    'kategori_perawatan' => [
        'grup' => 'medis', 'tabel' => 'kategori_perawatan', 'label' => 'Kategori Perawatan',
        'pk' => 'kd_kategori', 'auto' => false, 'urut' => 'nm_kategori',
        'kolom' => [
            ['nama' => 'nm_kategori', 'label' => 'Nama Kategori', 'tipe' => 'teks', 'maks' => 30],
        ],
    ],
];

// Nama tabel -> label manusia, dipakai pada pesan "masih dipakai di ...".
const REFERENSI_LABEL_TABEL = [
    'pasien' => 'data pasien',
    'pegawai' => 'data pegawai',
    'petugas' => 'data petugas',
    'dokter' => 'data dokter',
    'databarang' => 'data barang',
    'jns_perawatan' => 'tarif perawatan',
    'kamar' => 'data kamar',
    'penyakit' => 'data penyakit',
    'resep_dokter' => 'baris resep',
    'kamar_inap' => 'riwayat rawat inap',
    'reg_periksa' => 'data registrasi',
    'diagnosa_pasien' => 'data diagnosa',
    'resep_obat' => 'data resep',
];

function referensi_ambil(string $slug): ?array
{
    return REFERENSI_TABEL[$slug] ?? null;
}

/** Daftar tabel acuan yang boleh dibuka pengguna saat ini, dikelompokkan. */
function referensi_menu(): array
{
    $hasil = [];
    foreach (REFERENSI_TABEL as $slug => $def) {
        $grup = $def['grup'];
        if (!auth_can_perm(REFERENSI_GRUP[$grup]['izin'] ?? '')) {
            continue;
        }
        $hasil[$grup][] = ['slug' => $slug] + $def;
    }
    return $hasil;
}

/**
 * Tabel yang menunjuk ke baris ini beserta jumlah pemakaiannya, dibaca dari
 * information_schema sehingga penjaga penghapusan selalu mengikuti skema.
 *
 * @return list<array{tabel: string, kolom: string, jumlah: int, label: string}>
 */
function referensi_rujukan(string $tabel, string|int $nilai): array
{
    static $cache = [];
    if (!isset($cache[$tabel])) {
        $cache[$tabel] = db_all(
            "SELECT k.table_name AS tabel, k.column_name AS kolom
             FROM information_schema.key_column_usage k
             WHERE k.table_schema = ? AND k.referenced_table_name = ?
             ORDER BY k.table_name",
            [DB_NAME, $tabel]
        );
    }
    $hasil = [];
    foreach ($cache[$tabel] as $fk) {
        $anak = (string)$fk['tabel'];
        $kolom = (string)$fk['kolom'];
        // Nama tabel dan kolom berasal dari information_schema (bukan masukan
        // pengguna), jadi aman disisipkan; nilainya tetap lewat prepared statement.
        $jumlah = (int)db_val("SELECT COUNT(*) FROM `$anak` WHERE `$kolom` = ?", [$nilai]);
        if ($jumlah > 0) {
            $hasil[] = [
                'tabel' => $anak,
                'kolom' => $kolom,
                'jumlah' => $jumlah,
                'label' => REFERENSI_LABEL_TABEL[$anak] ?? ('data ' . str_replace('_', ' ', $anak)),
            ];
        }
    }
    return $hasil;
}

function referensi_pesan_rujukan(string $label, array $rujukan): string
{
    $bagian = array_map(fn($r) => $r['jumlah'] . ' ' . $r['label'], $rujukan);
    return "$label tidak dapat dihapus karena masih dipakai di " . implode(', ', $bagian) . '.';
}
