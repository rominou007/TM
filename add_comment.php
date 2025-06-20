<?php
session_start();
require_once 'functions.php';
require_once 'config/db_connect.php';

require_login();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        log_error('CSRF token mismatch on add_comment');
        header('Location: projects.php');
        exit;
    }
    $task_id = $_POST['task_id'] ?? '';
    $comment = trim($_POST['comment'] ?? '');
    
    // Validate input
    if (empty($task_id) || !is_numeric($task_id)) {
        $_SESSION['error'] = "Invalid task ID";
        header('Location: projects.php');
        exit;
    }
    
    if (empty($comment)) {
        $_SESSION['error'] = "Comment cannot be empty";
        header("Location: task.php?id=$task_id");
        exit;
    }
    
    try {
        // Verify task belongs to user's project
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tasks t
            JOIN projects p ON t.project_id = p.project_id
            WHERE t.task_id = ? AND p.user_id = ?
        ");
        $stmt->execute([$task_id, $_SESSION['user_id']]);
        
        if ($stmt->fetchColumn() == 0) {
            $_SESSION['error'] = "You don't have permission to comment on this task";
            header('Location: projects.php');
            exit;
        }
        
        // Insert comment
        $stmt = $pdo->prepare("
            INSERT INTO task_comments (task_id, user_id, comment)
            VALUES (?, ?, ?)
        ");
        $result = $stmt->execute([$task_id, $_SESSION['user_id'], $comment]);
        
        if ($result) {
            $_SESSION['success'] = "Comment added successfully";
        } else {
            $_SESSION['error'] = "Failed to add comment";
        }
        
        header("Location: task.php?id=$task_id");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        log_error('Add comment error: ' . $e->getMessage());
        header("Location: task.php?id=$task_id");
        exit;
    }
} else {
    // If not POST request, redirect to projects page
    header('Location: projects.php');
    exit;
}