<?php
// tasks.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config/db_connect.php';

// Handle success and error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch tasks with project info
$stmt = $pdo->prepare('
    SELECT t.*, p.name as project_name 
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    WHERE t.user_id = ?
    ORDER BY 
        CASE 
            WHEN t.status = "To Do" THEN 1
            WHEN t.status = "In Progress" THEN 2
            WHEN t.status = "Completed" THEN 3
        END,
        t.due_date ASC
');
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects for dropdown in modals
$stmt = $pdo->prepare("SELECT project_id, name FROM projects WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks</title>
    <?php include 'links.php'; ?>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                            <h1>Tasks</h1>
                            <p class="text-muted">Manage your tasks efficiently.</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                                <i class="bi bi-plus-lg"></i> New Task
                            </button>
                        </div>
                    </div>
                </header>
                
                <!-- Task Filter Controls -->
                <div class="mb-4">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-info" data-filter="To Do">To Do</button>
                        <button type="button" class="btn btn-outline-warning" data-filter="In Progress">In Progress</button>
                        <button type="button" class="btn btn-outline-success" data-filter="Completed">Completed</button>
                    </div>
                </div>
                
                <!-- Tasks Overview Section -->
                <div class="row mb-4" id="taskContainer">
                    <?php if (empty($tasks)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No tasks found. Click the "New Task" button to create your first task.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="col-md-6 col-lg-4 mb-3 task-card" data-status="<?php echo htmlspecialchars($task['status']); ?>">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                            <span class="badge bg-<?php 
                                                echo ($task['status'] == 'Completed') ? 'success' : 
                                                    (($task['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>"><?php echo htmlspecialchars($task['status']); ?></span>
                                        </div>
                                        
                                        <p class="card-text">
                                            <?php echo !empty($task['description']) ? 
                                                nl2br(htmlspecialchars(substr($task['description'], 0, 100))) . 
                                                (strlen($task['description']) > 100 ? '...' : '') : 
                                                '<em>No description</em>'; ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="badge bg-<?php 
                                                    echo ($task['priority'] == 'High') ? 'danger' : 
                                                        (($task['priority'] == 'Medium') ? 'warning' : 'secondary'); 
                                                ?>"><?php echo htmlspecialchars($task['priority']); ?> Priority</span>
                                            </div>
                                            <div>
                                                <?php if($task['due_date']): ?>
                                                    <small class="<?php echo strtotime($task['due_date']) < time() && 
                                                        $task['status'] != 'Completed' ? 'text-danger' : 'text-muted'; ?>">
                                                        Due: <?php echo date('Y-m-d', strtotime($task['due_date'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">No due date</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <p class="small text-muted">
                                            Project: <?php echo htmlspecialchars($task['project_name']); ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="update_task_status.php?id=<?php echo $task['task_id']; ?>&status=To Do" 
                                                   class="btn btn-outline-info <?php echo $task['status'] == 'To Do' ? 'active' : ''; ?>">To Do</a>
                                                <a href="update_task_status.php?id=<?php echo $task['task_id']; ?>&status=In Progress" 
                                                   class="btn btn-outline-warning <?php echo $task['status'] == 'In Progress' ? 'active' : ''; ?>">In Progress</a>
                                                <a href="update_task_status.php?id=<?php echo $task['task_id']; ?>&status=Completed" 
                                                   class="btn btn-outline-success <?php echo $task['status'] == 'Completed' ? 'active' : ''; ?>">Completed</a>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary edit-task-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editTaskModal" 
                                                    data-task-id="<?php echo $task['task_id']; ?>"
                                                    data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                    data-task-description="<?php echo htmlspecialchars($task['description']); ?>"
                                                    data-task-status="<?php echo htmlspecialchars($task['status']); ?>"
                                                    data-task-priority="<?php echo htmlspecialchars($task['priority']); ?>"
                                                    data-task-due-date="<?php echo $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : ''; ?>"
                                                    data-task-project="<?php echo htmlspecialchars($task['project_id']); ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            
                                            <a href="task.php?id=<?php echo $task['task_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteTaskModal" 
                                                    data-task-id="<?php echo $task['task_id']; ?>"
                                                    data-task-title="<?php echo htmlspecialchars($task['title']); ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="create_task.php" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTaskModalLabel">Create New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="projectSelect" class="form-label">Project</label>
                            <select class="form-select" id="projectSelect" name="project_id" required>
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['project_id']; ?>">
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="titleInput" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="titleInput" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descriptionInput" class="form-label">Description</label>
                            <textarea class="form-control" id="descriptionInput" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label for="statusSelect" class="form-label">Status</label>
                                <select class="form-select" id="statusSelect" name="status">
                                    <option value="To Do" selected>To Do</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            
                            <div class="col">
                                <label for="prioritySelect" class="form-label">Priority</label>
                                <select class="form-select" id="prioritySelect" name="priority">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dueDateInput" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="dueDateInput" name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="update_task.php" method="post">
                    <input type="hidden" name="task_id" id="editTaskId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label for="editStatus" class="form-label">Status</label>
                                <select class="form-select" id="editStatus" name="status">
                                    <option value="To Do">To Do</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            
                            <div class="col">
                                <label for="editPriority" class="form-label">Priority</label>
                                <select class="form-select" id="editPriority" name="priority">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="editDueDate" name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    <input type="hidden" name="referrer" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

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
                    <p>Are you sure you want to delete the task "<span id="deleteTaskTitle"></span>"?</p>
                    <p>This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Task</a>
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
        gsap.from('.task-card', {
            duration: 0.5,
            y: 20,
            opacity: 0,
            stagger: 0.1,
            ease: 'power2.out'
        });

        // Task filter functionality with smooth transitions
        const filterButtons = document.querySelectorAll('[data-filter]');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const tasks = document.querySelectorAll('.task-card');
                
                // Animate tasks with GSAP
                tasks.forEach(task => {
                    if (filter === 'all' || task.getAttribute('data-status') === filter) {
                        gsap.to(task, {
                            duration: 0.3,
                            opacity: 1,
                            scale: 1,
                            display: 'block',
                            ease: 'power2.out'
                        });
                    } else {
                        gsap.to(task, {
                            duration: 0.3,
                            opacity: 0,
                            scale: 0.95,
                            display: 'none',
                            ease: 'power2.in'
                        });
                    }
                });
            });
        });
        
        // Enhanced Edit Task Modal
        const editTaskModal = document.getElementById('editTaskModal');
        if (editTaskModal) {
            editTaskModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Extract task data from data attributes
                const taskId = button.getAttribute('data-task-id');
                const taskTitle = button.getAttribute('data-task-title');
                const taskDescription = button.getAttribute('data-task-description');
                const taskStatus = button.getAttribute('data-task-status');
                const taskPriority = button.getAttribute('data-task-priority');
                const taskDueDate = button.getAttribute('data-task-due-date');
                
                // Update the modal's content with animation
                const modalElements = {
                    taskId: editTaskModal.querySelector('#editTaskId'),
                    title: editTaskModal.querySelector('#editTitle'),
                    description: editTaskModal.querySelector('#editDescription'),
                    status: editTaskModal.querySelector('#editStatus'),
                    priority: editTaskModal.querySelector('#editPriority'),
                    dueDate: editTaskModal.querySelector('#editDueDate')
                };
                
                // Animate modal content
                gsap.from(editTaskModal.querySelector('.modal-content'), {
                    duration: 0.3,
                    y: 20,
                    opacity: 0,
                    ease: 'power2.out'
                });
                
                // Update values
                modalElements.taskId.value = taskId;
                modalElements.title.value = taskTitle;
                modalElements.description.value = taskDescription;
                modalElements.status.value = taskStatus;
                modalElements.priority.value = taskPriority;
                modalElements.dueDate.value = taskDueDate;
            });
        }
        
        // Enhanced Delete Task Modal
        const deleteTaskModal = document.getElementById('deleteTaskModal');
        if (deleteTaskModal) {
            deleteTaskModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Extract task data
                const taskId = button.getAttribute('data-task-id');
                const taskTitle = button.getAttribute('data-task-title');
                
                // Animate modal content
                gsap.from(deleteTaskModal.querySelector('.modal-content'), {
                    duration: 0.3,
                    y: 20,
                    opacity: 0,
                    ease: 'power2.out'
                });
                
                // Update the modal's content
                document.getElementById('deleteTaskTitle').textContent = taskTitle;
                document.getElementById('confirmDeleteBtn').href = 'delete_task.php?id=' + taskId;
            });
        }

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