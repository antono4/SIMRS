<?php
/**
 * Fungsi bantu umum: escaping, URL, redirect, flash, tanggal, umur, badge, paginasi.
 */
declare(strict_types=1);

function e(?string $value): string
{
    $s = (string)$value;
    // Data dari database latin1: konversi ke UTF-8 bila bukan UTF-8 valid,
    // agar htmlspecialchars tidak mengembalikan string kosong pada karakter beraksen.
    if ($s !== '' && !preg_match('//u', $s)) {
        if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        } elseif (function_exists('iconv')) {
            $t = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
            $s = $t !== false ? $t : $s;
        } else {
            $s = preg_replace_callback('/[\x80-\xFF]/', fn($m) => chr(0xC0 | (ord($m[0]) >> 6)) . chr(0x80 | (ord($m[0]) & 0x3F)), $s) ?? $s;
        }
    }
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return BASE_URL . 'index.php?' . http_build_query($params);
}

function asset(string $path): string
{
    return BASE_URL . 'assets/' . ltrim($path, '/');
}

function redirect(string $target): never
{
    header('Location: ' . $target);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function tgl_indo(?string $tanggal): string
{
    if (!$tanggal || $tanggal === '0000-00-00') {
        return '-';
    }
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($tanggal);
    if ($ts === false) {
        return (string)$tanggal;
    }
    return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function umur_dari(?string $tglLahir): array
{
    // Mengembalikan [umur, sttsumur] ala sistem (Th/Bl/Hr)
    if (!$tglLahir || $tglLahir === '0000-00-00') {
        return [0, 'Th'];
    }
    $lahir = new DateTime($tglLahir);
    $now = new DateTime('today');
    $diff = $lahir->diff($now);
    if ((int)$diff->y > 0) {
        return [(int)$diff->y, 'Th'];
    }
    if ((int)$diff->m > 0) {
        return [(int)$diff->m, 'Bl'];
    }
    return [max(1, (int)$diff->days), 'Hr'];
}

function badge_status(string $status): string
{
    $map = [
        'Belum' => 'text-bg-warning',
        'Sudah' => 'text-bg-success',
        'Batal' => 'text-bg-danger',
        'Berkas Diterima' => 'text-bg-info',
        'Dirujuk' => 'text-bg-primary',
        'Meninggal' => 'text-bg-dark',
        'Dirawat' => 'text-bg-secondary',
        'Pulang Paksa' => 'text-bg-danger',
    ];
    $class = $map[$status] ?? 'text-bg-secondary';
    return '<span class="badge ' . $class . '">' . e($status) . '</span>';
}

function paginate(int $total, int $perPage, int $current, string $page, array $params = []): string
{
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i > 1 && $i < $pages && abs($i - $current) > 2) {
            continue;
        }
        if (($i === 2 && $current > 4) || ($i === $pages - 1 && $current < $pages - 3)) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $active = $i === $current ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e(url($page, $params + ['hal' => $i])) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}
