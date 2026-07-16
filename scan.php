<?php
// Mobile-friendly order scan & status update page
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && $id) {
    $status  = sanitize($conn, $_POST['status']);
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($status, $allowed)) {
        $conn->query("UPDATE orders SET status='$status' WHERE id=$id");
    }
    header('Location: scan.php?id=' . $id . '&done=1');
    exit;
}

$order = $conn->query("
    SELECT o.*, c.name AS cust_name, c.phone AS cust_phone, c.address AS cust_address
    FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id=$id LIMIT 1
")->fetch_assoc();

if (!$order) { http_response_code(404); echo '<p style="padding:30px;font-family:Arial">Order not found.</p>'; exit; }

$items = $conn->query("SELECT * FROM order_items WHERE order_id=$id")->fetch_all(MYSQLI_ASSOC);

$statusColors = [
    'pending'    => '#f59e0b',
    'processing' => '#3b82f6',
    'shipped'    => '#06b6d4',
    'delivered'  => '#10b981',
    'cancelled'  => '#ef4444',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= htmlspecialchars($order['order_number']) ?> — Scan</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css">
<style>
  body { background:#f1f5f9; font-family:Arial,sans-serif; }
  .order-card { border-radius:16px; border:none; box-shadow:0 4px 24px rgba(0,0,0,.08); }
  .status-badge { display:inline-block; padding:6px 18px; border-radius:20px; font-weight:700; font-size:1rem; color:#fff; }
  .btn-stat { width:100%; padding:13px; border-radius:10px; font-size:1rem; font-weight:600; margin-bottom:8px; border:2px solid transparent; }
  .btn-stat.active-stat { border-width:3px; }
  .field-row { display:flex; align-items:flex-start; gap:10px; padding:7px 0; border-bottom:1px solid #f1f5f9; font-size:.95rem; }
  .field-icon { color:#94a3b8; width:18px; flex-shrink:0; margin-top:2px; }
</style>
</head>
<body>
<div class="container py-4" style="max-width:480px">

  <!-- Order header -->
  <div class="card order-card mb-3">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <div class="text-muted small">Order No.</div>
          <div class="fw-bold fs-5"><?= htmlspecialchars($order['order_number']) ?></div>
          <div class="text-muted small"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
        </div>
        <span class="status-badge" style="background:<?= $statusColors[$order['status']] ?? '#6b7280' ?>">
          <?= ucfirst($order['status']) ?>
        </span>
      </div>

      <?php if (isset($_GET['done'])): ?>
        <div class="alert alert-success py-2 mb-3"><strong>&#10003; Status updated!</strong></div>
      <?php endif; ?>

      <div class="field-row">
        <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        <span><?= htmlspecialchars($order['cust_name'] ?? 'Walk-in') ?></span>
      </div>
      <?php if ($order['cust_phone']): ?>
      <div class="field-row">
        <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
        <span><?= htmlspecialchars($order['cust_phone']) ?></span>
      </div>
      <?php endif; ?>
      <div class="field-row">
        <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        <strong>COD: <?= CURRENCY . number_format((float)$order['total'], 2) ?></strong>
      </div>

      <?php if ($items): ?>
      <div class="mt-3">
        <div class="text-muted small mb-1">Items</div>
        <?php foreach ($items as $item): ?>
          <div class="small py-1 d-flex justify-content-between border-bottom">
            <span><?= htmlspecialchars($item['product_name']) ?>
              <?php if (!empty($item['color'])): ?>
                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($item['color']) ?></span>
              <?php endif; ?>
            </span>
            <span class="text-muted">×<?= $item['quantity'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Status update -->
  <div class="card order-card">
    <div class="card-body p-4">
      <div class="fw-bold mb-3">Update Status</div>
      <form method="POST">
        <?php
        $btns = [
            'pending'    => ['Pending',    '#f59e0b', 'text-dark'],
            'processing' => ['Processing', '#3b82f6', 'text-white'],
            'shipped'    => ['Shipped',    '#06b6d4', 'text-white'],
            'delivered'  => ['Delivered',  '#10b981', 'text-white'],
            'cancelled'  => ['Cancelled',  '#ef4444', 'text-white'],
        ];
        foreach ($btns as $key => [$label, $color, $textClass]):
            $isCurrent = $order['status'] === $key;
        ?>
        <button type="submit" name="status" value="<?= $key ?>"
                class="btn-stat"
                style="background:<?= $isCurrent ? $color : '#f8fafc' ?>;
                       color:<?= $isCurrent ? ($textClass==='text-dark'?'#1f2937':'#fff') : '#374151' ?>;
                       border-color:<?= $color ?>;
                       border-width:<?= $isCurrent ? '3px' : '2px' ?>">
          <?= $label ?><?= $isCurrent ? ' ✓' : '' ?>
        </button>
        <?php endforeach; ?>
      </form>
    </div>
  </div>

  <div class="text-center mt-3">
    <a href="<?= BASE_URL ?>/order_view.php?id=<?= $order['id'] ?>" class="text-muted small">
      Open full order &rarr;
    </a>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
