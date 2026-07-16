<?php
// ─── Local overrides ──────────────────────────────────────────────────────────
// config.local.php holds this machine's real settings (password, DB details,
// store address). It is NOT tracked by git, so secrets never reach GitHub.
// It is loaded first: in PHP the first define() wins, so anything set there
// overrides the defaults below. See config.local.example.php to create one.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ─── Database Configuration ───────────────────────────────────────────────────
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');     // Change if your MySQL has a password
defined('DB_NAME') || define('DB_NAME', 'shopsw');

// ─── App Settings ─────────────────────────────────────────────────────────────
defined('SITE_NAME')     || define('SITE_NAME',     'Glomax Gadgets');
defined('CURRENCY')      || define('CURRENCY',      'Rs. ');
defined('APP_VERSION')   || define('APP_VERSION',   '1.0');
defined('STORE_ADDRESS') || define('STORE_ADDRESS', '');   // e.g. "123 Main St, Colombo 07"
defined('STORE_PHONE')   || define('STORE_PHONE',   '');   // e.g. "077 123 4567"

// ─── Login ────────────────────────────────────────────────────────────────────
// Password required to open the manager. Set the real one in config.local.php —
// do NOT put it here, this file is public on GitHub.
// To disable the login entirely, set it to an empty string ''.
defined('APP_PASSWORD')  || define('APP_PASSWORD',  'changeme');

// ─── Dynamic Base URL ─────────────────────────────────────────────────────────
// BASE_URL   — uses whatever host the browser used (localhost or LAN IP).
//              Makes all navigation links work on any device automatically.
// SCAN_BASE_URL — always uses the machine's real LAN IP so QR codes work on
//              phones even when the sticker page was opened via localhost on PC.
if (!defined('BASE_URL')) {
    $__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $__host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $__scheme . '://' . $__host . '/NEWSHOPSW');

    // For QR codes: find all local IPs (WiFi, hotspot, ethernet) and pick the best one
    $__allIPs = @gethostbynamel(gethostname()) ?: [];
    $__validIPs = array_values(array_filter($__allIPs, function($ip) {
        return $ip !== '127.0.0.1' && substr($ip, 0, 7) !== '169.254'; // exclude loopback & APIPA
    }));
    // Prefer hotspot IP (192.168.137.x) if WiFi is off; otherwise use first valid IP
    $__lanIP = $__validIPs[0] ?? $__host;
    foreach ($__validIPs as $__ip) {
        if (substr($__ip, 0, 11) === '192.168.137') { $__lanIP = $__ip; break; } // hotspot
    }
    define('SCAN_BASE_URL', $__scheme . '://' . $__lanIP . '/NEWSHOPSW');
    define('ALL_LOCAL_IPS', $__validIPs); // used by dashboard to show all access options
    unset($__scheme, $__host, $__allIPs, $__validIPs, $__lanIP, $__ip);
}
