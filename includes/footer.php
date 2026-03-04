  </main><!-- /page-content -->
</div><!-- /main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainWrapper').classList.toggle('expanded');
});

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
