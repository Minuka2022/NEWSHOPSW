<?php
require_once __DIR__ . '/includes/auth.php';   // starts session, loads config

// Already logged in (or login disabled) → go straight to the dashboard
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if (hash_equals(APP_PASSWORD, $pw)) {
        $_SESSION['authed'] = true;
        session_regenerate_id(true);            // prevent session fixation
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    $error = 'Incorrect password. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(SITE_NAME) ?> — Login</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
<style>
  body { background: linear-gradient(135deg,#1e3a8a,#2563eb); min-height: 100vh; font-family: Arial, sans-serif; display: flex; align-items: center; }
  .login-card { max-width: 380px; width: 100%; margin: 0 auto; border: none; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,.25); }
  .brand-icon { width: 60px; height: 60px; border-radius: 14px; background: #eff6ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
</style>
</head>
<body>
<div class="container">
  <div class="card login-card">
    <div class="card-body p-4 p-sm-5">
      <div class="brand-icon"><i class="fas fa-store" style="color:#2563eb;font-size:1.6rem"></i></div>
      <h4 class="text-center fw-bold mb-1"><?= htmlspecialchars(SITE_NAME) ?></h4>
      <p class="text-center text-muted small mb-4">Sign in to continue</p>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><i class="fas fa-triangle-exclamation me-1"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <label class="form-label">Password</label>
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="fas fa-lock"></i></span>
          <input type="password" name="password" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
          <i class="fas fa-right-to-bracket me-1"></i>Sign In
        </button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
