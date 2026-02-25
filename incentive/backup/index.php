<?php
// index.php - Redirect to appropriate page
session_start();

if (isset($_SESSION['user_id'])) {
    // Already logged in
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: checklist.php');
    }
} else {
    // Not logged in
    header('Location: login.php');
}
exit;
