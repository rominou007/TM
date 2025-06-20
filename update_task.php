<?php
// filepath: c:\xampp\htdocs\php\TM\update_task.php
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
        log_error('CSRF token mismatch on update_task');
        header('Location: tasks.php');
        exit;
    }
    $task_id = $_POST['task_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'To Do';
    $priority = $_POST['priority'] ?? 'Medium';
    $due_date = $_POST['due_date'] ?? null;
    
    // Validate input
    if (empty($task_id) || !is_numeric($task_id)) {
        $_SESSION['error'] = "Invalid task ID";
        header('Location: tasks.php');
        exit;
    }
    
    if (empty($title)) {
        $_SESSION['error'] = "Task title is required";
        header("Location: task.php?id=$task_id");
        exit;
    }
    
    try {
        // Verify task belongs to user's project
        $stmt = $pdo->prepare("
            SELECT t.project_id, p.user_id 
            FROM tasks t
            JOIN projects p ON t.project_id = p.project_id
            WHERE t.task_id = ?
        ");
        $stmt->execute([$task_id]);
        $result = $stmt->fetch();
        
        if (!$result || $result['user_id'] != $_SESSION['user_id']) {
            $_SESSION['error'] = "You don't have permission to update this task";
            header('Location: tasks.php');
            exit;
        }
        
        // Prepare SQL based on whether due_date is provided
        if (!empty($due_date)) {
            if ($setCompletedAt) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, status = ?, priority = ?, due_date = ?, completed_at = NOW(), updated_at = CURRENT_TIMESTAMP
                    WHERE task_id = ?
                ");
                $update_result = $stmt->execute([
                    $title, $description, $status, $priority, $due_date, $task_id
                ]);
            } elseif ($clearCompletedAt) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, status = ?, priority = ?, due_date = ?, completed_at = NULL, updated_at = CURRENT_TIMESTAMP
                    WHERE task_id = ?
                ");
                $update_result = $stmt->execute([
                    $title, $description, $status, $priority, $due_date, $task_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, status = ?, priority = ?, due_date = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE task_id = ?
                ");
                $update_result = $stmt->execute([
                    $title, $description, $status, $priority, $due_date, $task_id
                ]);
            }
        } else {
            if ($setCompletedAt) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, status = ?, priority = ?, due_date = NULL, completed_at = NOW(), updated_at = CURRENT_TIMESTAMP
                    WHERE task_id = ?
                ");
                $update_result = $stmt->execute([
                    $title, $description, $status, $priority, $task_id
                ]);
            } elseif ($clearCompletedAt) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, status = ?, priority = ?, due_date = NULL, completed_at = NULL, updated_at = CURRENT_TIMESTAMP
                    WHERE task_id = ?
                ");
                $update_result = $stmt->execute([
                    $title, $description, $status, $priority, $task_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, status = ?, priority = ?, due_date = NULL, updated_at = CURRENT_TIMESTAMP
                    WHERE task_id = ?
                ");
                $update_result = $stmt->execute([
                    $title, $description, $status, $priority, $task_id
                ]);
            }
        }
        
        if ($update_result) {
            $_SESSION['success'] = "Task updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update task";
        }
        
        // Store referrer for redirection if provided
        $referrer = $_POST['referrer'] ?? "task.php?id=$task_id";
        header("Location: $referrer");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: task.php?id=$task_id");
        exit;
    }
} else {
    // If not POST request, redirect to tasks page
    header('Location: tasks.php');
    exit;
}