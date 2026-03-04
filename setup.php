<?php
require_once 'config.php';

// Check-only mode
if (isset($_GET['check'])) {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        echo '<p style="color:red">DB Error: ' . $conn->connect_error . '</p>';
    } else {
        $tables = [];
        $r = $conn->query("SHOW TABLES");
        while ($row = $r->fetch_row()) $tables[] = $row[0];
        echo '<p style="color:green;font-family:Arial">&#10003; Connected to <b>' . DB_NAME . '</b>. Tables: ' . implode(', ', $tables) . '</p>';
    }
    exit;
}

$errors = []; $success = [];

// Connect without DB first to create it
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die('<h2 style="color:red;font-family:Arial">Cannot connect: ' . $conn->connect_error . '<br>Check config.php credentials.</h2>');
}

$conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);
$conn->set_charset('utf8mb4');

$sql_tables = "

CREATE TABLE IF NOT EXISTS categories (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT DEFAULT NULL,
    name        VARCHAR(200) NOT NULL,
    sku         VARCHAR(80) DEFAULT NULL,
    description TEXT,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost        DECIMAL(10,2) DEFAULT 0.00,
    stock       INT NOT NULL DEFAULT 0,
    unit        VARCHAR(40) DEFAULT 'pcs',
    active      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    phone      VARCHAR(40) DEFAULT NULL,
    email      VARCHAR(160) DEFAULT NULL,
    address    TEXT,
    notes      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    customer_id      INT DEFAULT NULL,
    order_number     VARCHAR(60) NOT NULL UNIQUE,
    status           ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    subtotal         DECIMAL(10,2) DEFAULT 0.00,
    discount         DECIMAL(10,2) DEFAULT 0.00,
    total            DECIMAL(10,2) DEFAULT 0.00,
    notes            TEXT,
    shipping_address TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT NOT NULL,
    product_id   INT DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity     INT NOT NULL DEFAULT 1,
    unit_price   DECIMAL(10,2) NOT NULL,
    total_price  DECIMAL(10,2) NOT NULL,
    color        VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS product_colors (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    color_name VARCHAR(100) NOT NULL,
    stock      INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

";

// Run each statement
foreach (array_filter(array_map('trim', explode(';', $sql_tables))) as $sql) {
    if ($sql) {
        if ($conn->query($sql)) {
            $success[] = substr($sql, 0, 60) . '...';
        } else {
            $errors[] = $conn->error . ' | ' . substr($sql, 0, 60);
        }
    }
}

// ── Column migrations (safe to run on every visit) ────────────────────────────────
$conn->query("ALTER TABLE order_items    ADD COLUMN IF NOT EXISTS color VARCHAR(100) DEFAULT NULL AFTER total_price");
$conn->query("ALTER TABLE product_colors ADD COLUMN IF NOT EXISTS stock INT NOT NULL DEFAULT 0   AFTER color_name");
$success[] = 'Column migrations applied';

// ── Seed categories (if empty or force) ──────────────────────────────────────────
$force = isset($_GET['force']);
$cat_count = $conn->query("SELECT COUNT(*) as c FROM categories")->fetch_assoc()['c'];
if ($cat_count == 0 || $force) {
    $conn->query("DELETE FROM product_colors");
    $conn->query("DELETE FROM order_items");
    $conn->query("DELETE FROM products");
    $conn->query("DELETE FROM customers");
    $conn->query("DELETE FROM categories");
    $conn->query("ALTER TABLE categories AUTO_INCREMENT=1");
    $conn->query("ALTER TABLE products AUTO_INCREMENT=1");
    $conn->query("ALTER TABLE customers AUTO_INCREMENT=1");
    $cats = ['Clothes', 'Bags', 'Tools', 'Accessories', 'Electronics', 'Other'];
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    foreach ($cats as $c) { $stmt->bind_param('s', $c); $stmt->execute(); }
    $success[] = 'Sample categories added';
}

// ── Seed customers ────────────────────────────────────────────────────────────────
$cust_count = $conn->query("SELECT COUNT(*) as c FROM customers")->fetch_assoc()['c'];
if ($cust_count == 0) {
    $customers = [
        ['John Smith',  '+1 555-0101', 'john@email.com',  '123 Main St, City, ST 10001'],
        ['Sarah Jones', '+1 555-0202', 'sarah@email.com', '456 Oak Ave, Town, ST 20002'],
    ];
    $stmt2 = $conn->prepare("INSERT INTO customers (name, phone, email, address) VALUES (?,?,?,?)");
    foreach ($customers as $cust) {
        $stmt2->bind_param('ssss', $cust[0], $cust[1], $cust[2], $cust[3]);
        $stmt2->execute();
    }
    $success[] = 'Sample customers added';
}

// ── Seed products ─────────────────────────────────────────────────────────────────
$prod_count = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
if ($prod_count == 0) {
    // Look up category IDs dynamically so they work regardless of insertion order
    $catIds = [];
    $cr = $conn->query("SELECT id, name FROM categories");
    while ($row = $cr->fetch_assoc()) $catIds[$row['name']] = $row['id'];

    $prods = [
        [$catIds['Clothes']     ?? 1, 'Blue Denim Jacket', 'JKT-001', 'Classic blue denim jacket', 59.99, 30.00, 20],
        [$catIds['Clothes']     ?? 1, 'White T-Shirt',     'TSH-001', 'Plain white t-shirt',        12.99,  5.00, 50],
        [$catIds['Bags']        ?? 2, 'Leather Handbag',   'BAG-001', 'Brown leather handbag',      79.99, 40.00, 15],
        [$catIds['Bags']        ?? 2, 'Canvas Tote',       'BAG-002', 'Eco canvas tote bag',         9.99,  4.00, 30],
        [$catIds['Tools']       ?? 3, 'Hammer',            'TLT-001', 'Steel claw hammer 16oz',     18.99,  9.00, 25],
        [$catIds['Tools']       ?? 3, 'Screwdriver Set',   'TLT-002', '12-piece screwdriver set',   24.99, 12.00, 10],
        [$catIds['Accessories'] ?? 4, 'Sunglasses',        'ACC-001', 'UV400 sunglasses',           14.99,  6.00, 40],
        [$catIds['Electronics'] ?? 5, 'USB-C Cable',       'ELC-001', '2m USB-C braided cable',      8.99,  3.00,  3],
    ];
    $stmt3 = $conn->prepare("INSERT INTO products (category_id, name, sku, description, price, cost, stock) VALUES (?,?,?,?,?,?,?)");
    foreach ($prods as $p) {
        $stmt3->bind_param('isssddi', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
        $stmt3->execute();
    }
    $success[] = 'Sample products added (8 products)';
}

// ── Seed bag colors (if empty) ────────────────────────────────────────────────────
$color_count = $conn->query("SELECT COUNT(*) as c FROM product_colors")->fetch_assoc()['c'];
if ($color_count == 0) {
    $bagRows   = $conn->query("SELECT p.id FROM products p JOIN categories c ON c.id=p.category_id WHERE c.name='Bags' AND p.active=1");
    $bagColors = ['Red','Blue','Black','Brown','Green','Purple','Pink'];
    $stmtC     = $conn->prepare("INSERT INTO product_colors (product_id, color_name, stock) VALUES (?,?,?)");
    $added = 0;
    while ($br = $bagRows->fetch_assoc()) {
        foreach ($bagColors as $col) {
            $defaultStock = 5;
            $stmtC->bind_param('isi', $br['id'], $col, $defaultStock);
            $stmtC->execute();
            $added++;
        }
    }
    if ($added) $success[] = 'Sample colors added for bag products';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ShopSW — Setup</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>body{background:#f1f5f9;font-family:'Inter',sans-serif;}</style>
</head>
<body>
<div class="container" style="max-width:680px;padding-top:60px">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h2 class="fw-bold mb-1">&#128736; ShopSW Setup</h2>
      <p class="text-muted mb-4">Database initialization</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <b>&#9888; Errors:</b><ul class="mb-0 mt-1">
          <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <b>&#10003; Setup completed!</b>
          <ul class="mb-0 mt-1">
          <?php foreach($success as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!$errors): ?>
        <div class="d-flex gap-2 mt-3 flex-wrap">
          <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary">&#128640; Open ShopSW Manager</a>
          <a href="<?= BASE_URL ?>/setup.php?check=1" class="btn btn-outline-secondary" target="_blank">Check DB Status</a>
          <a href="<?= BASE_URL ?>/setup.php?force=1" class="btn btn-outline-danger"
             onclick="return confirm('This will DELETE all sample data and re-seed. Continue?')">&#128260; Re-seed Sample Data</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-center text-muted mt-3 small">
    After setup, bookmark <code><?= BASE_URL ?>/index.php</code>
  </p>
</div>
</body>
</html>
