<?php
session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name']) && !isset($_GET['action'])) {
    $name   = sanitize($conn, $_POST['name']);
    $prefix = sanitize($conn, strtoupper(trim($_POST['sku_prefix'] ?? '')));
    $stmt = $conn->prepare("INSERT INTO categories (name, sku_prefix) VALUES (?, ?)");
    $stmt->bind_param('ss', $name, $prefix);
    $stmt->execute();
    flash('success', 'Category "' . $name . '" added.');
}

// Update SKU prefix
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_prefix') {
    $cid    = (int)($_POST['id'] ?? 0);
    $prefix = sanitize($conn, strtoupper(trim($_POST['sku_prefix'] ?? '')));
    if ($cid) {
        $stmt = $conn->prepare("UPDATE categories SET sku_prefix=? WHERE id=?");
        $stmt->bind_param('si', $prefix, $cid);
        $stmt->execute();
        flash('success', 'SKU prefix updated.');
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $cid = (int)$_GET['delete'];
    $conn->query("UPDATE products SET category_id=NULL WHERE category_id=$cid");
    $conn->query("DELETE FROM categories WHERE id=$cid");
    flash('success', 'Category deleted.');
}

$ref = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/products.php';
redirect($ref);
