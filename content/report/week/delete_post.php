<?php
/**
 * Delete Post Handler
 * จัดการการลบข้อมูล
 */

require_once 'SocialMediaImporter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

try {
    $importer = new SocialMediaImporter();
    
    if (is_array($input['id'])) {
        // ลบหลายรายการ
        $result = $importer->deletePosts($input['id']);
        $message = 'ลบข้อมูล ' . count($input['id']) . ' รายการสำเร็จ';
    } else {
        // ลบรายการเดียว
        $result = $importer->deletePost($input['id']);
        $message = 'ลบข้อมูลสำเร็จ';
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        throw new Exception('ไม่สามารถลบข้อมูลได้');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>