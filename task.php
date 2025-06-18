<?php
require_once 'config/db_connect.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid task ID";
    header('Location: projects.php');
    exit;
}

$task_id = $_GET['id'];

// Fetch task details with project info
$stmt = $pdo->prepare("
    SELECT t.*, p.name AS project_name, p.project_id 
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.task_id = ? AND p.user_id = ?
");
$stmt->execute([$task_id, $_SESSION['user_id']]);
$task = $stmt->fetch();

// If task doesn't exist or doesn't belong to the user
if (!$task) {
    $_SESSION['error'] = "Task not found";
    header('Location: projects.php');
    exit;
}

// Fetch task comments
$stmt = $pdo->prepare("
    SELECT c.*, u.username 
    FROM task_comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.task_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$task_id]);
$comments = $stmt->fetchAll();

// Message handling
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($task['title']); ?> - Task Management</title>
    <?php include 'links.php'; ?>
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'navbar.html'; ?>
    <div class="container-fluid">
        <div class="row vh-100">
            <!-- Main Content -->
            <div class="col-md-10 col-lg-11 p-4">
                <!-- Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success fade show mb-4"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger fade show mb-4"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="task-card">
                    <!-- Task Header with Actions -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="fw-bold mb-1"><?php echo htmlspecialchars($task['title']); ?></h1>
                            <p class="text-muted mb-0">
                                <a href="project.php?id=<?php echo $task['project_id']; ?>" class="text-decoration-none">
                                    <i class="bi bi-folder me-1"></i> <?php echo htmlspecialchars($task['project_name']); ?>
                                </a>
                            </p>
                        </div>
                        <div>
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTaskModal">
                                <i class="bi bi-pencil"></i> Edit Task
                            </button>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>Description</h5>
                                    <p class="card-text">
                                        <?php echo !empty($task['description']) ? 
                                            nl2br(htmlspecialchars($task['description'])) : 
                                            '<em class="text-secondary">No description provided</em>'; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Comments Section -->
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <i class="bi bi-chat-dots me-2"></i>
                                    <h5 class="mb-0">Comments</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($comments)): ?>
                                        <p class="text-muted">No comments yet.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush mb-3">
                                            <?php foreach ($comments as $comment): ?>
                                                <div class="comment list-group-item bg-transparent border-0 mb-2 p-0">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="fw-semibold"><?php echo htmlspecialchars($comment['username']); ?></span>
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock me-1"></i><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="comment-body ps-2">
                                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Add Comment Form -->
                                    <form action="add_comment.php" method="post" class="mt-3">
                                        <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                                        <div class="mb-3">
                                            <label for="comment" class="form-label">Add a comment</label>
                                            <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Post Comment</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Task Info Card -->
                            <div class="card mb-4">
                                <div class="card-header d-flex align-items-center">
                                    <i class="bi bi-info-square me-2"></i>
                                    <h5 class="mb-0">Task Information</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                            <span>Status</span>
                                            <span class="badge bg-<?php 
                                                echo ($task['status'] == 'Completed') ? 'success' : 
                                                    (($task['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo htmlspecialchars($task['status']); ?>
                                            </span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                            <span>Priority</span>
                                            <span class="badge bg-<?php 
                                                echo ($task['priority'] == 'High') ? 'danger' : 
                                                    (($task['priority'] == 'Medium') ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($task['priority']); ?>
                                            </span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                            <span>Due Date</span>
                                            <?php if($task['due_date']): ?>
                                                <span class="<?php echo strtotime($task['due_date']) < time() && 
                                                    $task['status'] != 'Completed' ? 'text-danger fw-bold' : ''; ?>">
                                                    <i class="bi bi-calendar-event me-1"></i><?php echo date('Y-m-d', strtotime($task['due_date'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                            <span>Created</span>
                                            <span><i class="bi bi-calendar me-1"></i><?php echo date('Y-m-d', strtotime($task['created_at'])); ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Quick Actions Card -->
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <i class="bi bi-lightning-charge me-2"></i>
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <?php if ($task['status'] != 'In Progress'): ?>
                                            <a href="update_task_status.php?id=<?php echo $task_id; ?>&status=In Progress" class="btn btn-warning">
                                                <i class="bi bi-play-fill"></i> Mark In Progress
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($task['status'] != 'Completed'): ?>
                                            <a href="update_task_status.php?id=<?php echo $task_id; ?>&status=Completed" class="btn btn-success">
                                                <i class="bi bi-check-lg"></i> Mark Completed
                                            </a>
                                        <?php else: ?>
                                            <a href="update_task_status.php?id=<?php echo $task_id; ?>&status=To Do" class="btn btn-info">
                                                <i class="bi bi-arrow-repeat"></i> Reopen Task
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteTaskModal">
                                            <i class="bi bi-trash"></i> Delete Task
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="update_task.php" method="post">
                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="taskTitle" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="taskTitle" name="title" 
                                   value="<?php echo htmlspecialchars($task['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="taskDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="taskDescription" name="description" 
                                      rows="3"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="taskStatus" class="form-label">Status</label>
                            <select class="form-select" id="taskStatus" name="status">
                                <option value="To Do" <?php echo $task['status'] == 'To Do' ? 'selected' : ''; ?>>To Do</option>
                                <option value="In Progress" <?php echo $task['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $task['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="taskPriority" class="form-label">Priority</label>
                            <select class="form-select" id="taskPriority" name="priority">
                                <option value="Low" <?php echo $task['priority'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $task['priority'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $task['priority'] == 'High' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="taskDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="taskDueDate" name="due_date" 
                                   value="<?php echo $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : ''; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Task Modal -->
    <div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTaskModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                    Are you sure you want to delete this task? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="delete_task.php?id=<?php echo $task_id; ?>&project_id=<?php echo $task['project_id']; ?>" 
                       class="btn btn-danger">Delete Task</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- GSAP for smooth animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animate task card on load
        gsap.from('.task-card', {
            duration: 0.5,
            y: 20,
            opacity: 0,
            ease: 'power2.out'
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