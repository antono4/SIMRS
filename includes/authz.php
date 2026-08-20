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
    'poliklinik' => 'registrasi',
    'penjab' => 'registrasi',
    'perawatan' => 'tarif_ralan',
    'registrasi' => 'registrasi',
    'tindakan' => 'tindakan_ralan',
    'diagnosa' => 'diagnosa_pasien',
    'resep' => 'resep_obat',
    'kamarinap' => 'kamar_inap',
    'kasir' => 'kasir_ralan',
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
    'registrasi' => 'Registrasi, Poliklinik & Penjab',
    'tarif_ralan' => 'Tarif Perawatan',
    'tindakan_ralan' => 'Tindakan / Perawatan',
    'diagnosa_pasien' => 'Diagnosa ICD-10',
    'resep_obat' => 'Resep Obat',
    'kamar_inap' => 'Kamar Inap',
    'kasir_ralan' => 'Kasir / Billing',
    'kunjungan_ralan' => 'Laporan Kunjungan',
    'pegawai_user' => 'Manajemen Pengguna',
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
