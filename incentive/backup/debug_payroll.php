<?php
// debug_payroll.php - Debug Payroll Page
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Payroll</h2>";
echo "<pre>";

echo "=== Step 1: Session ===\n";
session_start();
print_r($_SESSION);

echo "\n=== Step 2: Config ===\n";
require_once 'config.php';
echo "Config OK\n";

echo "\n=== Step 3: Functions ===\n";
require_once 'includes/functions.php';
echo "Functions OK\n";

echo "\n=== Step 4: isAdmin Check ===\n";
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
echo "User ID: $userId\n";
echo "isAdmin: " . (isAdmin($userId) ? 'TRUE' : 'FALSE') . "\n";

echo "\n=== Step 5: getSetting ===\n";
try {
    $targetPoints = getSetting($conn, 'target_points', 100);
    echo "target_points: $targetPoints\n";
    
    $maxIncentive = getSetting($conn, 'max_base_incentive', 2500);
    echo "max_base_incentive: $maxIncentive\n";
    
    $budgetCap = getSetting($conn, 'budget_cap_per_person', 2200);
    echo "budget_cap_per_person: $budgetCap\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Step 6: getMonthlyBranchSummary ===\n";
try {
    $yearMonth = date('Y-m');
    echo "Year-Month: $yearMonth\n";
    $branchSummary = getMonthlyBranchSummary($conn, $yearMonth);
    echo "Branches found: " . count($branchSummary) . "\n";
    print_r($branchSummary);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Step 7: calculatePayroll ===\n";
try {
    $payrollData = calculatePayroll($conn, $yearMonth);
    echo "Payroll calculated: " . count($payrollData) . " branches\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Step 8: Check Tables ===\n";
$tables = ['incentive_settings', 'incentive_branches', 'incentive_submissions', 'incentive_trophy_winners'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "$table: {$row['cnt']} rows\n";
    } else {
        echo "$table: ERROR - " . $conn->error . "\n";
    }
}

echo "</pre>";
echo "<p>✅ Debug Complete</p>";
?>