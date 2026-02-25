<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300);

if (!isset($_POST['filename'])) {
    die('ไม่พบชื่อไฟล์');
}

$filename = $_POST['filename'];

// autoload phpseclib
$phpseclib_base = __DIR__ . '/phpseclib-3.0.47/phpseclib/';

spl_autoload_register(function ($class) use ($phpseclib_base) {
    if (strpos($class, 'phpseclib3\\') === 0) {
        $relative_class = substr($class, 11);
        $file = $phpseclib_base . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

use phpseclib3\Net\SFTP;

// ข้อมูลการเชื่อมต่อ
$sftp_host = 'sftp.integratedretail.com';
$sftp_port = 2211;
$sftp_username = 'ULG_TEAM';
$sftp_password = 'Ui8c\o1z94YH';
$remote_directory = '/stocktoday/';

try {
    $sftp = new SFTP($sftp_host, $sftp_port, 30);
    
    if (!$sftp->login($sftp_username, $sftp_password)) {
        throw new Exception("การ login ล้มเหลว");
    }
    
    $remote_file = $remote_directory . $filename;
    $content = $sftp->get($remote_file);
    
    if ($content === false) {
        throw new Exception("ไม่สามารถดาวน์โหลดไฟล์ได้");
    }
    
    // ส่งไฟล์ให้ browser ดาวน์โหลด
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    echo $content;
    exit;
    
} catch (Exception $e) {
    die('เกิดข้อผิดพลาด: ' . $e->getMessage());
}
?>