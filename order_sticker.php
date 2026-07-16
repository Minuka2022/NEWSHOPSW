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

$shortNo   = str_pad($order['id'], 5, '0', STR_PAD_LEFT);
$orderDate = date('d/m/Y', strtotime($order['created_at']));
$orderTime = date('H:i',   strtotime($order['created_at']));
$shipTo    = $order['shipping_address'] ?: $order['cust_address'] ?: '';
$custName  = $order['cust_name']  ?: 'Walk-in Customer';
$custPhone = $order['cust_phone'] ?: '';
$scanUrl   = SCAN_BASE_URL . '/scan.php?id=' . $order['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Label — <?= htmlspecialchars($order['order_number']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #d1d5db; font-family: Arial, sans-serif; padding: 20px; }

.controls { text-align: center; margin-bottom: 20px; }
.controls button {
  background: #2563eb; color: #fff; border: none;
  padding: 9px 26px; border-radius: 6px; font-size: 0.95rem;
  cursor: pointer; margin: 0 5px;
}
.controls .btn-back { background: #6b7280; }
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
  <button class="btn-back" onclick="history.back()">&#8592; Back</button>
  <p>Set paper size to A5 / A6 / label paper (10&times;15 cm) for best results.</p>
</div>

<div class="sticker-wrap">
  <div class="sticker">

    <!-- Header: store name + No / Date / Time -->
    <div class="s-header">
      <div class="s-store-name"><?= htmlspecialchars(SITE_NAME) ?></div>
      <div class="s-meta">
        <div>No.&nbsp;&nbsp;<span class="meta-no"><?= $shortNo ?></span></div>
        <div>Date&nbsp;&nbsp;<?= $orderDate ?></div>
        <div>Time&nbsp;&nbsp;<?= $orderTime ?></div>
      </div>
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

    <!-- To: customer name, address, phone -->
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

    <!-- Items list + QR code -->
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
        <div id="qrcode"></div>
        <span class="s-qr-lbl">Scan to update status</span>
      </div>
    </div>

    <!-- Two delivery company sticker spaces -->
    <div class="s-delivery-spaces">
      <div class="s-delivery-box">Delivery Sticker</div>
      <div class="s-delivery-box">Delivery Sticker</div>
    </div>

  </div><!-- /sticker -->
</div>

<script src="<?= BASE_URL ?>/assets/vendor/qrcode/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById("qrcode"), {
  text: <?= json_encode($scanUrl) ?>,
  width: 72,
  height: 72,
  colorDark: "#000000",
  colorLight: "#ffffff",
  correctLevel: QRCode.CorrectLevel.M
});
</script>
</body>
</html>
