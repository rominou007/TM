<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'config/db_connect.php';
include_once 'functions.php';
// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle success and error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch projects for the current user
$stmt = $pdo->prepare('
    SELECT * FROM projects 
    WHERE user_id = ? 
    ORDER BY 
        CASE 
            WHEN status = "Planning" THEN 1
            WHEN status = "In Progress" THEN 2
            WHEN status = "Completed" THEN 3
        END,
        name ASC
');
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize empty arrays if no projects found
if (empty($projects)) {
    $projects = [];
}

// Fetch user's tasks
$stmt = $pdo->prepare("
    SELECT t.*, p.name as project_name 
    FROM tasks t 
    JOIN projects p ON t.project_id = p.project_id 
    WHERE t.user_id = ? 
    ORDER BY t.due_date ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects</title>
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
                            <h1>Projects</h1>
                            <p class="text-muted">Manage your projects and track their progress.</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                <i class="bi bi-plus-lg"></i> New Project
                            </button>
                        </div>
                    </div>
                </header>
                
                <!-- Project Filter Controls -->
                <div class="mb-4">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-info" data-filter="Planning">Planning</button>
                        <button type="button" class="btn btn-outline-warning" data-filter="In Progress">In Progress</button>
                        <button type="button" class="btn btn-outline-success" data-filter="Completed">Completed</button>
                    </div>
                </div>
                
                <!-- Projects Grid -->
                <div class="row mb-4" id="projectContainer">
                    <?php if (empty($projects)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No projects found. Click the "New Project" button to create your first project.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="col-md-6 col-lg-4 mb-3 project-card" data-status="<?php echo htmlspecialchars($project['status']); ?>">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h5 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h5>
                                            <span class="badge bg-<?php 
                                                echo ($project['status'] == 'Completed') ? 'success' : 
                                                    (($project['status'] == 'In Progress') ? 'warning' : 'info'); 
                                            ?>"><?php echo htmlspecialchars($project['status']); ?></span>
                                        </div>
                                        
                                        <p class="card-text">
                                            <?php echo !empty($project['description']) ? 
                                                nl2br(htmlspecialchars(substr($project['description'], 0, 100))) . 
                                                (strlen($project['description']) > 100 ? '...' : '') : 
                                                '<em>No description</em>'; ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="badge bg-<?php 
                                                    echo isset($project['priority']) ? 
                                                        (($project['priority'] == 'High') ? 'danger' : 
                                                        (($project['priority'] == 'Medium') ? 'warning' : 'secondary')) 
                                                        : 'secondary'; 
                                                ?>"><?php echo isset($project['priority']) ? htmlspecialchars($project['priority']) : 'Normal'; ?> Priority</span>
                                            </div>
                                        </div>
                                        
                                        <p class="small text-muted">
                                            <?php echo isset($project['description']) ? htmlspecialchars($project['description']) : 'No description'; ?>
                                        </p>
                                        
                                        <div class="mt-3">
                                            <a href="project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
                                            
                                            <button class="btn btn-sm btn-outline-primary edit-project-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editProjectModal" 
                                                    data-project-id="<?php echo $project['project_id']; ?>"
                                                    data-project-name="<?php echo htmlspecialchars($project['name']); ?>"
                                                    data-project-description="<?php echo isset($project['description']) ? htmlspecialchars($project['description']) : ''; ?>"
                                                    data-project-status="<?php echo htmlspecialchars($project['status']); ?>"
                                                    data-project-priority="<?php echo isset($project['priority']) ? htmlspecialchars($project['priority']) : 'Medium'; ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteProjectModal"
                                                    data-project-id="<?php echo $project['project_id']; ?>"
                                                    data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
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
    
    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="create_project.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProjectModalLabel">Create New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="projectName" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="projectName" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="projectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="projectDescription" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label for="projectStatus" class="form-label">Status</label>
                                <select class="form-select" id="projectStatus" name="status">
                                    <option value="Planning" selected>Planning</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            
                            <div class="col">
                                <label for="projectPriority" class="form-label">Priority</label>
                                <select class="form-select" id="projectPriority" name="priority">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="projectDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="projectDueDate" name="due_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Project Modal (single instance) -->
    <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="update_project.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="project_id" id="editProjectId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProjectModalLabel">Edit Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editProjectName" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="editProjectName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editProjectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editProjectDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="editProjectStatus" class="form-label">Status</label>
                                <select class="form-select" id="editProjectStatus" name="status">
                                    <option value="Planning">Planning</option>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Project Modal -->
    <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteProjectModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the project "<span id="deleteProjectName"></span>"?</p>
                    <p>This action cannot be undone and will also delete all associated tasks.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Project</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- GSAP for smooth animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    
    <script>
    window.CSRF_TOKEN = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize GSAP animations
        gsap.from('.project-card', {
            duration: 0.5,
            y: 20,
            opacity: 1,
            ease: 'power2.out'
        });

        // Remove any default opacity/greying out on project cards after animation
        document.querySelectorAll('.project-card').forEach(card => {
            card.style.opacity = '1';
            card.style.filter = 'none';
        });

        // Project filter functionality with smooth transitions
        const filterButtons = document.querySelectorAll('[data-filter]');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const projects = document.querySelectorAll('.project-card');
                
                projects.forEach(project => {
                    if (filter === 'all' || project.getAttribute('data-status') === filter) {
                        project.style.display = ''; // Restore Bootstrap's default (usually flex)
                        gsap.to(project, {
                            duration: 0.3,
                            opacity: 1,
                            scale: 1,
                            ease: 'power2.out',
                            onComplete: () => {
                                project.style.pointerEvents = '';
                            }
                        });
                    } else {
                        gsap.to(project, {
                            duration: 0.3,
                            opacity: 1,
                            scale: 1,
                            ease: 'power2.in',
                            onComplete: () => {
                                project.style.display = 'none';
                                project.style.pointerEvents = 'none';
                            }
                        });
                    }
                });
            });
        });
        
        // Only one Edit Project Modal JS block
        const editProjectModal = document.getElementById('editProjectModal');
        if (editProjectModal) {
            editProjectModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                if (!button) return;
                // Get data from button
                const projectId = button.getAttribute('data-project-id');
                const projectName = button.getAttribute('data-project-name');
                const projectDescription = button.getAttribute('data-project-description');
                const projectStatus = button.getAttribute('data-project-status');
                const projectPriority = button.getAttribute('data-project-priority');
                // Set modal fields by ID
                editProjectModal.querySelector('#editProjectId').value = projectId;
                editProjectModal.querySelector('#editProjectName').value = projectName;
                editProjectModal.querySelector('#editProjectDescription').value = projectDescription;
                editProjectModal.querySelector('#editProjectStatus').value = projectStatus;
                editProjectModal.querySelector('#editPriority').value = projectPriority;
                // Also update CSRF token
                const csrfInput = editProjectModal.querySelector('input[name="csrf_token"]');
                if (csrfInput && window.CSRF_TOKEN) {
                    csrfInput.value = window.CSRF_TOKEN;
                }
            });
        }
        
        // Enhanced Delete Project Modal
        const deleteProjectModal = document.getElementById('deleteProjectModal');
        if (deleteProjectModal) {
            deleteProjectModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Extract project data
                const projectId = button.getAttribute('data-project-id');
                const projectName = button.getAttribute('data-project-name');
                
                // Animate modal content
                gsap.from(deleteProjectModal.querySelector('.modal-content'), {
                    duration: 0.3,
                    y: 20,
                    opacity: 0,
                    ease: 'power2.out'
                });
                
                // Update the modal's content
                document.getElementById('deleteProjectName').textContent = projectName;
                document.getElementById('confirmDeleteBtn').href = 'delete_project.php?id=' + projectId;
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

        // Add Project Modal CSRF sync
        const addProjectModal = document.getElementById('addProjectModal');
        if (addProjectModal) {
            addProjectModal.addEventListener('show.bs.modal', function () {
                const csrfInput = addProjectModal.querySelector('input[name=\'csrf_token\']');
                if (csrfInput && window.CSRF_TOKEN) {
                    csrfInput.value = window.CSRF_TOKEN;
                }
            });
        }
    });
    </script>
</body>
</html>