<?php
// report_queries.php
// Assumes $pdo and $_SESSION['user_id'] are available

function getTasksCompletedPerWeek($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT YEARWEEK(created_at, 1) as yearweek, COUNT(*) as completed_count
        FROM tasks
        WHERE user_id = ? AND status = "Completed"
        GROUP BY yearweek
        ORDER BY yearweek DESC
        LIMIT 12
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAverageCompletionTime($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT AVG(DATEDIFF(due_date, created_at)) as avg_days
        FROM tasks
        WHERE user_id = ? AND status = "Completed" AND due_date IS NOT NULL
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

function getMostUsedTags($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT tags.name, COUNT(task_tags.task_id) as usage_count
        FROM tags
        JOIN task_tags ON tags.tag_id = task_tags.tag_id
        WHERE tags.user_id = ?
        GROUP BY tags.tag_id, tags.name
        ORDER BY usage_count DESC
        LIMIT 10
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMostActiveTasks($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT t.title, COUNT(tc.comment_id) as comment_count
        FROM tasks t
        LEFT JOIN task_comments tc ON t.task_id = tc.task_id
        WHERE t.user_id = ?
        GROUP BY t.task_id, t.title
        ORDER BY comment_count DESC
        LIMIT 10
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} 