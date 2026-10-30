<?php
/**
 * Ekspor data ke CSV — dipakai halaman daftar (pasien, obat, penyakit, dst).
 *
 * CSV dipilih daripada Excel/PDF karena tidak butuh pustaka tambahan dan tetap
 * bisa dibuka Excel, LibreOffice, maupun Google Sheets.
 */
declare(strict_types=1);

/**
 * Netralkan sel yang bisa dieksekusi sebagai rumus oleh Excel/LibreOffice.
 *
 * Nama pasien atau alamat yang diawali "=", "+", "-", atau "@" akan dijalankan
 * sebagai rumus saat berkas dibuka — termasuk rumus yang memanggil program luar
 * (DDE). Data pasien berasal dari input pengguna, jadi ini harus ditangani.
 *
 * "+" dan "-" hanya berbahaya bila bukan angka: "+cmd|..." dieksekusi, tetapi
 * "-5" dan "-" bukan. Data nyata banyak memakai "-" sebagai penanda kosong pada
 * kolom teks (telepon, nama ibu), jadi keduanya dibiarkan apa adanya agar tidak
 * berubah menjadi "'-".
 */
function ekspor_sel_aman(string $nilai): string
{
    if ($nilai === '') {
        return $nilai;
    }
    $awal = $nilai[0];
    if ($awal === '=' || $awal === '@' || $awal === "\t" || $awal === "\r") {
        return "'" . $nilai;
    }
    if ($awal === '+' || $awal === '-') {
        // Angka murni (termasuk "-", "-5", "+1.5", "-2e3") aman dibiarkan.
        if (preg_match('/^[+-]\d*\.?\d+([eE][+-]?\d+)?$/', $nilai) === 1 || $nilai === '+' || $nilai === '-') {
            return $nilai;
        }
        return "'" . $nilai;
    }
    return $nilai;
}

/**
 * Enkode satu baris CSV menurut RFC 4180.
 *
 * Tidak memakai fputcsv() karena parameternya berbeda antar-versi PHP: pada 8.4
 * menghilangkan argumen $escape memicu peringatan deprecation, sedangkan nilai
 * default barunya belum seragam di 8.2/8.3. Enkoder sendiri membuat hasilnya
 * sama di seluruh rentang versi yang didukung.
 *
 * @param array<int,string> $sel
 */
function ekspor_baris_csv(array $sel): string
{
    $keluar = [];
    foreach ($sel as $nilai) {
        if (strpbrk($nilai, "\",\r\n") !== false) {
            $keluar[] = '"' . str_replace('"', '""', $nilai) . '"';
        } else {
            $keluar[] = $nilai;
        }
    }
    return implode(',', $keluar) . "\r\n";
}

/**
 * Kirim satu berkas CSV sebagai unduhan, lalu hentikan skrip.
 *
 * $baris boleh berupa array (sudah dimuat semua) atau generator/iterator agar
 * tabel besar (mis. penyakit 39.000 baris) tidak perlu dimuat sekaligus.
 *
 * @param array<int,string>          $header Judul kolom.
 * @param iterable<int,array<mixed>> $baris  Baris data; urutan harus sama dengan $header.
 */
function ekspor_csv(string $namaBerkas, array $header, iterable $baris): never
{
    $namaBerkas = preg_replace('/[^A-Za-z0-9._-]/', '-', $namaBerkas) ?: 'ekspor';
    if (!str_ends_with(strtolower($namaBerkas), '.csv')) {
        $namaBerkas .= '.csv';
    }

    // Buang keluaran apa pun (spasi sebelum <?php, peringatan) agar berkas tidak rusak.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $namaBerkas . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }

    // BOM UTF-8: tanpa ini Excel menampilkan karakter beraksen sebagai mojibake.
    fwrite($out, "\xEF\xBB\xBF");
    fwrite($out, ekspor_baris_csv(array_map('ekspor_sel_aman', $header)));

    foreach ($baris as $r) {
        $sel = [];
        foreach ($r as $nilai) {
            if ($nilai === null) {
                $sel[] = '';
            } elseif (is_bool($nilai)) {
                $sel[] = $nilai ? '1' : '0';
            } else {
                $sel[] = ekspor_sel_aman((string)$nilai);
            }
        }
        fwrite($out, ekspor_baris_csv($sel));
    }

    fclose($out);
    exit;
}

/**
 * Tombol unduhan CSV untuk header kartu halaman daftar.
 * $params diteruskan apa adanya, jadi filter yang sedang aktif ikut terbawa.
 */
function ekspor_tombol(string $page, array $params = [], string $label = 'Ekspor CSV'): string
{
    $params['ekspor'] = 'csv';
    return '<a class="btn btn-sm btn-outline-success" href="' . e(url($page, $params)) . '">'
        . '<i class="bi bi-filetype-csv me-1"></i>' . e($label) . '</a>';
}

/**
 * Nama berkas ekspor bertanggal: "pasien-2026-10-30.csv".
 */
function ekspor_nama(string $dasar): string
{
    return $dasar . '-' . date('Y-m-d');
}

/**
 * Ambil baris sedikit demi sedikit memakai LIMIT/OFFSET.
 *
 * Dipakai ekspor tabel besar (penyakit ±39.000 baris, pasien ±9.800 baris) agar
 * seluruh isi tidak perlu dimuat ke memori sekaligus. Query tanpa buffering
 * (PDO::MYSQL_ATTR_USE_BUFFERED_QUERY) tidak dipakai karena setelannya berlaku
 * untuk seluruh koneksi, bukan hanya query ekspor ini.
 *
 * @param string            $sqlTanpaLimit Query lengkap tanpa LIMIT, boleh sudah berisi ORDER BY.
 * @param array<int,mixed>  $params
 * @return Generator<int,array<string,mixed>>
 */
function ekspor_batch(string $sqlTanpaLimit, array $params = [], int $ukuran = 500): Generator
{
    $offset = 0;
    while (true) {
        $baris = db_all($sqlTanpaLimit . ' LIMIT ' . $ukuran . ' OFFSET ' . $offset, $params);
        if ($baris === []) {
            return;
        }
        foreach ($baris as $r) {
            yield $r;
        }
        if (count($baris) < $ukuran) {
            return;
        }
        $offset += $ukuran;
    }
}
