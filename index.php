<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// ── Stats ──────────────────────────────────────────────────────────────────────
$today     = date('Y-m-d');
$thisMonth = date('Y-m');

$todaySales = $conn->query("SELECT COALESCE(SUM(total),0) as s FROM orders WHERE DATE(created_at)='$today' AND status!='cancelled'")->fetch_assoc()['s'];
$monthSales = $conn->query("SELECT COALESCE(SUM(total),0) as s FROM orders WHERE DATE_FORMAT(created_at,'%Y-%m')='$thisMonth' AND status!='cancelled'")->fetch_assoc()['s'];
$totalOrders= $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$totalProds = $conn->query("SELECT COUNT(*) as c FROM products WHERE active=1")->fetch_assoc()['c'];
$totalCusts = $conn->query("SELECT COUNT(*) as c FROM customers")->fetch_assoc()['c'];

// ── Last 7 days chart data ─────────────────────────────────────────────────────
$chartData = []; $chartLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $lbl = date('D d', strtotime("-$i days"));
    $r   = $conn->query("SELECT COALESCE(SUM(total),0) as s FROM orders WHERE DATE(created_at)='$d' AND status!='cancelled'")->fetch_assoc();
    $chartData[]   = (float)$r['s'];
    $chartLabels[] = $lbl;
}

// ── Recent Orders ──────────────────────────────────────────────────────────────
$recentOrders = $conn->query("
    SELECT o.*, c.name as customer_name
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    ORDER BY o.created_at DESC LIMIT 8
");

// ── Low Stock ──────────────────────────────────────────────────────────────────
$lowStockItems = $conn->query("SELECT * FROM products WHERE stock <= 5 AND active=1 ORDER BY stock ASC LIMIT 8");
?>

<div class="container-fluid">

  <!-- ── Stat Cards ── -->
  <div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#eff6ff">
          <i class="fas fa-dollar-sign" style="color:#3b82f6;font-size:1.4rem"></i>
        </div>
        <div>
          <div class="stat-value"><?= currency($todaySales) ?></div>
          <div class="stat-label">Today's Sales</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#f0fdf4">
          <i class="fas fa-chart-line" style="color:#10b981;font-size:1.4rem"></i>
        </div>
        <div>
          <div class="stat-value"><?= currency($monthSales) ?></div>
          <div class="stat-label">This Month</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#fef3c7">
          <i class="fas fa-shopping-cart" style="color:#f59e0b;font-size:1.4rem"></i>
        </div>
        <div>
          <div class="stat-value"><?= number_format($totalOrders) ?></div>
          <div class="stat-label">Total Orders</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#fdf2f8">
          <i class="fas fa-users" style="color:#a855f7;font-size:1.4rem"></i>
        </div>
        <div>
          <div class="stat-value"><?= number_format($totalCusts) ?></div>
          <div class="stat-label">Customers</div>
        </div>
      </div>
    </div>

  </div><!-- /row stats -->

  <div class="row g-3">

    <!-- ── Sales Chart ── -->
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><i class="fas fa-chart-bar me-2 text-primary"></i>Sales — Last 7 Days</span>
          <a href="reports.php" class="btn btn-outline-primary btn-sm">Full Report</a>
        </div>
        <div class="card-body" style="height:280px">
          <canvas id="salesChart"></canvas>
        </div>
      </div>
    </div>

    <!-- ── Low Stock Alert ── -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alert
        </div>
        <div class="card-body p-0">
          <?php if ($lowStockItems->num_rows === 0): ?>
            <div class="text-center text-muted py-5"><i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>All stocked up!</div>
          <?php else: ?>
            <div class="list-group list-group-flush">
            <?php while($item = $lowStockItems->fetch_assoc()): ?>
              <a href="products.php?action=edit&id=<?= $item['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
                <span class="small fw-500"><?= htmlspecialchars($item['name']) ?></span>
                <span class="badge <?= $item['stock'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                  <?= $item['stock'] ?> left
                </span>
              </a>
            <?php endwhile; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($lowStock > 0): ?>
          <div class="card-footer text-center py-2">
            <a href="products.php?filter=low_stock" class="small text-warning">View all <?= $lowStock ?> low-stock items</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Recent Orders ── -->
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><i class="fas fa-clock me-2 text-primary"></i>Recent Orders</span>
          <a href="orders.php" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th>Total</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($recentOrders->num_rows === 0): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No orders yet. <a href="orders.php?action=new">Create one!</a></td></tr>
              <?php else: ?>
                <?php while($ord = $recentOrders->fetch_assoc()): ?>
                <tr>
                  <td><a href="order_view.php?id=<?= $ord['id'] ?>" class="fw-600 text-primary"><?= htmlspecialchars($ord['order_number']) ?></a></td>
                  <td><?= htmlspecialchars($ord['customer_name'] ?? 'Walk-in') ?></td>
                  <td><?= statusBadge($ord['status']) ?></td>
                  <td class="fw-600"><?= currency($ord['total']) ?></td>
                  <td class="text-muted"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
                  <td>
                    <a href="order_view.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                    <a href="order_sticker.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-print"></i></a>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /row main -->
</div>

<?php
$extraJS = '<script>
var ctx = document.getElementById("salesChart").getContext("2d");
new Chart(ctx, {
  type: "bar",
  data: {
    labels: ' . json_encode($chartLabels) . ',
    datasets: [{
      label: "Sales (' . CURRENCY . ')",
      data:  ' . json_encode($chartData) . ',
      backgroundColor: "rgba(59,130,246,0.15)",
      borderColor:     "#3b82f6",
      borderWidth: 2,
      borderRadius: 6,
      tension: 0.4
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: "#f1f5f9" },
        ticks: { callback: v => "' . CURRENCY . '" + v.toFixed(0) }
      },
      x: { grid: { display: false } }
    }
  }
});
</script>';
require_once 'includes/footer.php';
