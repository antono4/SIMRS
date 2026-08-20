      </div>
    </div>
  </main>
  <!--end::App Main-->
  <!--begin::Footer-->
  <footer class="app-footer">
    <div class="float-end d-none d-sm-inline">AdminLTE 4 &middot; PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?> &middot; MySQL</div>
    <strong><?= e(setting_nama_rs()) ?></strong> &middot; <?= APP_NAME ?> v<?= APP_VERSION ?>
  </footer>
  <!--end::Footer-->
</div>
<script src="<?= asset('overlayscrollbars/overlayscrollbars.browser.es6.min.js') ?>"></script>
<script src="<?= asset('bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('adminlte/js/adminlte.min.js') ?>"></script>
<script>
  const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
  const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
  if (sidebarWrapper && typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== 'undefined') {
    OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
      scrollbars: { theme: 'os-theme-light', autoHide: 'leave', clickScroll: true },
    });
  }

  // Switch skin siang/malam
  (function () {
    const icon = document.getElementById('themeIcon');
    const apply = (theme) => {
      document.documentElement.setAttribute('data-bs-theme', theme);
      if (icon) icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    };
    apply(localStorage.getItem('sk-theme') === 'dark' ? 'dark' : 'light');
    document.getElementById('themeToggle')?.addEventListener('click', () => {
      const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('sk-theme', next);
      apply(next);
    });
  })();
</script>
<?= $pageScripts ?? '' ?>
</body>
</html>
