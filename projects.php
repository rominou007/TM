<?php
require_once 'config/db_connect.php';
session_start();
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
    <title>Projects - Task Management</title>
    <?php include 'links.php'; ?>
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
                    <h1>Projects</h1>
                    <p class="text-muted">Manage your projects and associated tasks.</p>
                </header>
                
                <!-- Projects Overview Section -->
                <h2 class="mb-3">Your Projects</h2>
                <div class="row mb-4">
                    <?php if (empty($projects)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No projects found. Use the "+" button in the sidebar to create your first project.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($project['description']); ?></p>
                                        <p class="card-text">
                                            <span class="badge bg-<?php 
                                                echo ($project['status'] == 'Completed') ? 'success' : 
                                                    (($project['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>"><?php echo htmlspecialchars($project['status']); ?></span>
                                        </p>
                                        <p class="card-text"><small class="text-muted">Created on <?php echo date('Y-m-d', strtotime($project['created_at'])); ?></small></p>
                                        <a href="project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-primary">View Project</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Tasks Overview Section -->
                <h2 class="mb-3">Related Tasks</h2>
                <div class="row">
                    <?php if (empty($tasks)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No tasks found. Create a project first, then add tasks to it.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($task['description']); ?></p>
                                        <p class="card-text">
                                            <span class="badge bg-<?php 
                                                echo ($task['status'] == 'Completed') ? 'success' : 
                                                    (($task['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>"><?php echo htmlspecialchars($task['status']); ?></span>
                                        </p>
                                        <p class="card-text"><small class="text-muted">Due on <?php echo date('Y-m-d', strtotime($task['due_date'])); ?></small></p>
                                        <p class="card-text"><small class="text-muted">Project: <?php echo htmlspecialchars($task['project_name']); ?></small></p>
                                        <a href="task.php?id=<?php echo $task['task_id']; ?>" class="btn btn-primary">View Task</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>