<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// ชื่อ Database ที่ใช้ร่วมกัน
$SHARED_DATABASE = defined('DB_NAME') ? DB_NAME : 'cmbase';

// ดึงรายการ Projects ทั้งหมด
$projects = [];
$daily_path = __DIR__;
if (is_dir($daily_path)) {
    $dirs = scandir($daily_path);
    foreach ($dirs as $dir) {
        if ($dir != '.' && $dir != '..' && is_dir($daily_path . '/' . $dir)) {
            $projects[] = [
                'name' => $dir,
                'display_name' => ucwords(str_replace('_', ' ', $dir)),
                'path' => '/content/daily/' . $dir . '/',
                'is_template' => ($dir == 'topologie')
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
    <title>Daily Projects - รวมงานทั้งหมด</title>
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
            max-width: 1400px;
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
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .header .user-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
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
        
        .badge-tables {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
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
        }
        
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .no-data {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        
        .no-data-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            
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
        <h1>📂 Daily Projects (<?php echo count($projects); ?>)</h1>
        <div class="user-info">
            👤 ผู้ใช้งาน: <strong><?php echo htmlspecialchars($user_name); ?></strong>
            <?php if ($role === 'admin'): ?>
            <span style="margin-left: 15px; color: #27ae60;"></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- รายการ Projects -->
    <div class="projects-grid">
        <?php foreach ($projects as $project): ?>
        <div class="project-card <?php echo $project['is_template'] ? 'template' : ''; ?>">
            <div class="project-header">
                <div class="project-title"><?php echo htmlspecialchars($project['display_name']); ?></div>
                <?php if ($project['is_template']): ?>
                
                <?php else: ?>
                <?php
                // นับจำนวนตาราง
                $table_count = 0;
                $project_prefix = $project['name'];
                $check_tables = ['calendar_events', 'calendar_engage', 'content_brief', 'tiktok_posts'];
                foreach ($check_tables as $table_name) {
                    $full_table = $project_prefix . '_' . $table_name;
                    $sql = "SHOW TABLES LIKE '$full_table'";
                    $res = mysqli_query($conn, $sql);
                    if ($res && mysqli_num_rows($res) > 0) {
                        $table_count++;
                    }
                    if ($res) mysqli_free_result($res);
                }
                ?>
                <span class="badge badge-tables">📊 <?php echo $table_count; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="project-info">

                <?php if (!$project['is_template']): ?>
                <br><small style="color: #27ae60;"></small>
                <?php endif; ?>
            </div>
            
            <div class="project-actions">
                <a href="<?php echo htmlspecialchars($project['path']); ?>" target="_blank" class="btn btn-primary">
                    🔗 เปิด Project
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- ปุ่มจัดการ -->
    <div class="action-buttons">
        <?php if ($role === 'admin'): ?>
        
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">
            🔙 กลับหน้าหลัก
        </a>
    </div>
    
</div>

<script>
// ไม่มี JavaScript ที่จำเป็น
</script>

</body>
</html>