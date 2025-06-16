<?php
// filepath: c:\xampp\htdocs\php\TM\create_project.php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'Planning';
    
    // Validate data
    if (empty($name)) {
        $_SESSION['error'] = "Project name is required";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'projects.php'));
        exit;
    }
    
    try {
        // Insert the project
        $stmt = $pdo->prepare("INSERT INTO projects (user_id, name, description, status) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $name,
            $description,
            $status
        ]);
        
        if ($result) {
            $project_id = $pdo->lastInsertId();
            $_SESSION['success'] = "Project created successfully!";
            header("Location: project.php?id=$project_id");
        } else {
            $_SESSION['error'] = "Failed to create project.";
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'projects.php'));
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'projects.php'));
    }
    exit;
}

// If not a POST request, redirect to projects page
header('Location: projects.php');
exit;