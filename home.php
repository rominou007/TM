<?php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user's projects
$stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

// Fetch user's tasks
$stmt = $pdo->prepare("
    SELECT t.*, p.name as project_name 
    FROM tasks t 
    JOIN projects p ON t.project_id = p.project_id 
    WHERE t.user_id = ? 
    ORDER BY t.due_date ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap CSS should be added here -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row vh-100">
            <!-- Include Navbar -->
            <?php include 'navbar.html'; ?>
            
            <!-- Main Content - increased width to compensate for narrower navbar -->
            <div class="col-md-10 col-lg-11 p-4">
                <!-- Header -->
                <header class="mb-4">
                    <h1>Dashboard</h1>
                    <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's an overview of your projects and tasks.</p>
                </header>
                
                <!-- Projects Overview Section -->
                <h2 class="mb-3">Projects Overview</h2>
                <div class="row mb-4">
                    <?php if (empty($projects)): ?>
                        <div class="col-12">
                            <p>No projects found. <a href="projects.php">Create your first project</a>.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h5>
                                        <p class="card-text">
                                            <span class="badge bg-<?php 
                                                echo ($project['status'] == 'Completed') ? 'success' : 
                                                    (($project['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>"><?php echo htmlspecialchars($project['status']); ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Tasks Section -->
                <h2 class="mb-3">Upcoming Tasks</h2>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tasks)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No tasks found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($task['due_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo ($task['status'] == 'Completed') ? 'success' : 
                                                            (($task['status'] == 'In Progress') ? 'warning' : 'info'); 
                                                    ?>"><?php echo htmlspecialchars($task['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>