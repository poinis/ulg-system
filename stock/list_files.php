<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

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
    
    if (!$sftp->chdir($remote_directory)) {
        throw new Exception("ไม่สามารถเข้าถึงโฟลเดอร์");
    }
    
    $files = $sftp->nlist();
    $matched_files = [];
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        
        if (preg_match('/^stock\s*online\s*(\d{8})\.CSV$/i', $file, $matches)) {
            $date_str = $matches[1];
            $formatted_date = substr($date_str, 0, 4) . '-' . 
                             substr($date_str, 4, 2) . '-' . 
                             substr($date_str, 6, 2);
            
            $matched_files[] = [
                'name' => $file,
                'date' => $formatted_date,
                'date_sort' => $date_str
            ];
        }
    }
    
    // เรียงลำดับตามวันที่ใหม่สุดก่อน
    usort($matched_files, function($a, $b) {
        return strcmp($b['date_sort'], $a['date_sort']);
    });
    
    echo json_encode([
        'success' => true,
        'files' => $matched_files,
        'total' => count($matched_files)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>