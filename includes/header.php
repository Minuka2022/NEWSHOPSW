<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$lowStock    = getLowStockCount($conn);
$pendingOrds = getPendingOrderCount($conn);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= SITE_NAME ?> — <?= $pageTitle ?? 'Home' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- ── Sidebar ── -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fas fa-store"></i>
    <span><?= SITE_NAME ?></span>
  </div>
  <nav class="sidebar-nav">
    <a href="<?= BASE_URL ?>/index.php"       class="nav-link <?= activeLink('index.php') ?>">
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/orders.php"      class="nav-link <?= activeLink('orders.php') ?>">
      <i class="fas fa-shopping-cart"></i> Orders
      <?php if ($pendingOrds > 0): ?>
        <span class="badge bg-warning text-dark ms-auto"><?= $pendingOrds ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/products.php"    class="nav-link <?= activeLink('products.php') ?>">
      <i class="fas fa-box-open"></i> Products
      <?php if ($lowStock > 0): ?>
        <span class="badge bg-danger ms-auto"><?= $lowStock ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/customers.php"   class="nav-link <?= activeLink('customers.php') ?>">
      <i class="fas fa-users"></i> Customers
    </a>
    <hr class="sidebar-divider">
    <a href="<?= BASE_URL ?>/print_stickers.php" class="nav-link <?= activeLink('print_stickers.php') ?>" target="_blank">
      <i class="fas fa-tags"></i> Print Labels
      <?php if ($pendingOrds > 0): ?>
        <span class="badge bg-warning text-dark ms-auto"><?= $pendingOrds ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/reports.php"     class="nav-link <?= activeLink('reports.php') ?>">
      <i class="fas fa-chart-line"></i> Reports
    </a>
    <a href="<?= BASE_URL ?>/setup.php?check=1" class="nav-link" target="_blank">
      <i class="fas fa-database"></i> DB Status
    </a>
  </nav>
  <div class="sidebar-footer">
    <small><i class="fas fa-circle text-success"></i> System Online</small>
  </div>
</div>

<!-- ── Main Wrapper ── -->
<div class="main-wrapper" id="mainWrapper">
  <!-- Top Navbar -->
  <header class="topbar">
    <button class="btn btn-sm btn-outline-secondary me-3" id="sidebarToggle">
      <i class="fas fa-bars"></i>
    </button>
    <span class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></span>
    <div class="topbar-right ms-auto d-flex align-items-center gap-3">
      <a href="<?= BASE_URL ?>/orders.php?action=new" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> New Order
      </a>
      <span class="text-muted small"><?= date('D, d M Y') ?></span>
    </div>
  </header>

  <!-- Flash Message -->
  <div class="container-fluid px-4 pt-3">
    <?php showFlash(); ?>
  </div>

  <!-- Page Content starts here -->
  <main class="page-content">
