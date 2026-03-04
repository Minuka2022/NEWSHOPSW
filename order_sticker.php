<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid order.');

$order = $conn->query("
    SELECT o.*, c.name as cust_name, c.phone as cust_phone, c.email as cust_email
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id=$id LIMIT 1
")->fetch_assoc();

if (!$order) die('Order not found.');

$items = $conn->query("SELECT * FROM order_items WHERE order_id=$id")->fetch_all(MYSQLI_ASSOC);

// Barcode-style representation of order number
$barcodeStr = str_replace('-', ' ', $order['order_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sticker — <?= htmlspecialchars($order['order_number']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #e5e7eb; font-family: 'Inter', Arial, sans-serif; }

  .no-print { text-align:center; padding:20px; background:#fff; border-bottom:1px solid #e5e7eb; }
  .no-print button { background:#3b82f6; color:#fff; border:none; padding:10px 28px; border-radius:8px; font-size:1rem; cursor:pointer; margin:0 6px; }
  .no-print .btn-back { background:#6b7280; }

  /* ── Sticker ── */
  .sticker-wrapper { display:flex; justify-content:center; padding:30px; gap:20px; flex-wrap:wrap; }

  .sticker {
    background: #fff;
    width: 400px;
    border: 3px solid #1e293b;
    border-radius: 6px;
    padding: 0;
    font-family: 'Inter', Arial, sans-serif;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  }

  /* Sticker Header */
  .s-head {
    background: #1e293b;
    color: #fff;
    text-align: center;
    padding: 16px 20px 12px;
  }
  .s-brand { font-size: 1.3rem; font-weight: 900; letter-spacing: 2px; }
  .s-tagline { font-size: 0.7rem; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }

  /* Order number */
  .s-order-num {
    text-align: center;
    padding: 14px 20px;
    border-bottom: 2px dashed #cbd5e1;
    background: #f8fafc;
  }
  .s-order-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; font-weight: 600; }
  .s-order-val   { font-size: 1.6rem; font-weight: 900; letter-spacing: 4px; color: #1e293b; margin: 4px 0; }
  .s-date        { font-size: 0.72rem; color: #64748b; }

  /* Customer section */
  .s-customer {
    padding: 14px 18px;
    border-bottom: 1px solid #e2e8f0;
  }
  .s-section-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; font-weight: 700; margin-bottom: 4px; }
  .s-cust-name     { font-size: 1rem; font-weight: 700; color: #1e293b; }
  .s-cust-phone    { font-size: 0.8rem; color: #475569; margin-top: 2px; }
  .s-address       { font-size: 0.8rem; color: #374151; margin-top: 4px; line-height: 1.5; }

  /* Items section */
  .s-items {
    padding: 12px 18px;
    border-bottom: 2px dashed #cbd5e1;
  }
  .s-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 0.8rem;
    border-bottom: 1px dotted #e2e8f0;
  }
  .s-item:last-child { border-bottom: none; }
  .s-item-name { color: #374151; font-weight: 500; flex: 1; }
  .s-item-qty  { color: #64748b; margin: 0 8px; }
  .s-item-price{ color: #1e293b; font-weight: 600; }

  /* Total */
  .s-total {
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
  }
  .s-total-label { font-size: 0.8rem; font-weight: 600; color: #374151; }
  .s-total-val   { font-size: 1.2rem; font-weight: 900; color: #1e293b; }

  /* Status */
  .s-status {
    text-align: center;
    padding: 10px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
  }

  /* Barcode */
  .s-barcode {
    text-align: center;
    padding: 12px 18px 16px;
    border-top: 1px solid #e2e8f0;
  }
  .s-barcode-font {
    font-family: 'Libre Barcode 39 Text', monospace;
    font-size: 2.2rem;
    color: #1e293b;
    line-height: 1;
    letter-spacing: 2px;
  }
  .s-barcode-text {
    font-size: 0.65rem;
    color: #94a3b8;
    letter-spacing: 3px;
    margin-top: 2px;
  }

  /* Status colors */
  .status-pending    { background:#fef3c7; color:#92400e; }
  .status-processing { background:#dbeafe; color:#1e40af; }
  .status-shipped    { background:#e0f2fe; color:#075985; }
  .status-delivered  { background:#d1fae5; color:#065f46; }
  .status-cancelled  { background:#fee2e2; color:#991b1b; }

  /* ── Print ── */
  @media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .sticker-wrapper { padding: 0; justify-content: flex-start; }
    .sticker { box-shadow: none; border: 2px solid #000; page-break-after: always; }
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Print Sticker</button>
  <button class="btn-back" onclick="history.back()">&#8592; Back</button>
  <p style="margin-top:10px;color:#6b7280;font-size:0.85rem">Use Ctrl+P or the button above to print. Set paper size to A5 or Letter for best results.</p>
</div>

<div class="sticker-wrapper print-area">
  <div class="sticker">

    <!-- Header -->
    <div class="s-head">
      <div class="s-brand"><?= SITE_NAME ?></div>
      <div class="s-tagline">Order Shipping Label</div>
    </div>

    <!-- Order Number -->
    <div class="s-order-num">
      <div class="s-order-label">Order Number</div>
      <div class="s-order-val"><?= htmlspecialchars($order['order_number']) ?></div>
      <div class="s-date"><?= date('D, d M Y  H:i', strtotime($order['created_at'])) ?></div>
    </div>

    <!-- Customer / Ship-to -->
    <div class="s-customer">
      <div class="s-section-label">Ship To</div>
      <div class="s-cust-name"><?= htmlspecialchars($order['cust_name'] ?? 'Walk-in Customer') ?></div>
      <?php if ($order['cust_phone']): ?>
        <div class="s-cust-phone">&#128222; <?= htmlspecialchars($order['cust_phone']) ?></div>
      <?php endif; ?>
      <?php if ($order['shipping_address']): ?>
        <div class="s-address"><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></div>
      <?php elseif ($order['cust_email']): ?>
        <div class="s-address"><?= htmlspecialchars($order['cust_email']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Items -->
    <div class="s-items">
      <div class="s-section-label" style="margin-bottom:6px">Items</div>
      <?php foreach($items as $item): ?>
        <div class="s-item">
          <span class="s-item-name"><?= htmlspecialchars($item['product_name']) ?></span>
          <span class="s-item-qty">x<?= $item['quantity'] ?></span>
          <span class="s-item-price"><?= currency($item['total_price']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Total -->
    <div class="s-total">
      <span class="s-total-label">
        <?php if ((float)$order['discount'] > 0): ?>
          Total (Disc: <?= currency($order['discount']) ?>)
        <?php else: ?>
          Order Total
        <?php endif; ?>
      </span>
      <span class="s-total-val"><?= currency($order['total']) ?></span>
    </div>

    <!-- Status -->
    <div class="s-status status-<?= $order['status'] ?>">
      &#9679; <?= strtoupper($order['status']) ?>
    </div>

    <!-- Notes -->
    <?php if ($order['notes']): ?>
    <div style="padding:10px 18px;font-size:0.75rem;color:#475569;border-top:1px dashed #e2e8f0;background:#fffbeb">
      <strong>Note:</strong> <?= htmlspecialchars($order['notes']) ?>
    </div>
    <?php endif; ?>

    <!-- Barcode -->
    <div class="s-barcode">
      <div class="s-barcode-font">*<?= htmlspecialchars($order['order_number']) ?>*</div>
      <div class="s-barcode-text"><?= htmlspecialchars($order['order_number']) ?></div>
    </div>

  </div><!-- /sticker -->
</div>

</body>
</html>
