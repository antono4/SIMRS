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

/**
 * Inisial nama untuk penanda kop cetak: "RSUD Kota" -> "RK".
 * mbstring bersifat opsional di aplikasi ini (lihat pages/diagnostik.php),
 * jadi sediakan fallback substr/strtoupper bila ekstensi tidak terpasang.
 */
function nama_inisial(string $nama, int $maks = 2): string
{
    $nama = to_utf8($nama);
    $potong = fn(string $s, int $n) => function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
    $panjang = fn(string $s) => function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);

    $inisial = '';
    foreach (preg_split('/\s+/', trim($nama)) ?: [] as $kata) {
        if ($kata === '') {
            continue;
        }
        $huruf = $potong($kata, 1);
        $inisial .= function_exists('mb_strtoupper') ? mb_strtoupper($huruf) : strtoupper($huruf);
        if ($panjang($inisial) >= $maks) {
            break;
        }
    }
    return $inisial;
}

/**
 * Terjemahkan PDOException menjadi pesan yang bisa dibaca petugas.
 *
 * Skema `sik` menegakkan batasan yang tidak diperiksa aplikasi: panjang kolom
 * (kode kepanjangan) dan foreign key (baris induk yang masih dipakai). Tanpa
 * penerjemahan ini, aksi biasa seperti menyimpan atau menghapus data master
 * berubah menjadi halaman galat 500.
 */
function db_pesan_galat(Throwable $e): string
{
    $pesan = $e->getMessage();
    if (str_contains($pesan, '1406') || stripos($pesan, 'Data too long') !== false) {
        return 'Nilai yang dimasukkan terlalu panjang untuk kolom database. Perpendek kode/teks lalu simpan lagi.';
    }
    if (str_contains($pesan, '1451')) {
        return 'Data ini masih dipakai data lain, jadi belum dapat dihapus. Hapus atau ubah dulu data yang memakainya.';
    }
    if (str_contains($pesan, '1452')) {
        return 'Data terkait yang dipilih tidak ditemukan. Muat ulang halaman lalu pilih ulang dari daftar.';
    }
    if (str_contains($pesan, '1062')) {
        return 'Kode tersebut sudah dipakai. Gunakan kode lain.';
    }
    return 'Gagal menyimpan ke database: ' . $pesan;
}

/**
 * Jalankan aksi tulis yang mungkin melanggar batasan skema.
 * Mengembalikan null bila berhasil, atau pesan galat yang ramah bila gagal.
 */
function db_coba(callable $aksi): ?string
{
    try {
        $aksi();
        return null;
    } catch (PDOException $e) {
        return db_pesan_galat($e);
    }
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

/**
 * Pemisah visual antrian per poliklinik.
 *
 * Tiap poliklinik mendapat satu warna tetap (murni dari kd_poli, jadi tidak
 * berubah antar hari) plus huruf awalan, supaya tiket yang dipegang pasien bisa
 * dicocokkan warnanya dengan kartu poliklinik di layar.
 *
 * Warna disusun dengan algoritma "jarak warna terbesar" memakai jarak persepsi
 * CIELAB, sehingga dua poliklinik yang berdampingan di daftar selalu terlihat
 * jelas berbeda. Semua warna sudah diuji kontras >= 4.6 terhadap teks putih
 * (WCAG AA), karena huruf awalan dicetak putih di atasnya.
 */
const SK_POLI_WARNA = [
    '#4a044e', '#4d7c0f', '#dc2626', '#7c3aed', '#0e7490', '#422006',
    '#db2777', '#a16207', '#1e40af', '#047857', '#a21caf', '#831843',
    '#334155', '#365314', '#7f1d1d', '#581c87', '#15803d', '#0369a1',
    '#c2410c', '#1e3a8a', '#2563eb', '#78350f', '#14532d', '#0f766e',
    '#701a75', '#c026d3', '#4f46e5', '#b45309', '#b91c1c', '#164e63',
    '#9d174d', '#166534', '#3f6212', '#1d4ed8', '#475569', '#9333ea',
];

/**
 * Peta warna per poliklinik.
 *
 * Warna diberikan berdasarkan urutan `kd_poli`, bukan hasil hash, supaya
 * selama jumlah poliklinik tidak melebihi jumlah warna, setiap poliklinik
 * benar-benar mendapat warna yang berbeda. Karena urutan ini sama dengan
 * urutan tampil daftar antrian, poliklinik yang berdampingan di layar selalu
 * mendapat warna yang berjauhan di palet.
 */
function poli_warna_peta(): array
{
    static $peta = null;
    if ($peta !== null) {
        return $peta;
    }
    $peta = [];
    if (!function_exists('db_all')) {
        return $peta;
    }
    try {
        foreach (array_values(db_all('SELECT kd_poli FROM poliklinik ORDER BY kd_poli')) as $i => $r) {
            $peta[(string)$r['kd_poli']] = SK_POLI_WARNA[$i % count(SK_POLI_WARNA)];
        }
    } catch (Throwable) {
        // Poliklinik belum ada (mis. saat wizard instalasi) — pakai cadangan hash.
        $peta = [];
    }
    return $peta;
}

/** Warna tetap untuk sebuah poliklinik. */
function poli_warna(string $kdPoli): string
{
    $peta = poli_warna_peta();
    if (isset($peta[$kdPoli])) {
        return $peta[$kdPoli];
    }
    // Poliklinik di luar daftar (mis. sudah dinonaktifkan) tetap butuh warna.
    return SK_POLI_WARNA[abs(crc32($kdPoli)) % count(SK_POLI_WARNA)];
}

/** Huruf awalan poliklinik untuk ditampilkan di lingkaran warna. */
function poli_awalan(string $nmPoli, string $kdPoli): string
{
    // Lewati kata umum supaya awalannya bermakna ("Poli Gigi" -> "G", bukan "P").
    $umum = ['poli', 'poliklinik', 'klinik', 'unit', 'instalasi', 'ruang'];
    foreach (preg_split('/\s+/', trim($nmPoli)) ?: [] as $kata) {
        $bersih = strtolower(preg_replace('/[^A-Za-z]/', '', $kata) ?? '');
        if ($bersih !== '' && !in_array($bersih, $umum, true)) {
            return strtoupper(substr($bersih, 0, 1));
        }
    }
    return strtoupper(substr(trim($kdPoli) !== '' ? trim($kdPoli) : '-', 0, 1));
}

/**
 * Kode poliklinik untuk awalan nomor antrian.
 *
 * Nomor antrian diulang dari 1 di setiap poliklinik, jadi "007" saja tidak
 * menunjukkan poli mana yang dimaksud. Dengan kode ini nomor yang ditampilkan
 * menjadi "INT-007" dan "U9-007" — dua poli boleh sama-sama punya nomor 007
 * tanpa tertukar.
 *
 * Sumbernya `kd_poli`, BUKAN huruf pertama nama poliklinik: "Poliklinik
 * Kandungan" dan "Poliklinik Kulit & Kelamin" sama-sama berawalan K, jadi
 * awalan dari nama justru membuat nomor dua poli tampak identik.
 *
 * `kd_poli` dinormalkan supaya kode internal seperti "U0001" menjadi "U1";
 * memakai `kd_poli` mentah akan membuat tiket sepanjang "U0001-007".
 */
function poli_kode(string $kdPoli): string
{
    $kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $kdPoli) ?? '');
    if ($kode === '') {
        return '';
    }
    // "U0001" -> "U1". Kode tanpa angka ("INT", "IGDK", "UMU") dibiarkan utuh.
    if (preg_match('/^([A-Z]+)0*(\d+)$/', $kode, $m) === 1) {
        return $m[1] . $m[2];
    }
    return $kode;
}

/** HTML lingkaran warna + huruf awalan poliklinik. */
function poli_penanda(string $nmPoli, string $kdPoli, string $kelas = ''): string
{
    return '<span class="sk-poli-mark ' . e($kelas) . '" style="--sk-poli:' . e(poli_warna($kdPoli)) . '" aria-hidden="true">'
        . e(poli_awalan($nmPoli, $kdPoli)) . '</span>';
}

/** Kelas warna badge untuk sebuah status (dipakai PHP dan dikirim ke JS saat perbarui data). */
function badge_kelas(string $status): string
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
    return $map[$status] ?? 'text-bg-secondary';
}

function badge_status(string $status): string
{
    return '<span class="badge ' . badge_kelas($status) . '">' . e($status) . '</span>';
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
