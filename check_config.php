<?php
echo "<h2>🔍 ตรวจสอบ Config File</h2>";
echo "<pre>";

// ตรวจสอบว่าไฟล์ config.php อยู่ที่ไหน
$config_path = __DIR__ . '/config.php';
echo "📁 Current directory: " . __DIR__ . "\n";
echo "📄 Config path: " . $config_path . "\n\n";

// ตรวจสอบว่าไฟล์มีอยู่หรือไม่
if (file_exists($config_path)) {
    echo "✅ config.php EXISTS\n\n";
    
    // แสดงเนื้อหาของไฟล์ (ซ่อน password)
    $content = file_get_contents($config_path);
    $content_safe = preg_replace("/\\\$pass\s*=\s*['\"].*?['\"]/", "\$pass = '***HIDDEN***'", $content);
    echo "📝 Content of config.php:\n";
    echo "─────────────────────────────\n";
    echo htmlspecialchars($content_safe);
    echo "\n─────────────────────────────\n\n";
    
    // ลอง include และตรวจสอบ $conn
    include $config_path;
    
    if (isset($conn) && $conn !== null) {
        echo "✅ \$conn is DEFINED and NOT NULL\n";
        echo "✅ Connection type: " . get_class($conn) . "\n";
        
        // ทดสอบ connection
        if ($conn->ping()) {
            echo "✅ Database connection is WORKING!\n";
        }
    } else {
        echo "❌ \$conn is NOT defined or NULL after including config.php\n";
    }
    
} else {
    echo "❌ config.php DOES NOT EXIST at: $config_path\n\n";
    
    // แสดงไฟล์ทั้งหมดใน directory
    echo "📂 Files in current directory:\n";
    $files = scandir(__DIR__);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "   - $file\n";
        }
    }
}

echo "</pre>";
?>