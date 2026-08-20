<?php
declare(strict_types=1);
$pageTitle = 'Akses Ditolak';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-body text-center py-5">
    <i class="bi bi-shield-lock text-danger" style="font-size:4rem"></i>
    <h2 class="mt-3">403 &mdash; Akses Ditolak</h2>
    <p class="text-muted">
      Akun <strong><?= e(auth_user()['username'] ?? '') ?></strong> (<?= e(auth_level_label()) ?>)
      tidak memiliki izin untuk membuka modul ini.<br />
      Hubungi administrator untuk menambahkan hak akses melalui menu Manajemen Pengguna.
    </p>
    <a href="<?= e(url('dashboard')) ?>" class="btn btn-primary"><i class="bi bi-house me-1"></i>Kembali ke Dashboard</a>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
