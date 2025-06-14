<?php
require_once 'config/db_connect.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if project ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid project ID";
    header('Location: projects.php');
    exit;
}

$project_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch project details
$stmt = $pdo->prepare("SELECT * FROM projects WHERE project_id = ? AND user_id = ?");
$stmt->execute([$project_id, $user_id]);
$project = $stmt->fetch();

// If project doesn't exist or doesn't belong to the user
if (!$project) {
    $_SESSION['error'] = "Project not found";
    header('Location: projects.php');
    exit;
}

// Fetch tasks for this project
$stmt = $pdo->prepare("
    SELECT * FROM tasks 
    WHERE project_id = ? 
    ORDER BY CASE 
        WHEN status = 'To Do' THEN 1 
        WHEN status = 'In Progress' THEN 2 
        WHEN status = 'Completed' THEN 3 
    END, due_date ASC
");
$stmt->execute([$project_id]);
$tasks = $stmt->fetchAll();

// Message handling
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['name']); ?> - Task Management</title>
    <?php include 'links.php'; ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row vh-100">
            <!-- Include Navbar -->
            <?php include 'navbar.html'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 col-lg-11 p-4">
                <!-- Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <!-- Project Header with Actions -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1><?php echo htmlspecialchars($project['name']); ?></h1>
                        <p class="text-muted mb-0">
                            <span class="badge bg-<?php 
                                echo ($project['status'] == 'Completed') ? 'success' : 
                                    (($project['status'] == 'In Progress') ? 'warning' : 'info'); 
                            ?>"><?php echo htmlspecialchars($project['status']); ?></span>
                            <span class="ms-2">Created on <?php echo date('Y-m-d', strtotime($project['created_at'])); ?></span>
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editProjectModal">
                            <i class="bi bi-pencil"></i> Edit Project
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTaskModal">
                            <i class="bi bi-plus"></i> New Task
                        </button>
                    </div>
                </div>
                
                <!-- Project Description -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Description</h5>
                        <p class="card-text">
                            <?php echo !empty($project['description']) ? 
                                nl2br(htmlspecialchars($project['description'])) : 
                                '<em>No description provided</em>'; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Project Tasks -->
                <h2 class="mb-3">Tasks</h2>
                
                <?php if (empty($tasks)): ?>
                    <div class="alert alert-info">
                        No tasks found for this project. Create your first task by clicking the "New Task" button.
                    </div>
                <?php else: ?>
                    <!-- Task Status Filter -->
                    <div class="mb-3">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="To Do">To Do</button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="In Progress">In Progress</button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="Completed">Completed</button>
                        </div>
                    </div>
                    
                    <!-- Task Cards -->
                    <div class="row" id="taskContainer">
                        <?php foreach ($tasks as $task): ?>
                            <div class="col-md-4 mb-3 task-card" data-status="<?php echo htmlspecialchars($task['status']); ?>">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                        <p class="card-text">
                                            <?php echo !empty($task['description']) ? 
                                                nl2br(htmlspecialchars(substr($task['description'], 0, 100))) . 
                                                (strlen($task['description']) > 100 ? '...' : '') : 
                                                '<em>No description</em>'; ?>
                                        </p>
                                        <p class="card-text">
                                            <span class="badge bg-<?php 
                                                echo ($task['status'] == 'Completed') ? 'success' : 
                                                    (($task['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>"><?php echo htmlspecialchars($task['status']); ?></span>
                                            <?php if($task['priority']): ?>
                                                <span class="badge bg-<?php 
                                                    echo ($task['priority'] == 'High') ? 'danger' : 
                                                        (($task['priority'] == 'Medium') ? 'warning' : 'secondary'); 
                                                ?>"><?php echo htmlspecialchars($task['priority']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if($task['due_date']): ?>
                                            <p class="card-text">
                                                <small class="<?php echo strtotime($task['due_date']) < time() && 
                                                    $task['status'] != 'Completed' ? 'text-danger' : 'text-muted'; ?>">
                                                    Due: <?php echo date('Y-m-d', strtotime($task['due_date'])); ?>
                                                </small>
                                            </p>
                                        <?php endif; ?>
                                        <a href="task.php?id=<?php echo $task['task_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                        <!-- Quick status change buttons -->
                                        <div class="mt-2">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="update_task_status.php?id=<?php echo $task['task_id']; ?>&status=To Do" 
                                                   class="btn btn-outline-info <?php echo $task['status'] == 'To Do' ? 'active' : ''; ?>">To Do</a>
                                                <a href="update_task_status.php?id=<?php echo $task['task_id']; ?>&status=In Progress" 
                                                   class="btn btn-outline-warning <?php echo $task['status'] == 'In Progress' ? 'active' : ''; ?>">In Progress</a>
                                                <a href="update_task_status.php?id=<?php echo $task['task_id']; ?>&status=Completed" 
                                                   class="btn btn-outline-success <?php echo $task['status'] == 'Completed' ? 'active' : ''; ?>">Completed</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="update_project.php" method="post">
                    <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProjectModalLabel">Edit Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="projectName" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="projectName" name="name" 
                                   value="<?php echo htmlspecialchars($project['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="projectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="projectDescription" name="description" 
                                      rows="3"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="projectStatus" class="form-label">Status</label>
                            <select class="form-select" id="projectStatus" name="status">
                                <option value="Planning" <?php echo $project['status'] == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                                <option value="In Progress" <?php echo $project['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $project['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- New Task Modal -->
    <div class="modal fade" id="newTaskModal" tabindex="-1" aria-labelledby="newTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="create_task.php" method="post">
                    <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newTaskModalLabel">Create New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="taskTitle" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="taskTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="taskDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="taskDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="taskStatus" class="form-label">Status</label>
                            <select class="form-select" id="taskStatus" name="status">
                                <option value="To Do">To Do</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="taskPriority" class="form-label">Priority</label>
                            <select class="form-select" id="taskPriority" name="priority">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="taskDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="taskDueDate" name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Task filtering functionality
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('[data-filter]');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const tasks = document.querySelectorAll('.task-card');
                
                tasks.forEach(task => {
                    if (filter === 'all' || task.getAttribute('data-status') === filter) {
                        task.style.display = '';
                    } else {
                        task.style.display = 'none';
                    }
                });
            });
        });
    });
    </script>
</body>
</html>