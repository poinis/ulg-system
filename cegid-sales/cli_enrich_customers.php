<?php
/**
 * CLI: Enrich customer data from Cegid SOAP API
 * Usage: php cli_enrich_customers.php [--brand=TOPOLOGIE] [--limit=500] [--batch=50]
 */
if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CegidCustomerSOAP.php';

$ulgcegid = Database::getInstance()->getConnection();

$cmbase = new PDO(
    "mysql:host=localhost;dbname=cmbase;charset=utf8mb4",
    "cmbase", "#wmIYH3wazaa",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Parse args
$brand = 'TOPOLOGIE';
$limit = 0; // 0 = all
$startFrom = '';
foreach ($argv as $arg) {
    if (preg_match('/^--brand=(.+)$/', $arg, $m)) $brand = $m[1];
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $limit = (int)$m[1];
    if (preg_match('/^--from=(.+)$/', $arg, $m)) $startFrom = $m[1];
}

echo "=== Customer Enrichment ===\n";
echo "Brand: $brand\n";

// Get all unique customer codes from daily_sales
$sql = "SELECT DISTINCT customer FROM daily_sales WHERE brand = ? AND customer IS NOT NULL AND customer != '' AND customer NOT LIKE 'WI%' ORDER BY customer";
$stmt = $cmbase->prepare($sql);
$stmt->execute([$brand]);
$allCustomers = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Total unique customers: " . count($allCustomers) . "\n";

// Find which ones already have birthday in ulgcegid.customers
$existing = [];
if (!empty($allCustomers)) {
    $chunks = array_chunk($allCustomers, 500);
    foreach ($chunks as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $s = $ulgcegid->prepare("SELECT customer_code FROM customers WHERE customer_code IN ($ph) AND birthday IS NOT NULL");
        $s->execute($chunk);
        $existing = array_merge($existing, $s->fetchAll(PDO::FETCH_COLUMN));
    }
}

$needEnrich = array_diff($allCustomers, $existing);
if ($startFrom) {
    $needEnrich = array_filter($needEnrich, fn($c) => $c >= $startFrom);
}
$needEnrich = array_values($needEnrich);

if ($limit > 0) {
    $needEnrich = array_slice($needEnrich, 0, $limit);
}

echo "Already enriched: " . count($existing) . "\n";
echo "Need enrichment: " . count($needEnrich) . "\n\n";

if (empty($needEnrich)) {
    echo "Nothing to do!\n";
    exit(0);
}

$soap = new CegidCustomerSOAP();
$enriched = 0;
$errors = 0;
$noData = 0;
$total = count($needEnrich);

$insertStmt = $ulgcegid->prepare("
    INSERT INTO customers (customer_code, first_name, last_name, phone, email, birthday, usual_store, member_type)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        first_name = VALUES(first_name), last_name = VALUES(last_name),
        phone = VALUES(phone), email = VALUES(email),
        birthday = VALUES(birthday), usual_store = VALUES(usual_store),
        member_type = VALUES(member_type)
");

$startTime = time();

foreach ($needEnrich as $i => $customerId) {
    try {
        $detail = $soap->getCustomerDetail($customerId);
        
        if ($detail) {
            $insertStmt->execute([
                $customerId,
                $detail['first_name'] ?? '',
                $detail['last_name'] ?? '',
                $detail['phone'] ?? '',
                $detail['email'] ?? '',
                $detail['birthday'] ?? null,
                $detail['usual_store'] ?? '',
                $detail['member_type'] ?? ''
            ]);
            $enriched++;
            
            $bday = $detail['birthday'] ?? 'none';
            if (($i + 1) % 100 === 0 || $i < 5) {
                $elapsed = time() - $startTime;
                $rate = $elapsed > 0 ? round(($i + 1) / $elapsed, 1) : 0;
                $eta = $rate > 0 ? round(($total - $i - 1) / $rate / 60, 1) : '?';
                echo sprintf("[%d/%d] %s → bday=%s | %.1f/s ETA: %s min\n", $i + 1, $total, $customerId, $bday, $rate, $eta);
            }
        } else {
            $noData++;
            // Still insert a record so we don't retry
            $insertStmt->execute([$customerId, '', '', '', '', null, '', '']);
        }
    } catch (Exception $e) {
        $errors++;
        if (($i + 1) % 100 === 0) {
            echo sprintf("[%d/%d] ERROR %s: %s\n", $i + 1, $total, $customerId, $e->getMessage());
        }
    }
    
    usleep(40000); // 40ms delay
}

$elapsed = time() - $startTime;
echo "\n=== Done ===\n";
echo "Enriched: $enriched\n";
echo "No data: $noData\n";
echo "Errors: $errors\n";
echo "Time: " . round($elapsed / 60, 1) . " min\n";
