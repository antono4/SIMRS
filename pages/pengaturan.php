<?php
// Modul Pengaturan Instansi — tabel setting aplikasi
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save') {
    $nama = trim($_POST['nama_instansi'] ?? '');
    if ($nama === '') {
        flash_set('danger', 'Nama rumah sakit wajib diisi.');
        redirect(url('pengaturan'));
    }
    setting_update([
        'nama_instansi' => $nama,
        'alamat_instansi' => trim($_POST['alamat_instansi'] ?? ''),
        'kabupaten' => trim($_POST['kabupaten'] ?? ''),
        'propinsi' => trim($_POST['propinsi'] ?? ''),
        'kontak' => trim($_POST['kontak'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ]);
    flash_set('success', 'Pengaturan instansi berhasil disimpan.');
    redirect(url('pengaturan'));
}

$pageTitle = 'Pengaturan Instansi';
$s = setting_rs() + ['nama_instansi' => '', 'alamat_instansi' => '', 'kabupaten' => '', 'propinsi' => '', 'kontak' => '', 'email' => ''];

require __DIR__ . '/../includes/header.php';
?>
<form method="post" action="<?= e(url('pengaturan')) ?>" class="card card-primary col-lg-7">
  <input type="hidden" name="act" value="save" />
  <div class="card-header"><h3 class="card-title"><i class="bi bi-hospital me-2"></i>Identitas Rumah Sakit</h3></div>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">Nama Rumah Sakit *</label>
      <input class="form-control form-control-lg" name="nama_instansi" maxlength="60" value="<?= e($s['nama_instansi']) ?>" required />
      <div class="form-text">Ditampilkan di sidebar, judul halaman, halaman login, dan footer.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Alamat</label>
      <input class="form-control" name="alamat_instansi" maxlength="150" value="<?= e($s['alamat_instansi']) ?>" />
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Kabupaten/Kota</label>
        <input class="form-control" name="kabupaten" maxlength="30" value="<?= e($s['kabupaten']) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Propinsi</label>
        <input class="form-control" name="propinsi" maxlength="30" value="<?= e($s['propinsi']) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Kontak / Telepon</label>
        <input class="form-control" name="kontak" maxlength="50" value="<?= e($s['kontak']) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" maxlength="50" value="<?= e($s['email']) ?>" />
      </div>
    </div>
  </div>
  <div class="card-footer">
    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
