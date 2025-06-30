<?php
header('Content-Type: application/json');
require_once '../config/db_connect.php';

// Example: fetch total tasks (customize as needed)
$stmt = $pdo->query('SELECT COUNT(*) as total FROM tasks');
$row = $stmt->fetch();

echo json_encode([
    'totalTasks' => $row['total'],
    // Add more stats as needed
]);