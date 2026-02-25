<?php
/**
 * สคริปต์สำหรับแปลงรหัสผ่าน plain text เป็น hashed password
 * รันเพียงครั้งเดียวเท่านั้น!
 * 
 * คำเตือน: สคริปต์นี้จะอัพเดทรหัสผ่านทั้งหมดในระบบ
 * กรุณาสำรองข้อมูลก่อนรัน
 */

require_once "config.php";

echo "กำลังแปลงรหัสผ่าน...\n\n";

// ดึงผู้ใช้ทั้งหมดที่มีรหัสผ่านแบบ plain text
// (รหัสผ่าน hash จะเริ่มต้นด้วย $2y$ หรือ $2a$)
$sql = "SELECT id, username, password FROM users WHERE password NOT LIKE '$2y$%' AND password NOT LIKE '$2a$%'";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Error: " . mysqli_error($conn) . "\n");
}

$count = 0;
$errors = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $user_id = $row['id'];
    $username = $row['username'];
    $plain_password = $row['password'];
    
    // สร้าง hash ใหม่
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    // อัพเดทในฐานข้อมูล
    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo "✓ แปลงรหัสผ่านสำหรับ user: {$username} (ID: {$user_id})\n";
            $count++;
        } else {
            echo "✗ ไม่สามารถแปลงรหัสผ่านสำหรับ user: {$username} (ID: {$user_id})\n";
            $errors++;
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo "✗ Error preparing statement for user: {$username}\n";
        $errors++;
    }
}

mysqli_close($conn);

echo "\n===========================================\n";
echo "สรุปผลการแปลง:\n";
echo "- แปลงสำเร็จ: {$count} users\n";
echo "- ล้มเหลว: {$errors} users\n";
echo "===========================================\n";

if ($count > 0) {
    echo "\n✓ การแปลงรหัสผ่านเสร็จสมบูรณ์!\n";
    echo "กรุณาอัพเดทไฟล์ index.php ให้ใช้ password_verify() แทน\n";
} else {
    echo "\nไม่มีรหัสผ่านที่ต้องแปลง\n";
}
?>