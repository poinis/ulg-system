<?php
// includes/functions.php - Helper Functions

// ================================
// กำหนด Role ที่เข้า Admin ได้ที่นี่
// ================================
define('ADMIN_ROLES', ['admin', 'owner', 'approve', 'area']);

/**
 * Check if user is admin (from database, not session)
 * รองรับหลาย role
 */
function isAdmin($sessionOrUserId) {
    global $conn;
    
    // Get user_id
    if (is_array($sessionOrUserId)) {
        $userId = $sessionOrUserId['user_id'] ?? $sessionOrUserId['id'] ?? null;
    } else {
        $userId = $sessionOrUserId;
    }
    
    if (!$userId) return false;
    
    // Check from database directly
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // เช็คว่า role อยู่ใน ADMIN_ROLES หรือไม่
        return in_array($row['role'], ADMIN_ROLES);
    }
    
    return false;
}

/**
 * Get current user info from database
 */
function getCurrentUser($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get setting value by key
 */
function getSetting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM incentive_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Get all active task types
 */
function getTaskTypes($conn) {
    $result = $conn->query("SELECT * FROM incentive_task_types WHERE is_active = 1 ORDER BY sort_order");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all active branches
 */
function getBranches($conn) {
    $result = $conn->query("SELECT * FROM incentive_branches WHERE is_active = 1 ORDER BY branch_name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get user's branch
 */
function getUserBranch($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT b.* FROM incentive_branches b
        JOIN incentive_user_branches ub ON b.id = ub.branch_id
        WHERE ub.user_id = ? AND b.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Set user's branch
 */
function setUserBranch($conn, $userId, $branchId) {
    // Delete existing
    $stmt = $conn->prepare("DELETE FROM incentive_user_branches WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Insert new
    $stmt = $conn->prepare("INSERT INTO incentive_user_branches (user_id, branch_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $branchId);
    return $stmt->execute();
}

/**
 * Get today's submissions for a user
 */
function getTodaySubmissions($conn, $userId, $date = null) {
    $date = $date ?? date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT s.*, t.task_code, t.task_name_th, t.points as task_points
        FROM incentive_submissions s
        JOIN incentive_task_types t ON s.task_type_id = t.id
        WHERE s.user_id = ? AND s.submission_date = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->bind_param("is", $userId, $date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check if user already submitted a task type today
 */
function hasSubmittedToday($conn, $userId, $taskTypeId, $date = null) {
    $date = $date ?? date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as cnt FROM incentive_submissions 
        WHERE user_id = ? AND task_type_id = ? AND submission_date = ?
    ");
    $stmt->bind_param("iis", $userId, $taskTypeId, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['cnt'] > 0;
}

/**
 * Submit a task
 */
function submitTask($conn, $userId, $branchId, $taskTypeId, $linkUrl = null, $imagePath = null) {
    $date = date('Y-m-d');
    
    // Check if already submitted today
    if (hasSubmittedToday($conn, $userId, $taskTypeId, $date)) {
        return ['success' => false, 'message' => 'คุณส่งงานประเภทนี้ไปแล้ววันนี้'];
    }
    
    $stmt = $conn->prepare("
        INSERT INTO incentive_submissions (user_id, branch_id, task_type_id, submission_date, link_url, image_path)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiisss", $userId, $branchId, $taskTypeId, $date, $linkUrl, $imagePath);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'ส่งงานสำเร็จ รอการตรวจสอบ', 'id' => $conn->insert_id];
    }
    return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error];
}

/**
 * Approve/Reject submission
 */
function reviewSubmission($conn, $submissionId, $status, $reviewerId, $rejectReason = null) {
    // Get task points
    $stmt = $conn->prepare("
        SELECT s.*, t.points FROM incentive_submissions s
        JOIN incentive_task_types t ON s.task_type_id = t.id
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $submissionId);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        return ['success' => false, 'message' => 'ไม่พบรายการ'];
    }
    
    $pointsEarned = ($status === 'approved') ? $submission['points'] : 0;
    
    $stmt = $conn->prepare("
        UPDATE incentive_submissions 
        SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reject_reason = ?, points_earned = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sisii", $status, $reviewerId, $rejectReason, $pointsEarned, $submissionId);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => $status === 'approved' ? 'อนุมัติสำเร็จ' : 'ปฏิเสธสำเร็จ'];
    }
    return ['success' => false, 'message' => 'เกิดข้อผิดพลาด'];
}

/**
 * Get submissions for admin (with filters)
 */
function getSubmissions($conn, $filters = []) {
    $where = ["1=1"];
    $params = [];
    $types = "";
    
    if (!empty($filters['branch_id'])) {
        $where[] = "s.branch_id = ?";
        $params[] = $filters['branch_id'];
        $types .= "i";
    }
    
    if (!empty($filters['date'])) {
        $where[] = "s.submission_date = ?";
        $params[] = $filters['date'];
        $types .= "s";
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "s.submission_date >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "s.submission_date <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    if (!empty($filters['status'])) {
        $where[] = "s.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['year_month'])) {
        $where[] = "DATE_FORMAT(s.submission_date, '%Y-%m') = ?";
        $params[] = $filters['year_month'];
        $types .= "s";
    }
    
    $sql = "
        SELECT s.*, 
               t.task_code, t.task_name_th, t.points as task_points, t.input_type,
               b.branch_name, b.branch_code,
               u.name as user_name, u.username,
               r.name as reviewer_name
        FROM incentive_submissions s
        JOIN incentive_task_types t ON s.task_type_id = t.id
        JOIN incentive_branches b ON s.branch_id = b.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN users r ON s.reviewed_by = r.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY s.submission_date DESC, s.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get monthly points summary by branch
 */
function getMonthlyBranchSummary($conn, $yearMonth) {
    $stmt = $conn->prepare("
        SELECT 
            b.id as branch_id,
            b.branch_code,
            b.branch_name,
            COALESCE(SUM(CASE WHEN s.status = 'approved' THEN s.points_earned ELSE 0 END), 0) as total_points,
            COUNT(DISTINCT s.user_id) as active_users,
            COUNT(CASE WHEN s.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN s.status = 'approved' THEN 1 END) as approved_count,
            COUNT(CASE WHEN s.status = 'rejected' THEN 1 END) as rejected_count
        FROM incentive_branches b
        LEFT JOIN incentive_submissions s ON b.id = s.branch_id 
            AND DATE_FORMAT(s.submission_date, '%Y-%m') = ?
        WHERE b.is_active = 1
        GROUP BY b.id
        ORDER BY total_points DESC
    ");
    $stmt->bind_param("s", $yearMonth);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Calculate payroll for a month
 */
function calculatePayroll($conn, $yearMonth) {
    $targetPoints = (int) getSetting($conn, 'target_points', 100);
    $maxIncentive = (float) getSetting($conn, 'max_base_incentive', 2500);
    $budgetCap = (float) getSetting($conn, 'budget_cap_per_person', 2200);
    $trophyBonus = (float) getSetting($conn, 'trophy_bonus_per_person', 500);
    
    $branches = getMonthlyBranchSummary($conn, $yearMonth);
    $results = [];
    
    foreach ($branches as $branch) {
        $payoutRatio = min($branch['total_points'] / $targetPoints, 1.0);
        $baseIncentive = round($payoutRatio * $maxIncentive, 2);
        
        // Get trophy bonuses for this branch
        $stmt = $conn->prepare("
            SELECT trophy_type, bonus_per_person 
            FROM incentive_trophy_winners 
            WHERE branch_id = ? AND `year_month` = ?
        ");
        $stmt->bind_param("is", $branch['branch_id'], $yearMonth);
        $stmt->execute();
        $trophies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $trophyTotal = 0;
        $trophyList = [];
        foreach ($trophies as $t) {
            $trophyTotal += $t['bonus_per_person'];
            $trophyList[] = $t['trophy_type'];
        }
        
        $totalPayout = min($baseIncentive + $trophyTotal, $budgetCap + $trophyTotal);
        
        $results[] = [
            'branch_id' => $branch['branch_id'],
            'branch_code' => $branch['branch_code'],
            'branch_name' => $branch['branch_name'],
            'total_points' => $branch['total_points'],
            'payout_ratio' => $payoutRatio,
            'payout_percent' => round($payoutRatio * 100, 1),
            'base_incentive' => $baseIncentive,
            'trophy_bonus' => $trophyTotal,
            'trophy_list' => $trophyList,
            'total_payout' => $totalPayout,
            'active_users' => $branch['active_users'],
            'pending_count' => $branch['pending_count']
        ];
    }
    
    return $results;
}

/**
 * Get user's monthly points
 */
function getUserMonthlyPoints($conn, $userId, $yearMonth) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(points_earned), 0) as total_points
        FROM incentive_submissions
        WHERE user_id = ? AND DATE_FORMAT(submission_date, '%Y-%m') = ? AND status = 'approved'
    ");
    $stmt->bind_param("is", $userId, $yearMonth);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) $row['total_points'];
}

/**
 * Get branch's monthly points
 */
function getBranchMonthlyPoints($conn, $branchId, $yearMonth) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(points_earned), 0) as total_points
        FROM incentive_submissions
        WHERE branch_id = ? AND DATE_FORMAT(submission_date, '%Y-%m') = ? AND status = 'approved'
    ");
    $stmt->bind_param("is", $branchId, $yearMonth);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) $row['total_points'];
}

/**
 * Award trophy to a branch
 */
function awardTrophy($conn, $branchId, $yearMonth, $trophyType, $awardedBy, $notes = null) {
    $bonusPerPerson = (float) getSetting($conn, 'trophy_bonus_per_person', 500);
    
    $stmt = $conn->prepare("
        INSERT INTO incentive_trophy_winners (branch_id, `year_month`, trophy_type, bonus_per_person, notes, awarded_by)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE bonus_per_person = VALUES(bonus_per_person), notes = VALUES(notes), awarded_by = VALUES(awarded_by)
    ");
    $stmt->bind_param("issdsi", $branchId, $yearMonth, $trophyType, $bonusPerPerson, $notes, $awardedBy);
    return $stmt->execute();
}

/**
 * Remove trophy from a branch
 */
function removeTrophy($conn, $branchId, $yearMonth, $trophyType) {
    $stmt = $conn->prepare("
        DELETE FROM incentive_trophy_winners 
        WHERE branch_id = ? AND `year_month` = ? AND trophy_type = ?
    ");
    $stmt->bind_param("iss", $branchId, $yearMonth, $trophyType);
    return $stmt->execute();
}

/**
 * Get trophy winners for a month
 */
function getTrophyWinners($conn, $yearMonth) {
    $stmt = $conn->prepare("
        SELECT tw.*, b.branch_name, b.branch_code
        FROM incentive_trophy_winners tw
        JOIN incentive_branches b ON tw.branch_id = b.id
        WHERE tw.`year_month` = ?
        ORDER BY tw.trophy_type
    ");
    $stmt->bind_param("s", $yearMonth);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get count of users in a branch
 */
function getBranchUserCount($conn, $branchId) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM incentive_user_branches WHERE branch_id = ?");
    $stmt->bind_param("i", $branchId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) $row['cnt'];
}

/**
 * Upload image and return path
 */
function uploadScreenshot($file, $userId) {
    $uploadDir = __DIR__ . '/../uploads/screenshots/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'ไฟล์ต้องเป็นรูปภาพเท่านั้น (JPG, PNG, GIF, WEBP)'];
    }
    
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'ไฟล์ต้องมีขนาดไม่เกิน 5MB'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = date('Ymd_His') . '_' . $userId . '_' . uniqid() . '.' . $ext;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => 'uploads/screenshots/' . $filename];
    }
    
    return ['success' => false, 'message' => 'อัปโหลดไฟล์ไม่สำเร็จ'];
}

/**
 * Format Thai date
 */
function thaiDate($date, $format = 'short') {
    $thaiMonths = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $thaiMonthsFull = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $ts = is_string($date) ? strtotime($date) : $date;
    $day = date('j', $ts);
    $month = (int) date('n', $ts);
    $year = date('Y', $ts) + 543;
    
    if ($format === 'full') {
        return "$day {$thaiMonthsFull[$month]} $year";
    }
    return "$day {$thaiMonths[$month]} $year";
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}