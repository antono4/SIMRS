<?php
/**
 * Fungsi bantu umum: escaping, URL, redirect, flash, tanggal, umur, badge, paginasi.
 */
declare(strict_types=1);

/**
 * Data dari database latin1: konversi ke UTF-8 bila bukan UTF-8 valid, agar
 * htmlspecialchars/json_encode tidak mengembalikan string kosong pada karakter beraksen.
 */
function to_utf8(?string $value): string
{
    $s = (string)$value;
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
    return $s;
}

function e(?string $value): string
{
    return htmlspecialchars(to_utf8($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

/**
 * Angka rupiah menjadi kata, untuk baris "Terbilang" pada nota.
 * Contoh: 155000 -> "Seratus lima puluh lima ribu rupiah".
 */
function terbilang(float $angka): string
{
    $n = (int)round(abs($angka));
    if ($n === 0) {
        return 'Nol rupiah';
    }

    $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
        'sepuluh', 'sebelas'];

    $kata = function (int $x) use (&$kata, $satuan): string {
        if ($x < 12) {
            return $satuan[$x];
        }
        if ($x < 20) {
            return $kata($x - 10) . ' belas';
        }
        if ($x < 100) {
            return $kata(intdiv($x, 10)) . ' puluh' . ($x % 10 ? ' ' . $kata($x % 10) : '');
        }
        if ($x < 200) {
            return 'seratus' . ($x % 100 ? ' ' . $kata($x % 100) : '');
        }
        if ($x < 1000) {
            return $kata(intdiv($x, 100)) . ' ratus' . ($x % 100 ? ' ' . $kata($x % 100) : '');
        }
        if ($x < 2000) {
            return 'seribu' . ($x % 1000 ? ' ' . $kata($x % 1000) : '');
        }
        if ($x < 1_000_000) {
            return $kata(intdiv($x, 1000)) . ' ribu' . ($x % 1000 ? ' ' . $kata($x % 1000) : '');
        }
        if ($x < 1_000_000_000) {
            return $kata(intdiv($x, 1_000_000)) . ' juta' . ($x % 1_000_000 ? ' ' . $kata($x % 1_000_000) : '');
        }
        if ($x < 1_000_000_000_000) {
            return $kata(intdiv($x, 1_000_000_000)) . ' miliar' . ($x % 1_000_000_000 ? ' ' . $kata($x % 1_000_000_000) : '');
        }
        return $kata(intdiv($x, 1_000_000_000_000)) . ' triliun' . ($x % 1_000_000_000_000 ? ' ' . $kata($x % 1_000_000_000_000) : '');
    };

    $teks = trim((string)preg_replace('/\s+/', ' ', $kata($n)));

    return ucfirst($teks) . ' rupiah';
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

/**
 * Judul halaman + aksi utama (opsional) untuk bilah judul di area konten.
 * $actions: daftar tombol/link yang sudah berupa HTML (mis. <a class="btn ...">).
 */
function page_header(string $title, ?string $subtitle = null, string $actions = ''): string
{
    $html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">';
    $html .= '<div><h3 class="mb-0">' . e($title) . '</h3>';
    if ($subtitle !== null && $subtitle !== '') {
        $html .= '<p class="text-body-secondary small mb-0 mt-1">' . e($subtitle) . '</p>';
    }
    $html .= '</div>';
    if ($actions !== '') {
        $html .= '<div class="d-flex flex-wrap gap-2">' . $actions . '</div>';
    }
    return $html . '</div>';
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
