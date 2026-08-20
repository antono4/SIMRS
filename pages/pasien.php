<?php
// Modul Master Pasien (CRUD) — tabel pasien skema sik
declare(strict_types=1);

$action = $_GET['action'] ?? 'list';
$perPage = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $rm = $_POST['no_rkm_medis'] ?? '';
        $dipakai = (int)db_val('SELECT COUNT(*) FROM reg_periksa WHERE no_rkm_medis = ?', [$rm]);
        if ($dipakai > 0) {
            flash_set('danger', "Pasien $rm tidak dapat dihapus karena memiliki $dipakai riwayat registrasi.");
        } else {
            db_exec('DELETE FROM pasien WHERE no_rkm_medis = ?', [$rm]);
            flash_set('success', "Data pasien $rm berhasil dihapus.");
        }
        redirect(url('pasien'));
    }

    if ($act === 'save') {
        $rm = trim($_POST['no_rkm_medis'] ?? '');
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $tglLahir = $_POST['tgl_lahir'] ?: null;
        [$umurTh] = umur_dari($tglLahir);
        $umurText = '';
        if ($tglLahir) {
            $d = (new DateTime($tglLahir))->diff(new DateTime('today'));
            $umurText = "{$d->y} Th {$d->m} Bl {$d->d} Hr";
        }
        $data = [
            'nm_pasien' => trim($_POST['nm_pasien'] ?? ''),
            'no_ktp' => trim($_POST['no_ktp'] ?? ''),
            'jk' => $_POST['jk'] ?? 'L',
            'tmp_lahir' => trim($_POST['tmp_lahir'] ?? ''),
            'tgl_lahir' => $tglLahir,
            'nm_ibu' => trim($_POST['nm_ibu'] ?? '-'),
            'alamat' => trim($_POST['alamat'] ?? ''),
            'gol_darah' => $_POST['gol_darah'] ?? '-',
            'pekerjaan' => trim($_POST['pekerjaan'] ?? '-'),
            'stts_nikah' => $_POST['stts_nikah'] ?? 'BELUM MENIKAH',
            'agama' => strtoupper(trim($_POST['agama'] ?? '-')),
            'tgl_daftar' => $_POST['tgl_daftar'] ?: date('Y-m-d'),
            'no_tlp' => trim($_POST['no_tlp'] ?? '-'),
            'umur' => $umurText,
            'pnd' => $_POST['pnd'] ?? '-',
            'keluarga' => $_POST['keluarga'] ?? 'DIRI SENDIRI',
            'namakeluarga' => trim($_POST['namakeluarga'] ?? '-'),
            'kd_pj' => $_POST['kd_pj'] ?? '-',
            'no_peserta' => trim($_POST['no_peserta'] ?? '-'),
            'kd_kel' => (int)($_POST['kd_kel'] ?? 1),
            'kd_kec' => (int)($_POST['kd_kec'] ?? 1),
            'kd_kab' => (int)($_POST['kd_kab'] ?? 1),
            'pekerjaanpj' => trim($_POST['pekerjaanpj'] ?? '-'),
            'alamatpj' => trim($_POST['alamatpj'] ?? '-'),
            'kelurahanpj' => trim($_POST['kelurahanpj'] ?? '-'),
            'kecamatanpj' => trim($_POST['kecamatanpj'] ?? '-'),
            'kabupatenpj' => trim($_POST['kabupatenpj'] ?? '-'),
            'perusahaan_pasien' => $_POST['perusahaan_pasien'] ?? '-',
            'suku_bangsa' => (int)($_POST['suku_bangsa'] ?? 1),
            'bahasa_pasien' => (int)($_POST['bahasa_pasien'] ?? 1),
            'cacat_fisik' => (int)($_POST['cacat_fisik'] ?? 1),
            'email' => trim($_POST['email'] ?? '-'),
            'nip' => trim($_POST['nip'] ?? '-'),
            'kd_prop' => (int)($_POST['kd_prop'] ?? 1),
            'propinsipj' => trim($_POST['propinsipj'] ?? '-'),
        ];

        if ($data['nm_pasien'] === '') {
            flash_set('danger', 'Nama pasien wajib diisi.');
            redirect(url('pasien', ['action' => 'form'] + ($isEdit ? ['rm' => $rm] : [])));
        }

        if ($isEdit) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            $data['no_rkm_medis'] = $rm;
            db_exec("UPDATE pasien SET $set WHERE no_rkm_medis = :no_rkm_medis", $data);
            flash_set('success', "Data pasien $rm berhasil diperbarui.");
        } else {
            $data['no_rkm_medis'] = $rm;
            $cols = implode(', ', array_keys($data));
            $marks = ':' . implode(', :', array_keys($data));
            db_exec("INSERT INTO pasien ($cols) VALUES ($marks)", $data);
            flash_set('success', "Pasien baru dengan No. RM $rm berhasil ditambahkan.");
        }
        redirect(url('pasien'));
    }
}

if ($action === 'form') {
    $pageTitle = 'Form Pasien';
    $rm = $_GET['rm'] ?? null;
    $pasien = $rm ? db_row('SELECT * FROM pasien WHERE no_rkm_medis = ?', [$rm]) : null;

    if (!$pasien) {
        // Nomor RM otomatis ala Khanza: nomor terakhir + 1, 6 digit
        $last = db_val("SELECT no_rkm_medis FROM pasien WHERE no_rkm_medis REGEXP '^[0-9]+$' ORDER BY CAST(no_rkm_medis AS UNSIGNED) DESC LIMIT 1");
        $nextRm = str_pad((string)((int)$last + 1), 6, '0', STR_PAD_LEFT);
        $pasien = [
            'no_rkm_medis' => $nextRm, 'nm_pasien' => '', 'no_ktp' => '', 'jk' => 'L',
            'tmp_lahir' => '', 'tgl_lahir' => '', 'nm_ibu' => '', 'alamat' => '', 'gol_darah' => '-',
            'pekerjaan' => '', 'stts_nikah' => 'BELUM MENIKAH', 'agama' => 'ISLAM', 'tgl_daftar' => date('Y-m-d'),
            'no_tlp' => '', 'pnd' => '-', 'keluarga' => 'DIRI SENDIRI', 'namakeluarga' => '', 'kd_pj' => '-',
            'no_peserta' => '', 'kd_kel' => 1, 'kd_kec' => 1, 'kd_kab' => 1, 'kd_prop' => 1,
            'pekerjaanpj' => '', 'alamatpj' => '', 'kelurahanpj' => '', 'kecamatanpj' => '', 'kabupatenpj' => '',
            'propinsipj' => '', 'perusahaan_pasien' => '-', 'suku_bangsa' => 1, 'bahasa_pasien' => 1,
            'cacat_fisik' => 1, 'email' => '', 'nip' => '',
        ];
    }

    $penjabList = db_all("SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab");
    $kelurahan = db_all('SELECT kd_kel, nm_kel FROM kelurahan ORDER BY nm_kel');
    $kecamatan = db_all('SELECT kd_kec, nm_kec FROM kecamatan ORDER BY nm_kec');
    $kabupaten = db_all('SELECT kd_kab, nm_kab FROM kabupaten ORDER BY nm_kab');
    $propinsi = db_all('SELECT kd_prop, nm_prop FROM propinsi ORDER BY nm_prop');
    $suku = db_all('SELECT id, nama_suku_bangsa FROM suku_bangsa ORDER BY nama_suku_bangsa');
    $bahasa = db_all('SELECT id, nama_bahasa FROM bahasa_pasien ORDER BY nama_bahasa');
    $cacat = db_all('SELECT id, nama_cacat FROM cacat_fisik ORDER BY nama_cacat');
    $perusahaan = db_all('SELECT kode_perusahaan, nama_perusahaan FROM perusahaan_pasien ORDER BY nama_perusahaan');

    require __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('pasien')) ?>">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $rm ? 'edit' : 'add' ?>" />
      <div class="row">
        <div class="col-lg-8">
          <div class="card card-primary mb-4">
            <div class="card-header"><h3 class="card-title">Identitas Pasien</h3></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">No. Rekam Medis</label>
                  <input class="form-control" name="no_rkm_medis" value="<?= e($pasien['no_rkm_medis']) ?>" <?= $rm ? 'readonly' : '' ?> required />
                </div>
                <div class="col-md-8">
                  <label class="form-label">Nama Pasien *</label>
                  <input class="form-control" name="nm_pasien" value="<?= e($pasien['nm_pasien']) ?>" required />
                </div>
                <div class="col-md-4">
                  <label class="form-label">No. KTP</label>
                  <input class="form-control" name="no_ktp" value="<?= e($pasien['no_ktp']) ?>" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Jenis Kelamin</label>
                  <select class="form-select" name="jk">
                    <option value="L" <?= $pasien['jk'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= $pasien['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Golongan Darah</label>
                  <select class="form-select" name="gol_darah">
                    <?php foreach (['-', 'A', 'B', 'O', 'AB'] as $g): ?>
                      <option <?= $pasien['gol_darah'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tempat Lahir</label>
                  <input class="form-control" name="tmp_lahir" value="<?= e($pasien['tmp_lahir']) ?>" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tanggal Lahir</label>
                  <input class="form-control" type="date" name="tgl_lahir" value="<?= e($pasien['tgl_lahir']) ?>" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Nama Ibu Kandung</label>
                  <input class="form-control" name="nm_ibu" value="<?= e($pasien['nm_ibu']) ?>" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Alamat</label>
                  <input class="form-control" name="alamat" value="<?= e($pasien['alamat']) ?>" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">No. Telepon</label>
                  <input class="form-control" name="no_tlp" value="<?= e($pasien['no_tlp']) ?>" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tanggal Daftar</label>
                  <input class="form-control" type="date" name="tgl_daftar" value="<?= e($pasien['tgl_daftar']) ?>" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Agama</label>
                  <input class="form-control" name="agama" value="<?= e($pasien['agama']) ?>" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Pendidikan</label>
                  <select class="form-select" name="pnd">
                    <?php foreach (['-', 'TS', 'TK', 'SD', 'SMP', 'SMA', 'SLTA/SEDERAJAT', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'] as $p): ?>
                      <option <?= $pasien['pnd'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status Nikah</label>
                  <select class="form-select" name="stts_nikah">
                    <?php foreach (['BELUM MENIKAH', 'MENIKAH', 'JANDA', 'DUDHA', 'JOMBLO'] as $s): ?>
                      <option <?= $pasien['stts_nikah'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Pekerjaan</label>
                  <input class="form-control" name="pekerjaan" value="<?= e($pasien['pekerjaan']) ?>" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Suku Bangsa</label>
                  <select class="form-select" name="suku_bangsa">
                    <?php foreach ($suku as $s): ?>
                      <option value="<?= $s['id'] ?>" <?= (int)$pasien['suku_bangsa'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nama_suku_bangsa']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Bahasa</label>
                  <select class="form-select" name="bahasa_pasien">
                    <?php foreach ($bahasa as $b): ?>
                      <option value="<?= $b['id'] ?>" <?= (int)$pasien['bahasa_pasien'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['nama_bahasa']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Cacat Fisik</label>
                  <select class="form-select" name="cacat_fisik">
                    <?php foreach ($cacat as $c): ?>
                      <option value="<?= $c['id'] ?>" <?= (int)$pasien['cacat_fisik'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nama_cacat']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Email</label>
                  <input class="form-control" name="email" value="<?= e($pasien['email']) ?>" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">NIP/NIK</label>
                  <input class="form-control" name="nip" value="<?= e($pasien['nip']) ?>" />
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card card-success mb-4">
            <div class="card-header"><h3 class="card-title">Penanggung Jawab & Wilayah</h3></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Cara Bayar</label>
                <select class="form-select" name="kd_pj">
                  <?php foreach ($penjabList as $pj): ?>
                    <option value="<?= e($pj['kd_pj']) ?>" <?= $pasien['kd_pj'] === $pj['kd_pj'] ? 'selected' : '' ?>><?= e($pj['png_jawab']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">No. Peserta (BPJS/dll)</label>
                <input class="form-control" name="no_peserta" value="<?= e($pasien['no_peserta']) ?>" />
              </div>
              <div class="mb-3">
                <label class="form-label">Hubungan Keluarga</label>
                <select class="form-select" name="keluarga">
                  <?php foreach (['DIRI SENDIRI', 'AYAH', 'IBU', 'ISTRI', 'SUAMI', 'SAUDARA', 'ANAK', 'LAIN-LAIN'] as $k): ?>
                    <option <?= $pasien['keluarga'] === $k ? 'selected' : '' ?>><?= $k ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Keluarga/PJ</label>
                <input class="form-control" name="namakeluarga" value="<?= e($pasien['namakeluarga']) ?>" />
              </div>
              <div class="mb-3">
                <label class="form-label">Kelurahan</label>
                <select class="form-select" name="kd_kel">
                  <?php foreach ($kelurahan as $k): ?>
                    <option value="<?= $k['kd_kel'] ?>" <?= (int)$pasien['kd_kel'] === (int)$k['kd_kel'] ? 'selected' : '' ?>><?= e($k['nm_kel']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Kecamatan</label>
                <select class="form-select" name="kd_kec">
                  <?php foreach ($kecamatan as $k): ?>
                    <option value="<?= $k['kd_kec'] ?>" <?= (int)$pasien['kd_kec'] === (int)$k['kd_kec'] ? 'selected' : '' ?>><?= e($k['nm_kec']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Kabupaten</label>
                <select class="form-select" name="kd_kab">
                  <?php foreach ($kabupaten as $k): ?>
                    <option value="<?= $k['kd_kab'] ?>" <?= (int)$pasien['kd_kab'] === (int)$k['kd_kab'] ? 'selected' : '' ?>><?= e($k['nm_kab']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Propinsi</label>
                <select class="form-select" name="kd_prop">
                  <?php foreach ($propinsi as $p): ?>
                    <option value="<?= $p['kd_prop'] ?>" <?= (int)$pasien['kd_prop'] === (int)$p['kd_prop'] ? 'selected' : '' ?>><?= e($p['nm_prop']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Perusahaan/Instansi</label>
                <select class="form-select" name="perusahaan_pasien">
                  <?php foreach ($perusahaan as $p): ?>
                    <option value="<?= e($p['kode_perusahaan']) ?>" <?= $pasien['perusahaan_pasien'] === $p['kode_perusahaan'] ? 'selected' : '' ?>><?= e($p['nama_perusahaan']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="card card-secondary mb-4">
            <div class="card-header"><h3 class="card-title">Alamat Penanggung Jawab</h3></div>
            <div class="card-body">
              <div class="mb-2"><input class="form-control" name="pekerjaanpj" placeholder="Pekerjaan PJ" value="<?= e($pasien['pekerjaanpj']) ?>" /></div>
              <div class="mb-2"><input class="form-control" name="alamatpj" placeholder="Alamat PJ" value="<?= e($pasien['alamatpj']) ?>" /></div>
              <div class="mb-2"><input class="form-control" name="kelurahanpj" placeholder="Kelurahan PJ" value="<?= e($pasien['kelurahanpj']) ?>" /></div>
              <div class="mb-2"><input class="form-control" name="kecamatanpj" placeholder="Kecamatan PJ" value="<?= e($pasien['kecamatanpj']) ?>" /></div>
              <div class="mb-2"><input class="form-control" name="kabupatenpj" placeholder="Kabupaten PJ" value="<?= e($pasien['kabupatenpj']) ?>" /></div>
              <div><input class="form-control" name="propinsipj" placeholder="Propinsi PJ" value="<?= e($pasien['propinsipj']) ?>" /></div>
            </div>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
            <a href="<?= e(url('pasien')) ?>" class="btn btn-outline-secondary">Batal</a>
          </div>
        </div>
      </div>
    </form>
    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

// Daftar pasien
$pageTitle = 'Data Pasien';
$q = trim($_GET['q'] ?? '');
$hal = max(1, (int)($_GET['hal'] ?? 1));

$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE nm_pasien LIKE ? OR no_rkm_medis LIKE ? OR no_ktp LIKE ? OR alamat LIKE ?';
    $like = "%$q%";
    $params = [$like, $like, $like, $like];
}
$total = (int)db_val("SELECT COUNT(*) FROM pasien $where", $params);
$offset = ($hal - 1) * $perPage;
$rows = db_all(
    "SELECT p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir, p.alamat, p.no_tlp, pj.png_jawab
     FROM pasien p LEFT JOIN penjab pj ON pj.kd_pj = p.kd_pj
     $where ORDER BY p.no_rkm_medis DESC LIMIT $perPage OFFSET $offset",
    $params
);

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h3 class="card-title mb-0">Daftar Pasien (<?= number_format($total) ?>)</h3>
      <div class="d-flex gap-2">
        <form class="d-flex" method="get" action="index.php">
          <input type="hidden" name="page" value="pasien" />
          <input class="form-control form-control-sm me-2" name="q" placeholder="Cari nama / No. RM / KTP / alamat" value="<?= e($q) ?>" />
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= e(url('pasien', ['action' => 'form'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Pasien Baru</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>No. RM</th><th>Nama Pasien</th><th>L/P</th><th>Tgl. Lahir</th><th>Alamat</th><th>Telepon</th><th>Cara Bayar</th><th style="width:130px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['no_rkm_medis']) ?></span></td>
            <td><?= e($r['nm_pasien']) ?></td>
            <td><?= e($r['jk']) ?></td>
            <td><?= e(tgl_indo($r['tgl_lahir'])) ?></td>
            <td><?= e($r['alamat']) ?></td>
            <td><?= e($r['no_tlp']) ?></td>
            <td><?= e($r['png_jawab'] ?? '-') ?></td>
            <td>
              <a class="btn btn-sm btn-warning" href="<?= e(url('pasien', ['action' => 'form', 'rm' => $r['no_rkm_medis']])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= e(url('pasien')) ?>" class="d-inline" onsubmit="return confirm('Hapus pasien <?= e($r['no_rkm_medis']) ?>?')">
                <input type="hidden" name="act" value="delete" />
                <input type="hidden" name="no_rkm_medis" value="<?= e($r['no_rkm_medis']) ?>" />
                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-end">
    <?= paginate($total, $perPage, $hal, 'pasien', ['q' => $q]) ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
