<?php
/**
 * CLI Sync - ใช้สำหรับ cron หรือ command line
 * Usage: 
 *   php cli_sync.php                          # sync yesterday
 *   php cli_sync.php --date=2026-02-25        # sync specific date
 *   php cli_sync.php --from=2026-02-20 --to=2026-02-25  # sync date range
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SalesSync.php';

// Parse arguments
$date = null;
$dateFrom = null;
$dateTo = null;

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--date=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $date = $m[1];
    } elseif (preg_match('/^--from=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $dateFrom = $m[1];
    } elseif (preg_match('/^--to=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $dateTo = $m[1];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg)) {
        $date = $arg;
    }
}

// Build list of dates to sync
$dates = [];
if ($dateFrom && $dateTo) {
    $current = new DateTime($dateFrom);
    $end = new DateTime($dateTo);
    while ($current <= $end) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
} elseif ($date) {
    $dates = [$date];
} else {
    $dates = [date('Y-m-d', strtotime('-1 day'))];
}

echo "🔄 Cegid SOAP Sales Sync\n";
echo "   Dates: " . count($dates) . " day(s) — {$dates[0]}" . (count($dates) > 1 ? " to " . end($dates) : "") . "\n\n";

$sync = new SalesSync();
$grandTotal = ['docs' => 0, 'payments' => 0, 'transactions' => 0];

foreach ($dates as $d) {
    echo "📅 {$d} ... ";
    
    try {
        $result = $sync->syncDate($d);
        
        if ($result['success']) {
            $docs = $result['documents']['success'];
            $pay = $result['payments']['success'];
            $tx = $result['transactions']['success'];
            $payFail = $result['payments']['failed'];
            $txFail = $result['transactions']['failed'];
            
            echo "✅ {$docs} docs, {$pay} payments, {$tx} lines";
            if ($payFail || $txFail) echo " (failed: {$payFail}p/{$txFail}t)";
            echo "\n";
            
            $grandTotal['docs'] += $docs;
            $grandTotal['payments'] += $pay;
            $grandTotal['transactions'] += $tx;
        } else {
            echo "❌ " . implode('; ', $result['errors']) . "\n";
        }
    } catch (Exception $e) {
        echo "❌ " . $e->getMessage() . "\n";
    }
}

echo "\n🏁 Total: {$grandTotal['docs']} documents, {$grandTotal['payments']} payments, {$grandTotal['transactions']} transaction lines\n";
