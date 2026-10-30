<?php
// Modul Data Referensi — satu halaman untuk seluruh tabel acuan skema sik.
// Daftar tabel, kolom, dan panjangnya ada di includes/referensi.php.
declare(strict_types=1);

require_once __DIR__ . '/../includes/referensi.php';

$menuRef = referensi_menu();
if (!$menuRef) {
    $pageTitle = 'Data Referensi';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="card"><div class="card-body text-center py-5">
      <i class="bi bi-shield-lock text-danger" style="font-size:3rem"></i>
      <h4 class="mt-3">Tidak ada grup data referensi yang dapat Anda buka</h4>
      <p class="text-muted mb-0">Hubungi administrator untuk menambahkan izin modul terkait.</p>
    </div></div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$grupAktif = $_GET['grup'] ?? array_key_first($menuRef);
if (!isset($menuRef[$grupAktif])) {
    $grupAktif = array_key_first($menuRef);
}
$tabelGrup = $menuRef[$grupAktif];
$tabelAktif = $_GET['tabel'] ?? $tabelGrup[0]['slug'];
$def = referensi_ambil($tabelAktif);
// Pastikan tabel yang diminta memang ada di grup aktif dan boleh dibuka
if ($def === null || $def['grup'] !== $grupAktif) {
    $tabelAktif = $tabelGrup[0]['slug'];
    $def = referensi_ambil($tabelAktif);
}

$pk = $def['pk'];
$kolom = $def['kolom'];
$urlDasar = ['grup' => $grupAktif, 'tabel' => $tabelAktif];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'delete') {
        $nilai = $_POST['pk'] ?? '';
        $rujukan = referensi_rujukan($def['tabel'], $nilai);
        if ($rujukan) {
            flash_set('danger', referensi_pesan_rujukan($def['label'], $rujukan));
        } else {
            $galat = db_coba(fn() => db_exec("DELETE FROM `{$def['tabel']}` WHERE `$pk` = ?", [$nilai]));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "{$def['label']} $nilai dihapus.");
        }
        redirect(url('referensi', $urlDasar));
    }

    if ($act === 'save') {
        $isEdit = ($_POST['mode'] ?? '') === 'edit';
        $nilaiPk = trim((string)($_POST['pk'] ?? ''));

        // Kumpulkan dan validasi nilai kolom non-PK
        $data = [];
        foreach ($kolom as $k) {
            $raw = trim((string)($_POST[$k['nama']] ?? ''));
            $label = $k['label'];
            if (!empty($k['wajib']) && $raw === '') {
                flash_set('danger', "$label wajib diisi.");
                redirect(url('referensi', $urlDasar + ['action' => 'form']));
            }
            if (isset($k['maks']) && strlen($raw) > (int)$k['maks']) {
                flash_set('danger', "$label maksimal {$k['maks']} karakter.");
                redirect(url('referensi', $urlDasar + ['action' => 'form']));
            }
            $data[$k['nama']] = match ($k['tipe']) {
                'angka' => (int)$raw,
                'desimal' => (float)$raw,
                default => $raw,
            };
        }
        if (!$def['auto']) {
            if ($nilaiPk === '') {
                flash_set('danger', "Kode {$def['label']} wajib diisi.");
                redirect(url('referensi', $urlDasar + ['action' => 'form']));
            }
            // Batas panjang kode diambil dari skema, jadi tidak perlu ditulis ulang di sini
            $batas = (int)db_val(
                'SELECT character_maximum_length FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [DB_NAME, $def['tabel'], $pk]
            );
            if ($batas > 0 && strlen($nilaiPk) > $batas) {
                flash_set('danger', "Kode {$def['label']} maksimal $batas karakter.");
                redirect(url('referensi', $urlDasar + ['action' => 'form']));
            }
        }

        $namaKolom = array_keys($data);
        if ($isEdit) {
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", $namaKolom));
            $params = array_values($data);
            $params[] = $nilaiPk;
            $galat = db_coba(fn() => db_exec("UPDATE `{$def['tabel']}` SET $set WHERE `$pk` = ?", $params));
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "{$def['label']} $nilaiPk diperbarui.");
        } else {
            if (!$namaKolom) {
                $galat = db_coba(fn() => db_exec("INSERT INTO `{$def['tabel']}` (`$pk`) VALUES (?)", [$nilaiPk]));
            } else {
                $daftar = '`' . implode('`, `', $namaKolom) . '`';
                $tanda = implode(', ', array_fill(0, count($namaKolom), '?'));
                $params = array_values($data);
                if ($def['auto']) {
                    $sql = "INSERT INTO `{$def['tabel']}` ($daftar) VALUES ($tanda)";
                } else {
                    $sql = "INSERT INTO `{$def['tabel']}` (`$pk`, $daftar) VALUES (?, $tanda)";
                    array_unshift($params, $nilaiPk);
                }
                $galat = db_coba(fn() => db_exec($sql, $params));
            }
            flash_set($galat === null ? 'success' : 'danger', $galat ?? "{$def['label']} ditambahkan.");
        }
        redirect(url('referensi', $urlDasar));
    }
}

// ---- Form tambah / ubah ----
if (($_GET['action'] ?? '') === 'form') {
    $editPk = $_GET['pk'] ?? null;
    $baris = null;
    if ($editPk !== null && $editPk !== '') {
        $baris = db_row("SELECT * FROM `{$def['tabel']}` WHERE `$pk` = ?", [$editPk]);
    }
    if (!$baris) {
        $baris = [$pk => ''];
        foreach ($kolom as $k) {
            $baris[$k['nama']] = '';
        }
        $editPk = null;
    }
    $pageTitle = 'Form ' . $def['label'];
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <form method="post" action="<?= e(url('referensi', $urlDasar)) ?>" class="card card-primary col-lg-8">
      <input type="hidden" name="act" value="save" />
      <input type="hidden" name="mode" value="<?= $editPk !== null ? 'edit' : 'add' ?>" />
      <div class="card-header"><h3 class="card-title"><?= $editPk !== null ? 'Ubah' : 'Tambah' ?> <?= e($def['label']) ?></h3></div>
      <div class="card-body">
        <div class="row g-3">
          <?php if (!$def['auto']): ?>
            <div class="col-md-6">
              <label class="form-label">Kode <?= e($def['label']) ?> *</label>
              <input class="form-control" name="pk" value="<?= e((string)($baris[$pk] ?? '')) ?>" <?= $editPk !== null ? 'readonly' : '' ?> required />
            </div>
          <?php elseif ($editPk !== null): ?>
            <input type="hidden" name="pk" value="<?= e((string)($baris[$pk] ?? '')) ?>" />
          <?php endif; ?>
          <?php foreach ($kolom as $k): ?>
            <div class="col-md-6">
              <label class="form-label"><?= e($k['label']) ?><?= !empty($k['wajib']) ? ' *' : '' ?></label>
              <input class="form-control" name="<?= e($k['nama']) ?>"
                     <?= isset($k['maks']) ? 'maxlength="' . (int)$k['maks'] . '"' : '' ?>
                     <?= $k['tipe'] === 'angka' ? 'type="number"' : ($k['tipe'] === 'desimal' ? 'type="number" step="0.01"' : '') ?>
                     value="<?= e((string)($baris[$k['nama']] ?? '')) ?>"
                     <?= !empty($k['wajib']) ? 'required' : '' ?> />
            </div>
          <?php endforeach; ?>
          <?php if (!$kolom): ?>
            <div class="col-12"><p class="text-muted mb-0">Tabel ini hanya menyimpan kode/nama tanpa kolom tambahan.</p></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= e(url('referensi', $urlDasar)) ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

// ---- Daftar ----
$pageTitle = 'Data Referensi — ' . $def['label'];
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
    $suka = [];
    $suka[] = "`$pk` LIKE ?";
    $params[] = "%$q%";
    foreach ($kolom as $k) {
        if (in_array($k['tipe'], ['teks'], true)) {
            $suka[] = "`{$k['nama']}` LIKE ?";
            $params[] = "%$q%";
        }
    }
    $where = 'WHERE (' . implode(' OR ', $suka) . ')';
}
$total = (int)db_val("SELECT COUNT(*) FROM `{$def['tabel']}` $where", $params);
$perPage = 25;
$hal = max(1, (int)($_GET['hal'] ?? 1));
$offset = ($hal - 1) * $perPage;
$urut = $def['urut'] ?? $pk;
$baris = db_all("SELECT * FROM `{$def['tabel']}` $where ORDER BY `$urut` LIMIT $perPage OFFSET $offset", $params);
$filterParams = $q !== '' ? ['q' => $q] : [];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row">
  <div class="col-lg-3">
    <div class="card">
      <div class="card-header"><h3 class="card-title mb-0">Grup Data Referensi</h3></div>
      <div class="card-body p-0">
        <ul class="nav nav-pills flex-column p-2 gap-1">
          <?php foreach ($menuRef as $grup => $daftar): ?>
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center gap-2 <?= $grup === $grupAktif ? 'active' : '' ?>"
                 href="<?= e(url('referensi', ['grup' => $grup])) ?>">
                <i class="bi <?= e(REFERENSI_GRUP[$grup]['ikon']) ?>"></i>
                <?= e(REFERENSI_GRUP[$grup]['label']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3 class="card-title mb-0"><?= e(REFERENSI_GRUP[$grupAktif]['label']) ?></h3></div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($tabelGrup as $t): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $t['slug'] === $tabelAktif ? 'active' : '' ?>"
               href="<?= e(url('referensi', ['grup' => $grupAktif, 'tabel' => $t['slug']])) ?>">
              <?= e($t['label']) ?>
              <span class="badge <?= $t['slug'] === $tabelAktif ? 'bg-body-secondary text-body-secondary' : 'text-bg-secondary' ?>"><?= (int)db_val("SELECT COUNT(*) FROM `{$t['tabel']}`") ?></span>
            </a>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-9">
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h3 class="card-title mb-0"><?= e($def['label']) ?> (<?= number_format($total, 0, ',', '.') ?>)</h3>
          <div class="d-flex flex-wrap gap-2">
            <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
              <input type="hidden" name="page" value="referensi" />
              <input type="hidden" name="grup" value="<?= e($grupAktif) ?>" />
              <input type="hidden" name="tabel" value="<?= e($tabelAktif) ?>" />
              <input class="form-control form-control-sm" name="q" placeholder="Cari..." value="<?= e($q) ?>" />
              <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            </form>
            <a href="<?= e(url('referensi', $urlDasar + ['action' => 'form'])) ?>" class="btn btn-sm btn-primary">
              <i class="bi bi-plus-lg me-1"></i>Tambah
            </a>
          </div>
        </div>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr>
              <th><?= e($def['auto'] ? 'ID' : 'Kode') ?></th>
              <?php foreach ($kolom as $k): ?>
                <th><?= e($k['label']) ?></th>
              <?php endforeach; ?>
              <th style="width:130px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($baris as $r): ?>
              <tr>
                <td><span class="badge text-bg-secondary"><?= e((string)$r[$pk]) ?></span></td>
                <?php foreach ($kolom as $k): ?>
                  <td>
                    <?php
                    $nilai = $r[$k['nama']] ?? '';
                    echo e($k['tipe'] === 'desimal' && $nilai !== '' && $nilai !== null
                        ? 'Rp ' . number_format((float)$nilai, 0, ',', '.')
                        : (string)$nilai);
                    ?>
                  </td>
                <?php endforeach; ?>
                <td>
                  <a class="btn btn-sm btn-warning" href="<?= e(url('referensi', $urlDasar + ['action' => 'form', 'pk' => $r[$pk]])) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" action="<?= e(url('referensi', $urlDasar)) ?>" class="d-inline"
                        onsubmit="return confirm('Hapus <?= e($def['label']) ?> <?= e((string)$r[$pk]) ?>?')">
                    <input type="hidden" name="act" value="delete" />
                    <input type="hidden" name="pk" value="<?= e((string)$r[$pk]) ?>" />
                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$baris): ?>
              <tr><td colspan="<?= count($kolom) + 2 ?>" class="text-center text-muted py-4">Belum ada data.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($total > $perPage): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <small class="text-muted">Menampilkan <?= count($baris) ?> dari <?= number_format($total, 0, ',', '.') ?> baris</small>
          <?= paginate($total, $perPage, $hal, 'referensi', $urlDasar + $filterParams) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
