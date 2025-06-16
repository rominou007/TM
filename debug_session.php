<?php
// filepath: c:\xampp\htdocs\php\TM\debug_session.php
session_start();

echo "<h1>Session Debug Information</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Clear error messages if needed
if (isset($_GET['clear']) && $_GET['clear'] === 'errors') {
    unset($_SESSION['error']);
    unset($_SESSION['success']);
    echo "<p>Error and success messages cleared from session.</p>";
}

echo "<p><a href='debug_session.php?clear=errors'>Clear error messages</a></p>";
echo "<p><a href='tasks.php'>Back to Tasks</a></p>";