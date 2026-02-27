<?php
/**
 * CLI Sync - ใช้สำหรับ cron หรือ command line
 * Usage: php cli_sync.php [date]
 * Example: php cli_sync.php 2026-02-25
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SalesSync.php';

$date = $argv[1] ?? date('Y-m-d', strtotime('-1 day'));

echo "🔄 Syncing Cegid sales for: {$date}\n";

try {
    $sync = new SalesSync();
    $result = $sync->syncDate($date);
    
    if ($result['success']) {
        echo "✅ Success!\n";
        echo "   Payments: {$result['payments']['success']}/{$result['payments']['total']}\n";
        echo "   Transactions: {$result['transactions']['success']}/{$result['transactions']['total']}\n";
    } else {
        echo "❌ Failed!\n";
        foreach ($result['errors'] as $err) {
            echo "   Error: {$err}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    exit(1);
}
