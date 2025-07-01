<?php
// filepath: c:\xampp\htdocs\php\TM\settings.php
session_start();
require_once 'config/db_connect.php';
require_once 'functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Profile update form
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        
        // Validate input
        if (empty($username)) {
            $error_message = "Username cannot be empty";
        } elseif (empty($email)) {
            $error_message = "Email cannot be empty";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format";
        } else {
            // Check if username already exists (except for current user)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Username already taken";
            } else {
                // Check if email already exists (except for current user)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Email already registered";
                } else {
                    try {
                        // Update user profile
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ? WHERE user_id = ?");
                        $stmt->execute([$username, $email, $full_name, $user_id]);
                        $success_message = "Profile updated successfully";
                        
                        // Update session data
                        $_SESSION['username'] = $username;
                    } catch (PDOException $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change form
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($current_password)) {
            $error_message = "Current password is required";
        } elseif (empty($new_password)) {
            $error_message = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $error_message = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $error_message = "Current password is incorrect";
            } else {
                try {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success_message = "Password updated successfully";
                } catch (PDOException $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['update_preferences'])) {
        // User preferences form
        $theme = $_POST['theme'] ?? 'light';
        $date_format = $_POST['date_format'] ?? 'Y-m-d';
        $time_format = $_POST['time_format'] ?? '24hour';
        $task_sort = $_POST['task_sort'] ?? 'due_date';
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        
        try {
            // Check if user settings exist
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_settings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            if ($stmt->fetchColumn() > 0) {
                // Update existing settings
                $stmt = $pdo->prepare("
                    UPDATE user_settings 
                    SET theme = ?, date_format = ?, time_format = ?, task_sort = ?, notifications = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$theme, $date_format, $time_format, $task_sort, $notifications, $user_id]);
            } else {
                // Insert new settings
                $stmt = $pdo->prepare("
                    INSERT INTO user_settings 
                    (user_id, theme, date_format, time_format, task_sort, notifications)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $theme, $date_format, $time_format, $task_sort, $notifications]);
            }
            
            $success_message = "Preferences updated successfully";
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Fetch user settings if they exist
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
    
    // Set defaults if no settings found
    if (!$settings) {
        $settings = [
            'theme' => 'light',
            'date_format' => 'Y-m-d',
            'time_format' => '24hour',
            'task_sort' => 'due_date',
            'notifications' => 1
        ];
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Task Management</title>
    <?php include 'links.php'; ?>
    
    <style>
        /* Additional settings-specific styles */
        .settings-list-group .list-group-item {
            background-color: var(--card-bg);
            color: var(--text-primary);
            border-color: var(--border-color);
            border-radius: 8px;
            margin-bottom: 4px;
        }
        
        .settings-list-group .list-group-item.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .settings-list-group .list-group-item:hover:not(.active) {
            background-color: var(--card-bg);
            color: var(--primary-color);
        }
        
        /* Override any Bootstrap styles that conflict with our theme */
        .card {
            background-color: var(--card-bg) !important;
            color: var(--text-primary) !important;
        }
        
        .card-header {
            background-color: var(--card-bg) !important;
            border-bottom-color: var(--border-color) !important;
        }
        
        .danger-header {
            background-color: var(--danger-color) !important;
            color: white !important;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        
        .modal-header, .modal-footer {
            border-color: var(--border-color);
        } 
        
        /* Fix for system theme detection */
        @media (prefers-color-scheme: dark) {
            .auto-theme-dark {
                background-color: var(--background-dark);
                color: var(--text-primary);
            }
        }
        
        @media (prefers-color-scheme: light) {
            .auto-theme-light {
                background-color: #f8f9fa;
                color: #212529;
            }
        }  
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Navbar -->
            <?php include 'navbar.html'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 col-lg-11 p-4">
                <header class="mb-4">
                    <h1>Settings</h1>
                    <p class="text-muted">Manage your account and preferences</p>
                </header>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <!-- Settings Navigation -->
                        <div class="list-group settings-list-group">
                            <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                                <i class="bi bi-person"></i> Profile
                            </a>
                            <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="list">
                                <i class="bi bi-shield-lock"></i> Security
                            </a>
                            <a href="#preferences" class="list-group-item list-group-item-action" data-bs-toggle="list">
                                <i class="bi bi-sliders"></i> Preferences
                            </a>
                            <a href="#data" class="list-group-item list-group-item-action" data-bs-toggle="list">
                                <i class="bi bi-database"></i> Data Management
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-9">
                        <div class="tab-content">
                            <!-- Profile Settings -->
                            <div class="tab-pane fade show active" id="profile">
                                <div class="card project-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Profile Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label for="username" class="form-label">Username</label>
                                                <input type="text" class="form-control" id="username" name="username" 
                                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="full_name" class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                            </div>
                                            
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                Save Changes
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Security Settings -->
                            <div class="tab-pane fade" id="security">
                                <div class="card project-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Change Password</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <div class="form-text text-muted">Password must be at least 8 characters long.</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                            
                                            <button type="submit" name="change_password" class="btn btn-primary">
                                                Update Password
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="card project-card mt-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Login Sessions</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>You are currently logged in from this device.</p>
                                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#logoutAllModal">
                                            Log Out from All Devices
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Preferences -->
                            <div class="tab-pane fade" id="preferences">
                                <div class="card project-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Application Preferences</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label class="form-label">Theme</label>
                                                <div class="d-flex flex-wrap">
                                                    <div class="form-check me-3 mb-2">
                                                        <input class="form-check-input" type="radio" name="theme" id="theme_light" 
                                                               value="light" <?php echo ($settings['theme'] == 'light') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="theme_light">
                                                            Light
                                                        </label>
                                                    </div>
                                                    <div class="form-check me-3 mb-2">
                                                        <input class="form-check-input" type="radio" name="theme" id="theme_dark" 
                                                               value="dark" <?php echo ($settings['theme'] == 'dark') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="theme_dark">
                                                            Dark
                                                        </label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" name="theme" id="theme_auto" 
                                                               value="auto" <?php echo ($settings['theme'] == 'auto') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="theme_auto">
                                                            System Default
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="date_format" class="form-label">Date Format</label>
                                                <select class="form-select" id="date_format" name="date_format">
                                                    <option value="Y-m-d" <?php echo ($settings['date_format'] == 'Y-m-d') ? 'selected' : ''; ?>>
                                                        YYYY-MM-DD (<?php echo date('Y-m-d'); ?>)
                                                    </option>
                                                    <option value="m/d/Y" <?php echo ($settings['date_format'] == 'm/d/Y') ? 'selected' : ''; ?>>
                                                        MM/DD/YYYY (<?php echo date('m/d/Y'); ?>)
                                                    </option>
                                                    <option value="d/m/Y" <?php echo ($settings['date_format'] == 'd/m/Y') ? 'selected' : ''; ?>>
                                                        DD/MM/YYYY (<?php echo date('d/m/Y'); ?>)
                                                    </option>
                                                    <option value="d.m.Y" <?php echo ($settings['date_format'] == 'd.m.Y') ? 'selected' : ''; ?>>
                                                        DD.MM.YYYY (<?php echo date('d.m.Y'); ?>)
                                                    </option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Time Format</label>
                                                <div class="d-flex flex-wrap">
                                                    <div class="form-check me-3 mb-2">
                                                        <input class="form-check-input" type="radio" name="time_format" id="time_24" 
                                                               value="24hour" <?php echo ($settings['time_format'] == '24hour') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="time_24">
                                                            24-hour (<?php echo date('H:i'); ?>)
                                                        </label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" name="time_format" id="time_12" 
                                                               value="12hour" <?php echo ($settings['time_format'] == '12hour') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="time_12">
                                                            12-hour (<?php echo date('h:i A'); ?>)
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="task_sort" class="form-label">Default Task Sorting</label>
                                                <select class="form-select" id="task_sort" name="task_sort">
                                                    <option value="due_date" <?php echo ($settings['task_sort'] == 'due_date') ? 'selected' : ''; ?>>
                                                        Due Date
                                                    </option>
                                                    <option value="priority" <?php echo ($settings['task_sort'] == 'priority') ? 'selected' : ''; ?>>
                                                        Priority
                                                    </option>
                                                    <option value="title" <?php echo ($settings['task_sort'] == 'title') ? 'selected' : ''; ?>>
                                                        Title
                                                    </option>
                                                    <option value="status" <?php echo ($settings['task_sort'] == 'status') ? 'selected' : ''; ?>>
                                                        Status
                                                    </option>
                                                    <option value="created" <?php echo ($settings['task_sort'] == 'created') ? 'selected' : ''; ?>>
                                                        Date Created
                                                    </option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="notifications" name="notifications" 
                                                           <?php echo ($settings['notifications'] == 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifications">Enable Notifications</label>
                                                </div>
                                                <div class="form-text text-muted">Receive notifications for upcoming due dates and reminders.</div>
                                            </div>
                                            
                                            <button type="submit" name="update_preferences" class="btn btn-primary">
                                                Save Preferences
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data Management -->
                            <div class="tab-pane fade" id="data">
                                <div class="card project-card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Export Data</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Export your tasks and projects data in various formats.</p>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="export_data.php?format=json" class="btn btn-outline-primary">
                                                <i class="bi bi-filetype-json"></i> Export as JSON
                                            </a>
                                            <a href="export_data.php?format=csv" class="btn btn-outline-success">
                                                <i class="bi bi-filetype-csv"></i> Export as CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card project-card mt-4">
                                    <div class="card-header danger-header">
                                        <h5 class="card-title mb-0">Danger Zone</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <h6>Clear Completed Tasks</h6>
                                            <p>Remove all tasks marked as completed. This action cannot be undone.</p>
                                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearCompletedModal">
                                                <i class="bi bi-trash"></i> Clear Completed Tasks
                                            </button>
                                        </div>
                                        
                                        <div class="mb-4 border-top pt-4">
                                            <h6>Reset Application</h6>
                                            <p>Delete all your tasks and projects. This action cannot be undone.</p>
                                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetAppModal">
                                                <i class="bi bi-exclamation-triangle"></i> Reset Application Data
                                            </button>
                                        </div>
                                        
                                        <div class="border-top pt-4">
                                            <h6>Delete Account</h6>
                                            <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                                <i class="bi bi-person-x"></i> Delete Account
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
    </div>
    
    <!-- Logout All Sessions Modal -->
    <div class="modal fade" id="logoutAllModal" tabindex="-1" aria-labelledby="logoutAllModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutAllModalLabel">Log Out from All Devices</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>This will log you out from all devices where you are currently signed in. You will need to log in again on each device.</p>
                    <p>Do you want to continue?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="logout_all.php" class="btn btn-warning">Log Out from All Devices</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Clear Completed Tasks Modal -->
    <div class="modal fade" id="clearCompletedModal" tabindex="-1" aria-labelledby="clearCompletedModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clearCompletedModalLabel">Clear Completed Tasks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete all completed tasks? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="clear_completed_tasks.php" class="btn btn-danger">Delete Completed Tasks</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Application Modal -->
    <div class="modal fade" id="resetAppModal" tabindex="-1" aria-labelledby="resetAppModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetAppModalLabel">Reset Application Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">WARNING: This will delete all your projects and tasks!</p>
                    <p>This action cannot be undone. Your account will remain active, but all your data will be removed.</p>
                    <p>Type "RESET" in the box below to confirm:</p>
                    <input type="text" id="resetConfirmation" class="form-control" placeholder="Type RESET to confirm">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="reset_app_data.php" class="btn btn-danger" id="resetAppButton" disabled>Reset Application Data</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">WARNING: This will permanently delete your account!</p>
                    <p>This action cannot be undone. All your data will be permanently removed.</p>
                    <div class="mb-3">
                        <label for="deletePassword" class="form-label">Enter your password to confirm:</label>
                        <input type="password" id="deletePassword" class="form-control" placeholder="Enter your password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete_account.php" method="post">
                        <input type="hidden" name="confirm_delete" value="1">
                        <input type="password" name="password" id="hiddenPassword" style="display:none;">
                        <button type="submit" class="btn btn-danger" id="deleteAccountButton">Delete My Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab management with URL hash
    let url = document.location.toString();
    if (url.match('#')) {
        const tabId = url.split('#')[1];
        const tabEl = document.querySelector(`a[href="#${tabId}"]`);
        if (tabEl) {
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
        }
    }
    
    // Handle tab navigation
    document.querySelectorAll('.list-group-item[data-bs-toggle="list"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (e) {
            history.pushState(null, null, '#' + e.target.getAttribute('href').substr(1));
        });
    });
    
    // Reset confirmation
    const resetConfirmInput = document.getElementById('resetConfirmation');
    const resetAppButton = document.getElementById('resetAppButton');
    
    if (resetConfirmInput && resetAppButton) {
        resetConfirmInput.addEventListener('input', function() {
            resetAppButton.disabled = this.value !== 'RESET';
        });
    }
    
    // Delete account password transfer
    const deletePassword = document.getElementById('deletePassword');
    const hiddenPassword = document.getElementById('hiddenPassword');
    const deleteAccountButton = document.getElementById('deleteAccountButton');
    
    if (deletePassword && hiddenPassword && deleteAccountButton) {
        deletePassword.addEventListener('input', function() {
            hiddenPassword.value = this.value;
            deleteAccountButton.disabled = this.value.length === 0;
        });
        
        // Initialize button state
        deleteAccountButton.disabled = true;
    }
});
    </script>
</body>
</html>