<?php
// Pengaturan instansi — tabel setting Khanza (nama_instansi, alamat, dll)
declare(strict_types=1);

function setting_rs(): array
{
    static $setting = null;
    if ($setting === null) {
        $setting = db_row('SELECT nama_instansi, alamat_instansi, kabupaten, propinsi, kontak, email FROM setting LIMIT 1') ?? [];
    }
    return $setting;
}

function setting_nama_rs(): string
{
    $nama = trim((string)(setting_rs()['nama_instansi'] ?? ''));
    return $nama !== '' ? $nama : APP_NAME;
}

function setting_update(array $data): void
{
    $ada = db_val('SELECT COUNT(*) FROM setting');
    if ($ada > 0) {
        db_exec(
            'UPDATE setting SET nama_instansi=?, alamat_instansi=?, kabupaten=?, propinsi=?, kontak=?, email=?',
            [$data['nama_instansi'], $data['alamat_instansi'], $data['kabupaten'], $data['propinsi'], $data['kontak'], $data['email']]
        );
    } else {
        db_exec(
            "INSERT INTO setting (nama_instansi, alamat_instansi, kabupaten, propinsi, kontak, email, aktifkan, logo)
             VALUES (?,?,?,?,?,?,'No','')",
            [$data['nama_instansi'], $data['alamat_instansi'], $data['kabupaten'], $data['propinsi'], $data['kontak'], $data['email']]
        );
    }
}
