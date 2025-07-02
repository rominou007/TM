<?php
// report_queries.php
// Assumes $pdo and $_SESSION['user_id'] are available

$selectedProjectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;

function getTasksCompletedPerWeek($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT YEARWEEK(created_at, 1) as yearweek, COUNT(*) as completed_count
        FROM tasks
        WHERE user_id = ? AND status = "Completed"
    ';
    $params = [$user_id];

    if ($project_id !== null) {
        $query .= ' AND project_id = ?';
        $params[] = $project_id;
    }

    $query .= '
        GROUP BY yearweek
        ORDER BY yearweek DESC
        LIMIT 12
    ';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAverageCompletionTime($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT AVG(DATEDIFF(due_date, created_at)) as avg_days
        FROM tasks
        WHERE user_id = ? AND status = "Completed" AND due_date IS NOT NULL
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND project_id = ?';
        $params[] = $project_id;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function getOverdueTasks($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT * FROM tasks
        WHERE user_id = ? AND status != "Completed" AND due_date < CURDATE()
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND project_id = ?';
        $params[] = $project_id;
    }
    $query .= ' ORDER BY due_date ASC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTasksByStatus($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT status, COUNT(*) as count
        FROM tasks
        WHERE user_id = ?
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND project_id = ?';
        $params[] = $project_id;
    }
    $query .= ' GROUP BY status';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTasksByPriority($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT priority, COUNT(*) as count
        FROM tasks
        WHERE user_id = ?
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND project_id = ?';
        $params[] = $project_id;
    }
    $query .= ' GROUP BY priority';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProjectProgress($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT p.project_id, p.name,
            COUNT(t.task_id) as total_tasks,
            SUM(CASE WHEN t.status = "Completed" THEN 1 ELSE 0 END) as completed_tasks
        FROM projects p
        LEFT JOIN tasks t ON p.project_id = t.project_id
        WHERE p.user_id = ?
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND p.project_id = ?';
        $params[] = $project_id;
    }
    $query .= ' GROUP BY p.project_id, p.name';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUpcomingDeadlines($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT * FROM tasks
        WHERE user_id = ? AND status != "Completed" AND due_date >= CURDATE()
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND project_id = ?';
        $params[] = $project_id;
    }
    $query .= ' ORDER BY due_date ASC LIMIT 10';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMostUsedTags($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT tags.name, COUNT(task_tags.task_id) as usage_count
        FROM tags
        JOIN task_tags ON tags.tag_id = task_tags.tag_id
        JOIN tasks ON tasks.task_id = task_tags.task_id
        WHERE tags.user_id = ?
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND tasks.project_id = ?';
        $params[] = $project_id;
    }
    $query .= ' GROUP BY tags.tag_id, tags.name
        ORDER BY usage_count DESC
        LIMIT 10';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMostActiveTasks($pdo, $user_id, $project_id = null) {
    $query = '
        SELECT t.title, COUNT(tc.comment_id) as comment_count
        FROM tasks t
        LEFT JOIN task_comments tc ON t.task_id = tc.task_id
        WHERE t.user_id = ?
    ';
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= ' AND t.project_id = ?';
        $params[] = $project_id;
    }
    $query .= ' GROUP BY t.task_id, t.title
        ORDER BY comment_count DESC
        LIMIT 10';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBurndownData($pdo, $user_id, $project_id = null) {
    // Get all dates from the earliest task to today
    $query = "SELECT MIN(created_at) as min_date FROM tasks WHERE user_id = ?";
    $params = [$user_id];
    if ($project_id !== null) {
        $query .= " AND project_id = ?";
        $params[] = $project_id;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $minDate = $stmt->fetchColumn();
    $dates = [];
    $current = new DateTime($minDate ?: date('Y-m-d'));
    $today = new DateTime();
    while ($current <= $today) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }

    // For each date, count incomplete tasks as of that day
    $burndown = [];
    foreach ($dates as $date) {
        $query = "
            SELECT COUNT(*) FROM tasks
            WHERE user_id = ?
              AND created_at <= ?
              AND (completed_at IS NULL OR completed_at > ?)
        ";
        $params = [$user_id, $date . ' 23:59:59', $date . ' 23:59:59'];
        if ($project_id !== null) {
            $query .= " AND project_id = ?";
            $params[] = $project_id;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $burndown[] = [
            'date' => $date,
            'remaining' => (int)$stmt->fetchColumn()
        ];
    }
    return $burndown;
}

