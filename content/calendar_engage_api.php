<?php
/**
 * Calendar Engage API
 * 
 * Endpoints:
 * - GET  /calendar_engage_api.php?action=list
 * - GET  /calendar_engage_api.php?action=get&event_id={id}
 * - POST /calendar_engage_api.php?action=create
 * - PUT  /calendar_engage_api.php?action=update
 * - DELETE /calendar_engage_api.php?action=delete&event_id={id}
 */

require_once "config.php";

// ตั้งค่า Header
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// จัดการ OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ฟังก์ชันส่ง Response
function sendResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// ตรวจสอบ API Key (ถ้าต้องการ)
function validateApiKey() {
    $headers = getallheaders();
    $api_key = $headers['Authorization'] ?? $_GET['api_key'] ?? '';
    
    // ตัวอย่าง: ตรวจสอบ API Key
    // $valid_keys = ['your-secret-api-key-here'];
    // if (!in_array($api_key, $valid_keys)) {
    //     sendResponse(false, null, 'Invalid API key', 401);
    // }
    
    return true;
}

// เริ่มต้น API
validateApiKey();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        
        // ===============================================
        // GET: ดึงรายการ Engage ทั้งหมด
        // ===============================================
        case 'list':
            if ($method !== 'GET') {
                sendResponse(false, null, 'Method not allowed', 405);
            }
            
            $status = $_GET['status'] ?? 'all'; // all, pending, completed
            $month = $_GET['month'] ?? date('Y-m');
            
            $sql = "SELECT 
                        e.id,
                        e.job_title,
                        e.category,
                        e.assignee,
                        e.post_date,
                        e.engage_date,
                        e.engage_status,
                        eng.reach,
                        eng.impressions,
                        eng.likes,
                        eng.comments,
                        eng.shares,
                        eng.saves,
                        eng.note,
                        eng.updated_by,
                        eng.updated_at
                    FROM calendar_events e
                    LEFT JOIN calendar_engage eng ON e.id = eng.event_id
                    WHERE e.engage_date IS NOT NULL";
            
            if ($status !== 'all') {
                $sql .= " AND e.engage_status = ?";
            }
            
            if (!empty($month)) {
                $sql .= " AND e.engage_date LIKE ?";
            }
            
            $sql .= " ORDER BY e.engage_date ASC";
            
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($status !== 'all' && !empty($month)) {
                $month_pattern = $month . '%';
                mysqli_stmt_bind_param($stmt, "ss", $status, $month_pattern);
            } elseif ($status !== 'all') {
                mysqli_stmt_bind_param($stmt, "s", $status);
            } elseif (!empty($month)) {
                $month_pattern = $month . '%';
                mysqli_stmt_bind_param($stmt, "s", $month_pattern);
            }
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $engages = array();
            while ($row = mysqli_fetch_assoc($result)) {
                $engages[] = $row;
            }
            
            mysqli_stmt_close($stmt);
            sendResponse(true, $engages, 'Engage list retrieved successfully');
            break;
        
        // ===============================================
        // GET: ดึงข้อมูล Engage ตาม event_id
        // ===============================================
        case 'get':
            if ($method !== 'GET') {
                sendResponse(false, null, 'Method not allowed', 405);
            }
            
            $event_id = intval($_GET['event_id'] ?? 0);
            if ($event_id <= 0) {
                sendResponse(false, null, 'Invalid event_id', 400);
            }
            
            $sql = "SELECT 
                        e.id,
                        e.job_title,
                        e.category,
                        e.assignee,
                        e.post_date,
                        e.engage_date,
                        e.engage_status,
                        eng.reach,
                        eng.impressions,
                        eng.likes,
                        eng.comments,
                        eng.shares,
                        eng.saves,
                        eng.note,
                        eng.updated_by,
                        eng.updated_at
                    FROM calendar_events e
                    LEFT JOIN calendar_engage eng ON e.id = eng.event_id
                    WHERE e.id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $event_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $engage = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if (!$engage) {
                sendResponse(false, null, 'Engage not found', 404);
            }
            
            sendResponse(true, $engage, 'Engage retrieved successfully');
            break;
        
        // ===============================================
        // POST: สร้าง/อัปเดต Engage ใหม่
        // ===============================================
        case 'create':
        case 'update':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendResponse(false, null, 'Method not allowed', 405);
            }
            
            // รับข้อมูล JSON
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data) {
                $data = $_POST; // fallback to form data
            }
            
            $event_id = intval($data['event_id'] ?? 0);
            $reach = intval($data['reach'] ?? 0);
            $impressions = intval($data['impressions'] ?? 0);
            $likes = intval($data['likes'] ?? 0);
            $comments = intval($data['comments'] ?? 0);
            $shares = intval($data['shares'] ?? 0);
            $saves = intval($data['saves'] ?? 0);
            $note = mysqli_real_escape_string($conn, $data['note'] ?? '');
            $updated_by = mysqli_real_escape_string($conn, $data['updated_by'] ?? 'API');
            
            if ($event_id <= 0) {
                sendResponse(false, null, 'Invalid event_id', 400);
            }
            
            // ตรวจสอบว่ามี Engage อยู่แล้วหรือไม่
            $sql_check = "SELECT id FROM calendar_engage WHERE event_id = ?";
            $stmt_check = mysqli_prepare($conn, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "i", $event_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            $engage_exists = mysqli_stmt_num_rows($stmt_check) > 0;
            mysqli_stmt_close($stmt_check);
            
            if ($engage_exists) {
                // อัปเดต
                $sql = "UPDATE calendar_engage 
                        SET reach = ?, impressions = ?, likes = ?, comments = ?, 
                            shares = ?, saves = ?, note = ?, updated_by = ?, updated_at = NOW()
                        WHERE event_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiiiisssi", 
                    $reach, $impressions, $likes, $comments, $shares, $saves, $note, $updated_by, $event_id);
            } else {
                // สร้างใหม่
                $engage_date = date('Y-m-d');
                $sql = "INSERT INTO calendar_engage 
                        (event_id, engage_date, reach, impressions, likes, comments, 
                         shares, saves, note, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "isiiiiiiss", 
                    $event_id, $engage_date, $reach, $impressions, $likes, $comments, 
                    $shares, $saves, $note, $updated_by);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                // อัปเดตสถานะ engage_status
                $sql_update = "UPDATE calendar_events SET engage_status = 'completed' WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "i", $event_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
                
                mysqli_stmt_close($stmt);
                
                sendResponse(true, [
                    'event_id' => $event_id,
                    'reach' => $reach,
                    'impressions' => $impressions,
                    'likes' => $likes,
                    'comments' => $comments,
                    'shares' => $shares,
                    'saves' => $saves
                ], $engage_exists ? 'Engage updated successfully' : 'Engage created successfully');
            } else {
                mysqli_stmt_close($stmt);
                sendResponse(false, null, 'Failed to save engage: ' . mysqli_error($conn), 500);
            }
            break;
        
        // ===============================================
        // DELETE: ลบ Engage
        // ===============================================
        case 'delete':
            if ($method !== 'DELETE' && $method !== 'POST') {
                sendResponse(false, null, 'Method not allowed', 405);
            }
            
            $event_id = intval($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
            if ($event_id <= 0) {
                sendResponse(false, null, 'Invalid event_id', 400);
            }
            
            $sql = "DELETE FROM calendar_engage WHERE event_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $event_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // อัปเดตสถานะกลับเป็น pending
                $sql_update = "UPDATE calendar_events SET engage_status = 'pending' WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "i", $event_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
                
                mysqli_stmt_close($stmt);
                sendResponse(true, ['event_id' => $event_id], 'Engage deleted successfully');
            } else {
                mysqli_stmt_close($stmt);
                sendResponse(false, null, 'Failed to delete engage', 500);
            }
            break;
        
        // ===============================================
        // สถิติ Engage
        // ===============================================
        case 'stats':
            if ($method !== 'GET') {
                sendResponse(false, null, 'Method not allowed', 405);
            }
            
            $month = $_GET['month'] ?? date('Y-m');
            
            // นับจำนวน
            $sql_stats = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN engage_status = 'pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN engage_status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN engage_date < CURDATE() AND engage_status = 'pending' THEN 1 ELSE 0 END) as overdue
                          FROM calendar_events 
                          WHERE engage_date IS NOT NULL";
            
            if (!empty($month)) {
                $sql_stats .= " AND engage_date LIKE ?";
                $stmt = mysqli_prepare($conn, $sql_stats);
                $month_pattern = $month . '%';
                mysqli_stmt_bind_param($stmt, "s", $month_pattern);
            } else {
                $stmt = mysqli_prepare($conn, $sql_stats);
            }
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $stats = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            // รวมข้อมูล Engage
            $sql_totals = "SELECT 
                            SUM(reach) as total_reach,
                            SUM(impressions) as total_impressions,
                            SUM(likes) as total_likes,
                            SUM(comments) as total_comments,
                            SUM(shares) as total_shares,
                            SUM(saves) as total_saves
                           FROM calendar_engage";
            
            $result_totals = mysqli_query($conn, $sql_totals);
            $totals = mysqli_fetch_assoc($result_totals);
            
            sendResponse(true, [
                'counts' => $stats,
                'totals' => $totals
            ], 'Statistics retrieved successfully');
            break;
        
        default:
            sendResponse(false, null, 'Invalid action', 400);
    }
    
} catch (Exception $e) {
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>