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
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Get first day of the month
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDay);
$dateComponents = getdate($firstDay);
$monthName = $dateComponents['month'];
$dayOfWeek = $dateComponents['wday'];

// Get tasks for the month
$startDate = date('Y-m-d', $firstDay);
$endDate = date('Y-m-t', $firstDay);

$stmt = $pdo->prepare('
    SELECT t.*, p.name as project_name 
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ? 
    AND t.due_date BETWEEN ? AND ?
    ORDER BY t.due_date ASC
');
$stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
$tasks = $stmt->fetchAll();

// Organize tasks by date
$tasksByDate = [];
foreach ($tasks as $task) {
    $date = date('j', strtotime($task['due_date']));
    if (!isset($tasksByDate[$date])) {
        $tasksByDate[$date] = [];
    }
    $tasksByDate[$date][] = $task;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Task Manager</title>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1>Calendar</h1>
                            <p class="text-muted">View and manage your tasks by date</p>
                        </div>
                        <div class="btn-group">
                            <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>" class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <button class="btn btn-secondary" disabled>
                                <?php echo $monthName . ' ' . $year; ?>
                            </button>
                            <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>" class="btn btn-secondary">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </header>
                
                <!-- Calendar -->
                <div class="calendar">
                    <!-- Calendar Header -->
                    <div class="calendar-header p-3">
                        <div class="row text-center">
                            <div class="col">Sun</div>
                            <div class="col">Mon</div>
                            <div class="col">Tue</div>
                            <div class="col">Wed</div>
                            <div class="col">Thu</div>
                            <div class="col">Fri</div>
                            <div class="col">Sat</div>
                        </div>
                    </div>
                    
                    <!-- Calendar Body -->
                    <div class="calendar-body p-3">
                        <?php
                        $currentDay = 1;
                        $currentDate = date('Y-m-d');
                        
                        // Create the calendar
                        echo '<div class="row">';
                        
                        // Print initial empty cells
                        for ($i = 0; $i < $dayOfWeek; $i++) {
                            echo '<div class="col calendar-day p-2"></div>';
                        }
                        
                        // Print the days of the month
                        while ($currentDay <= $numberDays) {
                            if ($dayOfWeek == 7) {
                                $dayOfWeek = 0;
                                echo '</div><div class="row">';
                            }
                            
                            $currentDayDate = date('Y-m-d', mktime(0, 0, 0, $month, $currentDay, $year));
                            $isToday = ($currentDayDate == $currentDate);
                            
                            echo '<div class="col calendar-day p-2 ' . ($isToday ? 'today' : '') . '">';
                            echo '<div class="date-number">';
                            if ($isToday) {
                                echo '<span class="today-indicator d-inline-flex align-items-center justify-content-center me-1" style="background: var(--primary-color); color: #fff; border-radius: 50%; width: 2em; height: 2em; font-weight: 700; font-size: 1.1em;">' . $currentDay . '</span>';
                                echo '<span class="fw-bold text-primary">Today</span>';
                            } else {
                                echo $currentDay;
                            }
                            echo '</div>';
                            
                            // Display tasks for this day
                            if (isset($tasksByDate[$currentDay])) {
                                echo '<div class="task-list">';
                                foreach ($tasksByDate[$currentDay] as $task) {
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
                                    echo '<a href="task.php?id=' . $taskId . '" class="task-item bg-' . $statusClass . ' mb-1 text-decoration-none text-white position-relative" 
                                        data-snapshot="' . $snapshot . '">' . htmlspecialchars($task['title']) . '</a>';
                                }
                                echo '</div>';
                            }
                            
                            echo '</div>';
                            
                            $currentDay++;
                            $dayOfWeek++;
                        }
                        
                        // Complete the row
                        while ($dayOfWeek < 7) {
                            echo '<div class="col calendar-day p-2"></div>';
                            $dayOfWeek++;
                        }
                        
                        echo '</div>';
                        ?>
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
        // Animate calendar on load
        gsap.from('.calendar', {
            duration: 0.5,
            y: 20,
            opacity: 0,
            ease: 'power2.out'
        });
        
        // Add hover effects to calendar days
        const calendarDays = document.querySelectorAll('.calendar-day');
        calendarDays.forEach(day => {
            day.addEventListener('mouseenter', () => {
                gsap.to(day, {
                    duration: 0.2,
                    backgroundColor: 'rgba(108, 99, 255, 0.1)',
                    ease: 'power2.out'
                });
            });
            
            day.addEventListener('mouseleave', () => {
                gsap.to(day, {
                    duration: 0.2,
                    backgroundColor: 'transparent',
                    ease: 'power2.out'
                });
            });
        });

        // Snapshot Info Box
        let infoBox = document.createElement('div');
        infoBox.className = 'calendar-snapshot-box shadow-lg';
        infoBox.style.position = 'fixed';
        infoBox.style.zIndex = '9999';
        infoBox.style.display = 'none';
        infoBox.style.minWidth = '220px';
        infoBox.style.maxWidth = '320px';
        infoBox.style.background = 'var(--card-bg)';
        infoBox.style.color = 'var(--text-primary)';
        infoBox.style.borderRadius = '12px';
        infoBox.style.padding = '1rem';
        infoBox.style.boxShadow = '0 8px 24px rgba(0,0,0,0.25)';
        infoBox.style.pointerEvents = 'none';
        infoBox.style.transition = 'opacity 0.2s';
        document.body.appendChild(infoBox);

        function showInfoBox(e, data) {
            infoBox.innerHTML = `
                <div class="fw-bold mb-1"><i class="bi bi-clipboard me-2"></i>${data.title}</div>
                <div class="mb-1"><i class="bi bi-folder me-2"></i><span class="text-secondary">${data.project}</span></div>
                <div class="mb-1"><span class="badge bg-${data.status === 'Completed' ? 'success' : (data.status === 'In Progress' ? 'warning' : 'info')}">${data.status}</span>
                    <span class="badge bg-${data.priority === 'High' ? 'danger' : (data.priority === 'Medium' ? 'warning' : 'secondary')}">${data.priority}</span></div>
                <div><i class="bi bi-calendar-event me-2"></i>${data.due_date ? data.due_date : '<span class="text-muted">No due date</span>'}</div>
            `;
            infoBox.style.display = 'block';
            infoBox.style.opacity = '1';
            positionInfoBox(e);
        }
        function hideInfoBox() {
            infoBox.style.display = 'none';
            infoBox.style.opacity = '0';
        }
        function positionInfoBox(e) {
            let x = e.clientX + 16;
            let y = e.clientY + 16;
            if (x + infoBox.offsetWidth > window.innerWidth) x = window.innerWidth - infoBox.offsetWidth - 8;
            if (y + infoBox.offsetHeight > window.innerHeight) y = window.innerHeight - infoBox.offsetHeight - 8;
            infoBox.style.left = x + 'px';
            infoBox.style.top = y + 'px';
        }
        document.querySelectorAll('.task-item').forEach(item => {
            item.addEventListener('mouseenter', function(e) {
                const data = JSON.parse(this.getAttribute('data-snapshot'));
                showInfoBox(e, data);
            });
            item.addEventListener('mousemove', function(e) {
                positionInfoBox(e);
            });
            item.addEventListener('mouseleave', function() {
                hideInfoBox();
            });
        });
    });
    </script>
</body>
</html>