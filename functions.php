<?php
// functions.php - Common utilities for security and error handling

// CSRF Token Utilities
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Error Logging
function log_error($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/error.log';
    $date = date('Y-m-d H:i:s');
    $msg = "[$date] $message\n";
    file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
}

// Session Check Helper
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Regenerate session ID securely
function secure_session_regenerate() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
} 