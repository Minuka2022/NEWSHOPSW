  </main><!-- /page-content -->
</div><!-- /main-wrapper -->

<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/chart/chart.umd.min.js"></script>
<script>
// Sidebar toggle — mobile slides in/out, desktop collapses to icons
(function() {
  var sidebar  = document.getElementById('sidebar');
  var wrapper  = document.getElementById('mainWrapper');
  var overlay  = document.getElementById('sidebarOverlay');
  var toggle   = document.getElementById('sidebarToggle');

  function isMobile() { return window.innerWidth <= 768; }

  function closeMobileSidebar() {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
  }

  toggle?.addEventListener('click', function() {
    if (isMobile()) {
      sidebar.classList.toggle('mobile-open');
      overlay.classList.toggle('active');
    } else {
      sidebar.classList.toggle('collapsed');
      wrapper.classList.toggle('expanded');
    }
  });

  // Close when tapping the overlay
  overlay?.addEventListener('click', closeMobileSidebar);

  // Close when a nav link is tapped on mobile
  document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
    link.addEventListener('click', function() {
      if (isMobile()) closeMobileSidebar();
    });
  });

  // Ensure correct state when resizing
  window.addEventListener('resize', function() {
    if (!isMobile()) {
      closeMobileSidebar();
    }
  });
})();

// Auto-dismiss alerts after 4 seconds
document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
        var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert.close();
    }, 4000);
});
</script>
<?php if (isset($extraJS)) echo $extraJS; ?>
</body>
</html>
