<?php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'To Do';
    $priority = $_POST['priority'] ?? 'Medium';
    $due_date = $_POST['due_date'] ?? null;
    
    // Basic validation
    if (empty($project_id) || !is_numeric($project_id)) {
        $_SESSION['error'] = "Invalid project ID";
        header('Location: projects.php');
        exit;
    }
    
    if (empty($title)) {
        $_SESSION['error'] = "Task title is required";
        header("Location: project.php?id=$project_id");
        exit;
    }
    
    try {
        // Verify project belongs to user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$project_id, $_SESSION['user_id']]);
        
        if ($stmt->fetchColumn() == 0) {
            $_SESSION['error'] = "You don't have permission to add tasks to this project";
            header('Location: projects.php');
            exit;
        }
        
        // Prepare SQL statement based on whether due_date is provided
        if (!empty($due_date)) {
            $stmt = $pdo->prepare("
                INSERT INTO tasks 
                (project_id, user_id, title, description, status, priority, due_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $project_id, 
                $_SESSION['user_id'], 
                $title, 
                $description, 
                $status, 
                $priority, 
                $due_date
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tasks 
                (project_id, user_id, title, description, status, priority) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $project_id, 
                $_SESSION['user_id'], 
                $title, 
                $description, 
                $status, 
                $priority
            ]);
        }
        
        if ($result) {
            $_SESSION['success'] = "Task created successfully";
        } else {
            $_SESSION['error'] = "Failed to create task";
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