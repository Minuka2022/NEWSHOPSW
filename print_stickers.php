<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Fetch all pending orders with customer info
$orders = $conn->query("
    SELECT o.*, c.name AS cust_name, c.phone AS cust_phone, c.address AS cust_address
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.status = 'pending'
    ORDER BY o.created_at ASC
")->fetch_all(MYSQLI_ASSOC);

// Pre-load items for each order
$orderItems = [];
if ($orders) {
    $ids  = implode(',', array_column($orders, 'id'));
    $rows = $conn->query("SELECT * FROM order_items WHERE order_id IN ($ids) ORDER BY order_id, id")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $orderItems[$row['order_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Print Pending Labels — <?= SITE_NAME ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #d1d5db; font-family: Arial, sans-serif; padding: 20px; }

.controls {
  text-align: center;
  margin-bottom: 20px;
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.controls h4 { font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; }
.controls p  { color: #6b7280; font-size: 0.82rem; margin-bottom: 12px; }
.controls button {
  background: #2563eb; color: #fff; border: none;
  padding: 10px 30px; border-radius: 6px; font-size: 1rem;
  cursor: pointer; margin: 0 5px;
}
.controls .btn-back { background: #6b7280; }
.count-badge {
  display: inline-block;
  background: #fbbf24; color: #1f2937;
  border-radius: 12px; padding: 2px 10px;
  font-size: 0.85rem; font-weight: bold;
  margin-left: 6px;
}

/* ── Sticker ── */
.sticker-page {
  display: flex;
  justify-content: center;
  padding: 15px 0;
}

.sticker {
  background: #fff;
  width: 420px;
  border: 2px solid #111;
  font-family: Arial, sans-serif;
  font-size: 12px;
}

/* Header */
.s-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 8px 12px 6px;
  border-bottom: 2px solid #111;
}
.s-store-name {
  font-size: 1.25rem;
  font-weight: 900;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  line-height: 1.2;
}
.s-meta { text-align: right; font-size: 0.68rem; line-height: 2; }
.s-meta .meta-no { font-size: 0.95rem; font-weight: bold; color: #cc0000; }

/* From */
.s-from {
  padding: 5px 12px;
  font-size: 0.68rem;
  line-height: 1.7;
  color: #444;
  border-bottom: 2px solid #111;
}

/* To */
.s-to { padding: 10px 12px 8px; border-bottom: 1px solid #888; }
.s-to-row { display: flex; align-items: flex-start; gap: 6px; }
.s-to-lbl { font-size: 0.68rem; font-weight: bold; min-width: 18px; padding-top: 3px; }
.s-cust-name { font-size: 1.05rem; font-weight: 700; line-height: 1.3; }
.s-address { font-size: 0.78rem; line-height: 1.6; color: #222; margin: 4px 0 0 24px; white-space: pre-line; }
.s-phone { margin-top: 7px; font-size: 0.85rem; }
.s-phone-lbl { font-size: 0.68rem; font-weight: bold; display: inline-block; width: 22px; }

/* COD */
.s-cod {
  padding: 7px 12px;
  font-size: 1rem;
  font-weight: bold;
  border-top: 1px solid #888;
  border-bottom: 1px solid #ccc;
}

/* Items + QR */
.s-bottom {
  display: flex;
  align-items: flex-start;
  padding: 8px 12px 10px;
  gap: 10px;
  min-height: 60px;
  border-bottom: 1px solid #ccc;
}
.s-items-list { flex: 1; font-size: 0.8rem; line-height: 2.1; }
.s-item-row { display: flex; align-items: baseline; gap: 5px; }
.s-item-nm  { flex: 1; }
.s-item-qty { color: #555; }
.s-item-color { font-weight: bold; }

/* QR code */
.s-qr { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.s-qr canvas, .s-qr img { display: block; }
.s-qr-lbl { font-size: 0.55rem; color: #666; text-align: center; }

/* Delivery sticker spaces */
.s-delivery-spaces { display: flex; gap: 8px; padding: 8px 12px 10px; }
.s-delivery-box {
  flex: 1;
  height: 55px;
  border: 2px dashed #aaa;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  color: #bbb;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

/* No orders */
.no-orders {
  text-align: center; padding: 60px 20px;
  background: #fff; border-radius: 8px;
  color: #6b7280; font-size: 1rem;
}

/* ── Print ── */
@media print {
  body { background: #fff; padding: 0; }
  .controls { display: none !important; }
  .sticker-page { padding: 0; justify-content: flex-start; page-break-after: always; }
  .sticker-page:last-child { page-break-after: avoid; }
  .sticker { border: 1px solid #000; }
}
</style>
</head>
<body>

<div class="controls">
  <h4>&#128438; Print Pending Order Labels <span class="count-badge"><?= count($orders) ?> pending</span></h4>
  <p>Each label prints on a separate page. Set paper to A5 / A6 / 10&times;15 cm for best results.</p>
  <?php if ($orders): ?>
    <button onclick="printWhenReady()">&#128438; Print All Labels</button>
  <?php endif; ?>
  <button class="btn-back" onclick="history.back()">&#8592; Back to Orders</button>
</div>

<?php if (!$orders): ?>
  <div class="no-orders">
    <div style="font-size:2.5rem;margin-bottom:12px">&#10003;</div>
    No pending orders — nothing to print!
  </div>
<?php endif; ?>

<?php foreach ($orders as $order):
    $shortNo   = str_pad($order['id'], 5, '0', STR_PAD_LEFT);
    $orderDate = date('d/m/Y', strtotime($order['created_at']));
    $orderTime = date('H:i',   strtotime($order['created_at']));
    $shipTo    = $order['shipping_address'] ?: $order['cust_address'] ?: '';
    $custName  = $order['cust_name']  ?: 'Walk-in Customer';
    $custPhone = $order['cust_phone'] ?: '';
    $items     = $orderItems[$order['id']] ?? [];
?>
<div class="sticker-page">
  <div class="sticker">

    <!-- Header -->
    <div class="s-header">
      <div class="s-store-name"><?= htmlspecialchars(SITE_NAME) ?></div>
      <div class="s-meta">
        <div>No.&nbsp;&nbsp;<span class="meta-no"><?= $shortNo ?></span></div>
        <div>Date&nbsp;&nbsp;<?= $orderDate ?></div>
        <div>Time&nbsp;&nbsp;<?= $orderTime ?></div>
      </div>
    </div>

    <!-- From -->
    <div class="s-from">
      <strong>From:</strong>&nbsp;<?= htmlspecialchars(SITE_NAME) ?><br>
      <?php if (STORE_ADDRESS): ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars(STORE_ADDRESS) ?><br>
      <?php endif; ?>
      <?php if (STORE_PHONE): ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars(STORE_PHONE) ?>
      <?php endif; ?>
    </div>

    <!-- To -->
    <div class="s-to">
      <div class="s-to-row">
        <span class="s-to-lbl">To</span>
        <span class="s-cust-name"><?= htmlspecialchars($custName) ?></span>
      </div>
      <?php if ($shipTo): ?>
        <div class="s-address"><?= htmlspecialchars($shipTo) ?></div>
      <?php endif; ?>
      <?php if ($custPhone): ?>
        <div class="s-phone">
          <span class="s-phone-lbl">T.P.</span>&nbsp;&nbsp;<?= htmlspecialchars($custPhone) ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- COD Amount -->
    <div class="s-cod">
      COD Amount:&nbsp;&nbsp;<?= CURRENCY . number_format((float)$order['total'], 2) ?>
    </div>

    <!-- Items + QR -->
    <div class="s-bottom">
      <div class="s-items-list">
        <?php foreach ($items as $item): ?>
          <div class="s-item-row">
            <span class="s-item-nm"><?= htmlspecialchars($item['product_name']) ?> &times;<?= (int)$item['quantity'] ?></span>
            <?php if (!empty($item['color'])): ?>
              <span class="s-item-color"><?= htmlspecialchars($item['color']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="s-qr">
        <div id="qrcode-<?= $order['id'] ?>"></div>
        <span class="s-qr-lbl">Scan to update status</span>
      </div>
    </div>

    <!-- Two delivery company sticker spaces -->
    <div class="s-delivery-spaces">
      <div class="s-delivery-box">Delivery Sticker</div>
      <div class="s-delivery-box">Delivery Sticker</div>
    </div>

  </div>
</div>
<?php endforeach; ?>

<script src="<?= BASE_URL ?>/assets/vendor/qrcode/qrcode.min.js"></script>
<script>
var qrData = <?= json_encode(array_map(fn($o) => [
    'id'  => $o['id'],
    'url' => SCAN_BASE_URL . '/scan.php?id=' . $o['id']
], $orders)) ?>;

qrData.forEach(function(item) {
  var el = document.getElementById("qrcode-" + item.id);
  if (el) {
    new QRCode(el, {
      text: item.url,
      width: 72,
      height: 72,
      colorDark: "#000000",
      colorLight: "#ffffff",
      correctLevel: QRCode.CorrectLevel.M
    });
  }
});

// Wait for QR codes to render before opening print dialog
function printWhenReady() {
  setTimeout(function() { window.print(); }, 300);
}
</script>
</body>
</html>
