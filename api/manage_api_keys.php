<?php
// manage_api_keys.php
session_start();
require_once 'config.php';

// 1. ตรวจสอบ Login และ Role (แก้เงื่อนไขตามระบบ Role ของคุณ)
if (!isset($_SESSION['id'])) { header('Location: login.php'); exit; }

// ตัวอย่าง: อนุญาตเฉพาะ owner หรือ admin เท่านั้น
// if ($_SESSION['role'] !== 'owner') { die("Access Denied"); }

$db = Database::getInstance()->getConnection();
$message = '';

// 2. ฟังก์ชันสร้าง Key ใหม่
if (isset($_POST['action']) && $_POST['action'] == 'generate') {
    try {
        // สร้าง Random String 32 ตัวอักษร
        $new_key = 'wj_' . bin2hex(random_bytes(16)); 
        $label = $_POST['label'] ?? 'API Key';
        $user_id = $_SESSION['id'];

        $stmt = $db->prepare("INSERT INTO api_keys (user_id, api_key, label) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $new_key, $label]);
        $message = "สร้าง API Key สำเร็จ!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// 3. ฟังก์ชันระงับ/ลบ Key
if (isset($_GET['revoke_id'])) {
    $stmt = $db->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?");
    $stmt->execute([$_GET['revoke_id']]);
    header("Location: manage_api_keys.php"); // Refresh
    exit;
}

// 4. ดึงรายการ Key ทั้งหมด
$keys = $db->query("SELECT k.*, u.username FROM api_keys k LEFT JOIN users u ON k.user_id = u.id ORDER BY k.created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการ API Keys</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .btn { padding: 10px 20px; background: #0288d1; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #d32f2f; font-size: 12px; padding: 5px 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f9f9f9; }
        .key-text { font-family: monospace; background: #eee; padding: 4px 8px; border-radius: 4px; color: #d32f2f; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .badge-active { background: #c8e6c9; color: #2e7d32; }
        .badge-inactive { background: #ffcdd2; color: #c62828; }
        .form-box { background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
        input[type="text"] { padding: 10px; border: 1px solid #ccc; border-radius: 5px; flex: 1; }
    </style>
</head>
<body>
    <div class="nav-menu">
        <a href="document.php" class="btn" style="background:#666;">← กลับหน้าหลัก</a>
    </div>
    <br>
    
    <div class="container">
        <h2>🔑 จัดการ API Keys (สำหรับเชื่อมต่อภายนอก)</h2>
        
        <?php if($message): ?><div style="color:green; margin-bottom:10px; font-weight:bold;"><?=$message?></div><?php endif; ?>

        <form method="POST" class="form-box">
            <input type="hidden" name="action" value="generate">
            <label>สร้าง Key ใหม่:</label>
            <input type="text" name="label" placeholder="ชื่ออ้างอิง (เช่น เชื่อมต่อระบบบัญชี, ให้ Developer)" required>
            <button type="submit" class="btn">✨ สร้าง Key</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ชื่ออ้างอิง</th>
                    <th>API Key</th>
                    <th>ผู้สร้าง</th>
                    <th>สถานะ</th>
                    <th>วันที่สร้าง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($keys as $k): ?>
                <tr>
                    <td><?=htmlspecialchars($k['label'])?></td>
                    <td><span class="key-text"><?=$k['api_key']?></span></td>
                    <td><?=$k['username'] ?? $k['user_id']?></td>
                    <td>
                        <?php if($k['is_active']): ?>
                            <span class="badge badge-active">ใช้งาน</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">ระงับ</span>
                        <?php endif; ?>
                    </td>
                    <td><?=date('d/m/Y H:i', strtotime($k['created_at']))?></td>
                    <td>
                        <?php if($k['is_active']): ?>
                            <a href="?revoke_id=<?=$k['id']?>" class="btn btn-danger" onclick="return confirm('ต้องการระงับ Key นี้ใช่หรือไม่? ระบบที่ใช้อยู่จะใช้งานไม่ได้ทันที')">ระงับ</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>