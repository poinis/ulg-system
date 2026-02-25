<?php
// กำหนด Timezone เป็นเวลาไทย
date_default_timezone_set('Asia/Bangkok');

$host = 'localhost';
$user = 'cmbase';
$pass = '#wmIYH3wazaa';
$db = 'cmbase';
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>