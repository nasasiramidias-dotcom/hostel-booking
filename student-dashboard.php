<?php
// Redirector to make direct navigation stable for students.
// Some links/buttons may incorrectly point to this file, causing 404.
// This ensures students always land on the actual dashboard.

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== 'student') {
    header('Location: dashboards/' . $_SESSION['role'] . '.php');
    exit();
}

header('Location: dashboards/student.php');
exit();

