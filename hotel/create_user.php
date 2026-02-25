<?php
// สร้าง hash password สำหรับผู้ใช้ทดสอบ
$passwords = [
    'admin123' => password_hash('admin123', PASSWORD_DEFAULT),

];

echo "Hash Passwords:\n\n";
foreach ($passwords as $plain => $hash) {
    echo "Password: $plain\n";
    echo "Hash: $hash\n\n";
}

// สร้าง SQL INSERT
echo "\n\n--- SQL สำหรับ INSERT ---\n\n";
echo "INSERT INTO users (username, password, name, role, department) VALUES\n";
echo "('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'ผู้ดูแลระบบ', 'admin', 'IT'),\n";

?>