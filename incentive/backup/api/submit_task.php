<?php
// api/submit_task.php - Submit Task API
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
}

// Check POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$userId = $_SESSION['user_id'];
$branchId = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
$taskTypeId = isset($_POST['task_type_id']) ? (int) $_POST['task_type_id'] : 0;
$linkUrl = isset($_POST['link_url']) ? trim($_POST['link_url']) : null;

// Validate
if (!$branchId || !$taskTypeId) {
    jsonResponse(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
}

// Get task type info
$stmt = $conn->prepare("SELECT * FROM incentive_task_types WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $taskTypeId);
$stmt->execute();
$taskType = $stmt->get_result()->fetch_assoc();

if (!$taskType) {
    jsonResponse(['success' => false, 'message' => 'ไม่พบประเภทงานนี้']);
}

// Check if already submitted today
if (hasSubmittedToday($conn, $userId, $taskTypeId)) {
    jsonResponse(['success' => false, 'message' => 'คุณส่งงานประเภทนี้ไปแล้ววันนี้']);
}

$imagePath = null;

// Handle based on input type
if ($taskType['input_type'] === 'link') {
    // Validate link
    if (empty($linkUrl)) {
        jsonResponse(['success' => false, 'message' => 'กรุณากรอก Link']);
    }
    
    // Basic URL validation
    if (!filter_var($linkUrl, FILTER_VALIDATE_URL)) {
        jsonResponse(['success' => false, 'message' => 'Link ไม่ถูกต้อง']);
    }
    
} else {
    // Image upload
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'กรุณาอัปโหลดรูปภาพ']);
    }
    
    $upload = uploadScreenshot($_FILES['image'], $userId);
    if (!$upload['success']) {
        jsonResponse($upload);
    }
    
    $imagePath = $upload['path'];
    $linkUrl = null;
}

// Submit task
$result = submitTask($conn, $userId, $branchId, $taskTypeId, $linkUrl, $imagePath);
jsonResponse($result);
