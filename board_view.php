<?php
// filepath: c:\xampp\htdocs\php\TM\board_view.php
session_start();
require_once 'config/db_connect.php';

// Fetch all projects
$stmt = $pdo->prepare("SELECT * FROM projects ORDER BY created_at DESC");
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tasks grouped by project_id
$stmt = $pdo->prepare("SELECT * FROM tasks ORDER BY priority DESC, due_date ASC");
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tasksByProject = [];
foreach ($tasks as $task) {
    $tasksByProject[$task['project_id']][] = $task;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Board View - Projects & Tasks</title>
    <?php include 'links.php'; ?>
    <style>
        .board-container {
            display: flex;
            gap: 2rem;
            overflow-x: auto;
            padding: 2rem 0;
        }
        .board-column {
            min-width: 320px;
            background: var(--card-bg, #23272b);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 1rem;
            flex: 0 0 320px;
            display: flex;
            flex-direction: column;
            max-height: 80vh;
        }
        .board-column h4 {
            margin-bottom: 1rem;
            color: var(--primary-color, #6C63FF);
        }
        .task-card {
            background: var(--background-dark, #181a1b);
            border-radius: 8px;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            color: var(--text-primary, #fff);
            border-left: 4px solid var(--primary-color, #6C63FF);
        }
        .task-card:last-child {
            margin-bottom: 0;
        }
        .task-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .task-meta {
            font-size: 0.92em;
            color: var(--text-secondary, #aaa);
        }
        .empty-tasks {
            color: var(--text-secondary, #aaa);
            font-style: italic;
            margin-top: 1rem;
        }
        @media (max-width: 900px) {
            .board-container { gap: 1rem; }
            .board-column { min-width: 260px; flex-basis: 260px; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
            <h2 class="mb-0">Board View: Projects & Tasks</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                <i class="fas fa-plus me-1"></i> Add Project
            </button>
        </div>
        <div class="board-container">
            <?php foreach ($projects as $project): ?>
                <div class="board-column">
                    <a href="project.php?id=<?php echo urlencode($project['project_id']); ?>" style="text-decoration:none; color:inherit;">
                        <h4 class="mb-1" style="cursor:pointer;">
                            <?php echo htmlspecialchars($project['name']); ?>
                        </h4>
                    </a>
                    <div class="mb-2">
                        <?php
                            $status = strtolower($project['status'] ?? '');
                            $statusClass = 'bg-secondary';
                            if ($status === 'planning') {
                                $statusClass = 'bg-info text-dark'; // blue
                            } elseif ($status === 'in progress' || $status === 'in progess') {
                                $statusClass = 'bg-warning text-dark'; // yellow
                            } elseif ($status === 'completed') {
                                $statusClass = 'bg-success'; // green
                            }
                        ?>
                        <span class="badge <?php echo $statusClass; ?>" style="font-size:0.85em;">
                            <?php echo htmlspecialchars($project['status'] ?? ''); ?>
                        </span>
                    </div>
                    <div class="mb-2 text-muted" style="font-size:0.95em;">
                        <?php echo htmlspecialchars($project['description'] ?? ''); ?>
                    </div>
                    <?php if (!empty($tasksByProject[$project['project_id']])): ?>
                        <?php foreach ($tasksByProject[$project['project_id']] as $task): ?>
                            <a href="task.php?id=<?php echo urlencode($task['task_id']); ?>" style="text-decoration:none; color:inherit;">
                                <div class="task-card mb-2" tabindex="0" style="position:relative; cursor:pointer;">
                                    <div class="task-title">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                        <?php if ($task['status'] === 'Completed'): ?>
                                            <span class="badge bg-success ms-1">Done</span>
                                        <?php elseif ($task['status'] === 'In Progress'): ?>
                                            <span class="badge bg-info ms-1">In Progress</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning ms-1"><?php echo htmlspecialchars($task['status']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="task-meta">
                                        Due: <?php echo htmlspecialchars($task['due_date']); ?> |
                                        Priority: <?php echo htmlspecialchars($task['priority']); ?>
                                    </div>
                                    <div class="task-meta">
                                        <?php echo htmlspecialchars($task['description']); ?>
                                    </div>
                                    <!-- Task details tooltip -->
                                    <div class="task-detail-tooltip" style="display:none; position:absolute; left:105%; top:0; z-index:10; min-width:220px; max-width:320px; background:var(--card-bg,#23272b); color:var(--text-primary,#fff); border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.18); padding:1rem; font-size:0.97em;">
                                        <div><strong>Task:</strong> <?php echo htmlspecialchars($task['title']); ?></div>
                                        <div><strong>Status:</strong> <?php echo htmlspecialchars($task['status']); ?></div>
                                        <div><strong>Due Date:</strong> <?php echo htmlspecialchars($task['due_date']); ?></div>
                                        <div><strong>Priority:</strong> <?php echo htmlspecialchars($task['priority']); ?></div>
                                        <div><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
                                        <?php if (!empty($task['assigned_to'])): ?>
                                            <div><strong>Assigned to:</strong> <?php echo htmlspecialchars($task['assigned_to']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-tasks">No tasks for this project.</div>
                    <?php endif; ?>
                    <div class="mt-auto pt-2">
                        <a href="add_task.php?project_id=<?php echo urlencode($project['project_id']); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-plus me-1"></i> Add Task
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="post" action="create_project.php">
          <div class="modal-header">
            <h5 class="modal-title" id="addProjectModalLabel">Add Project</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <!-- CSRF token for security -->
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
              <div class="mb-3">
                  <label for="projectName" class="form-label">Project Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="projectName" name="name" required maxlength="100">
              </div>
              <div class="mb-3">
                  <label for="projectDescription" class="form-label">Description</label>
                  <textarea class="form-control" id="projectDescription" name="description" rows="3" maxlength="500"></textarea>
              </div>
              <div class="mb-3">
                  <label for="projectStatus" class="form-label">Status <span class="text-danger">*</span></label>
                  <select class="form-select" id="projectStatus" name="status" required>
                      <option value="Planning">Planning</option>
                      <option value="In progress">In progess</option>
                      <option value="Completed">Completed</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label for="projectPriority" class="form-label">Priority <span class="text-danger">*</span></label>
                  <select class="form-select" id="projectPriority" name="priority" required>
                      <option value="Low">Low</option>
                      <option value="Medium" selected>Medium</option>
                      <option value="High">High</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Project</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    document.querySelectorAll('.task-card').forEach(card => {
        const tooltip = card.querySelector('.task-detail-tooltip');
        if (!tooltip) return;

        function showTip() {
            tooltip.style.display = 'block';
            // Reset position
            tooltip.style.top = '0';
            tooltip.style.left = '105%';

            // Get bounding rectangles
            const tipRect = tooltip.getBoundingClientRect();
            const cardRect = card.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;

            // If tooltip goes off bottom, shift up
            if (tipRect.bottom > viewportHeight) {
                let shift = tipRect.bottom - viewportHeight + 8; // 8px margin
                tooltip.style.top = `-${shift}px`;
            }

            // If tooltip goes off top, align to top
            if (tipRect.top < 0) {
                tooltip.style.top = `${-cardRect.top + 8}px`;
            }

            // If tooltip goes off right, show on left
            if (tipRect.right > viewportWidth) {
                tooltip.style.left = 'auto';
                tooltip.style.right = '105%';
            } else {
                tooltip.style.right = 'auto';
            }
        }
        function hideTip() { tooltip.style.display = 'none'; }

        card.addEventListener('mouseenter', showTip);
        card.addEventListener('mouseleave', hideTip);
        card.addEventListener('focus', showTip);
        card.addEventListener('blur', hideTip);
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>