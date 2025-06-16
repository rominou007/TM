<?php
// filepath: c:\xampp\htdocs\php\TM\reports.php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Task statistics
$task_stats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status = 'To Do' THEN 1 ELSE 0 END) AS todo_tasks,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks
    FROM tasks
    WHERE user_id = ?
");
$task_stats->execute([$user_id]);
$task_statistics = $task_stats->fetch();

// Project statistics
$project_stats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_projects,
        SUM(CASE WHEN status = 'Planning' THEN 1 ELSE 0 END) AS planning_projects,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_projects,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_projects
    FROM projects
    WHERE user_id = ?
");
$project_stats->execute([$user_id]);
$project_statistics = $project_stats->fetch();

// Overdue tasks
$overdue_tasks = $pdo->prepare("
    SELECT t.*, p.name AS project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ?
      AND t.status <> 'Completed'
      AND t.due_date < CURRENT_DATE
    ORDER BY t.due_date
");
$overdue_tasks->execute([$user_id]);
$overdue = $overdue_tasks->fetchAll();

// Tasks due soon (next 7 days)
$due_soon_tasks = $pdo->prepare("
    SELECT t.*, p.name AS project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ?
      AND t.status <> 'Completed'
      AND t.due_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
    ORDER BY t.due_date
");
$due_soon_tasks->execute([$user_id]);
$due_soon = $due_soon_tasks->fetchAll();

// Recently completed tasks (last 14 days)
$completed_tasks = $pdo->prepare("
    SELECT t.*, p.name AS project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ?
      AND t.status = 'Completed'
      AND t.due_date >= DATE_SUB(CURRENT_DATE, INTERVAL 14 DAY)
    ORDER BY t.due_date DESC
");
$completed_tasks->execute([$user_id]);
$recently_completed = $completed_tasks->fetchAll();

// Get task counts by project
$project_task_counts = $pdo->prepare("
    SELECT p.project_id, p.name, COUNT(t.task_id) as task_count,
        SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_count
    FROM projects p
    LEFT JOIN tasks t ON p.project_id = t.project_id
    WHERE p.user_id = ?
    GROUP BY p.project_id
    ORDER BY task_count DESC
");
$project_task_counts->execute([$user_id]);
$project_tasks = $project_task_counts->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Task Management</title>
    <?php include 'links.php'; ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row vh-100">
            <!-- Include Navbar -->
            <?php include 'navbar.html'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 col-lg-11 p-4">
                <!-- Header -->
                <header class="mb-4">
                    <h1>Reports & Analytics</h1>
                    <p class="text-muted">Track your productivity and project statistics</p>
                </header>
                
                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Task Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-primary"><?php echo $task_statistics['total_tasks']; ?></h3>
                                        <span class="text-muted">Total</span>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-info"><?php echo $task_statistics['todo_tasks']; ?></h3>
                                        <span class="text-muted">To Do</span>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-warning"><?php echo $task_statistics['in_progress_tasks']; ?></h3>
                                        <span class="text-muted">In Progress</span>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-success"><?php echo $task_statistics['completed_tasks']; ?></h3>
                                        <span class="text-muted">Completed</span>
                                    </div>
                                </div>
                                
                                <?php if ($task_statistics['total_tasks'] > 0): ?>
                                    <div class="progress mt-3">
                                        <?php 
                                        $todo_percent = ($task_statistics['todo_tasks'] / $task_statistics['total_tasks']) * 100;
                                        $in_progress_percent = ($task_statistics['in_progress_tasks'] / $task_statistics['total_tasks']) * 100;
                                        $completed_percent = ($task_statistics['completed_tasks'] / $task_statistics['total_tasks']) * 100;
                                        ?>
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $todo_percent; ?>%">
                                            <?php if ($todo_percent > 10): echo $task_statistics['todo_tasks']; endif; ?>
                                        </div>
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $in_progress_percent; ?>%">
                                            <?php if ($in_progress_percent > 10): echo $task_statistics['in_progress_tasks']; endif; ?>
                                        </div>
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $completed_percent; ?>%">
                                            <?php if ($completed_percent > 10): echo $task_statistics['completed_tasks']; endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-center mt-2">
                                        <small class="text-muted">Completion Rate: 
                                            <?php echo round($completed_percent, 1); ?>%
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Project Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-primary"><?php echo $project_statistics['total_projects']; ?></h3>
                                        <span class="text-muted">Total</span>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-info"><?php echo $project_statistics['planning_projects']; ?></h3>
                                        <span class="text-muted">Planning</span>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-warning"><?php echo $project_statistics['in_progress_projects']; ?></h3>
                                        <span class="text-muted">In Progress</span>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <h3 class="text-success"><?php echo $project_statistics['completed_projects']; ?></h3>
                                        <span class="text-muted">Completed</span>
                                    </div>
                                </div>
                                
                                <?php if ($project_statistics['total_projects'] > 0): ?>
                                    <div class="progress mt-3">
                                        <?php 
                                        $planning_percent = ($project_statistics['planning_projects'] / $project_statistics['total_projects']) * 100;
                                        $in_progress_percent = ($project_statistics['in_progress_projects'] / $project_statistics['total_projects']) * 100;
                                        $completed_percent = ($project_statistics['completed_projects'] / $project_statistics['total_projects']) * 100;
                                        ?>
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $planning_percent; ?>%">
                                            <?php if ($planning_percent > 10): echo $project_statistics['planning_projects']; endif; ?>
                                        </div>
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $in_progress_percent; ?>%">
                                            <?php if ($in_progress_percent > 10): echo $project_statistics['in_progress_projects']; endif; ?>
                                        </div>
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $completed_percent; ?>%">
                                            <?php if ($completed_percent > 10): echo $project_statistics['completed_projects']; endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-center mt-2">
                                        <small class="text-muted">Completion Rate: 
                                            <?php echo round($completed_percent, 1); ?>%
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Task Lists -->
                <div class="row">
                    <!-- Overdue Tasks -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">Overdue Tasks (<?php echo count($overdue); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($overdue)): ?>
                                    <p class="text-muted">No overdue tasks. Great job staying on track!</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($overdue as $task): ?>
                                            <a href="task.php?id=<?php echo $task['task_id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                    <small class="text-danger"><?php echo date('M d', strtotime($task['due_date'])); ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($task['project_name']); ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Due Soon Tasks -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">Due Soon (<?php echo count($due_soon); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($due_soon)): ?>
                                    <p class="text-muted">No tasks due in the next 7 days.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($due_soon as $task): ?>
                                            <a href="task.php?id=<?php echo $task['task_id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                    <small><?php echo date('M d', strtotime($task['due_date'])); ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($task['project_name']); ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recently Completed -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">Recently Completed (<?php echo count($recently_completed); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recently_completed)): ?>
                                    <p class="text-muted">No tasks completed in the last 14 days.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($recently_completed as $task): ?>
                                            <a href="task.php?id=<?php echo $task['task_id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                    <small><?php echo date('M d', strtotime($task['due_date'])); ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($task['project_name']); ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Task Counts -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Projects Overview</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($project_tasks)): ?>
                                    <p class="text-muted">No projects created yet.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($project_tasks as $project): ?>
                                            <a href="project.php?id=<?php echo $project['project_id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($project['name']); ?></h6>
                                                    <small><?php echo $project['task_count']; ?> tasks</small>
                                                </div>
                                                <?php if ($project['task_count'] > 0): ?>
                                                    <div class="progress mt-2" style="height: 5px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo ($project['completed_count'] / $project['task_count']) * 100; ?>%">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo $project['completed_count']; ?> of <?php echo $project['task_count']; ?> completed
                                                        (<?php echo round(($project['completed_count'] / $project['task_count']) * 100); ?>%)
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">No tasks</small>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
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