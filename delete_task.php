<?php
// filepath: c:\xampp\htdocs\php\TM\delete_task.php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid task ID";
    header('Location: tasks.php');
    exit;
}

$task_id = $_GET['id'];
$project_id = $_GET['project_id'] ?? null;

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
        $_SESSION['error'] = "You don't have permission to delete this task";
        header('Location: tasks.php');
        exit;
    }
    
    // Store project ID for redirection if not provided
    if (!$project_id) {
        $project_id = $result['project_id'];
    }
    
    // Delete task
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ?");
    $delete_result = $stmt->execute([$task_id]);
    
    if ($delete_result) {
        $_SESSION['success'] = "Task deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete task";
    }
    
    // Redirect back to project page or tasks page
    if ($project_id) {
        header("Location: project.php?id=$project_id");
    } else {
        header("Location: tasks.php");
    }
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: tasks.php');
    exit;
}