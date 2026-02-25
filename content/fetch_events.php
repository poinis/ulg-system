<?php
require_once "config.php";

header('Content-Type: application/json');

// เปลี่ยนจากแสดงเฉพาะ 3 สถานะ เป็นแสดงทุกสถานะ (เพิ่ม check_ad)
$sql = "SELECT id, job_title, platform, status, due_date, check_ad FROM content_brief WHERE status IN ('completed','in_progress','pending','need_update','need_info','approved')";
$result = mysqli_query($conn, $sql);

$events = [];

while ($row = mysqli_fetch_assoc($result)) {
    $color = '#3788d8'; // default blue
    switch ($row['status']) {
        case 'completed': 
            $color = '#2ecc71'; // เขียว - เสร็จสิ้น
            break;
        case 'approved': 
            $color = '#3498db'; // น้ำเงิน - อนุมัติแล้ว
            break;
        case 'in_progress': 
            $color = '#f39c12'; // ส้ม - กำลังดำเนินการ
            break;
        case 'pending': 
            $color = '#e74c3c'; // แดง - รออนุมัติ
            break;
        case 'need_update': 
            $color = '#994a41ff'; // แดง - รอแก้ไข
            break;
        case 'need_info': 
            $color = '#c0392b'; // แดงเข้ม - ตีกลับ/สอบถาม
            break;
    }
    
    // สร้างชื่องาน โดยเพิ่มดาว ⭐ ถ้ามี check_ad
    $title = $row['job_title'] . " (" . $row['platform'] . ")";
    
    if (!empty($row['check_ad'])) {
        $title = "⭐ " . $title;
    }
    
    $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $row['due_date'],
        'color' => $color,
        'url' => "detail_content.php?id=" . $row['id']  // ลิงก์ไปยังหน้ารายละเอียด
    ];
}

echo json_encode($events);
?>