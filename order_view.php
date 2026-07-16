<?php
// ── Bootstrap (no HTML output yet) ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('danger','Invalid order.'); redirect(BASE_URL.'/orders.php'); }

// ── Delete ────────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM order_items WHERE order_id=$id");
    $conn->query("DELETE FROM orders WHERE id=$id");
    flash('success', 'Order deleted.');
    redirect(BASE_URL . '/orders.php');
}

// ── Status update ─────────────────────────────────────────────────────────────
if (isset($_GET['set_status'])) {
    $st      = sanitize($conn, $_GET['set_status']);
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($st, $allowed)) {
        $conn->query("UPDATE orders SET status='$st' WHERE id=$id");
        flash('success', 'Status updated to ' . ucfirst($st));
    }
    redirect(BASE_URL . '/order_view.php?id=' . $id);
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$order = $conn->query("
    SELECT o.*, c.name as cust_name, c.phone as cust_phone, c.email as cust_email
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id=$id LIMIT 1
")->fetch_assoc();

if (!$order) { flash('danger','Order not found.'); redirect(BASE_URL.'/orders.php'); }

$items = $conn->query("
    SELECT oi.*, p.sku FROM order_items oi
    LEFT JOIN products p ON p.id=oi.product_id
    WHERE oi.order_id=$id
")->fetch_all(MYSQLI_ASSOC);

$statusColors = ['pending'=>'warning','processing'=>'primary','shipped'=>'info','delivered'=>'success','cancelled'=>'danger'];
$nextStatus   = ['pending'=>'processing','processing'=>'shipped','shipped'=>'delivered'];

$pageTitle = 'Order ' . $order['order_number'];
require_once 'includes/header.php';
?>

<div class="container-fluid">

  <!-- ── Header bar ── -->
  <div class="d-flex align-items-start gap-2 mb-3 flex-wrap">
    <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i></a>
    <div class="flex-grow-1">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($order['order_number']) ?></h5>
        <?= statusBadge($order['status']) ?>
      </div>
      <div class="text-muted small mt-1"><?= date('d M Y H:i', strtotime($order['created_at'])) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if (isset($nextStatus[$order['status']])): ?>
        <a href="?id=<?= $id ?>&set_status=<?= $nextStatus[$order['status']] ?>" class="btn btn-success btn-sm">
          <i class="fas fa-arrow-right me-1"></i>Mark <?= ucfirst($nextStatus[$order['status']]) ?>
        </a>
      <?php endif; ?>
      <a href="order_sticker.php?id=<?= $id ?>" target="_blank" class="btn btn-primary btn-sm">
        <i class="fas fa-print me-1"></i><span class="d-none d-sm-inline">Print </span>Sticker
      </a>
      <a href="?id=<?= $id ?>&delete=1" class="btn btn-danger btn-sm"
         onclick="return confirm('Delete order <?= htmlspecialchars($order['order_number']) ?>? This cannot be undone.')">
        <i class="fas fa-trash me-1"></i><span class="d-none d-sm-inline">Delete</span>
      </a>
    </div>
  </div>

  <div class="row g-3">

    <!-- ── Order Items ── -->
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-list me-2 text-primary"></i>Order Items</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Product</th>
                  <th class="d-none d-sm-table-cell">SKU</th>
                  <th class="text-center">Qty</th>
                  <th class="text-end d-none d-sm-table-cell">Unit</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($items as $i => $item): ?>
                <tr>
                  <td class="text-muted"><?= $i+1 ?></td>
                  <td>
                    <div class="fw-500"><?= htmlspecialchars($item['product_name']) ?></div>
                    <?php if (!empty($item['color'])): ?>
                      <span class="badge bg-secondary"><?= htmlspecialchars($item['color']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="d-none d-sm-table-cell"><code class="small"><?= htmlspecialchars($item['sku'] ?? '—') ?></code></td>
                  <td class="text-center"><?= $item['quantity'] ?></td>
                  <td class="text-end d-none d-sm-table-cell"><?= currency($item['unit_price']) ?></td>
                  <td class="text-end fw-600"><?= currency($item['total_price']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot style="background:#f8fafc">
                <tr>
                  <td colspan="3" class="text-end fw-500 d-none d-sm-table-cell">Subtotal</td>
                  <td colspan="2" class="text-end fw-500 d-sm-none">Subtotal</td>
                  <td class="text-end"><?= currency($order['subtotal']) ?></td>
                </tr>
                <?php if ((float)$order['discount'] > 0): ?>
                <tr>
                  <td colspan="3" class="text-end text-danger fw-500 d-none d-sm-table-cell">Discount</td>
                  <td colspan="2" class="text-end text-danger fw-500 d-sm-none">Discount</td>
                  <td class="text-end text-danger">-<?= currency($order['discount']) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="font-size:1.1rem">
                  <td colspan="3" class="text-end fw-bold d-none d-sm-table-cell">TOTAL</td>
                  <td colspan="2" class="text-end fw-bold d-sm-none">TOTAL</td>
                  <td class="text-end fw-bold text-primary"><?= currency($order['total']) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <?php if ($order['notes']): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-sticky-note me-2"></i>Notes</div>
        <div class="card-body"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Sidebar ── -->
    <div class="col-lg-4">

      <!-- Update Status -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-stream me-2"></i>Update Status</div>
        <div class="card-body">
          <div class="d-grid gap-1">
            <?php foreach(['pending','processing','shipped','delivered','cancelled'] as $st): ?>
              <a href="?id=<?= $id ?>&set_status=<?= $st ?>"
                 class="btn btn-sm <?= $order['status']===$st ? 'btn-'.$statusColors[$st] : 'btn-outline-'.$statusColors[$st] ?>
                 <?= $st==='pending'&&$order['status']==='pending'?'text-dark':'' ?>">
                <?= ucfirst($st) ?><?= $order['status']===$st ? ' ✓' : '' ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Customer -->
      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-user me-2"></i>Customer</div>
        <div class="card-body">
          <?php if ($order['cust_name']): ?>
            <div class="fw-bold"><?= htmlspecialchars($order['cust_name']) ?></div>
            <?php if ($order['cust_phone']): ?>
              <a href="tel:<?= htmlspecialchars($order['cust_phone']) ?>" class="text-muted small d-block">
                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['cust_phone']) ?>
              </a>
            <?php endif; ?>
            <?php if ($order['cust_email']): ?>
              <div class="text-muted small"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($order['cust_email']) ?></div>
            <?php endif; ?>
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

      <!-- Order Info -->
      <div class="card">
        <div class="card-header"><i class="fas fa-info-circle me-2"></i>Order Info</div>
        <div class="card-body small">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Order #</span><strong><?= htmlspecialchars($order['order_number']) ?></strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Created</span><span><?= date('d M Y H:i', strtotime($order['created_at'])) ?></span></div>
          <div class="d-flex justify-content-between mb-3"><span class="text-muted">Updated</span><span><?= date('d M Y H:i', strtotime($order['updated_at'])) ?></span></div>
          <a href="?id=<?= $id ?>&delete=1" class="btn btn-danger btn-sm w-100"
             onclick="return confirm('Delete order <?= htmlspecialchars($order['order_number']) ?>? This cannot be undone.')">
            <i class="fas fa-trash me-1"></i>Delete Order
          </a>
        </div>
      </div>

    </div>
  </div>
</div>

<?php
ob_start(); ?>
<script>
(function(){
  var baseline = <?= json_encode($order['updated_at']) ?>;
  setInterval(function(){
    fetch('orders.php?action=last_updated')
      .then(function(r){ return r.json(); })
      .then(function(d){ if(d.ts && d.ts !== baseline) location.reload(); })
      .catch(function(){});
  }, 8000);
})();
</script>
<?php $extraJS = ob_get_clean();
require_once 'includes/footer.php'; ?>
