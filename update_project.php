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
        log_error('CSRF token mismatch on update_project');
        header('Location: projects.php');
        exit;
    }
    $project_id = $_POST['project_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'Planning';
    $priority = $_POST['priority'] ?? 'Medium';
    
    // Validate input
    if (empty($project_id) || !is_numeric($project_id)) {
        $_SESSION['error'] = "Invalid project ID";
        header('Location: projects.php');
        exit;
    }
    
    if (empty($name)) {
        $_SESSION['error'] = "Project name is required";
        header("Location: project.php?id=$project_id");
        exit;
    }
    
    try {
        // Verify project belongs to user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$project_id, $_SESSION['user_id']]);
        
        if ($stmt->fetchColumn() == 0) {
            $_SESSION['error'] = "You don't have permission to update this project";
            header('Location: projects.php');
            exit;
        }
        
        // Fetch current status and completed_at
        $stmt = $pdo->prepare("SELECT status, completed_at FROM projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
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
            $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, status = ?, priority = ?, completed_at = NOW(), updated_at = CURRENT_TIMESTAMP WHERE project_id = ? AND user_id = ?");
            $result = $stmt->execute([$name, $description, $status, $priority, $project_id, $_SESSION['user_id']]);
        } elseif ($clearCompletedAt) {
            $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, status = ?, priority = ?, completed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE project_id = ? AND user_id = ?");
            $result = $stmt->execute([$name, $description, $status, $priority, $project_id, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, status = ?, priority = ?, updated_at = CURRENT_TIMESTAMP WHERE project_id = ? AND user_id = ?");
            $result = $stmt->execute([$name, $description, $status, $priority, $project_id, $_SESSION['user_id']]);
        }
        
        if ($result) {
            $_SESSION['success'] = "Project updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update project";
        }
        
        header("Location: project.php?id=$project_id");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: project.php?id=$project_id");
        exit;
    }
} else {
    // If not POST request, redirect to projects page
    header('Location: projects.php');
    exit;
}