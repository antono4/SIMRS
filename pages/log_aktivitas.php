<?php
// Log aktivitas — jejak audit perubahan data. Hanya administrator.
declare(strict_types=1);

$user = auth_user();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    $pageTitle = 'Akses Ditolak';
    require_once __DIR__ . '/403.php';
    return;
}

audit_bootstrap();

$q = trim($_GET['q'] ?? '');
$modul = trim($_GET['modul'] ?? '');
$aksi = trim($_GET['aksi'] ?? '');
$hal = max(1, (int)($_GET['hal'] ?? 1));
$perPage = 50;

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (pengguna LIKE ? OR kunci LIKE ? OR keterangan LIKE ?)';
    $like = "%$q%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($modul !== '') {
    $where .= ' AND modul = ?';
    $params[] = $modul;
}
if ($aksi !== '') {
    $where .= ' AND aksi = ?';
    $params[] = $aksi;
}

$total = (int)db_val("SELECT COUNT(*) FROM log_aktivitas $where", $params);
$offset = ($hal - 1) * $perPage;

if (($_GET['ekspor'] ?? '') === 'csv') {
    audit_catat('log_aktivitas', 'cetak', 'log_aktivitas', $modul, 'Ekspor CSV ' . $total . ' baris');
    ekspor_csv(
        ekspor_nama('log-aktivitas'),
        ['Waktu', 'Pengguna', 'Peran', 'Modul', 'Aksi', 'Tabel', 'Kunci', 'Keterangan', 'Alamat IP'],
        ekspor_batch(
            "SELECT waktu, pengguna, peran, modul, aksi, `tabel`, kunci, keterangan, alamat_ip
             FROM log_aktivitas $where ORDER BY waktu DESC, id DESC",
            $params
        )
    );
}

$rows = db_all(
    "SELECT * FROM log_aktivitas $where ORDER BY waktu DESC, id DESC LIMIT $perPage OFFSET $offset",
    $params
);
$modulList = db_all('SELECT DISTINCT modul FROM log_aktivitas ORDER BY modul');
$aksiList = db_all('SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi');

$pageTitle = 'Log Aktivitas';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h3 class="card-title mb-0">Log Aktivitas (<?= number_format($total, 0, ',', '.') ?>)</h3>
      <div class="d-flex flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
          <input type="hidden" name="page" value="log_aktivitas" />
          <input class="form-control form-control-sm" style="min-width:10rem" name="q" placeholder="Cari pengguna / kunci / keterangan" value="<?= e($q) ?>" />
          <select class="form-select form-select-sm" name="modul" style="width:auto">
            <option value="">Semua modul</option>
            <?php foreach ($modulList as $m): ?>
              <option value="<?= e($m['modul']) ?>" <?= $modul === $m['modul'] ? 'selected' : '' ?>><?= e($m['modul']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-select form-select-sm" name="aksi" style="width:auto">
            <option value="">Semua aksi</option>
            <?php foreach ($aksiList as $a): ?>
              <option value="<?= e($a['aksi']) ?>" <?= $aksi === $a['aksi'] ? 'selected' : '' ?>><?= e(audit_aksi_label($a['aksi'])) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <?= ekspor_tombol('log_aktivitas', array_filter(['q' => $q, 'modul' => $modul, 'aksi' => $aksi], fn($v) => $v !== '')) ?>
      </div>
    </div>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-sm table-striped table-hover mb-0">
      <thead>
        <tr><th>Waktu</th><th>Pengguna</th><th>Modul</th><th>Aksi</th><th>Tabel</th><th>Kunci</th><th>Keterangan</th><th>IP</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-nowrap"><?= e(date('d/m/Y H:i:s', strtotime((string)$r['waktu']))) ?></td>
            <td><?= e($r['pengguna']) ?><?= $r['peran'] !== '' ? ' <small class="text-muted">(' . e($r['peran']) . ')</small>' : '' ?></td>
            <td><span class="badge text-bg-light text-dark"><?= e($r['modul']) ?></span></td>
            <td><span class="badge <?= audit_aksi_kelas($r['aksi']) ?>"><?= e(audit_aksi_label($r['aksi'])) ?></span></td>
            <td><code><?= e($r['tabel']) ?></code></td>
            <td><?= e((string)$r['kunci']) ?></td>
            <td><?= e((string)$r['keterangan']) ?></td>
            <td class="text-muted small"><?= e((string)$r['alamat_ip']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-end">
    <?= paginate($total, $perPage, $hal, 'log_aktivitas', array_filter(['q' => $q, 'modul' => $modul, 'aksi' => $aksi], fn($v) => $v !== '')) ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
