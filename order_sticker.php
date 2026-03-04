<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid order.');

$order = $conn->query("
    SELECT o.*, c.name AS cust_name, c.phone AS cust_phone, c.address AS cust_address
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id=$id LIMIT 1
")->fetch_assoc();

if (!$order) die('Order not found.');

$items = $conn->query("SELECT * FROM order_items WHERE order_id=$id ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Short numeric ID for the "No." field
$shortNo = str_pad($order['id'], 5, '0', STR_PAD_LEFT);
$orderDate = date('d/m/Y', strtotime($order['created_at']));
$orderTime = date('H:i',   strtotime($order['created_at']));

$shipTo  = $order['shipping_address'] ?: $order['cust_address'] ?: '';
$custName = $order['cust_name'] ?: 'Walk-in Customer';
$custPhone = $order['cust_phone'] ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Label — <?= htmlspecialchars($order['order_number']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #d1d5db; font-family: Arial, sans-serif; padding: 20px; }

.controls {
  text-align: center;
  margin-bottom: 20px;
}
.controls button {
  background: #2563eb; color: #fff; border: none;
  padding: 9px 26px; border-radius: 6px; font-size: 0.95rem;
  cursor: pointer; margin: 0 5px;
}
.controls .btn-stop { background: #6b7280; }
.controls p { margin-top: 8px; color: #6b7280; font-size: 0.8rem; }

/* ── Sticker ── */
.sticker-wrap { display: flex; justify-content: center; }

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
.s-meta {
  text-align: right;
  font-size: 0.68rem;
  line-height: 2;
}
.s-meta .meta-no {
  font-size: 0.95rem;
  font-weight: bold;
  color: #cc0000;
}

/* Barcode strip */
.s-bc-strip {
  border-bottom: 1px solid #ccc;
  text-align: center;
  height: 30px;
  overflow: hidden;
  padding: 0 6px;
  line-height: 1;
}
.s-bc-strip span {
  font-family: 'Libre Barcode 39 Text', monospace;
  font-size: 2.4rem;
  color: #111;
  letter-spacing: 1px;
  vertical-align: top;
}

/* From */
.s-from {
  padding: 5px 12px;
  font-size: 0.68rem;
  line-height: 1.7;
  color: #444;
  border-bottom: 2px solid #111;
}

/* To */
.s-to {
  padding: 10px 12px 8px;
  border-bottom: 1px solid #888;
}
.s-to-row {
  display: flex;
  align-items: flex-start;
  gap: 6px;
}
.s-to-lbl {
  font-size: 0.68rem;
  font-weight: bold;
  min-width: 18px;
  padding-top: 3px;
}
.s-cust-name {
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.3;
}
.s-address {
  font-size: 0.78rem;
  line-height: 1.6;
  color: #222;
  margin: 4px 0 0 24px;
  white-space: pre-line;
}
.s-phone {
  margin-top: 7px;
  font-size: 0.85rem;
}
.s-phone-lbl {
  font-size: 0.68rem;
  font-weight: bold;
  display: inline-block;
  width: 22px;
}

/* COD */
.s-cod {
  padding: 7px 12px;
  font-size: 1rem;
  font-weight: bold;
  border-top: 1px solid #888;
  border-bottom: 2px solid #111;
}

/* Items + barcode */
.s-bottom {
  display: flex;
  align-items: flex-start;
  padding: 8px 12px 10px;
  gap: 10px;
  min-height: 60px;
}
.s-items-list {
  flex: 1;
  font-size: 0.8rem;
  line-height: 2.1;
}
.s-item-row {
  display: flex;
  align-items: baseline;
  gap: 5px;
}
.s-item-nm  { flex: 1; }
.s-item-qty { color: #555; }
.s-item-color { font-weight: bold; }

/* Red barcode box (bottom-right) */
.s-bc-box {
  background: #cc0000;
  color: #fff;
  border-radius: 3px;
  padding: 5px 7px;
  text-align: center;
  min-width: 95px;
  align-self: flex-end;
}
.bc-font {
  font-family: 'Libre Barcode 39 Text', monospace;
  font-size: 1.6rem;
  line-height: 1;
  display: block;
  color: #fff;
  letter-spacing: 1px;
}
.bc-num {
  font-size: 0.55rem;
  letter-spacing: 0.5px;
  word-break: break-all;
  margin-top: 2px;
  display: block;
}

/* ── Print ── */
@media print {
  body { background: #fff; padding: 0; }
  .controls { display: none !important; }
  .sticker-wrap { justify-content: flex-start; }
  .sticker { border: 1px solid #000; }
}
</style>
</head>
<body>

<div class="controls">
  <button onclick="window.print()">&#128438; Print Label</button>
  <button class="btn-stop" onclick="history.back()">&#8592; Back</button>
  <p>Set paper size to A5 / A6 / label paper (10×15 cm) for best results.</p>
</div>

<div class="sticker-wrap">
  <div class="sticker">

    <!-- Header: store name + order meta -->
    <div class="s-header">
      <div class="s-store-name"><?= htmlspecialchars(SITE_NAME) ?></div>
      <div class="s-meta">
        <div>No.&nbsp;&nbsp;<span class="meta-no"><?= $shortNo ?></span></div>
        <div>Date&nbsp;&nbsp;<?= $orderDate ?></div>
        <div>Time&nbsp;&nbsp;<?= $orderTime ?></div>
      </div>
    </div>

    <!-- Barcode strip -->
    <div class="s-bc-strip">
      <span>*<?= htmlspecialchars($order['order_number']) ?>*</span>
    </div>

    <!-- From: store info -->
    <div class="s-from">
      <strong>From:</strong>&nbsp;<?= htmlspecialchars(SITE_NAME) ?><br>
      <?php if (STORE_ADDRESS): ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars(STORE_ADDRESS) ?><br>
      <?php endif; ?>
      <?php if (STORE_PHONE): ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars(STORE_PHONE) ?>
      <?php endif; ?>
    </div>

    <!-- To: customer -->
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
      COD Amount:&nbsp;&nbsp;<?= number_format((float)$order['total'], 2) ?>
    </div>

    <!-- Items list + red barcode box -->
    <div class="s-bottom">
      <div class="s-items-list">
        <?php foreach ($items as $item): ?>
          <div class="s-item-row">
            <span class="s-item-nm"><?= htmlspecialchars($item['product_name']) ?>-</span>
            <span class="s-item-qty"><?= (int)$item['quantity'] ?></span>
            <?php if (!empty($item['color'])): ?>
              <span class="s-item-color"><?= htmlspecialchars($item['color']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="s-bc-box">
        <span class="bc-font">*<?= $shortNo ?>*</span>
        <span class="bc-num"><?= htmlspecialchars($order['order_number']) ?></span>
      </div>
    </div>

  </div><!-- /sticker -->
</div>

</body>
</html>
