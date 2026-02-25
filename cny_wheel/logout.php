<?php
session_start();
$_SESSION = array();
session_destroy();

// กำหนด URL ปลายทางที่ต้องการให้ไปหลังจาก Logout
header("Location: https://weedjai.com"); 
exit;
?>