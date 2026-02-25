<?php
// debug.php - ตรวจสอบ session และ role
session_start();
require_once 'config.php';

echo "<h2>🔍 Debug Session & Role</h2>";
echo "<pre>";

// 1. Check Session
echo "=== SESSION ===\n";
print_r($_SESSION);

echo "\n=== YOUR USER INFO ===\n";
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, username, name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    print_r($user);
    
    echo "\n=== ROLE CHECK ===\n";
    echo "role value: '" . $user['role'] . "'\n";
    echo "role === 'admin': " . ($user['role'] === 'admin' ? 'TRUE' : 'FALSE') . "\n";
    echo "strlen(role): " . strlen($user['role']) . "\n";
    echo "hex: " . bin2hex($user['role']) . "\n";
} else {
    echo "Not logged in!\n";
}

// 2. Check all admin users
echo "\n=== ALL ADMIN USERS ===\n";
$result = $conn->query("SELECT id, username, name, role FROM users WHERE role = 'admin'");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | {$row['username']} | {$row['name']} | role: '{$row['role']}'\n";
}

// 3. Check all roles
echo "\n=== ALL ROLES IN SYSTEM ===\n";
$result = $conn->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
while ($row = $result->fetch_assoc()) {
    echo "'{$row['role']}' => {$row['cnt']} users\n";
}

echo "</pre>";

echo "<p><a href='login.php'>🔄 Go to Login</a></p>";
?>