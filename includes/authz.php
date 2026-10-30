<?php
/**
 * Otorisasi / user level — memetakan halaman aplikasi ke kolom izin tabel user.
 */
declare(strict_types=1);

// Halaman => kolom izin pada tabel user (null = bebas untuk semua yang login)
const PAGE_PERMISSIONS = [
    'dashboard' => null,
    'pasien' => 'pasien',
    'dokter' => 'dokter',
    'pegawai' => 'pegawai_admin',
    'petugas' => 'petugas',
    'poliklinik' => 'registrasi',
    'penjab' => 'registrasi',
    'perawatan' => 'tarif_ralan',
    'penyakit' => 'penyakit',
    'obat' => 'obat',
    'bangsal' => 'kamar',
    'kamar' => 'kamar',
    // Data Referensi membuka tabel berbeda per grup; izinnya dicek per grup di
    // pages/referensi.php (auth_can_perm), jadi di sini cukup "boleh login".
    'referensi' => null,
    'registrasi' => 'registrasi',
    'antrian' => 'registrasi',
    'display' => null,   // layar publik, tanpa login
    'tindakan' => 'tindakan_ralan',
    'diagnosa' => 'diagnosa_pasien',
    'resep' => 'resep_obat',
    // Rekam medis memakai izin catatan_pasien: kolom `rekam_medis` tidak ada di
    // tabel user skema ini, sedangkan catatan_pasien ada dan belum dipakai modul lain.
    'rekam_medis' => 'catatan_pasien',
    'kamarinap' => 'kamar_inap',
    'kasir' => 'kasir_ralan',
    'billing' => 'kasir_ralan',   // cetak billing: izin sama dengan Kasir
    'kartu_berobat' => 'pasien',        // cetak kartu berobat
    'tiket_antrian' => 'registrasi',    // cetak tiket antrian
    'etiket_resep' => 'resep_obat',     // cetak etiket obat
    'cetak_rekam_medis' => 'catatan_pasien',
    'log_aktivitas' => null,   // dicek manual (hanya admin) di pages/log_aktivitas.php
    'laporan' => 'kunjungan_ralan',
    'users' => 'pegawai_user',
    'pengaturan' => 'pegawai_admin',
    'akun' => null,   // akun saya: semua pengguna login
    'admin' => null,  // panel admin: dicek manual (hanya role admin) di pages/admin.php
];

// Katalog izin yang dikelola di modul Manajemen Pengguna
const PERMISSION_CATALOG = [
    'pasien' => 'Master Pasien',
    'dokter' => 'Master Dokter',
    'petugas' => 'Master Petugas / Paramedis',
    'pegawai_admin' => 'Master Pegawai',
    'registrasi' => 'Registrasi, Poliklinik & Penjab',
    'tarif_ralan' => 'Tarif Perawatan',
    'tindakan_ralan' => 'Tindakan / Perawatan',
    'diagnosa_pasien' => 'Diagnosa ICD-10',
    'penyakit' => 'Master Penyakit (ICD-10)',
    'resep_obat' => 'Resep Obat',
    'obat' => 'Master Data Barang / Obat',
    'kamar_inap' => 'Kamar Inap',
    'kamar' => 'Master Bangsal & Kamar',
    'kasir_ralan' => 'Kasir / Billing',
    'kunjungan_ralan' => 'Laporan Kunjungan',
    'pegawai_user' => 'Manajemen Pengguna',
    'catatan_pasien' => 'Rekam Medis (catatan pasien)',
];

function auth_can(string $page): bool
{
    $user = auth_user();
    if ($user === null) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true; // superuser: akses penuh
    }
    $flag = PAGE_PERMISSIONS[$page] ?? null;
    if ($flag === null) {
        return true;
    }
    return !empty($user['permissions'][$flag]);
}

/**
 * Periksa satu kolom izin langsung (dipakai modul yang menaungi beberapa
 * halaman, mis. Data Referensi yang membuka tabel berbeda per grup).
 * Nama izin kosong berarti tidak dibatasi.
 */
function auth_can_perm(string $flag): bool
{
    $user = auth_user();
    if ($user === null) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    if ($flag === '') {
        return true;
    }
    return !empty($user['permissions'][$flag]);
}

// Label level pengguna untuk ditampilkan di antarmuka
function auth_level_label(): string
{
    $user = auth_user();
    if ($user === null) {
        return '';
    }
    if (($user['role'] ?? '') === 'admin') {
        return 'Administrator';
    }
    $aktif = array_keys(array_filter($user['permissions'] ?? []));
    $catalogAktif = array_intersect($aktif, array_keys(PERMISSION_CATALOG));
    return 'Petugas (' . count($catalogAktif) . ' modul)';
}
