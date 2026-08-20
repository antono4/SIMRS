<?php
/**
 * Layout AdminLTE 4: head, navbar (tema siang/malam + level user), sidebar.
 * Variabel yang dipakai: $pageTitle, $currentPage, $pageScripts (di footer).
 */
declare(strict_types=1);

$menu = [
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    [
        'page' => 'master', 'label' => 'Master Data', 'icon' => 'bi-database',
        'children' => [
            ['page' => 'pasien', 'label' => 'Pasien', 'icon' => 'bi-people'],
            ['page' => 'dokter', 'label' => 'Dokter', 'icon' => 'bi-heart-pulse'],
            ['page' => 'poliklinik', 'label' => 'Poliklinik', 'icon' => 'bi-hospital'],
            ['page' => 'penjab', 'label' => 'Penanggung Jawab', 'icon' => 'bi-shield-check'],
            ['page' => 'perawatan', 'label' => 'Tarif Perawatan', 'icon' => 'bi-cash-stack'],
        ],
    ],
    ['page' => 'registrasi', 'label' => 'Registrasi', 'icon' => 'bi-clipboard2-plus'],
    ['page' => 'kamarinap', 'label' => 'Kamar Inap', 'icon' => 'bi-hospital'],
    ['page' => 'kasir', 'label' => 'Kasir / Billing', 'icon' => 'bi-cash-coin'],
    ['page' => 'laporan', 'label' => 'Laporan Kunjungan', 'icon' => 'bi-bar-chart'],
    ['page' => 'users', 'label' => 'Pengguna', 'icon' => 'bi-person-gear'],
    ['page' => 'pengaturan', 'label' => 'Pengaturan', 'icon' => 'bi-gear'],
    ['page' => 'admin', 'label' => 'Administrator', 'icon' => 'bi-shield-lock', 'admin_only' => true],
    ['page' => 'akun', 'label' => 'Akun Saya', 'icon' => 'bi-person-circle'],
];
// Saring menu sesuai hak akses (user level)
$isAdmin = (auth_user()['role'] ?? '') === 'admin';
$menu = array_values(array_filter(array_map(function (array $item) use ($isAdmin): ?array {
    if (!empty($item['admin_only']) && !$isAdmin) {
        return null;
    }
    if (isset($item['children'])) {
        $item['children'] = array_values(array_filter($item['children'], fn($c) => auth_can($c['page'])));
        return $item['children'] ? $item : null;
    }
    return auth_can($item['page']) ? $item : null;
}, $menu)));
$user = auth_user();
$namaRs = setting_nama_rs();
?>
<!doctype html>
<html lang="id">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title><?= e($pageTitle ?? $namaRs) ?> | <?= e($namaRs) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script>
    // Skin siang/malam — default siang, tersimpan di localStorage
    (function () {
      const theme = localStorage.getItem('sk-theme') === 'dark' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <link rel="stylesheet" href="<?= asset('overlayscrollbars/overlayscrollbars.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('icons/css/bootstrap-icons.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('adminlte/css/adminlte.min.css') ?>" />
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
  <!--begin::Header-->
  <nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </a>
        </li>
        <li class="nav-item d-none d-md-block">
          <a href="<?= e(url('dashboard')) ?>" class="nav-link">Beranda</a>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <button class="nav-link btn btn-link" id="themeToggle" role="button" aria-label="Ganti tema siang/malam" title="Ganti tema siang/malam">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
          </button>
        </li>
        <li class="nav-item d-none d-md-block">
          <span class="nav-link"><span class="badge text-bg-warning"><?= e(auth_level_label()) ?></span></span>
        </li>
        <li class="nav-item dropdown user-menu">
          <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i>
            <span class="d-none d-md-inline"><?= e($user['username'] ?? '') ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
            <li class="user-header text-bg-primary">
              <i class="bi bi-person-circle" style="font-size:3rem"></i>
              <p><?= e($user['username'] ?? '') ?><small><?= e(auth_level_label()) ?></small></p>
            </li>
            <li class="user-footer d-flex justify-content-between">
              <a href="<?= e(url('akun')) ?>" class="btn btn-default btn-flat">Akun Saya</a>
              <a href="<?= e(url('logout')) ?>" class="btn btn-default btn-flat">Keluar</a>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>
  <!--end::Header-->
  <!--begin::Sidebar-->
  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
      <a href="<?= e(url('dashboard')) ?>" class="brand-link">
        <i class="bi bi-hospital fs-4 me-2"></i>
        <span class="brand-text fw-light"><?= e($namaRs) ?></span>
      </a>
    </div>
    <div class="sidebar-wrapper">
      <nav class="mt-2" aria-label="Main navigation">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false" id="navigation">
          <?php foreach ($menu as $item): ?>
            <?php if (isset($item['children'])): ?>
              <?php $open = in_array($currentPage ?? '', array_column($item['children'], 'page'), true); ?>
              <li class="nav-item<?= $open ? ' menu-open' : '' ?>">
                <a href="#" class="nav-link<?= $open ? ' active' : '' ?>">
                  <i class="nav-icon bi <?= e($item['icon']) ?>"></i>
                  <p><?= e($item['label']) ?><i class="nav-arrow bi bi-chevron-right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                  <?php foreach ($item['children'] as $child): ?>
                    <li class="nav-item">
                      <a href="<?= e(url($child['page'])) ?>" class="nav-link<?= ($currentPage ?? '') === $child['page'] ? ' active' : '' ?>">
                        <i class="nav-icon bi <?= e($child['icon']) ?>"></i>
                        <p><?= e($child['label']) ?></p>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </li>
            <?php else: ?>
              <li class="nav-item">
                <a href="<?= e(url($item['page'])) ?>" class="nav-link<?= ($currentPage ?? '') === $item['page'] ? ' active' : '' ?>">
                  <i class="nav-icon bi <?= e($item['icon']) ?>"></i>
                  <p><?= e($item['label']) ?></p>
                </a>
              </li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </aside>
  <!--end::Sidebar-->
  <!--begin::App Main-->
  <main class="app-main">
    <div class="app-content-header">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-sm-6">
            <h3 class="mb-0 fw-bold"><?= e($pageTitle ?? '') ?></h3>
          </div>
          <div class="col-sm-6">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb float-sm-end mb-0 bg-body px-3 py-2 rounded-pill shadow-sm">
                <li class="breadcrumb-item"><a href="<?= e(url('dashboard')) ?>"><i class="bi bi-house-door me-1"></i>Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($pageTitle ?? '') ?></li>
              </ol>
            </nav>
          </div>
        </div>
      </div>
    </div>
    <div class="app-content">
      <div class="container-fluid">
        <?php foreach (flash_get() as $flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>
