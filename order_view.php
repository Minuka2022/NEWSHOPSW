<?php
$pageTitle = 'Order Details';
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('danger','Invalid order.'); redirect(BASE_URL.'/orders.php'); }

// Status update
if (isset($_GET['set_status'])) {
    $st = sanitize($conn, $_GET['set_status']);
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($st, $allowed)) {
        $conn->query("UPDATE orders SET status='$st' WHERE id=$id");
        flash('success', 'Status updated to ' . ucfirst($st));
        redirect(BASE_URL . '/order_view.php?id=' . $id);
    }
}

$order = $conn->query("
    SELECT o.*, c.name as cust_name, c.phone as cust_phone, c.email as cust_email
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id=$id LIMIT 1
")->fetch_assoc();

if (!$order) { flash('danger','Order not found.'); redirect(BASE_URL.'/orders.php'); }

$items = $conn->query("SELECT oi.*, p.sku FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=$id")->fetch_all(MYSQLI_ASSOC);

$statusColors = ['pending'=>'warning','processing'=>'primary','shipped'=>'info','delivered'=>'success','cancelled'=>'danger'];
$nextStatus   = ['pending'=>'processing','processing'=>'shipped','shipped'=>'delivered'];
?>
<div class="container-fluid">

  <!-- Header bar -->
  <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Orders</a>
    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($order['order_number']) ?></h5>
    <?= statusBadge($order['status']) ?>
    <div class="ms-auto d-flex gap-2 flex-wrap">
      <?php if (isset($nextStatus[$order['status']])): ?>
        <a href="?id=<?= $id ?>&set_status=<?= $nextStatus[$order['status']] ?>" class="btn btn-success btn-sm">
          <i class="fas fa-arrow-right me-1"></i>Mark <?= ucfirst($nextStatus[$order['status']]) ?>
        </a>
      <?php endif; ?>
      <a href="order_sticker.php?id=<?= $id ?>" target="_blank" class="btn btn-primary btn-sm">
        <i class="fas fa-print me-1"></i>Print Sticker
      </a>
      <?php if ($order['status'] !== 'cancelled'): ?>
        <a href="?id=<?= $id ?>&set_status=cancelled" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel this order?')">
          <i class="fas fa-times me-1"></i>Cancel
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-3">

    <!-- Order Items -->
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-list me-2 text-primary"></i>Order Items</div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead>
              <tr><th>#</th><th>Product</th><th>SKU</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
              <?php foreach($items as $i => $item): ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td class="fw-500"><?= htmlspecialchars($item['product_name']) ?></td>
                <td><code class="small"><?= htmlspecialchars($item['sku'] ?? '—') ?></code></td>
                <td class="text-center"><?= $item['quantity'] ?></td>
                <td class="text-end"><?= currency($item['unit_price']) ?></td>
                <td class="text-end fw-600"><?= currency($item['total_price']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot style="background:#f8fafc">
              <tr>
                <td colspan="5" class="text-end fw-500">Subtotal</td>
                <td class="text-end"><?= currency($order['subtotal']) ?></td>
              </tr>
              <?php if ((float)$order['discount'] > 0): ?>
              <tr>
                <td colspan="5" class="text-end text-danger fw-500">Discount</td>
                <td class="text-end text-danger">-<?= currency($order['discount']) ?></td>
              </tr>
              <?php endif; ?>
              <tr style="font-size:1.1rem">
                <td colspan="5" class="text-end fw-bold">TOTAL</td>
                <td class="text-end fw-bold text-primary"><?= currency($order['total']) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <?php if ($order['notes']): ?>
      <div class="card">
        <div class="card-header"><i class="fas fa-sticky-note me-2"></i>Notes</div>
        <div class="card-body"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Side info -->
    <div class="col-lg-4">

      <!-- Status timeline -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-stream me-2"></i>Update Status</div>
        <div class="card-body">
          <?php foreach(['pending','processing','shipped','delivered','cancelled'] as $st): ?>
            <a href="?id=<?= $id ?>&set_status=<?= $st ?>"
               class="btn btn-sm w-100 mb-1 <?= $order['status']===$st ? 'btn-'.$statusColors[$st] : 'btn-outline-'.$statusColors[$st] ?>
               <?= $st==='pending'&&$order['status']==='pending'?'text-dark':'' ?>">
              <?= ucfirst($st) ?>
              <?= $order['status']===$st ? ' &#10003;' : '' ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Customer info -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-user me-2"></i>Customer</div>
        <div class="card-body">
          <?php if ($order['cust_name']): ?>
            <div class="fw-bold"><?= htmlspecialchars($order['cust_name']) ?></div>
            <?php if ($order['cust_phone']): ?><div class="text-muted small"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['cust_phone']) ?></div><?php endif; ?>
            <?php if ($order['cust_email']): ?><div class="text-muted small"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($order['cust_email']) ?></div><?php endif; ?>
          <?php else: ?>
            <span class="text-muted">Walk-in customer</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Shipping -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-map-marker-alt me-2"></i>Shipping Address</div>
        <div class="card-body">
          <?= $order['shipping_address'] ? nl2br(htmlspecialchars($order['shipping_address'])) : '<span class="text-muted">No address</span>' ?>
        </div>
      </div>

      <!-- Order meta -->
      <div class="card">
        <div class="card-header"><i class="fas fa-info-circle me-2"></i>Order Info</div>
        <div class="card-body small">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Order #</span><strong><?= htmlspecialchars($order['order_number']) ?></strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Created</span><span><?= date('d M Y H:i', strtotime($order['created_at'])) ?></span></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Updated</span><span><?= date('d M Y H:i', strtotime($order['updated_at'])) ?></span></div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
