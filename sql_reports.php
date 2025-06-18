<?php
// sql_reports.php
// Assumes $pdo and $_SESSION['user_id'] are available

function getTasksCompletedPerWeek($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT YEARWEEK(completed_at, 1) as yearweek, COUNT(*) as completed_count
        FROM tasks
        WHERE user_id = ? AND status = "Completed" AND completed_at IS NOT NULL
        GROUP BY yearweek
        ORDER BY yearweek DESC
        LIMIT 12
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAverageCompletionTime($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours
        FROM tasks
        WHERE user_id = ? AND status = "Completed" AND completed_at IS NOT NULL
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function getOverdueTasks($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT * FROM tasks
        WHERE user_id = ? AND status != "Completed" AND due_date < CURDATE()
        ORDER BY due_date ASC
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTasksByStatus($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT status, COUNT(*) as count
        FROM tasks
        WHERE user_id = ?
        GROUP BY status
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTasksByPriority($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT priority, COUNT(*) as count
        FROM tasks
        WHERE user_id = ?
        GROUP BY priority
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProjectProgress($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT p.project_id, p.name,
            COUNT(t.task_id) as total_tasks,
            SUM(CASE WHEN t.status = "Completed" THEN 1 ELSE 0 END) as completed_tasks
        FROM projects p
        LEFT JOIN tasks t ON p.project_id = t.project_id
        WHERE p.user_id = ?
        GROUP BY p.project_id, p.name
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUpcomingDeadlines($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT * FROM tasks
        WHERE user_id = ? AND status != "Completed" AND due_date >= CURDATE()
        ORDER BY due_date ASC
        LIMIT 10
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} 