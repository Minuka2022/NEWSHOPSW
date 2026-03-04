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
