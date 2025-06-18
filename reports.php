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

// Handle success and error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Initialize statistics arrays
$task_statistics = [
    'total_tasks' => 0,
    'todo_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0
];

$project_statistics = [
    'total_projects' => 0,
    'planning_projects' => 0,
    'in_progress_projects' => 0,
    'completed_projects' => 0
];

$task_priority = [
    'Low' => 0,
    'Medium' => 0,
    'High' => 0
];

$project_priority = [
    'Low' => 0,
    'Medium' => 0,
    'High' => 0
];

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
$task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

if ($task_stats) {
    $task_statistics['total_tasks'] = (int)$task_stats['total_tasks'];
    $task_statistics['todo_tasks'] = (int)$task_stats['todo_tasks'];
    $task_statistics['in_progress_tasks'] = (int)$task_stats['in_progress_tasks'];
    $task_statistics['completed_tasks'] = (int)$task_stats['completed_tasks'];
}

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
$project_stats = $stmt->fetch(PDO::FETCH_ASSOC);

if ($project_stats) {
    $project_statistics['total_projects'] = (int)$project_stats['total_projects'];
    $project_statistics['planning_projects'] = (int)$project_stats['planning_projects'];
    $project_statistics['in_progress_projects'] = (int)$project_stats['in_progress_projects'];
    $project_statistics['completed_projects'] = (int)$project_stats['completed_projects'];
}

// Fetch task priority distribution
$stmt = $pdo->prepare('
    SELECT status, COUNT(*) as count
    FROM tasks
    WHERE user_id = ?
    GROUP BY status
');
$stmt->execute([$_SESSION['user_id']]);
$task_priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($task_priorities as $priority) {
    $task_priority[$priority['status']] = (int)$priority['count'];
}

// Fetch project priority distribution
$stmt = $pdo->prepare('
    SELECT status, COUNT(*) as count
    FROM projects
    WHERE user_id = ?
    GROUP BY status
');
$stmt->execute([$_SESSION['user_id']]);
$project_priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($project_priorities as $priority) {
    $project_priority[$priority['status']] = (int)$priority['count'];
}

// Fetch recent activity
$stmt = $pdo->prepare('
    (SELECT 
        created_at,
        "Task" as type,
        title as description,
        status
    FROM tasks 
    WHERE user_id = ?)
    UNION ALL
    (SELECT 
        created_at,
        "Project" as type,
        name as description,
        status
    FROM projects 
    WHERE user_id = ?)
    ORDER BY created_at DESC
    LIMIT 10
');
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize empty array if no activity found
if (empty($recent_activity)) {
    $recent_activity = [];
}

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
    <title>Reports</title>
    <?php include 'links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
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
                
                <header class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1>Reports</h1>
                            <p class="text-muted">View detailed reports and analytics.</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print Report
                            </button>
                        </div>
                    </div>
                </header>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Total Tasks</h6>
                                <h2 class="card-title mb-0"><?php echo $task_statistics['total_tasks']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Completed Tasks</h6>
                                <h2 class="card-title mb-0"><?php echo $task_statistics['completed_tasks']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Total Projects</h6>
                                <h2 class="card-title mb-0"><?php echo $project_statistics['total_projects']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Completion Rate</h6>
                                <h2 class="card-title mb-0"><?php echo round(($task_statistics['completed_tasks'] / $task_statistics['total_tasks']) * 100); ?>%</h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Task Status Distribution -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Task Status Distribution</h5>
                                <canvas id="taskStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Status Distribution -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Project Status Distribution</h5>
                                <canvas id="projectStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Task Priority Distribution -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Task Priority Distribution</h5>
                                <canvas id="taskPriorityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Priority Distribution -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Project Priority Distribution</h5>
                                <canvas id="projectPriorityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Activity</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_activity as $activity): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($activity['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['type']); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo ($activity['status'] == 'Completed') ? 'success' : 
                                                                (($activity['status'] == 'In Progress') ? 'warning' : 'info'); 
                                                        ?>"><?php echo htmlspecialchars($activity['status']); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
        // Initialize GSAP animations
        gsap.from('.card', {
            duration: 0.5,
            y: 20,
            opacity: 0,
            stagger: 0.1,
            ease: 'power2.out'
        });

        // Task Status Chart
        const taskStatusCtx = document.getElementById('taskStatusChart').getContext('2d');
        new Chart(taskStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['To Do', 'In Progress', 'Completed'],
                datasets: [{
                    data: [
                        <?php echo $task_statistics['todo_tasks']; ?>,
                        <?php echo $task_statistics['in_progress_tasks']; ?>,
                        <?php echo $task_statistics['completed_tasks']; ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#fff'
                        }
                    }
                }
            }
        });

        // Project Status Chart
        const projectStatusCtx = document.getElementById('projectStatusChart').getContext('2d');
        new Chart(projectStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Planning', 'In Progress', 'Completed'],
                datasets: [{
                    data: [
                        <?php echo $project_statistics['planning_projects']; ?>,
                        <?php echo $project_statistics['in_progress_projects']; ?>,
                        <?php echo $project_statistics['completed_projects']; ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#fff'
                        }
                    }
                }
            }
        });

        // Task Priority Chart
        const taskPriorityCtx = document.getElementById('taskPriorityChart').getContext('2d');
        new Chart(taskPriorityCtx, {
            type: 'bar',
            data: {
                labels: ['Low', 'Medium', 'High'],
                datasets: [{
                    label: 'Number of Tasks',
                    data: [
                        <?php echo $task_priority['Low']; ?>,
                        <?php echo $task_priority['Medium']; ?>,
                        <?php echo $task_priority['High']; ?>
                    ],
                    backgroundColor: [
                        'rgba(108, 117, 125, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(108, 117, 125, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });

        // Project Priority Chart
        const projectPriorityCtx = document.getElementById('projectPriorityChart').getContext('2d');
        new Chart(projectPriorityCtx, {
            type: 'bar',
            data: {
                labels: ['Low', 'Medium', 'High'],
                datasets: [{
                    label: 'Number of Projects',
                    data: [
                        <?php echo $project_priority['Low']; ?>,
                        <?php echo $project_priority['Medium']; ?>,
                        <?php echo $project_priority['High']; ?>
                    ],
                    backgroundColor: [
                        'rgba(108, 117, 125, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(108, 117, 125, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });

        // Add hover effects to cards
        const cards = document.querySelectorAll('.card');
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
</body>
</html>