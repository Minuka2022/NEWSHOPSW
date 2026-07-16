<?php
function currency($amount) {
    return CURRENCY . number_format((float)$amount, 2);
}

function generateOrderNumber($conn) {
    $prefix = 'ORD-' . date('Ymd') . '-';
    $result = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE order_number LIKE '$prefix%'");
    $row    = $result->fetch_assoc();
    return $prefix . str_pad($row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
}

function statusBadge($status) {
    $map = [
        'pending'    => ['warning',  'Pending'],
        'processing' => ['primary',  'Processing'],
        'shipped'    => ['info',     'Shipped'],
        'delivered'  => ['success',  'Delivered'],
        'cancelled'  => ['danger',   'Cancelled'],
    ];
    $s = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge bg-' . $s[0] . '">' . $s[1] . '</span>';
}

function sanitize($conn, $str) {
    return $conn->real_escape_string(trim(strip_tags($str)));
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function showFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icon = $f['type'] === 'success' ? '&#10003;' : '&#9888;';
        echo '<div class="alert alert-' . $f['type'] . ' alert-dismissible fade show" role="alert">
            ' . $icon . ' ' . htmlspecialchars($f['msg']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}

function activeLink($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return ($current === $page) ? 'active' : '';
}

function getLowStockCount($conn) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE stock <= 5 AND active=1");
    return (int)$r->fetch_assoc()['cnt'];
}

function getPendingOrderCount($conn) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE status='pending'");
    return (int)$r->fetch_assoc()['cnt'];
}

// ─── Stock adjustment helpers ─────────────────────────────────────────────────
// Add an order's quantities back into stock (per-color if the item has a color,
// otherwise the product's main stock). Used when cancelling/deleting an order.
function restoreOrderStock($conn, $order_id) {
    $order_id = (int)$order_id;
    $res = $conn->query("SELECT product_id, quantity, color FROM order_items WHERE order_id=$order_id");
    while ($it = $res->fetch_assoc()) {
        $pid = (int)$it['product_id'];
        $qty = (int)$it['quantity'];
        if (!$pid || $qty <= 0) continue;                 // product deleted or empty row
        if (!empty($it['color'])) {
            $stmt = $conn->prepare("UPDATE product_colors SET stock=stock+? WHERE product_id=? AND color_name=?");
            $stmt->bind_param('iis', $qty, $pid, $it['color']);
        } else {
            $stmt = $conn->prepare("UPDATE products SET stock=stock+? WHERE id=?");
            $stmt->bind_param('ii', $qty, $pid);
        }
        $stmt->execute();
    }
}

// Take an order's quantities back out of stock. Used when an order returns from
// cancelled to an active status.
function deductOrderStock($conn, $order_id) {
    $order_id = (int)$order_id;
    $res = $conn->query("SELECT product_id, quantity, color FROM order_items WHERE order_id=$order_id");
    while ($it = $res->fetch_assoc()) {
        $pid = (int)$it['product_id'];
        $qty = (int)$it['quantity'];
        if (!$pid || $qty <= 0) continue;
        if (!empty($it['color'])) {
            $stmt = $conn->prepare("UPDATE product_colors SET stock=stock-? WHERE product_id=? AND color_name=?");
            $stmt->bind_param('iis', $qty, $pid, $it['color']);
        } else {
            $stmt = $conn->prepare("UPDATE products SET stock=stock-? WHERE id=?");
            $stmt->bind_param('ii', $qty, $pid);
        }
        $stmt->execute();
    }
}
