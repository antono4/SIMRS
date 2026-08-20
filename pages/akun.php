<?php
// Modul User: kotak masuk pemberitahuan, info akun, dan ganti kata sandi
declare(strict_types=1);

$user = auth_user();
$username = $user['username'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'ganti_password') {
        $lama = (string)($_POST['pass_lama'] ?? '');
        $baru = (string)($_POST['pass_baru'] ?? '');
        $ulang = (string)($_POST['pass_ulang'] ?? '');
        if ($baru === '' || $baru !== $ulang) {
            flash_set('danger', 'Kata sandi baru kosong atau tidak sama dengan konfirmasi.');
        } elseif (!auth_attempt($username, $lama)) {
            flash_set('danger', 'Kata sandi lama salah.');
        } else {
            if ($role === 'admin') {
                db_exec('UPDATE admin SET passworde = AES_ENCRYPT(?, ?) WHERE CAST(AES_DECRYPT(usere, ?) AS CHAR) = ?',
                    [$baru, AES_KEY, AES_KEY, $username]);
            } else {
                db_exec('UPDATE user SET password = AES_ENCRYPT(?, ?) WHERE CAST(AES_DECRYPT(id_user, ?) AS CHAR) = ?',
                    [$baru, AES_KEY, AES_KEY, $username]);
            }
            flash_set('success', 'Kata sandi berhasil diganti.');
        }
        redirect(url('akun', ['tab' => 'password']));
    }
}

$tab = $_GET['tab'] ?? 'inbox';
$pageTitle = 'Akun Saya';

// Kotak masuk: kunjungan hari ini & pasien yang sedang dirawat (pemberitahuan operasional)
$kunjunganHariIni = db_all(
    "SELECT rp.no_rawat, rp.jam_reg, p.nm_pasien, pl.nm_poli, d.nm_dokter, rp.stts
     FROM reg_periksa rp
     JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
     JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
     JOIN dokter d ON d.kd_dokter = rp.kd_dokter
     WHERE rp.tgl_registrasi = CURDATE() AND rp.stts != 'Batal'
     ORDER BY rp.jam_reg DESC LIMIT 15"
);
$ranapAktif = db_all(
    "SELECT ki.no_rawat, ki.kd_kamar, ki.tgl_masuk, p.nm_pasien
     FROM kamar_inap ki
     JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
     JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
     WHERE ki.tgl_keluar = '0000-00-00' ORDER BY ki.tgl_masuk DESC LIMIT 10"
);
$belumBayar = (int)db_val("SELECT COUNT(*) FROM reg_periksa WHERE status_bayar = 'Belum Bayar' AND stts != 'Batal' AND tgl_registrasi = CURDATE()");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row">
  <div class="col-lg-3">
    <div class="card card-primary card-outline">
      <div class="card-body text-center">
        <i class="bi bi-person-circle" style="font-size:4rem"></i>
        <h5 class="mt-2 mb-0"><?= e($username) ?></h5>
        <span class="badge text-bg-<?= $role === 'admin' ? 'primary' : 'info' ?> mt-1"><?= e(auth_level_label()) ?></span>
        <ul class="list-group list-group-flush mt-3 text-start">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            Kunjungan Hari Ini <span class="badge text-bg-success rounded-pill"><?= count($kunjunganHariIni) ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            Sedang Dirawat <span class="badge text-bg-danger rounded-pill"><?= count($ranapAktif) ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            Belum Bayar (hari ini) <span class="badge text-bg-warning rounded-pill"><?= $belumBayar ?></span>
          </li>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-lg-9">
    <div class="card">
      <div class="card-header p-0 pt-1">
        <ul class="nav nav-tabs">
          <li class="nav-item"><a class="nav-link <?= $tab === 'inbox' ? 'active' : '' ?>" href="<?= e(url('akun')) ?>"><i class="bi bi-inbox me-1"></i>Kotak Masuk</a></li>
          <li class="nav-item"><a class="nav-link <?= $tab === 'password' ? 'active' : '' ?>" href="<?= e(url('akun', ['tab' => 'password'])) ?>"><i class="bi bi-key me-1"></i>Ganti Kata Sandi</a></li>
        </ul>
      </div>
      <div class="card-body">
        <?php if ($tab === 'inbox'): ?>
          <h6 class="fw-bold"><i class="bi bi-calendar-day me-1"></i>Kunjungan Hari Ini</h6>
          <div class="table-responsive mb-4">
            <table class="table table-sm table-striped mb-0">
              <thead><tr><th>Jam</th><th>No. Rawat</th><th>Pasien</th><th>Poli</th><th>Dokter</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($kunjunganHariIni as $k): ?>
                  <tr>
                    <td><?= e(substr((string)$k['jam_reg'], 0, 5)) ?></td>
                    <td><?= e($k['no_rawat']) ?></td>
                    <td><?= e($k['nm_pasien']) ?></td>
                    <td><?= e($k['nm_poli']) ?></td>
                    <td><?= e($k['nm_dokter']) ?></td>
                    <td><?= badge_status($k['stts'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$kunjunganHariIni): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">Belum ada kunjungan hari ini.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <h6 class="fw-bold"><i class="bi bi-hospital me-1"></i>Pasien Sedang Dirawat</h6>
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead><tr><th>No. Rawat</th><th>Pasien</th><th>Kamar</th><th>Sejak</th></tr></thead>
              <tbody>
                <?php foreach ($ranapAktif as $r): ?>
                  <tr>
                    <td><?= e($r['no_rawat']) ?></td>
                    <td><?= e($r['nm_pasien']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($r['kd_kamar']) ?></span></td>
                    <td><?= e(tgl_indo($r['tgl_masuk'])) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$ranapAktif): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada pasien yang sedang dirawat.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <form method="post" action="<?= e(url('akun')) ?>" class="col-lg-6">
            <input type="hidden" name="act" value="ganti_password" />
            <div class="mb-3">
              <label class="form-label">Kata Sandi Lama</label>
              <input class="form-control" type="password" name="pass_lama" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Kata Sandi Baru</label>
              <input class="form-control" type="password" name="pass_baru" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Ulangi Kata Sandi Baru</label>
              <input class="form-control" type="password" name="pass_ulang" required />
            </div>
            <button class="btn btn-primary"><i class="bi bi-key me-1"></i>Ganti Kata Sandi</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
