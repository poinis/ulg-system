<?php
// api/review.php - Review Submission API
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';
require_once '../includes/functions.php';

// Check login & admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid method'], 405);
}

$submissionId = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$rejectReason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : null;

if (!$submissionId || !in_array($action, ['approve', 'reject'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters']);
}

$status = ($action === 'approve') ? 'approved' : 'rejected';
$result = reviewSubmission($conn, $submissionId, $status, $_SESSION['user_id'], $rejectReason);

jsonResponse($result);
