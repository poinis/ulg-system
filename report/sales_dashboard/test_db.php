<?php
/**
 * Test Database Connection & Tables
 */
require_once 'config.php';

echo "<h2>🔧 Database Test</h2>";
echo "<pre>";

// Test 1: Connection
echo "1. Testing connection...\n";
try {
    $pdo = getDB();
    echo "   ✅ Connected to database: " . DB_NAME . "\n";
} catch (Exception $e) {
    echo "   ❌ Connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Check tables
echo "\n2. Checking tables...\n";
$tables = ['payments', 'sales', 'daily_summary', 'store_details', 'import_logs'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "   ✅ Table '$table' exists ($count rows)\n";
    } else {
        echo "   ❌ Table '$table' NOT FOUND - Run database.sql first!\n";
    }
}

// Test 3: Check uploads directory
echo "\n3. Checking uploads directory...\n";
if (file_exists(UPLOAD_DIR)) {
    echo "   ✅ Directory exists: " . UPLOAD_DIR . "\n";
    if (is_writable(UPLOAD_DIR)) {
        echo "   ✅ Directory is writable\n";
    } else {
        echo "   ❌ Directory is NOT writable - chmod 755 uploads/\n";
    }
} else {
    echo "   ❌ Directory NOT found: " . UPLOAD_DIR . "\n";
    echo "   Creating...\n";
    if (mkdir(UPLOAD_DIR, 0755, true)) {
        echo "   ✅ Created successfully\n";
    } else {
        echo "   ❌ Failed to create\n";
    }
}

// Test 4: PHP Info
echo "\n4. PHP Info...\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   post_max_size: " . ini_get('post_max_size') . "\n";
echo "   mbstring: " . (extension_loaded('mbstring') ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "   pdo_mysql: " . (extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Not loaded') . "\n";

echo "\n</pre>";
echo "<p><a href='upload.php'>Go to Upload Page</a></p>";
