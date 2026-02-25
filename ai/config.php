<?php
session_start();

// การตั้งค่า Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'cmbase');
define('DB_USER', 'cmbase');
define('DB_PASS', '#wmIYH3wazaa');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// API Keys
define('GEMINI_API_KEY', 'AIzaSyAseM5AcZIIPhysOy1wZjzw7aOfYHeujRg');

// ราคาเครดิตแต่ละโหมด
define('CREDITS', [
    'image' => 2,
    'video' => 10,
    'voice' => 1
]);

// จำลองการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

function getUserCredits($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function deductCredits($pdo, $userId, $amount, $description) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        
        $stmt = $pdo->prepare("INSERT INTO credit_logs (user_id, amount, type, description) VALUES (?, ?, 'deduct', ?)");
        $stmt->execute([$userId, $amount, $description]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
?>
