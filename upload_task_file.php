<?php
// filepath: c:\xampp\htdocs\php\TM\upload_task_file.php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Not logged in');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['task_file'], $_POST['task_id'])) {
    $task_id = (int)$_POST['task_id'];
    $user_id = $_SESSION['user_id'];
    $file = $_FILES['task_file'];

    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        exit('File upload error.');
    }
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        exit('File too large.');
    }

    $allowed = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx','txt','zip','rar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        exit('Invalid file type.');
    }

    // Save file
    $uploadDir = __DIR__ . '/uploads/tasks/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $newName = uniqid('task_'.$task_id.'_') . '.' . $ext;
    $dest = $uploadDir . $newName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        exit('Failed to move file.');
    }

    // Save to DB
    $stmt = $pdo->prepare("INSERT INTO task_files (task_id, user_id, file_name, file_path) VALUES (?, ?, ?, ?)");
    $stmt->execute([$task_id, $user_id, $file['name'], 'uploads/tasks/' . $newName]);

    header('Location: task.php?id=' . $task_id);
    exit;
}
?>