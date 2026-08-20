<?php
/**
 * Lapisan akses database (PDO) ke skema MySQL aplikasi.
 * db() mengembalikan koneksi lazily dengan fallback host/port (variasi XAMPP).
 */
declare(strict_types=1);

function db_kandidat_host(): array
{
    $list = [[DB_HOST, DB_PORT]];
    if (DB_HOST === 'localhost') {
        $list[] = ['127.0.0.1', DB_PORT];
    } elseif (DB_HOST === '127.0.0.1') {
        $list[] = ['localhost', DB_PORT];
    }
    $list[] = [DB_HOST, DB_PORT === '3306' ? '3307' : '3306'];
    return $list;
}

function db_connect(bool $tanpaDb): PDO
{
    $last = null;
    foreach (db_kandidat_host() as [$host, $port]) {
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ($tanpaDb ? '' : ';dbname=' . DB_NAME) . ';charset=' . DB_CHARSET;
        try {
            return new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $last = $e;
        }
    }
    throw $last ?? new PDOException('Tidak dapat terhubung ke server MySQL.');
}

function db(bool $tanpaDb = false): PDO
{
    static $pdo = null;
    static $pdoTanpaDb = null;
    if ($tanpaDb) {
        return $pdoTanpaDb ??= db_connect(true);
    }
    return $pdo ??= db_connect(false);
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
