<?php
// user_edit.php และ user_add.php ใช้โค้ดเดียวกัน
// ไฟล์นี้เป็นเพียง redirect
header("Location: user_add.php" . (isset($_GET['id']) ? "?id=" . $_GET['id'] : ""));
exit;
?>