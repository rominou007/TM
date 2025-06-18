<?php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch task statistics
$stmt = $pdo->prepare('
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = "To Do" THEN 1 ELSE 0 END) as todo_tasks,
        SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) as completed_tasks
    FROM tasks 
    WHERE user_id = ?
');
$stmt->execute([$_SESSION['user_id']]);
$task_stats = $stmt->fetch();

// Fetch project statistics
$stmt = $pdo->prepare('
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN status = "Planning" THEN 1 ELSE 0 END) as planning_projects,
        SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) as in_progress_projects,
        SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) as completed_projects
    FROM projects 
    WHERE user_id = ?
');
$stmt->execute([$_SESSION['user_id']]);
$project_stats = $stmt->fetch();

// Fetch recent tasks
$stmt = $pdo->prepare('
    SELECT t.*, p.name as project_name 
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 5
');
$stmt->execute([$_SESSION['user_id']]);
$recent_tasks = $stmt->fetchAll();

// Fetch tasks for the current week
$today = new DateTime();
$startOfWeek = clone $today;
$startOfWeek->modify('last sunday');
$endOfWeek = clone $startOfWeek;
$endOfWeek->modify('+6 days');
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[] = clone $startOfWeek;
    $startOfWeek->modify('+1 day');
}
$weekStartStr = $weekDates[0]->format('Y-m-d');
$weekEndStr = $weekDates[6]->format('Y-m-d');
$stmt = $pdo->prepare('
    SELECT t.*, p.name as project_name 
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ? 
    AND t.due_date BETWEEN ? AND ?
    ORDER BY t.due_date ASC
');
$stmt->execute([$_SESSION['user_id'], $weekStartStr, $weekEndStr]);
$weekTasks = $stmt->fetchAll();
// Group tasks by date
$tasksByDate = [];
foreach ($weekTasks as $task) {
    $date = (new DateTime($task['due_date']))->format('Y-m-d');
    if (!isset($tasksByDate[$date])) $tasksByDate[$date] = [];
    $tasksByDate[$date][] = $task;
}

// Fetch all projects for Add Task modal
$stmt = $pdo->prepare('SELECT * FROM projects WHERE user_id = ? ORDER BY name ASC');
$stmt->execute([$_SESSION['user_id']]);
$all_projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Task Manager</title>
    <?php include 'links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'navbar.html'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 col-lg-11 p-4">
                <header class="mb-4">
                    <h1>Dashboard</h1>
                    <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
                </header>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <!-- Task Statistics -->
                    <div class="col-md-6 col-lg-3 mb-3">
                        <a href="tasks.php" class="text-decoration-none text-reset">
                            <div class="report-card h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="report-title">Total Tasks</h6>
                                        <div class="report-value"><?php echo $task_stats['total_tasks']; ?></div>
                                    </div>
                                    <div class="report-icon">
                                        <i class="fas fa-tasks fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">To Do</span>
                                        <span class="text-primary"><?php echo $task_stats['todo_tasks']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">In Progress</span>
                                        <span class="text-warning"><?php echo $task_stats['in_progress_tasks']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Completed</span>
                                        <span class="text-success"><?php echo $task_stats['completed_tasks']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Project Statistics -->
                    <div class="col-md-6 col-lg-3 mb-3">
                        <a href="projects.php" class="text-decoration-none text-reset">
                            <div class="report-card h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="report-title">Total Projects</h6>
                                        <div class="report-value"><?php echo $project_stats['total_projects']; ?></div>
                                    </div>
                                    <div class="report-icon">
                                        <i class="fas fa-project-diagram fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Planning</span>
                                        <span class="text-info"><?php echo $project_stats['planning_projects']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">In Progress</span>
                                        <span class="text-warning"><?php echo $project_stats['in_progress_projects']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Completed</span>
                                        <span class="text-success"><?php echo $project_stats['completed_projects']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="report-card">
                            <h6 class="report-title mb-3">Quick Actions</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                                    <i class="fas fa-plus me-2"></i>New Task
                                </button>
                                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                                    <i class="fas fa-folder-plus me-2"></i>New Project
                                </button>
                                <a href="calendar.php" class="btn btn-secondary">
                                    <i class="fas fa-calendar me-2"></i>View Calendar
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="report-card h-100">
                            <h6 class="report-title mb-3">Recent Activity</h6>
                            <div class="activity-list">
                                <?php foreach ($recent_tasks as $task): ?>
                                    <a href="task.php?id=<?php echo $task['task_id']; ?>" class="activity-item mb-3 text-decoration-none text-reset d-block">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($task['project_name']); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo ($task['status'] == 'Completed') ? 'success' : 
                                                    (($task['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>"><?php echo htmlspecialchars($task['status']); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Week Calendar -->
                <div class="calendar mb-4">
                    <div class="calendar-header p-3">
                        <div class="row text-center">
                            <?php foreach ($weekDates as $date): ?>
                                <div class="col">
                                    <span class="fw-semibold"><?php echo $date->format('D'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="calendar-body p-3">
                        <div class="row">
                            <?php foreach ($weekDates as $date): 
                                $dateStr = $date->format('Y-m-d');
                                $isToday = ($dateStr === (new DateTime())->format('Y-m-d'));
                            ?>
                                <div class="col calendar-day p-2<?php echo $isToday ? ' today' : ''; ?>">
                                    <div class="date-number mb-1">
                                        <?php if ($isToday): ?>
                                            <span class="today-indicator d-inline-flex align-items-center justify-content-center me-1" style="background: var(--primary-color); color: #fff; border-radius: 50%; width: 2em; height: 2em; font-weight: 700; font-size: 1.1em;">
                                                <?php echo $date->format('j'); ?>
                                            </span>
                                            <span class="fw-bold text-primary">Today</span>
                                        <?php else: ?>
                                            <?php echo $date->format('j'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($tasksByDate[$dateStr])): ?>
                                        <div class="task-list">
                                            <?php foreach ($tasksByDate[$dateStr] as $task): 
                                                $statusClass = ($task['status'] == 'Completed') ? 'success' : 
                                                    (($task['status'] == 'In Progress') ? 'warning' : 'info');
                                                $priorityClass = ($task['priority'] == 'High') ? 'danger' : (($task['priority'] == 'Medium') ? 'warning' : 'secondary');
                                                $taskId = (int)$task['task_id'];
                                                $snapshot = htmlspecialchars(json_encode([
                                                    'title' => $task['title'],
                                                    'project' => $task['project_name'],
                                                    'status' => $task['status'],
                                                    'priority' => $task['priority'],
                                                    'due_date' => $task['due_date']
                                                ]));
                                            ?>
                                                <a href="task.php?id=<?php echo $taskId; ?>" class="task-item bg-<?php echo $statusClass; ?> mb-1 text-decoration-none text-white position-relative" data-snapshot="<?php echo $snapshot; ?>"><?php echo htmlspecialchars($task['title']); ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- GSAP for smooth animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animate cards on load
        gsap.from('.report-card', {
            duration: 0.5,
            y: 20,
            opacity: 0,
            stagger: 0.1,
            ease: 'power2.out'
        });
        
        // Add hover effects to cards
        const cards = document.querySelectorAll('.report-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                gsap.to(card, {
                    duration: 0.3,
                    y: -5,
                    boxShadow: '0 8px 15px rgba(0, 0, 0, 0.2)',
                    ease: 'power2.out'
                });
            });
            
            card.addEventListener('mouseleave', () => {
                gsap.to(card, {
                    duration: 0.3,
                    y: 0,
                    boxShadow: '0 4px 6px rgba(0, 0, 0, 0.1)',
                    ease: 'power2.out'
                });
            });
        });
    });
    </script>

    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="create_task.php" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTaskModalLabel">Create New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="projectSelect" class="form-label">Project</label>
                            <select class="form-select" id="projectSelect" name="project_id" required>
                                <option value="">Select Project</option>
                                <?php foreach ($all_projects as $project): ?>
                                    <option value="<?php echo $project['project_id']; ?>">
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="titleInput" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="titleInput" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="descriptionInput" class="form-label">Description</label>
                            <textarea class="form-control" id="descriptionInput" name="description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="statusSelect" class="form-label">Status</label>
                                <select class="form-select" id="statusSelect" name="status">
                                    <option value="To Do" selected>To Do</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col">
                                <label for="prioritySelect" class="form-label">Priority</label>
                                <select class="form-select" id="prioritySelect" name="priority">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="dueDateInput" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="dueDateInput" name="due_date">
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
    <!-- Add Project Modal -->
    <div class="modal fade" id="newProjectModal" tabindex="-1" aria-labelledby="newProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="create_project.php" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newProjectModalLabel">Create New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="projectName" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="projectName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="projectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="projectDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="projectStatus" class="form-label">Status</label>
                                <select class="form-select" id="projectStatus" name="status">
                                    <option value="Planning" selected>Planning</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col">
                                <label for="projectPriority" class="form-label">Priority</label>
                                <select class="form-select" id="projectPriority" name="priority">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="projectDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="projectDueDate" name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>