<?php
// filepath: c:\xampp\htdocs\php\TM\calendar.php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Get first day of the month
$first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day_timestamp); // 1 (Monday) to 7 (Sunday)
$days_in_month = date('t', $first_day_timestamp);

// Get previous and next month navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get tasks for this month
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

$stmt = $pdo->prepare("
    SELECT t.task_id, t.title, t.due_date, t.status, t.priority, p.name as project_name, p.project_id
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ? AND t.due_date BETWEEN ? AND ?
    ORDER BY t.due_date ASC
");
$stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
$tasks = $stmt->fetchAll();

// Organize tasks by date
$tasks_by_date = [];
foreach ($tasks as $task) {
    $day = date('j', strtotime($task['due_date']));
    $tasks_by_date[$day][] = $task;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Task Management</title>
    <?php include 'links.php'; ?>
    <style>
        .calendar-day {
            min-height: 100px;
            border: 1px solid #dee2e6;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
        }
        .calendar-day.past {
            background-color: #f8f9fa;
        }
        .calendar-day.today {
            background-color: #e8f4f8;
        }
        .calendar-day .date {
            font-weight: bold;
            font-size: 1.2em;
        }
        .task-pill {
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row vh-100">
            <!-- Include Navbar -->
            <?php include 'navbar.html'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 col-lg-11 p-4">
                <!-- Header with Month Navigation -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><?php echo date('F Y', $first_day_timestamp); ?></h1>
                    <div>
                        <a href="calendar.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" 
                           class="btn btn-outline-primary me-2">
                            <i class="bi bi-chevron-left"></i> Previous Month
                        </a>
                        <a href="calendar.php" class="btn btn-outline-secondary me-2">Today</a>
                        <a href="calendar.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" 
                           class="btn btn-outline-primary">
                            Next Month <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Calendar -->
                <div class="card">
                    <div class="card-body">
                        <!-- Weekday headers -->
                        <div class="row text-center fw-bold mb-2">
                            <div class="col">Mon</div>
                            <div class="col">Tue</div>
                            <div class="col">Wed</div>
                            <div class="col">Thu</div>
                            <div class="col">Fri</div>
                            <div class="col">Sat</div>
                            <div class="col">Sun</div>
                        </div>
                        
                        <!-- Calendar days -->
                        <?php 
                        $day_count = 1;
                        $total_cells = ceil(($days_in_month + $first_day_of_week - 1) / 7) * 7;
                        
                        for ($i = 0; $i < $total_cells; $i++) {
                            if ($i % 7 === 0) {
                                echo '<div class="row">';
                            }
                            
                            if ($i < $first_day_of_week - 1 || $day_count > $days_in_month) {
                                // Empty cells
                                echo '<div class="col p-2 calendar-day bg-light"></div>';
                            } else {
                                // Get CSS classes for the day
                                $classes = 'col p-2 calendar-day';
                                $current_date = "$year-$month-$day_count";
                                if ($current_date == date('Y-m-d')) {
                                    $classes .= ' today';
                                } elseif ($current_date < date('Y-m-d')) {
                                    $classes .= ' past';
                                }
                                
                                echo '<div class="' . $classes . '">';
                                echo '<div class="date mb-1">' . $day_count . '</div>';
                                
                                // Display tasks for this day
                                if (isset($tasks_by_date[$day_count])) {
                                    foreach ($tasks_by_date[$day_count] as $task) {
                                        $status_class = ($task['status'] == 'Completed') ? 'success' : 
                                            (($task['status'] == 'In Progress') ? 'warning' : 'info');
                                        $priority_class = ($task['priority'] == 'High') ? 'text-danger' : 
                                            (($task['priority'] == 'Medium') ? 'text-warning' : '');
                                        
                                        echo '<div class="task-pill bg-' . $status_class . ' bg-opacity-25 p-1 rounded" 
                                                  data-bs-toggle="tooltip" data-bs-placement="top" 
                                                  title="' . htmlspecialchars($task['title']) . ' (' . htmlspecialchars($task['project_name']) . ')"
                                                  onclick="window.location = \'task.php?id=' . $task['task_id'] . '\'">';
                                        echo '<small class="' . $priority_class . '">' . htmlspecialchars(substr($task['title'], 0, 20)) . 
                                             (strlen($task['title']) > 20 ? '...' : '') . '</small>';
                                        echo '</div>';
                                    }
                                }
                                
                                echo '</div>';
                                $day_count++;
                            }
                            
                            if (($i + 1) % 7 === 0) {
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Task Legend -->
                <div class="mt-4">
                    <h5>Legend</h5>
                    <div class="d-flex flex-wrap gap-3">
                        <div>
                            <span class="badge bg-info">To Do</span>
                        </div>
                        <div>
                            <span class="badge bg-warning">In Progress</span>
                        </div>
                        <div>
                            <span class="badge bg-success">Completed</span>
                        </div>
                        <div>
                            <span class="text-danger">●</span> High Priority
                        </div>
                        <div>
                            <span class="text-warning">●</span> Medium Priority
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltips.map(function(tooltip) {
                return new bootstrap.Tooltip(tooltip);
            });
        });
    </script>
</body>
</html>