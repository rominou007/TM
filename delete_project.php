<?php
session_start();
require_once 'functions.php';
require_once 'config/db_connect.php';

require_login();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Validate project ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid project ID";
    header('Location: projects.php');
    exit;
}

// Validate CSRF token
if (!validate_csrf_token($_GET['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid CSRF token.';
    log_error('CSRF token mismatch on delete_project');
    header('Location: projects.php');
    exit;
}

$project_id = $_GET['id'];

try {
    // Verify project ownership
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if (!$project || $project['user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = "You don't have permission to delete this project";
        header('Location: projects.php');
        exit;
    }

    // Delete project (tasks will cascade via FK)
    $stmt = $pdo->prepare("DELETE FROM projects WHERE project_id = ?");
    $result = $stmt->execute([$project_id]);

    if ($result) {
        $_SESSION['success'] = "Project deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete project";
    }

    header('Location: projects.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    log_error('Delete project error: ' . $e->getMessage());
    header('Location: projects.php');
    exit;
}
