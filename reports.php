<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config/db_connect.php';
require_once 'report_queries.php';
$user_id = $_SESSION['user_id'];

$tasksPerWeek = getTasksCompletedPerWeek($pdo, $user_id);
$avgCompletion = getAverageCompletionTime($pdo, $user_id);
$overdueTasks = getOverdueTasks($pdo, $user_id);
$tasksByStatus = getTasksByStatus($pdo, $user_id);
$tasksByPriority = getTasksByPriority($pdo, $user_id);
$projectProgress = getProjectProgress($pdo, $user_id);
$upcomingDeadlines = getUpcomingDeadlines($pdo, $user_id);
$mostUsedTags = getMostUsedTags($pdo, $user_id);
$mostActiveTasks = getMostActiveTasks($pdo, $user_id);
$burndownData = getBurndownData($pdo, $user_id);

// Prepare data for charts
function chartLabels($arr, $key) {
    return array_map(fn($row) => $row[$key], $arr);
}
function chartData($arr, $key) {
    return array_map(fn($row) => (int)$row[$key], $arr);
}
$burndownLabels = chartLabels($burndownData, 'date');
$burndownValues = chartData($burndownData, 'remaining');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Reports</title>
    <?php include 'links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { position: relative; height: 320px; }
        .card-title { font-weight: 600; }
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-md-10 offset-md-1 col-lg-10 offset-lg-1 p-4">
                <header class="mb-4">
                    <h1 class="mb-3">Analytics & Reports</h1>
                </header>
                <form class="mb-4" method="get" id="projectFilterForm">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="projectFilter" class="col-form-label fw-semibold">Show Analytics For:</label>
                        </div>
                        <div class="col-auto">
                            <select class="form-select" id="projectFilter" name="project_id" onchange="document.getElementById('projectFilterForm').submit()">
                                <option value="">All Projects</option>
                                <?php
                                // Fetch all projects for the dropdown
                                $allProjects = $pdo->query("SELECT project_id, name FROM projects WHERE user_id = " . (int)$user_id . " ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($allProjects as $proj) {
                                    $selected = (isset($_GET['project_id']) && $_GET['project_id'] == $proj['project_id']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($proj['project_id']) . '" ' . $selected . '>' . htmlspecialchars($proj['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <div class="mb-2"><i class="fas fa-clock fa-2x text-primary"></i></div>
                                <h6 class="card-title">Avg. Completion (days)</h6>
                                <div class="display-6 mb-0"><?= $avgCompletion !== false ? round($avgCompletion, 2) : 'N/A' ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <div class="mb-2"><i class="fas fa-exclamation-circle fa-2x text-danger"></i></div>
                                <h6 class="card-title">Overdue Tasks</h6>
                                <div class="display-6 mb-0 text-danger"><?= count($overdueTasks) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <div class="mb-2"><i class="fas fa-calendar-alt fa-2x text-warning"></i></div>
                                <h6 class="card-title">Upcoming Deadlines</h6>
                                <div class="display-6 mb-0 text-warning"><?= count($upcomingDeadlines) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <div class="mb-2"><i class="fas fa-tasks fa-2x text-success"></i></div>
                                <h6 class="card-title">Projects</h6>
                                <div class="display-6 mb-0 text-success"><?= count($projectProgress) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Tasks Completed Per Week</h5>
                                <div class="chart-container">
                                    <canvas id="tasksPerWeekChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Tasks by Status</h5>
                                <div class="chart-container">
                                    <canvas id="tasksByStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Tasks by Priority</h5>
                                <div class="chart-container">
                                    <canvas id="tasksByPriorityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Project Progress</h5>
                                <div class="chart-container">
                                    <canvas id="projectProgressChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Most Used Tags</h5>
                                <div class="chart-container">
                                    <canvas id="tagsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Most Active Tasks</h5>
                                <div class="chart-container">
                                    <canvas id="activeTasksChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center">
                                <i class="fas fa-hourglass-half text-warning me-2"></i>
                                <span class="fw-semibold">Upcoming Deadlines</span>
                            </div>
                            <div class="card-body pt-2">
                                <?php if (empty($upcomingDeadlines)): ?>
                                    <div class="alert alert-info mb-0">No upcoming deadlines.</div>
                                <?php else: ?>
                                <ul class="list-group list-group-flush">
                                <?php foreach ($upcomingDeadlines as $task): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                        <span><i class="fas fa-tasks text-primary me-2"></i><?= htmlspecialchars($task['title']) ?></span>
                                        <span class="badge bg-warning text-dark rounded-pill"><i class="far fa-calendar-alt me-1"></i><?= htmlspecialchars($task['due_date']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                <span class="fw-semibold">Overdue Tasks</span>
                            </div>
                            <div class="card-body pt-2">
                                <?php if (empty($overdueTasks)): ?>
                                    <div class="alert alert-success mb-0">No overdue tasks. Great job!</div>
                                <?php else: ?>
                                <ul class="list-group list-group-flush">
                                <?php foreach ($overdueTasks as $task): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                        <span><i class="fas fa-tasks text-primary me-2"></i><?= htmlspecialchars($task['title']) ?></span>
                                        <span class="badge bg-danger rounded-pill"><i class="far fa-calendar-alt me-1"></i><?= htmlspecialchars($task['due_date']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Burndown Chart (Open Tasks Over Time)</h5>
                                <div class="chart-container">
                                    <canvas id="burndownChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Chart Data from PHP
    const tasksPerWeekLabels = <?= json_encode(chartLabels($tasksPerWeek, 'yearweek')) ?>;
    const tasksPerWeekData = <?= json_encode(chartData($tasksPerWeek, 'completed_count')) ?>;
    const tasksByStatusLabels = <?= json_encode(chartLabels($tasksByStatus, 'status')) ?>;
    const tasksByStatusData = <?= json_encode(chartData($tasksByStatus, 'count')) ?>;
    const tasksByPriorityLabels = <?= json_encode(chartLabels($tasksByPriority, 'priority')) ?>;
    const tasksByPriorityData = <?= json_encode(chartData($tasksByPriority, 'count')) ?>;
    const projectNames = <?= json_encode(chartLabels($projectProgress, 'name')) ?>;
    const projectProgressData = <?= json_encode(array_map(function($row) {
        return $row['total_tasks'] > 0 ? round($row['completed_tasks'] / $row['total_tasks'] * 100, 1) : 0;
    }, $projectProgress)) ?>;
    const tagsLabels = <?= json_encode(chartLabels($mostUsedTags, 'name')) ?>;
    const tagsData = <?= json_encode(chartData($mostUsedTags, 'usage_count')) ?>;
    const activeTasksLabels = <?= json_encode(chartLabels($mostActiveTasks, 'title')) ?>;
    const activeTasksData = <?= json_encode(chartData($mostActiveTasks, 'comment_count')) ?>;
    const burndownLabels = <?= json_encode($burndownLabels) ?>;
    const burndownValues = <?= json_encode($burndownValues) ?>;

    // Chart.js Theme (auto-detect dark mode)
    function getChartTheme() {
        return document.documentElement.classList.contains('light-mode') ? 'light' : 'dark';
    }
    function getTextColor() {
        return getChartTheme() === 'dark' ? '#fff' : '#222';
    }
    function getGridColor() {
        return getChartTheme() === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.07)';
    }
    // Chart.js Configs
    function makeChart(id, type, labels, data, label, colors) {
        const ctx = document.getElementById(id).getContext('2d');
        return new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: getTextColor() } },
                    title: { display: false }
                },
                scales: type === 'bar' || type === 'line' ? {
                    x: { ticks: { color: getTextColor() }, grid: { color: getGridColor() } },
                    y: { ticks: { color: getTextColor() }, grid: { color: getGridColor() }, beginAtZero: true }
                } : {}
            }
        });
    }
    // Colors
    const palette = [
        '#6C63FF', '#FF6584', '#00D9F5', '#00C853', '#FFD600', '#FF1744', '#A0A0A0', '#5A52E0', '#FFB300', '#43A047'
    ];
    // Render Charts
    let charts = [];
    function renderCharts() {
        charts.forEach(c => c.destroy());
        charts = [];
        charts.push(makeChart('tasksPerWeekChart', 'bar', tasksPerWeekLabels, tasksPerWeekData, 'Tasks Completed', palette));
        charts.push(makeChart('tasksByStatusChart', 'doughnut', tasksByStatusLabels, tasksByStatusData, 'Status', palette));
        charts.push(makeChart('tasksByPriorityChart', 'doughnut', tasksByPriorityLabels, tasksByPriorityData, 'Priority', palette));
        charts.push(makeChart('projectProgressChart', 'bar', projectNames, projectProgressData, 'Progress (%)', palette));
        charts.push(makeChart('tagsChart', 'bar', tagsLabels, tagsData, 'Tag Usage', palette));
        charts.push(makeChart('activeTasksChart', 'bar', activeTasksLabels, activeTasksData, 'Comments', palette));
        charts.push(makeChart('burndownChart', 'line', burndownLabels, burndownValues, 'Open Tasks', palette));
    }
    document.addEventListener('DOMContentLoaded', renderCharts);
    // Re-render on theme toggle
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) themeToggle.addEventListener('click', () => setTimeout(renderCharts, 300));
    </script>
</body>
</html>