<?php
// filepath: c:\xampp\htdocs\php\TM\add_task.php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get project ID if provided
$project_id = $_GET['project_id'] ?? null;

// Fetch all user's projects for dropdown
$stmt = $pdo->prepare("SELECT project_id, name FROM projects WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

// If no projects exist, redirect to projects page with message
if (empty($projects)) {
    $_SESSION['error'] = "You need to create a project before adding tasks";
    header('Location: projects.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'To Do';
    $priority = $_POST['priority'] ?? 'Medium';
    $due_date = $_POST['due_date'] ?? null;
    $project_id = $_POST['project_id'] ?? null;
    
    // Basic validation
    if (empty($title)) {
        $error = "Task title is required";
    } elseif (empty($project_id)) {
        $error = "Project selection is required";
    } else {
        try {
            // Verify project belongs to user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$project_id, $_SESSION['user_id']]);
            
            if ($stmt->fetchColumn() == 0) {
                $error = "Invalid project selected";
            } else {
                // Insert task
                if (!empty($due_date)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tasks 
                        (project_id, user_id, title, description, status, priority, due_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $result = $stmt->execute([
                        $project_id, 
                        $_SESSION['user_id'], 
                        $title, 
                        $description, 
                        $status, 
                        $priority, 
                        $due_date
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO tasks 
                        (project_id, user_id, title, description, status, priority) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $result = $stmt->execute([
                        $project_id, 
                        $_SESSION['user_id'], 
                        $title, 
                        $description, 
                        $status, 
                        $priority
                    ]);
                }
                
                if ($result) {
                    $_SESSION['success'] = "Task created successfully";
                    header("Location: project.php?id=$project_id");
                    exit;
                } else {
                    $error = "Failed to create task";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Task - Task Management</title>
    <?php include 'links.php'; ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row vh-100">
            <!-- Include Navbar -->
            <?php include 'navbar.html'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 col-lg-11 p-4">
                <!-- Header -->
                <header class="mb-4">
                    <h1>Add New Task</h1>
                    <p class="text-muted">Create a new task for your projects</p>
                </header>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <!-- Task Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label for="project_id" class="form-label">Project</label>
                                <select class="form-select" id="project_id" name="project_id" required>
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['project_id']; ?>" 
                                            <?php echo ($project_id == $project['project_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Task Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                    echo htmlspecialchars($_POST['description'] ?? ''); 
                                ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="To Do" <?php echo isset($_POST['status']) && $_POST['status'] == 'To Do' ? 'selected' : ''; ?>>To Do</option>
                                        <option value="In Progress" <?php echo isset($_POST['status']) && $_POST['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="Completed" <?php echo isset($_POST['status']) && $_POST['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="Low" <?php echo isset($_POST['priority']) && $_POST['priority'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="Medium" <?php echo isset($_POST['priority']) && $_POST['priority'] == 'Medium' ? 'selected' : 'selected'; ?>>Medium</option>
                                        <option value="High" <?php echo isset($_POST['priority']) && $_POST['priority'] == 'High' ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="due_date" class="form-label">Due Date</label>
                                    <input type="date" class="form-control" id="due_date" name="due_date" 
                                           value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="<?php echo $project_id ? "project.php?id=$project_id" : 'tasks.php'; ?>" class="btn btn-secondary">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">Create Task</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>