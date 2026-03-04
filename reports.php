<?php
$pageTitle = 'Reports & Analytics';
require_once 'includes/header.php';

// ── Date range ───────────────────────────────────────────────────────────────────
$from = sanitize($conn, $_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($conn, $_GET['to']   ?? date('Y-m-d'));

// ── Summary stats ────────────────────────────────────────────────────────────────
$summary = $conn->query("
    SELECT
        COUNT(*) as total_orders,
        COALESCE(SUM(total),0) as revenue,
        COALESCE(SUM(discount),0) as total_discounts,
        COALESCE(AVG(total),0) as avg_order
    FROM orders
    WHERE DATE(created_at) BETWEEN '$from' AND '$to'
    AND status != 'cancelled'
")->fetch_assoc();

$cancelled = $conn->query("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at) BETWEEN '$from' AND '$to' AND status='cancelled'")->fetch_assoc()['c'];

// ── Daily chart (last 30 days in range) ──────────────────────────────────────────
$dailySales = $conn->query("
    SELECT DATE(created_at) as day, COALESCE(SUM(total),0) as total, COUNT(*) as cnt
    FROM orders
    WHERE DATE(created_at) BETWEEN '$from' AND '$to' AND status != 'cancelled'
    GROUP BY DATE(created_at) ORDER BY day ASC
")->fetch_all(MYSQLI_ASSOC);

$chartDays = array_column($dailySales, 'day');
$chartRev  = array_column($dailySales, 'total');
$chartCnt  = array_column($dailySales, 'cnt');

// ── Top products ─────────────────────────────────────────────────────────────────
$topProducts = $conn->query("
    SELECT oi.product_name,
           SUM(oi.quantity) as qty_sold,
           SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE DATE(o.created_at) BETWEEN '$from' AND '$to' AND o.status != 'cancelled'
    GROUP BY oi.product_name ORDER BY revenue DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Top customers ─────────────────────────────────────────────────────────────────
$topCustomers = $conn->query("
    SELECT c.name, c.phone,
           COUNT(o.id) as orders,
           SUM(o.total) as spent
    FROM customers c
    JOIN orders o ON o.customer_id = c.id
    WHERE DATE(o.created_at) BETWEEN '$from' AND '$to' AND o.status != 'cancelled'
    GROUP BY c.id ORDER BY spent DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Status breakdown ─────────────────────────────────────────────────────────────
$statusBreakdown = $conn->query("
    SELECT status, COUNT(*) as cnt, COALESCE(SUM(total),0) as total
    FROM orders WHERE DATE(created_at) BETWEEN '$from' AND '$to'
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid">

  <!-- Date filter -->
  <form class="filter-bar d-flex gap-2 align-items-center mb-4 flex-wrap" method="GET">
    <label class="fw-600 small">From</label>
    <input type="date" name="from" class="form-control" style="max-width:160px" value="<?= $from ?>">
    <label class="fw-600 small">To</label>
    <input type="date" name="to"   class="form-control" style="max-width:160px" value="<?= $to ?>">
    <button class="btn btn-primary">Apply</button>
    <div class="d-flex gap-2 ms-2">
      <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
      <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
      <a href="?from=<?= date('Y-m-d', strtotime('-30 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Last 30 Days</a>
      <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Year</a>
    </div>
  </form>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#eff6ff"><i class="fas fa-dollar-sign" style="color:#3b82f6;font-size:1.4rem"></i></div>
        <div><div class="stat-value"><?= currency($summary['revenue']) ?></div><div class="stat-label">Revenue</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#f0fdf4"><i class="fas fa-receipt" style="color:#10b981;font-size:1.4rem"></i></div>
        <div><div class="stat-value"><?= number_format($summary['total_orders']) ?></div><div class="stat-label">Orders</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#fef3c7"><i class="fas fa-chart-line" style="color:#f59e0b;font-size:1.4rem"></i></div>
        <div><div class="stat-value"><?= currency($summary['avg_order']) ?></div><div class="stat-label">Avg Order</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:#fee2e2"><i class="fas fa-times-circle" style="color:#ef4444;font-size:1.4rem"></i></div>
        <div><div class="stat-value"><?= $cancelled ?></div><div class="stat-label">Cancelled</div></div>
      </div>
    </div>
  </div>

  <!-- Charts row -->
  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><i class="fas fa-chart-area me-2 text-primary"></i>Daily Revenue</div>
        <div class="card-body" style="height:280px"><canvas id="dailyChart"></canvas></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="fas fa-chart-pie me-2 text-primary"></i>Order Status</div>
        <div class="card-body">
          <?php if (empty($statusBreakdown)): ?>
            <div class="text-center text-muted py-5">No data</div>
          <?php else: ?>
            <?php
            $statusColors2 = ['pending'=>'#f59e0b','processing'=>'#3b82f6','shipped'=>'#06b6d4','delivered'=>'#10b981','cancelled'=>'#ef4444'];
            foreach ($statusBreakdown as $sb):
              $color = $statusColors2[$sb['status']] ?? '#94a3b8';
            ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="d-flex align-items-center gap-2">
                <span style="width:12px;height:12px;border-radius:3px;background:<?= $color ?>;display:inline-block"></span>
                <span class="small fw-500"><?= ucfirst($sb['status']) ?></span>
              </div>
              <div class="text-end">
                <span class="badge bg-light text-dark border"><?= $sb['cnt'] ?></span>
                <span class="small text-muted ms-1"><?= currency($sb['total']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Top Products -->
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header"><i class="fas fa-star me-2 text-warning"></i>Top Products</div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead><tr><th>#</th><th>Product</th><th class="text-center">Qty</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
            <?php if (empty($topProducts)): ?>
              <tr><td colspan="4" class="text-center py-4 text-muted">No data</td></tr>
            <?php else: ?>
              <?php foreach($topProducts as $i => $p): ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td><?= htmlspecialchars($p['product_name']) ?></td>
                <td class="text-center"><span class="badge bg-primary"><?= $p['qty_sold'] ?></span></td>
                <td class="text-end fw-600"><?= currency($p['revenue']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Top Customers -->
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header"><i class="fas fa-crown me-2 text-warning"></i>Top Customers</div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead><tr><th>#</th><th>Customer</th><th class="text-center">Orders</th><th class="text-end">Spent</th></tr></thead>
            <tbody>
            <?php if (empty($topCustomers)): ?>
              <tr><td colspan="4" class="text-center py-4 text-muted">No data</td></tr>
            <?php else: ?>
              <?php foreach($topCustomers as $i => $c): ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td>
                  <div class="fw-500"><?= htmlspecialchars($c['name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($c['phone']) ?></div>
                </td>
                <td class="text-center"><span class="badge bg-success"><?= $c['orders'] ?></span></td>
                <td class="text-end fw-600"><?= currency($c['spent']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php
$extraJS = '<script>
var ctx = document.getElementById("dailyChart").getContext("2d");
new Chart(ctx, {
  type: "line",
  data: {
    labels: ' . json_encode(array_map(fn($d) => date('d M', strtotime($d)), $chartDays)) . ',
    datasets: [{
      label: "Revenue",
      data:  ' . json_encode(array_map('floatval', $chartRev)) . ',
      backgroundColor: "rgba(59,130,246,0.1)",
      borderColor: "#3b82f6",
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointBackgroundColor: "#3b82f6",
      pointRadius: 4
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: "#f1f5f9" }, ticks: { callback: v => "' . CURRENCY . '" + v.toFixed(0) } },
      x: { grid: { display: false } }
    }
  }
});
</script>';
require_once 'includes/footer.php';
