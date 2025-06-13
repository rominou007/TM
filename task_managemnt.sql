-- Create the database
CREATE DATABASE IF NOT EXISTS task_management;
USE task_management;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('Planning', 'In Progress', 'Completed') DEFAULT 'Planning',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Tasks table
CREATE TABLE IF NOT EXISTS tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('To Do', 'In Progress', 'Completed') DEFAULT 'To Do',
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Task comments table
CREATE TABLE IF NOT EXISTS task_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- User settings table
CREATE TABLE IF NOT EXISTS user_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    theme VARCHAR(20) DEFAULT 'light',
    notifications BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Task tags table
CREATE TABLE IF NOT EXISTS tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Task-Tag relationship (many-to-many)
CREATE TABLE IF NOT EXISTS task_tags (
    task_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (task_id, tag_id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE
);

-- Add sample data (optional - for testing)
-- Insert a test user (password is 'password123' hashed)
INSERT INTO users (username, password, email) VALUES 
('testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'test@example.com');

-- Insert sample projects
INSERT INTO projects (user_id, name, description, status) VALUES
(1, 'Website Redesign', 'Overhaul the company website with modern design', 'In Progress'),
(1, 'Mobile App Development', 'Create a companion mobile app for our service', 'Planning'),
(1, 'Bug Fixes', 'Address reported software bugs', 'In Progress');

-- Insert sample tasks
INSERT INTO tasks (project_id, user_id, title, description, status, priority, due_date) VALUES
(1, 1, 'Design Homepage', 'Create wireframes for the new homepage', 'In Progress', 'High', DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)),
(1, 1, 'Implement Contact Form', 'Add form validation and email functionality', 'To Do', 'Medium', DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY)),
(2, 1, 'Research Frameworks', 'Compare React Native vs Flutter for development', 'Completed', 'High', DATE_ADD(CURRENT_DATE, INTERVAL -3 DAY)),
(3, 1, 'Fix Login Issue', 'Address timeout problem on login screen', 'To Do', 'High', DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY));

-- Insert sample tags
INSERT INTO tags (name, user_id) VALUES
('urgent', 1),
('frontend', 1),
('backend', 1),
('design', 1);

-- Tag some tasks
INSERT INTO task_tags (task_id, tag_id) VALUES
(1, 4), -- Design Homepage - design
(1, 2), -- Design Homepage - frontend
(2, 2), -- Implement Contact Form - frontend
(2, 3), -- Implement Contact Form - backend
(4, 1); -- Fix Login Issue - urgent