<?php
// ไฟล์สำหรับลบ token ที่หมดอายุ
// รัน cron job ทุก 1 ชั่วโมง: 0 * * * * php /path/to/cleanup_expired_tokens.php

require_once "config.php";

$sql = "DELETE FROM password_resets WHERE expires_at < NOW()";
if (mysqli_query($conn, $sql)) {
    $deleted = mysqli_affected_rows($conn);
    echo date('Y-m-d H:i:s') . " - Deleted {$deleted} expired tokens\n";
} else {
    echo date('Y-m-d H:i:s') . " - Error: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>