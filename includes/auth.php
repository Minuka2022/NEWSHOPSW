<?php
// ─── Authentication gate ──────────────────────────────────────────────────────
// Include this at the very top of every protected page (before any output),
// then call requireLogin(). Login is a single shared password from config.php.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

// Login is disabled if no password is configured.
function loginEnabled() {
    return defined('APP_PASSWORD') && APP_PASSWORD !== '';
}

function isLoggedIn() {
    return !loginEnabled() || !empty($_SESSION['authed']);
}

// Redirect to the login page if not authenticated. Must run before HTML output.
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}
