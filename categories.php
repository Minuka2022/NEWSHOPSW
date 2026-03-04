<?php
session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = sanitize($conn, $_POST['name']);
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    flash('success', 'Category "' . $name . '" added.');
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
