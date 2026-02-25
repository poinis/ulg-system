<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(600);

if (!isset($_POST['filenames'])) {
    die('ไม่พบรายการไฟล์');
}

$filenames = json_decode($_POST['filenames'], true);

if (empty($filenames)) {
    die('ไม่มีไฟล์ที่เลือก');
}

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
    
    // สร้างไฟล์ ZIP
    $zip = new ZipArchive();
    $zip_filename = 'stock_files_' . date('Ymd_His') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        throw new Exception("ไม่สามารถสร้างไฟล์ ZIP ได้");
    }
    
    foreach ($filenames as $filename) {
        $remote_file = $remote_directory . $filename;
        $content = $sftp->get($remote_file);
        
        if ($content !== false) {
            $zip->addFromString($filename, $content);
        }
    }
    
    $zip->close();
    
    // ส่งไฟล์ ZIP ให้ดาวน์โหลด
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    
    readfile($zip_path);
    unlink($zip_path);
    exit;
    
} catch (Exception $e) {
    die('เกิดข้อผิดพลาด: ' . $e->getMessage());
}
?>
```

**โครงสร้างไฟล์ทั้งหมด:**
```
your-project/
├── index.html (ไฟล์ HTML ที่สร้างใน artifact)
├── list_files.php
├── download_file.php
├── download_multiple.php
├── phpseclib-3.0.47/
│   └── phpseclib/
└── stocktoday/