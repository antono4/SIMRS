<?php
declare(strict_types=1);
http_response_code(404);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>404 | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="<?= asset('bootstrap/css/bootstrap.min.css') ?>" />
</head>
<body class="bg-body-secondary d-flex align-items-center" style="min-height:100vh">
  <div class="container text-center">
    <h1 class="display-1">404</h1>
    <p class="lead">Halaman tidak ditemukan.</p>
    <a class="btn btn-primary" href="<?= e(url('dashboard')) ?>">Kembali ke Dashboard</a>
  </div>
</body>
</html>
