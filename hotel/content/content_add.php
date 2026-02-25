<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];

$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$content_id = $is_edit ? intval($_GET['id']) : 0;

$content = null;
if ($is_edit) {
    $sql = "SELECT * FROM content_calendar WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $content_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $content = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$content) {
        header("location: content_dashboard.php");
        exit;
    }
}

// ดึงรายชื่อเพจ
$pages = [];
$sql = "SELECT * FROM facebook_pages WHERE is_active = TRUE ORDER BY page_name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $pages[] = $row;
}

// ค่าเริ่มต้นจาก URL
$default_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$default_time = isset($_GET['time']) ? $_GET['time'] : '12:00';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $page_id = !empty($_POST['page_id']) ? intval($_POST['page_id']) : null;
    $job_title = cleanInput($_POST['job_title']);
    $description = cleanInput($_POST['description']);
    $category = cleanInput($_POST['category']);
    $content_type = cleanInput($_POST['content_type']);
    $status = cleanInput($_POST['status']);
    $assignee = cleanInput($_POST['assignee']);
    $brief_date = !empty($_POST['brief_date']) ? cleanInput($_POST['brief_date']) : null;
    $post_date = !empty($_POST['post_date']) ? cleanInput($_POST['post_date']) : null;
    $post_time = !empty($_POST['post_time']) ? cleanInput($_POST['post_time']) : null;
    $facebook_post_id = cleanInput($_POST['facebook_post_id']);
    $facebook_post_url = cleanInput($_POST['facebook_post_url']);
    $drive_folder_url = cleanInput($_POST['drive_folder_url']);
    $notes = cleanInput($_POST['notes']);
    
    if (empty($job_title) || empty($category)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } else {
        if ($is_edit) {
            // อัพเดท
            $sql = "UPDATE content_calendar SET 
                    page_id = ?, job_title = ?, description = ?, category = ?, content_type = ?,
                    status = ?, assignee = ?, brief_date = ?, post_date = ?, post_time = ?,
                    facebook_post_id = ?, facebook_post_url = ?, drive_folder_url = ?, notes = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isssssssssssssi", 
                $page_id, $job_title, $description, $category, $content_type,
                $status, $assignee, $brief_date, $post_date, $post_time,
                $facebook_post_id, $facebook_post_url, $drive_folder_url, $notes, $content_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // ถ้าเปลี่ยนเป็น posted ให้สร้าง engagement record
                if ($content['status'] != 'posted' && $status == 'posted' && !empty($post_date)) {
                    $check_date = date('Y-m-d', strtotime($post_date . ' + 14 days'));
                    $sql_eng = "INSERT INTO facebook_engagement (content_id, post_id, check_date, status) 
                               VALUES (?, ?, ?, 'pending')";
                    $stmt_eng = mysqli_prepare($conn, $sql_eng);
                    mysqli_stmt_bind_param($stmt_eng, "iss", $content_id, $facebook_post_id, $check_date);
                    mysqli_stmt_execute($stmt_eng);
                    mysqli_stmt_close($stmt_eng);
                }
                
                $success = 'บันทึกข้อมูลเรียบร้อยแล้ว';
                header("refresh:1;url=content_detail.php?id={$content_id}");
            } else {
                $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            // สร้างใหม่
            $sql = "INSERT INTO content_calendar 
                    (page_id, job_title, description, category, content_type, status, assignee, 
                     brief_date, post_date, post_time, facebook_post_id, facebook_post_url, 
                     drive_folder_url, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isssssssssssssi", 
                $page_id, $job_title, $description, $category, $content_type, $status, $assignee,
                $brief_date, $post_date, $post_time, $facebook_post_id, $facebook_post_url,
                $drive_folder_url, $notes, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                
                // ถ้าสร้างเป็น posted ให้สร้าง engagement record
                if ($status == 'posted' && !empty($post_date)) {
                    $check_date = date('Y-m-d', strtotime($post_date . ' + 14 days'));
                    $sql_eng = "INSERT INTO facebook_engagement (content_id, post_id, check_date, status) 
                               VALUES (?, ?, ?, 'pending')";
                    $stmt_eng = mysqli_prepare($conn, $sql_eng);
                    mysqli_stmt_bind_param($stmt_eng, "iss", $new_id, $facebook_post_id, $check_date);
                    mysqli_stmt_execute($stmt_eng);
                    mysqli_stmt_close($stmt_eng);
                }
                
                $success = 'สร้างคอนเทนต์เรียบร้อยแล้ว';
                header("refresh:1;url=content_detail.php?id={$new_id}");
            } else {
                $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'แก้ไขคอนเทนต์' : 'เพิ่มคอนเทนต์ใหม่'; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .form-card h2 {
            color: #667eea;
            margin-bottom: 25px;
            font-size: 1.5em;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group label.required:after {
            content: ' *';
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><?php echo $is_edit ? '✏️ แก้ไขคอนเทนต์' : '➕ เพิ่มคอนเทนต์ใหม่'; ?></h1>
            <a href="content_dashboard.php" class="back-link">← กลับปฏิทิน</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-card">
            <h2><?php echo $is_edit ? 'แก้ไขข้อมูลคอนเทนต์' : 'สร้างคอนเทนต์ใหม่'; ?></h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="required">หัวข้องาน</label>
                    <input type="text" name="job_title" 
                           value="<?php echo $content ? htmlspecialchars($content['job_title']) : ''; ?>" 
                           placeholder="เช่น โพสต์รีวิวสินค้า A" required>
                </div>
                
                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="description" placeholder="รายละเอียดเพิ่มเติม..."><?php echo $content ? htmlspecialchars($content['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Facebook Page</label>
                        <select name="page_id">
                            <option value="">-- เลือกเพจ --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo $page['id']; ?>" 
                                    <?php echo ($content && $content['page_id'] == $page['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($page['page_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">หมวดหมู่</label>
                        <select name="category" required>
                            <option value="">-- เลือกหมวดหมู่ --</option>
                            <option value="Product Knowledge" <?php echo ($content && $content['category'] == 'Product Knowledge') ? 'selected' : ''; ?>>Product Knowledge</option>
                            <option value="Promotion" <?php echo ($content && $content['category'] == 'Promotion') ? 'selected' : ''; ?>>Promotion</option>
                            <option value="Lifestyle" <?php echo ($content && $content['category'] == 'Lifestyle') ? 'selected' : ''; ?>>Lifestyle</option>
                            <option value="Event" <?php echo ($content && $content['category'] == 'Event') ? 'selected' : ''; ?>>Event</option>
                            <option value="News" <?php echo ($content && $content['category'] == 'News') ? 'selected' : ''; ?>>News</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ประเภทคอนเทนต์</label>
                        <select name="content_type">
                            <option value="image" <?php echo ($content && $content['content_type'] == 'image') ? 'selected' : ''; ?>>รูปภาพ</option>
                            <option value="video" <?php echo ($content && $content['content_type'] == 'video') ? 'selected' : ''; ?>>วิดีโอ</option>
                            <option value="carousel" <?php echo ($content && $content['content_type'] == 'carousel') ? 'selected' : ''; ?>>Carousel</option>
                            <option value="reel" <?php echo ($content && $content['content_type'] == 'reel') ? 'selected' : ''; ?>>Reel</option>
                            <option value="story" <?php echo ($content && $content['content_type'] == 'story') ? 'selected' : ''; ?>>Story</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select name="status">
                            <option value="planned" <?php echo ($content && $content['status'] == 'planned') ? 'selected' : ''; ?>>กำลังวางแผน</option>
                            <option value="in_progress" <?php echo ($content && $content['status'] == 'in_progress') ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                            <option value="approved" <?php echo ($content && $content['status'] == 'approved') ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                            <option value="posted" <?php echo ($content && $content['status'] == 'posted') ? 'selected' : ''; ?>>โพสต์แล้ว</option>
                            <option value="completed" <?php echo ($content && $content['status'] == 'completed') ? 'selected' : ''; ?>>เสร็จสมบูรณ์</option>
                            <option value="cancelled" <?php echo ($content && $content['status'] == 'cancelled') ? 'selected' : ''; ?>>ยกเลิก</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ผู้รับผิดชอบ</label>
                        <input type="text" name="assignee" 
                               value="<?php echo $content ? htmlspecialchars($content['assignee']) : ''; ?>" 
                               placeholder="เช่น จันทร์, มร">
                    </div>
                    
                    <div class="form-group">
                        <label>วันที่สรุป Brief</label>
                        <input type="date" name="brief_date" 
                               value="<?php echo $content ? $content['brief_date'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>วันที่โพสต์</label>
                        <input type="date" name="post_date" 
                               value="<?php echo $content ? $content['post_date'] : $default_date; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>เวลาที่โพสต์</label>
                        <input type="time" name="post_time" 
                               value="<?php echo $content ? substr($content['post_time'], 0, 5) : $default_time; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Facebook Post ID</label>
                        <input type="text" name="facebook_post_id" 
                               value="<?php echo $content ? htmlspecialchars($content['facebook_post_id']) : ''; ?>" 
                               placeholder="เช่น 123456789_987654321">
                    </div>
                    
                    <div class="form-group">
                        <label>Facebook Post URL</label>
                        <input type="url" name="facebook_post_url" 
                               value="<?php echo $content ? htmlspecialchars($content['facebook_post_url']) : ''; ?>" 
                               placeholder="https://facebook.com/...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Google Drive Folder URL</label>
                    <input type="url" name="drive_folder_url" 
                           value="<?php echo $content ? htmlspecialchars($content['drive_folder_url']) : ''; ?>" 
                           placeholder="https://drive.google.com/drive/folders/...">
                </div>
                
                <div class="form-group">
                    <label>หมายเหตุ</label>
                    <textarea name="notes" rows="3" placeholder="หมายเหตุเพิ่มเติม..."><?php echo $content ? htmlspecialchars($content['notes']) : ''; ?></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? '💾 บันทึกการแก้ไข' : '📝 สร้างคอนเทนต์'; ?>
                    </button>
                    <a href="content_dashboard.php" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>