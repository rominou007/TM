<?php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if required parameters are provided
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error'] = "Invalid request";
    header('Location: projects.php');
    exit;
}

$task_id = $_GET['id'];
$status = $_GET['status'];

// Validate status
$valid_statuses = ['To Do', 'In Progress', 'Completed'];
if (!in_array($status, $valid_statuses)) {
    $_SESSION['error'] = "Invalid status";
    header('Location: projects.php');
    exit;
}

try {
    // Get the project ID and verify ownership
    $stmt = $pdo->prepare("
        SELECT t.project_id, p.user_id 
        FROM tasks t
        JOIN projects p ON t.project_id = p.project_id
        WHERE t.task_id = ?
    ");
    $stmt->execute([$task_id]);
    $result = $stmt->fetch();
    
    if (!$result) {
        $_SESSION['error'] = "Task not found";
        header('Location: projects.php');
        exit;
    }
    
    if ($result['user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = "You don't have permission to update this task";
        header('Location: projects.php');
        exit;
    }
    
    $project_id = $result['project_id'];
    
    // Fetch current status and completed_at
    $stmt = $pdo->prepare("SELECT status, completed_at FROM tasks WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $current = $stmt->fetch();
    $setCompletedAt = false;
    $clearCompletedAt = false;
    if ($current) {
        if ($status === 'Completed' && ($current['status'] !== 'Completed' || $current['completed_at'] === null)) {
            $setCompletedAt = true;
        } elseif ($current['status'] === 'Completed' && $status !== 'Completed') {
            $clearCompletedAt = true;
        }
    }
    if ($setCompletedAt) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NOW(), updated_at = CURRENT_TIMESTAMP WHERE task_id = ?");
        $update_result = $stmt->execute([$status, $task_id]);
    } elseif ($clearCompletedAt) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE task_id = ?");
        $update_result = $stmt->execute([$status, $task_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE task_id = ?");
        $update_result = $stmt->execute([$status, $task_id]);
    }
    
    if ($update_result) {
        $_SESSION['success'] = "Task status updated to " . htmlspecialchars($status);
    } else {
        $_SESSION['error'] = "Failed to update task status";
    }
    
    // Redirect back to project page
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'tasks.php';
    header('Location: ' . $referrer);
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: projects.php');
    exit;
}