<?php
// Autentikasi kompatibel skema bawaan: tabel admin/user dienkripsi AES dengan kunci 'nur'
declare(strict_types=1);

function auth_attempt(string $username, string $password): bool
{
    // Superuser (tabel admin)
    $row = db_row(
        "SELECT CAST(AES_DECRYPT(usere, ?) AS CHAR) AS usr
         FROM admin
         WHERE CAST(AES_DECRYPT(usere, ?) AS CHAR) = ?
           AND CAST(AES_DECRYPT(passworde, ?) AS CHAR) = ?",
        [AES_KEY, AES_KEY, $username, AES_KEY, $password]
    );
    if ($row !== null) {
        $_SESSION['auth'] = ['username' => $username, 'role' => 'admin'];
        tracker_catat_login($username);
        return true;
    }

    // User/petugas (tabel user) — sekaligus ambil seluruh izin modulnya
    $row = db_row(
        "SELECT * FROM user
         WHERE CAST(AES_DECRYPT(id_user, ?) AS CHAR) = ?
           AND CAST(AES_DECRYPT(password, ?) AS CHAR) = ?",
        [AES_KEY, $username, AES_KEY, $password]
    );
    if ($row !== null) {
        $permissions = [];
        foreach ($row as $kolom => $nilai) {
            if ($nilai === 'true') {
                $permissions[$kolom] = true;
            }
        }
        $_SESSION['auth'] = ['username' => $username, 'role' => 'user', 'permissions' => $permissions];
        tracker_catat_login($username);
        return true;
    }
    return false;
}

function auth_check(): bool
{
    $u = $_SESSION['auth'] ?? null;
    return is_array($u) && !empty($u['username']) && !empty($u['role']);
}

function auth_user(): ?array
{
    return $_SESSION['auth'] ?? null;
}

function auth_require(): void
{
    if (!auth_check()) {
        redirect(url('login'));
    }
}

function auth_logout(): void
{
    unset($_SESSION['auth']);
}
