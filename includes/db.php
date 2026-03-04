<?php
require_once dirname(__DIR__) . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('<div style="font-family:Arial;padding:40px;text-align:center;">
        <h2 style="color:#ef4444">&#9888; Database Error</h2>
        <p>' . htmlspecialchars($conn->connect_error) . '</p>
        <p>Please run <a href="' . BASE_URL . '/setup.php">setup.php</a> first.</p>
    </div>');
}

$conn->set_charset('utf8mb4');
