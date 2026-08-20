<?php
// Fungsi bantu umum
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($params);
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
    $th = (int)$lahir->diff($now)->y;
    if ($th > 0) {
        return [$th, 'Th'];
    }
    $bl = (int)$lahir->diff($now)->m;
    if ($bl > 0) {
        return [$bl, 'Bl'];
    }
    return [max(1, (int)$lahir->diff($now)->days), 'Hr'];
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
