<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);

require_once "../config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: ../index.php");
    exit;
}

$username = $_SESSION['username'];
$user_name = '';
$role = '';

// ดึงข้อมูล user
$sql_user = "SELECT name, role FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $user_name, $role);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// ตรวจสอบสิทธิ์ (เฉพาะ admin)
if ($role !== 'admin') {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

$success = "";
$error = "";
$details = [];

// ชื่อ Database เดิมที่ใช้ร่วมกัน
$SHARED_DATABASE = defined('DB_NAME') ? DB_NAME : 'cmbase';

// ตารางที่ต้องสร้างให้ Project ใหม่ (ไม่รวม users ที่ใช้ร่วมกัน)
$TABLES_TO_CLONE = [
    'calendar_events',
    'calendar_engage', 
    'tiktok_posts'
];

// ฟังก์ชันสร้าง Tables ใหม่ใน Database เดิม (แก้ไข - ไม่คัดลอกข้อมูล)
function createProjectTables($project_prefix, $template_prefix, $conn, $shared_db, $tables_to_clone) {
    $log = [];
    
    try {
        // เลือกใช้ Database เดิม
        if (!mysqli_select_db($conn, $shared_db)) {
            return ['success' => false, 'message' => "ไม่สามารถเชื่อมต่อ Database: $shared_db"];
        }
        
        $log[] = "✓ เชื่อมต่อ Database: $shared_db";
        $log[] = "ℹ️ ใช้ตาราง 'users' ร่วมกัน (ไม่สร้างใหม่)";
        $log[] = "ℹ️ สร้างเฉพาะโครงสร้างตาราง (ไม่คัดลอกข้อมูล)";
        
        // นับจำนวนตารางที่จะสร้าง
        $tables_to_create = [];
        foreach ($tables_to_clone as $table_name) {
            $template_table = $template_prefix . '_' . $table_name;
            $new_table = $project_prefix . '_' . $table_name;
            
            // ตรวจสอบว่าตาราง template มีอยู่หรือไม่
            $sql_check_template = "SHOW TABLES LIKE '$template_table'";
            $result_check = mysqli_query($conn, $sql_check_template);
            
            if ($result_check && mysqli_num_rows($result_check) > 0) {
                $tables_to_create[] = [
                    'template' => $template_table,
                    'new' => $new_table
                ];
            } else {
                $log[] = "⚠ ไม่พบตาราง Template: $template_table - ข้าม";
            }
            
            if ($result_check) {
                mysqli_free_result($result_check);
            }
        }
        
        if (empty($tables_to_create)) {
            return ['success' => false, 'message' => "ไม่พบตาราง Template ที่จะคัดลอก"];
        }
        
        $log[] = "✓ พบตาราง Template: " . count($tables_to_create) . " ตาราง";
        
        // คัดลอกแต่ละตาราง
        foreach ($tables_to_create as $table_info) {
            $template_table = $table_info['template'];
            $new_table = $table_info['new'];
            
            try {
                // ตรวจสอบว่าตารางใหม่มีอยู่แล้วหรือไม่
                $sql_check = "SHOW TABLES LIKE '$new_table'";
                $result_check = mysqli_query($conn, $sql_check);
                if ($result_check && mysqli_num_rows($result_check) > 0) {
                    mysqli_free_result($result_check);
                    $log[] = "⚠ ตาราง $new_table มีอยู่แล้ว - ข้าม";
                    continue;
                }
                if ($result_check) {
                    mysqli_free_result($result_check);
                }
                
                // ดึง CREATE TABLE statement
                $sql_create_table = "SHOW CREATE TABLE `$template_table`";
                $result_create = mysqli_query($conn, $sql_create_table);
                
                if (!$result_create) {
                    $log[] = "✗ ไม่สามารถอ่านโครงสร้างตาราง $template_table: " . mysqli_error($conn);
                    continue;
                }
                
                $row_create = mysqli_fetch_array($result_create);
                $create_statement = $row_create[1];
                mysqli_free_result($result_create);
                
                // แทนที่ชื่อตารางใน CREATE statement
                $create_statement = str_replace("`$template_table`", "`$new_table`", $create_statement);
                
                // แก้ไข Foreign Key Constraint Names
                // เปลี่ยนชื่อ constraint จาก template_xxx เป็น new_table_xxx
                $create_statement = preg_replace(
                    '/CONSTRAINT `' . preg_quote($template_prefix) . '_([^`]+)`/',
                    'CONSTRAINT `' . $project_prefix . '_$1`',
                    $create_statement
                );
                
                // สร้างตารางใหม่ (เฉพาะโครงสร้าง ไม่มีข้อมูล)
                if (!mysqli_query($conn, $create_statement)) {
                    $log[] = "✗ ไม่สามารถสร้างตาราง $new_table: " . mysqli_error($conn);
                    continue;
                }
                $log[] = "✓ สร้างตาราง: $new_table (เฉพาะโครงสร้าง)";
                
            } catch (Exception $e) {
                $log[] = "✗ Error ที่ตาราง $template_table: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true, 
            'message' => 'สร้างตารางสำเร็จ',
            'log' => $log,
            'table_count' => count($tables_to_create)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

// ฟังก์ชันสร้าง Daily Project ใหม่
function createDailyProject($project_name, $template_project, $conn, $shared_db, $tables_to_clone, $create_tables) {
    $details = [];
    $base_path = __DIR__;
    $template_path = $base_path . '/' . $template_project;
    $new_project_name = strtolower(str_replace(' ', '_', $project_name));
    $new_project_path = $base_path . '/' . $new_project_name;
    
    // ตรวจสอบว่า template มีอยู่จริง
    if (!is_dir($template_path)) {
        return ['success' => false, 'message' => "ไม่พบ Template Project: $template_project"];
    }
    
    // ตรวจสอบว่าชื่อ project ซ้ำหรือไม่
    if (is_dir($new_project_path)) {
        return ['success' => false, 'message' => "Project นี้มีอยู่แล้ว: $project_name"];
    }
    
    // สร้างตารางใหม่ใน Database เดิม (ถ้าเลือก)
    if ($create_tables) {
        try {
            $template_prefix = strtolower($template_project);
            $project_prefix = $new_project_name;
            
            $db_result = createProjectTables($project_prefix, $template_prefix, $conn, $shared_db, $tables_to_clone);
            
            if (!$db_result['success']) {
                return $db_result;
            }
            
            $details['database'] = [
                'database' => $shared_db,
                'template_prefix' => $template_prefix,
                'new_prefix' => $project_prefix,
                'log' => $db_result['log'] ?? [],
                'tables' => $db_result['table_count'] ?? 0,
                'tables_list' => $tables_to_clone
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสร้างตาราง: ' . $e->getMessage()];
        }
    }
    
    // สร้างโฟลเดอร์ใหม่
    if (!mkdir($new_project_path, 0755, true)) {
        return ['success' => false, 'message' => "ไม่สามารถสร้างโฟลเดอร์ได้"];
    }
    
    // คัดลอกไฟล์ทั้งหมดจาก template
    try {
        $files_copied = copyDirectory($template_path, $new_project_path, $template_project, $new_project_name, $shared_db);
        
        if ($files_copied === false) {
            return ['success' => false, 'message' => "เกิดข้อผิดพลาดในการคัดลอกไฟล์"];
        }
        
        $details['files'] = [
            'count' => $files_copied,
            'source' => $template_path,
            'target' => $new_project_path
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการคัดลอกไฟล์: ' . $e->getMessage()];
    }
    
    return [
        'success' => true, 
        'message' => "สร้าง Project สำเร็จ!",
        'project_url' => '/content/daily/' . $new_project_name . '/',
        'details' => $details
    ];
}

// ฟังก์ชันคัดลอกโฟลเดอร์และไฟล์ทั้งหมด
function copyDirectory($src, $dst, $old_name, $new_name, $shared_db) {
    $count = 0;
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                $sub_count = copyDirectory($src . '/' . $file, $dst . '/' . $file, $old_name, $new_name, $shared_db);
                if ($sub_count === false) return false;
                $count += $sub_count;
            } else {
                // อ่านไฟล์
                $content = file_get_contents($src . '/' . $file);
                
                // แทนที่ชื่อ project และ table prefix ในไฟล์
                $old_name_lower = strtolower($old_name);
                $new_name_lower = strtolower(str_replace(' ', '_', $new_name));
                $old_prefix = $old_name_lower . '_';
                $new_prefix = $new_name_lower . '_';
                
                // แทนที่ table prefix (เช่น topologie_content_brief → pronto_content_brief)
                $replacements = [
                    // Table prefixes
                    "`{$old_prefix}" => "`{$new_prefix}",
                    " {$old_prefix}" => " {$new_prefix}",
                    "FROM {$old_prefix}" => "FROM {$new_prefix}",
                    "FROM `{$old_prefix}" => "FROM `{$new_prefix}",
                    "INTO {$old_prefix}" => "INTO {$new_prefix}",
                    "INTO `{$old_prefix}" => "INTO `{$new_prefix}",
                    "TABLE {$old_prefix}" => "TABLE {$new_prefix}",
                    "TABLE `{$old_prefix}" => "TABLE `{$new_prefix}",
                    // Database name (ใช้ Database เดิม)
                    "define('DB_NAME', 'weedjai_{$old_name_lower}')" => "define('DB_NAME', '$shared_db')",
                    "define(\"DB_NAME\", \"weedjai_{$old_name_lower}\")" => "define(\"DB_NAME\", \"$shared_db\")",
                    // Project names
                    ucfirst($old_name) => ucfirst($new_name),
                    strtolower($old_name) => $new_name_lower,
                    strtoupper($old_name) => strtoupper($new_name),
                    str_replace('_', ' ', ucwords($old_name_lower, '_')) => ucwords(str_replace('_', ' ', $new_name_lower)),
                ];
                
                $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                
                // บันทึกไฟล์ใหม่
                if (file_put_contents($dst . '/' . $file, $content) !== false) {
                    $count++;
                } else {
                    return false;
                }
            }
        }
    }
    closedir($dir);
    return $count;
}

// ฟังก์ชันลบ Project และ Tables
function deleteProject($project_name, $conn, $shared_db, $delete_tables) {
    $base_path = __DIR__;
    $project_path = $base_path . '/' . strtolower(str_replace(' ', '_', $project_name));
    
    if (!is_dir($project_path)) {
        return ['success' => false, 'message' => "ไม่พบ Project นี้"];
    }
    
    // ป้องกันการลบ template
    if (in_array(strtolower($project_name), ['topologie', 'template'])) {
        return ['success' => false, 'message' => "ไม่สามารถลบ Template Project ได้"];
    }
    
    // ลบตารางที่มี prefix ของ project (ถ้าเลือก)
    $tables_deleted = 0;
    if ($delete_tables) {
        mysqli_select_db($conn, $shared_db);
        $project_prefix = strtolower(str_replace(' ', '_', $project_name));
        
        // ดึงรายการตารางที่มี prefix ของ project
        $sql_tables = "SHOW TABLES LIKE '{$project_prefix}_%'";
        $result = mysqli_query($conn, $sql_tables);
        
        if ($result) {
            while ($row = mysqli_fetch_array($result)) {
                $table = $row[0];
                $sql_drop = "DROP TABLE IF EXISTS `$table`";
                if (mysqli_query($conn, $sql_drop)) {
                    $tables_deleted++;
                }
            }
            mysqli_free_result($result);
        }
    }
    
    // ลบโฟลเดอร์และไฟล์ทั้งหมด
    if (deleteDirectory($project_path)) {
        $message = "ลบ Project สำเร็จ";
        if ($tables_deleted > 0) {
            $message .= " (ลบ $tables_deleted ตาราง)";
        }
        return ['success' => true, 'message' => $message];
    } else {
        return ['success' => false, 'message' => "เกิดข้อผิดพลาดในการลบ Project"];
    }
}

// ฟังก์ชันลบโฟลเดอร์
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// จัดการ Form Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['create_project'])) {
        $project_name = trim($_POST['project_name']);
        $template = $_POST['template'] ?? 'topologie';
        $create_tables = isset($_POST['create_tables']);
        
        if (empty($project_name)) {
            $error = "กรุณาระบุชื่อ Project";
        } else {
            $result = createDailyProject($project_name, $template, $conn, $SHARED_DATABASE, $TABLES_TO_CLONE, $create_tables);
            if ($result['success']) {
                $success = $result['message'];
                $details = $result['details'] ?? [];
            } else {
                $error = $result['message'];
            }
        }
    } elseif (isset($_POST['delete_project'])) {
        $project_name = $_POST['project_to_delete'];
        $delete_tables = isset($_POST['delete_tables']);
        $result = deleteProject($project_name, $conn, $SHARED_DATABASE, $delete_tables);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// ดึงรายการ Projects ทั้งหมด
$projects = [];
$daily_path = __DIR__;
if (is_dir($daily_path)) {
    $dirs = scandir($daily_path);
    foreach ($dirs as $dir) {
        if ($dir != '.' && $dir != '..' && is_dir($daily_path . '/' . $dir)) {
            // ตรวจสอบว่ามีตารางที่กำหนดไว้หรือไม่
            mysqli_select_db($conn, $SHARED_DATABASE);
            $project_prefix = $dir;
            $has_tables = false;
            $table_count = 0;
            
            // นับเฉพาะตารางที่ระบุใน $TABLES_TO_CLONE
            foreach ($TABLES_TO_CLONE as $table_name) {
                $full_table_name = $project_prefix . '_' . $table_name;
                $sql_check = "SHOW TABLES LIKE '$full_table_name'";
                $result = mysqli_query($conn, $sql_check);
                if ($result && mysqli_num_rows($result) > 0) {
                    $table_count++;
                    $has_tables = true;
                }
                if ($result) {
                    mysqli_free_result($result);
                }
            }
            
            $projects[] = [
                'name' => $dir,
                'display_name' => ucwords(str_replace('_', ' ', $dir)),
                'path' => '/content/daily/' . $dir . '/',
                'is_template' => ($dir == 'topologie'),
                'has_tables' => $has_tables,
                'table_count' => $table_count,
                'table_prefix' => $project_prefix . '_',
                'expected_tables' => count($TABLES_TO_CLONE)
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ Daily Projects</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            color: #667eea;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            margin-bottom: 10px;
        }

        .header .db-info {
            background: linear-gradient(135deg, #e8edff 0%, #f8f9ff 100%);
            padding: 12px 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-top: 15px;
            display: inline-block;
        }

        .header .db-info code {
            background: white;
            padding: 3px 10px;
            border-radius: 4px;
            color: #667eea;
            font-weight: 600;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            font-size: 1.5em;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 15px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group small {
            display: block;
            color: #666;
            margin-top: 8px;
            font-size: 13px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: linear-gradient(135deg, #e8edff 0%, #f8f9ff 100%);
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.4);
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0 !important;
            cursor: pointer;
            font-weight: 600;
            color: #667eea;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(235, 51, 73, 0.4);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(235, 51, 73, 0.5);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .details-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .details-box strong {
            color: #1976d2;
            display: block;
            margin-bottom: 10px;
        }
        
        .details-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .details-box li {
            margin: 8px 0;
            color: #555;
        }

        .details-box code {
            background: white;
            padding: 2px 8px;
            border-radius: 4px;
            color: #1976d2;
            font-weight: 600;
        }
        
        .details-box details {
            margin-top: 15px;
            background: white;
            padding: 15px;
            border-radius: 8px;
        }
        
        .details-box summary {
            cursor: pointer;
            font-weight: 600;
            color: #1976d2;
            padding: 5px;
        }
        
        .details-box summary:hover {
            color: #0d47a1;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .project-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid #667eea;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .project-card.template {
            border-left-color: #f39c12;
            background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .project-title {
            font-size: 1.4em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-template {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .badge-active {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }
        
        .badge-tables {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            margin-left: 8px;
        }
        
        .project-info {
            color: #666;
            font-size: 0.95em;
            margin: 15px 0;
            line-height: 1.8;
        }
        
        .project-info code {
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #667eea;
            font-weight: 600;
        }
        
        .project-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f7ff 100%);
            border-left: 5px solid #2196f3;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .info-box strong {
            color: #1976d2;
            font-size: 1.1em;
        }
        
        .info-box ul {
            margin-left: 25px;
            margin-top: 12px;
            line-height: 1.8;
        }
        
        .info-box li {
            color: #555;
            margin: 8px 0;
        }
        
        .delete-options {
            display: none;
            margin-top: 15px;
            padding: 20px;
            background: linear-gradient(135deg, #fff3cd 0%, #fffbf0 100%);
            border-radius: 10px;
            border-left: 4px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            
            .project-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header">
        <h1>🚀 จัดการ Daily Projects</h1>
        <p>สร้างและจัดการ Daily Post Projects ใช้ Database ร่วมกันแต่แยกตารางด้วย Prefix</p>
        <div class="db-info">
            🗄️ Database: <code><?php echo htmlspecialchars($SHARED_DATABASE); ?></code>
        </div>
        <br>
        <small>ผู้ใช้งาน: <strong><?php echo htmlspecialchars($user_name); ?></strong></small>
    </div>
    
    <?php if (!empty($success)): ?>
    <div class="alert alert-success">
        ✅ <?php echo htmlspecialchars($success); ?>
        
        <?php if (!empty($details)): ?>
        <div class="details-box">
            <strong>📋 รายละเอียดการสร้าง:</strong>
            
            <?php if (isset($details['database'])): ?>
            <div style="margin-top: 15px;">
                <strong>🗄️ Database & Tables:</strong>
                <ul>
                    <li>Database: <code><?php echo htmlspecialchars($details['database']['database']); ?></code></li>
                    <li>Template Prefix: <code><?php echo htmlspecialchars($details['database']['template_prefix']); ?>_</code></li>
                    <li>New Prefix: <code><?php echo htmlspecialchars($details['database']['new_prefix']); ?>_</code></li>
                    <li>จำนวนตาราง: <strong><?php echo $details['database']['tables']; ?></strong> ตาราง</li>
                    <li>ตารางที่สร้าง: 
                        <?php 
                        if (isset($details['database']['tables_list'])) {
                            foreach ($details['database']['tables_list'] as $table) {
                                echo '<code>' . htmlspecialchars($details['database']['new_prefix']) . '_' . htmlspecialchars($table) . '</code> ';
                            }
                        }
                        ?>
                    </li>
                    <li style="color: #27ae60; font-weight: 600;">👥 ใช้ตาราง <code>users</code> ร่วมกัน</li>
                </ul>
                <details style="margin-top: 15px;">
                    <summary>📝 ดูรายละเอียดการสร้างตาราง</summary>
                    <ul style="margin-top: 10px;">
                        <?php foreach ($details['database']['log'] as $log): ?>
                        <li><?php echo htmlspecialchars($log); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>
            <?php endif; ?>
            
            <?php if (isset($details['files'])): ?>
            <div style="margin-top: 15px;">
                <strong>📁 ไฟล์:</strong>
                <ul>
                    <li>คัดลอก: <strong><?php echo $details['files']['count']; ?></strong> ไฟล์</li>
                    <li>จาก: <code><?php echo basename($details['files']['source']); ?></code></li>
                    <li>ไป: <code><?php echo basename($details['files']['target']); ?></code></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-error">
        ❌ <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <!-- ฟอร์มสร้าง Project ใหม่ -->
    <div class="card">
        <h2>➕ สร้าง Daily Project ใหม่</h2>
        
        <div class="info-box">
            <strong>💡 วิธีใช้งาน (ใช้ Database ร่วมกัน):</strong>
            <ul>
                <li>ระบุชื่อ Project (เช่น "Pronto", "Soup", "Superdry")</li>
                <li>เลือก Template ที่จะใช้เป็นต้นแบบ</li>
                <li>✅ เลือก "สร้างตารางใหม่" เพื่อคัดลอกตารางพร้อมข้อมูล</li>
                <li>🗄️ ตารางใหม่จะมี Prefix: <code>[project_name]_</code></li>
                <li>👥 ใช้ตาราง <code>users</code> ร่วมกัน (ไม่สร้างใหม่)</li>
                <li>📊 ตารางที่จะสร้าง: 
                    <?php foreach ($TABLES_TO_CLONE as $table): ?>
                        <code><?php echo htmlspecialchars($table); ?></code>
                    <?php endforeach; ?>
                </li>
                <li>⚠️ กระบวนการอาจใช้เวลาสักครู่ โปรดรอจนกว่าจะเสร็จสมบูรณ์</li>
            </ul>
        </div>
        
        <form method="POST" action="" onsubmit="return confirmCreate();">
            <div class="form-group">
                <label>ชื่อ Project <span style="color: #e74c3c;">*</span></label>
                <input type="text" name="project_name" required placeholder="เช่น Pronto, Soup, Superdry" pattern="[A-Za-z0-9\s]+" title="ใช้ตัวอักษรภาษาอังกฤษและตัวเลขเท่านั้น">
                <small>ชื่อจะถูกแปลงเป็นตัวพิมพ์เล็กและใช้ _ แทนช่องว่าง (เช่น "My Project" → "my_project")</small>
            </div>
            
            <div class="form-group">
                <label>Template ต้นแบบ</label>
                <select name="template" required>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo htmlspecialchars($project['name']); ?>" <?php echo $project['is_template'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['display_name']); ?>
                            <?php echo $project['has_tables'] ? ' (' . $project['table_count'] . ' ตาราง)' : ''; ?>
                            <?php echo $project['is_template'] ? ' - แนะนำ' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>เลือก Project ที่จะใช้เป็นต้นแบบ (แนะนำให้ใช้ Topologie)</small>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="create_tables" id="create_tables" checked>
                    <label for="create_tables">📊 สร้างตารางใหม่ (คัดลอกโครงสร้างและข้อมูลจาก Template)</label>
                </div>
                <small style="margin-left: 20px; margin-top: 10px;">
                    ตารางจะมี Prefix: <code>[project_name]_</code> ใน Database: <code><?php echo htmlspecialchars($SHARED_DATABASE); ?></code>
                </small>
            </div>
            
            <button type="submit" name="create_project" class="btn btn-primary">
                🚀 สร้าง Project ใหม่
            </button>
        </form>
    </div>
    
    <!-- รายการ Projects -->
    <div class="card">
        <h2>📁 Projects ทั้งหมด (<?php echo count($projects); ?>)</h2>
        
        <?php if (empty($projects)): ?>
            <p style="text-align: center; padding: 60px 40px; color: #999; font-size: 1.2em;">
                ยังไม่มี Project<br>
                <small>เริ่มต้นโดยการสร้าง Project ใหม่ด้านบน</small>
            </p>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                <div class="project-card <?php echo $project['is_template'] ? 'template' : ''; ?>">
                    <div class="project-header">
                        <div class="project-title"><?php echo htmlspecialchars($project['display_name']); ?></div>
                        <div>
                            <span class="badge <?php echo $project['is_template'] ? 'badge-template' : 'badge-active'; ?>">
                                <?php echo $project['is_template'] ? '⭐ Template' : '✓ Active'; ?>
                            </span>
                            <?php if ($project['has_tables']): ?>
                            <span class="badge badge-tables" title="จำนวนตาราง">
                                📊 <?php echo $project['table_count']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="project-info">
                        📂 Folder: <code><?php echo htmlspecialchars($project['name']); ?></code><br>
                        <?php if ($project['has_tables']): ?>
                        📊 Table Prefix: <code><?php echo htmlspecialchars($project['table_prefix']); ?></code><br>
                        🗄️ Database: <code><?php echo htmlspecialchars($SHARED_DATABASE); ?></code><br>
                        <?php if ($project['table_count'] == $project['expected_tables']): ?>
                            <small style="color: #27ae60;">✓ มี <?php echo $project['table_count']; ?>/<?php echo $project['expected_tables']; ?> ตาราง (ครบถ้วน)</small>
                        <?php else: ?>
                            <small style="color: #e67e22;">⚠️ มี <?php echo $project['table_count']; ?>/<?php echo $project['expected_tables']; ?> ตาราง (ไม่ครบ)</small>
                        <?php endif; ?>
                        <?php else: ?>
                        <span style="color: #e67e22; font-weight: 600;">⚠️ ยังไม่มีตาราง</span><br>
                        <small>👥 ใช้ตาราง users ร่วมกัน</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="project-actions">
                        <a href="<?php echo htmlspecialchars($project['path']); ?>" target="_blank" class="btn btn-primary" style="flex: 1;">
                            🔗 เปิด Project
                        </a>
                        
                        <?php if (!$project['is_template']): ?>
                        <button type="button" class="btn btn-danger" onclick="toggleDeleteOptions('<?php echo htmlspecialchars($project['name']); ?>')">
                            🗑️ ลบ
                        </button>
                        
                        <div class="delete-options" id="delete-<?php echo htmlspecialchars($project['name']); ?>">
                            <form method="POST" action="" onsubmit="return confirmDelete('<?php echo htmlspecialchars($project['display_name']); ?>', <?php echo $project['table_count']; ?>, document.getElementById('delete_tables_<?php echo htmlspecialchars($project['name']); ?>').checked);">
                                <input type="hidden" name="project_to_delete" value="<?php echo htmlspecialchars($project['name']); ?>">
                                
                                <p style="margin-bottom: 15px; color: #856404; font-weight: 600;">
                                    ⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบ Project นี้?
                                </p>
                                
                                <?php if ($project['has_tables']): ?>
                                <div class="checkbox-group" style="margin-bottom: 15px; background: white;">
                                    <input type="checkbox" name="delete_tables" id="delete_tables_<?php echo htmlspecialchars($project['name']); ?>" checked>
                                    <label for="delete_tables_<?php echo htmlspecialchars($project['name']); ?>">
                                        ลบตารางด้วย (<?php echo $project['table_count']; ?> ตาราง)
                                    </label>
                                </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" name="delete_project" class="btn btn-danger" style="flex: 1;">
                                        ✓ ยืนยันการลบ
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="toggleDeleteOptions('<?php echo htmlspecialchars($project['name']); ?>')" style="flex: 1;">
                                        ยกเลิก
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ปุ่มกลับ -->
    <div style="text-align: center; margin-top: 30px;">
        <a href="../dashboard_content.php" class="btn btn-secondary">
            🔙 กลับหน้าหลัก
        </a>
    </div>
    
</div>

<script>
function confirmCreate() {
    const projectName = document.querySelector('input[name="project_name"]').value;
    const template = document.querySelector('select[name="template"]').value;
    const createTables = document.querySelector('input[name="create_tables"]').checked;
    
    let message = `คุณต้องการสร้าง Project "${projectName}" จาก Template "${template}" หรือไม่?\n\n`;
    if (createTables) {
        message += '✅ จะสร้างตารางใหม่พร้อมคัดลอกข้อมูลทั้งหมด\n';
        message += '📊 ตารางจะมี Prefix: ' + projectName.toLowerCase().replace(/\s+/g, '_') + '_\n';
        message += '🗄️ ใน Database: <?php echo $SHARED_DATABASE; ?>\n';
        message += '⚠️ กระบวนการอาจใช้เวลาสักครู่';
    } else {
        message += '⚠️ จะไม่สร้างตาราง (เฉพาะไฟล์)';
    }
    
    return confirm(message);
}

function confirmDelete(projectName, tableCount, deleteTables) {
    let message = `⚠️ คุณแน่ใจหรือไม่ที่จะลบ Project "${projectName}"?\n\n`;
    if (deleteTables && tableCount > 0) {
        message += `📊 จะลบตารางทั้งหมด ${tableCount} ตาราง\n`;
        message += '🗄️ จาก Database: <?php echo $SHARED_DATABASE; ?>\n';
    }
    message += '\n❌ การกระทำนี้ไม่สามารถย้อนกลับได้!';
    
    return confirm(message);
}

function toggleDeleteOptions(projectName) {
    const deleteBox = document.getElementById('delete-' + projectName);
    
    // ซ่อนทุก delete-options อื่นก่อน
    document.querySelectorAll('.delete-options').forEach(box => {
        if (box !== deleteBox) {
            box.style.display = 'none';
        }
    });
    
    // Toggle delete box ปัจจุบัน
    if (deleteBox.style.display === 'none' || deleteBox.style.display === '') {
        deleteBox.style.display = 'block';
    } else {
        deleteBox.style.display = 'none';
    }
}

// ซ่อน delete options ทั้งหมดตอนเริ่มต้น
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-options').forEach(box => {
        box.style.display = 'none';
    });
});
</script>

</body>
</html>