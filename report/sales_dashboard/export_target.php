<?php
/**
 * Export Target to Excel (All Brands)
 */
require_once 'config.php';

$pdo = getDB();
$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedBrand = $_GET['brand'] ?? 'topologie';

$year = substr($selectedMonth, 0, 4);
$month = substr($selectedMonth, 5, 2);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, intval($month), intval($year));
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$sheetName = $monthNames[intval($month)-1] . ' ' . $year;
$dayNames = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

$brandNames = [
    'topologie' => 'Topologie', 'superdry' => 'Superdry', 'pronto' => 'Pronto',
    'freitag' => 'Freitag', 'hooga' => 'Hooga', 'soup' => 'Soup',
    'sw19' => 'SW19', 'izipizi' => 'Izipizi', 'pavement' => 'Pavement',
];

// Helper functions (same as targets.php)
function getTarget($pdo, $brand, $key, $month) {
    $stmt = $pdo->prepare("SELECT target_value FROM targets WHERE brand = ? AND store_key = ? AND month = ?");
    $stmt->execute([$brand, $key, $month]);
    $row = $stmt->fetch();
    return $row ? floatval($row['target_value']) : 0;
}
function getPaymentSum($pdo, $date, $store, $method = null) {
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE sale_date = ? AND store = ?";
    $params = [$date, $store];
    if ($method) { $sql .= " AND payment_method = ?"; $params[] = $method; }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return floatval($stmt->fetch()['total']);
}
function getPaymentCount($pdo, $date, $store, $method) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM payments WHERE sale_date = ? AND store = ? AND payment_method = ?");
    $stmt->execute([$date, $store, $method]);
    return intval($stmt->fetch()['cnt']);
}
function getSalesSum($pdo, $date, $warehouse, $brand = null) {
    $sql = "SELECT COALESCE(SUM(qty), 0) as pcs, COALESCE(SUM(total_incl_tax), 0) as amount 
            FROM sales WHERE sale_date = ? AND warehouse = ? AND total_incl_tax != 0";
    $params = [$date, $warehouse];
    if ($brand) { $sql .= " AND brand = ?"; $params[] = $brand; }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetch();
}
function getSalesBag($pdo, $date, $warehouse) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) as qty FROM sales WHERE sale_date = ? AND warehouse = ? AND class LIKE '%BAG%' AND total_incl_tax != 0");
    $stmt->execute([$date, $warehouse]);
    return intval($stmt->fetch()['qty']);
}
function getSalesSBag($pdo, $date, $warehouse) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) as qty FROM sales WHERE sale_date = ? AND warehouse = ? AND class = 'SHOPPING BAG' AND total_incl_tax != 0");
    $stmt->execute([$date, $warehouse]);
    return intval($stmt->fetch()['qty']);
}
function getSalesNegative($pdo, $date, $warehouse) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(qty)), 0) as qty FROM sales WHERE sale_date = ? AND warehouse = ? AND total_incl_tax < 0 AND item_description NOT LIKE '%Cash flow%'");
    $stmt->execute([$date, $warehouse]);
    return intval($stmt->fetch()['qty']);
}
function getOnlinePcs($pdo, $date, $store) {
    $stmt = $pdo->prepare("SELECT DISTINCT bill_number FROM payments WHERE sale_date = ? AND store = ? AND payment_method IN ('Online Cash', 'Freitag Online')");
    $stmt->execute([$date, $store]);
    $bills = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($bills)) return 0;
    $placeholders = implode(',', array_fill(0, count($bills), '?'));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) as pcs FROM sales WHERE sale_date = ? AND warehouse = ? AND bill_number IN ($placeholders) AND total_incl_tax != 0");
    $stmt->execute(array_merge([$date, $store], $bills));
    return intval($stmt->fetch()['pcs']);
}
function formatNum($val) { return ($val && $val != 0) ? number_format($val, 0) : '-'; }
function calcPct($actual, $target) { return (!$target) ? '-' : round($actual / $target * 100) . '%'; }

$filename = "Sale_target_" . ($brandNames[$selectedBrand] ?? 'Export') . "_$year.xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
echo "\xEF\xBB\xBF";
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
    td, th { mso-number-format:\@; }
    .num { mso-number-format:"\#\,\#\#0"; text-align: right; }
    .header-blue { background: #4472C4; color: white; font-weight: bold; text-align: center; }
    .header-light { background: #5B9BD5; color: white; font-weight: bold; text-align: center; }
    .date-cell { background: #FFC000; font-weight: bold; text-align: center; }
    .total-row { background: #92D050; font-weight: bold; }
    .target-row { background: #FFC000; font-weight: bold; }
    .percent-row { background: #F4B183; font-weight: bold; }
    .data-odd { background: #E9ECF1; }
    .data-even { background: #D6DCE5; }
</style>
</head>
<body>
<?php
// ============================================
// TOPOLOGIE
// ============================================
if ($selectedBrand === 'topologie'):
    $stores = [
        ['key'=>'ladprao', 'name'=>'LADPRAO', 'code'=>6030, 'usePayment'=>true],
        ['key'=>'paragon', 'name'=>'PARAGON', 'code'=>8010, 'usePayment'=>false],
        ['key'=>'ctw', 'name'=>'CENTRAL WORLD', 'code'=>3020, 'usePayment'=>false],
        ['key'=>'mega', 'name'=>'MEGA BANGNA', 'code'=>6050, 'usePayment'=>true],
        ['key'=>'dusit', 'name'=>'DUSIT CENTRAL PARK', 'code'=>6060, 'usePayment'=>true, 'adjustTopup'=>true],
        ['key'=>'online', 'name'=>'ONLINE', 'code'=>2009, 'usePayment'=>false],
    ];
?>
<table border="1" cellpadding="3" cellspacing="0">
    <tr><td colspan="16" style="font-size:14pt; font-weight:bold;">Sale Target Topologie</td></tr>
    <tr><td colspan="16"></td></tr>
    <tr>
        <td colspan="2"></td>
        <?php foreach ($stores as $s): ?><td colspan="2" class="header-blue">TOPOLOGIE</td><?php endforeach; ?>
        <td colspan="2"></td>
    </tr>
    <tr>
        <td colspan="2" class="header-light"><?= $sheetName ?></td>
        <?php foreach ($stores as $s): ?><td colspan="2" class="header-light"><?= $s['name'] ?></td><?php endforeach; ?>
        <td colspan="2" class="header-light">TOTAL</td>
    </tr>
    <tr>
        <td class="header-blue">DATE</td><td class="header-blue"></td>
        <?php foreach ($stores as $s): ?><td class="header-blue">PCS.</td><td class="header-blue">Amount</td><?php endforeach; ?>
        <td class="header-blue">PCS.</td><td class="header-blue">SALE AMOUNT</td>
    </tr>
    <?php 
    $totals = []; foreach ($stores as $s) { $totals[$s['key'].'_pcs']=0; $totals[$s['key'].'_amt']=0; }
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
        $rowClass = $day % 2 == 1 ? 'data-odd' : 'data-even';
        $rowData = []; $rowPcs = 0; $rowAmt = 0;
        foreach ($stores as $s) {
            $sales = getSalesSum($pdo, $date, $s['code'], 'TOPOLOGIE');
            $pcs = intval($sales['pcs']);
            if ($s['usePayment'] ?? false) {
                $amt = getPaymentSum($pdo, $date, $s['code']);
                if ($s['adjustTopup'] ?? false) {
                    $pcs -= getPaymentCount($pdo, $date, $s['code'], 'Top-up Marketing');
                    $amt -= getPaymentSum($pdo, $date, $s['code'], 'Top-up Marketing');
                }
            } else { $amt = floatval($sales['amount']); }
            $rowData[$s['key']] = ['pcs'=>$pcs, 'amt'=>$amt];
            $rowPcs += $pcs; $rowAmt += $amt;
            $totals[$s['key'].'_pcs'] += $pcs; $totals[$s['key'].'_amt'] += $amt;
        }
    ?>
    <tr class="<?= $rowClass ?>">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <?php foreach ($stores as $s): ?>
        <td class="num"><?= formatNum($rowData[$s['key']]['pcs']) ?></td>
        <td class="num"><?= formatNum($rowData[$s['key']]['amt']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td class="num"><?= formatNum($rowAmt) ?></td>
    </tr>
    <?php endfor;
    $grandPcs = 0; $grandAmt = 0;
    foreach ($stores as $s) { $grandPcs += $totals[$s['key'].'_pcs']; $grandAmt += $totals[$s['key'].'_amt']; }
    $targets = []; foreach ($stores as $s) { $targets[$s['key']] = getTarget($pdo, $selectedBrand, $s['key'], $selectedMonth); }
    $totalTarget = array_sum($targets);
    ?>
    <tr class="total-row">
        <td colspan="2">TOTAL</td>
        <?php foreach ($stores as $s): ?>
        <td class="num"><?= formatNum($totals[$s['key'].'_pcs']) ?></td>
        <td class="num"><?= formatNum($totals[$s['key'].'_amt']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($grandPcs) ?></td>
        <td class="num"><?= formatNum($grandAmt) ?></td>
    </tr>
    <tr class="target-row">
        <td colspan="2">TARGET</td>
        <?php foreach ($stores as $s): ?>
        <td>-</td><td class="num"><?= formatNum($targets[$s['key']]) ?></td>
        <?php endforeach; ?>
        <td>-</td><td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <?php foreach ($stores as $s): ?>
        <td>-</td><td><?= calcPct($totals[$s['key'].'_amt'], $targets[$s['key']]) ?></td>
        <?php endforeach; ?>
        <td>-</td><td><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>

<?php
// ============================================
// FREITAG
// ============================================
elseif ($selectedBrand === 'freitag'):
?>
<table border="1" cellpadding="3" cellspacing="0">
    <tr><td colspan="24" style="font-size:14pt; font-weight:bold;">Sale Target Freitag</td></tr>
    <tr><td colspan="24"></td></tr>
    <tr>
        <td colspan="2"></td>
        <td colspan="6" class="header-blue">U001-FRT</td>
        <td colspan="6" class="header-blue">U003-CHM</td>
        <td colspan="5" class="header-blue">U004-SIL</td>
        <td colspan="3" class="header-blue">SOUP</td>
        <td colspan="2"></td>
    </tr>
    <tr>
        <td colspan="2" class="header-light"><?= $sheetName ?></td>
        <td colspan="6" class="header-light">FREITAG - BKK</td>
        <td colspan="6" class="header-light">FREITAG - CM</td>
        <td colspan="5" class="header-light">FREITAG - SILOM</td>
        <td colspan="3" class="header-light">EMSPHERE</td>
        <td colspan="2" class="header-light">TOTAL</td>
    </tr>
    <tr>
        <td class="header-blue">DATE</td><td class="header-blue"></td>
        <td class="header-blue">PCS.</td><td class="header-blue">REPAIR</td><td class="header-blue">Amount</td><td class="header-blue">ONLINE</td><td class="header-blue">Amount</td><td class="header-blue">Total</td>
        <td class="header-blue">PCS.</td><td class="header-blue">REPAIR</td><td class="header-blue">Amount</td><td class="header-blue">ONLINE</td><td class="header-blue">Amount</td><td class="header-blue">Total</td>
        <td class="header-blue">PCS.</td><td class="header-blue">Amount</td><td class="header-blue">ONLINE</td><td class="header-blue">Amount</td><td class="header-blue">Total</td>
        <td class="header-blue">PCS.</td><td class="header-blue"></td><td class="header-blue">Amount</td>
        <td class="header-blue">PCS.</td><td class="header-blue">Total</td>
    </tr>
    <?php 
    $totals = array_fill_keys(['bkk_pcs','bkk_repair','bkk_amt','bkk_online_pcs','bkk_online_amt','bkk_total',
        'cm_pcs','cm_repair','cm_amt','cm_online_pcs','cm_online_amt','cm_total',
        'silom_pcs','silom_amt','silom_online_pcs','silom_online_amt','silom_total','soup_pcs','soup_amt'], 0);
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
        $rowClass = $day % 2 == 1 ? 'data-odd' : 'data-even';
        
        $bkk = getSalesSum($pdo, $date, 3010, 'FREITAG');
        $bkk_repair = getPaymentCount($pdo, $date, 3010, 'FREITAG REPAIR CASH');
        $bkk_online_pcs = getOnlinePcs($pdo, $date, 3010);
        $bkk_online_amt = getPaymentSum($pdo, $date, 3010, 'Online Cash');
        $bkk_amt = getPaymentSum($pdo, $date, 3010) - $bkk_online_amt;
        $bkk_total = getPaymentSum($pdo, $date, 3010);
        
        $cm = getSalesSum($pdo, $date, 3030, 'FREITAG');
        $cm_repair = getPaymentCount($pdo, $date, 3030, 'FREITAG REPAIR CASH');
        $cm_online_pcs = getOnlinePcs($pdo, $date, 3030);
        $cm_online_amt = getPaymentSum($pdo, $date, 3030, 'Online Cash');
        $cm_amt = getPaymentSum($pdo, $date, 3030) - $cm_online_amt;
        $cm_total = getPaymentSum($pdo, $date, 3030);
        
        $silom = getSalesSum($pdo, $date, 3060, 'FREITAG');
        $silom_online_pcs = getOnlinePcs($pdo, $date, 3060);
        $silom_online_amt = getPaymentSum($pdo, $date, 3060, 'Online Cash');
        $silom_amt = getPaymentSum($pdo, $date, 3060) - $silom_online_amt;
        $silom_total = getPaymentSum($pdo, $date, 3060);
        
        $soup = getSalesSum($pdo, $date, 6010, 'FREITAG');
        
        $totals['bkk_pcs'] += intval($bkk['pcs']); $totals['bkk_repair'] += $bkk_repair;
        $totals['bkk_amt'] += $bkk_amt; $totals['bkk_online_pcs'] += $bkk_online_pcs;
        $totals['bkk_online_amt'] += $bkk_online_amt; $totals['bkk_total'] += $bkk_total;
        $totals['cm_pcs'] += intval($cm['pcs']); $totals['cm_repair'] += $cm_repair;
        $totals['cm_amt'] += $cm_amt; $totals['cm_online_pcs'] += $cm_online_pcs;
        $totals['cm_online_amt'] += $cm_online_amt; $totals['cm_total'] += $cm_total;
        $totals['silom_pcs'] += intval($silom['pcs']); $totals['silom_amt'] += $silom_amt;
        $totals['silom_online_pcs'] += $silom_online_pcs; $totals['silom_online_amt'] += $silom_online_amt;
        $totals['silom_total'] += $silom_total;
        $totals['soup_pcs'] += intval($soup['pcs']); $totals['soup_amt'] += floatval($soup['amount']);
        
        $rowPcs = intval($bkk['pcs']) + intval($cm['pcs']) + intval($silom['pcs']) + intval($soup['pcs']);
        $rowAmt = $bkk_total + $cm_total + $silom_total + floatval($soup['amount']);
    ?>
    <tr class="<?= $rowClass ?>">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <td class="num"><?= formatNum($bkk['pcs']) ?></td>
        <td class="num"><?= formatNum($bkk_repair) ?></td>
        <td class="num"><?= formatNum($bkk_amt) ?></td>
        <td class="num"><?= formatNum($bkk_online_pcs) ?></td>
        <td class="num"><?= formatNum($bkk_online_amt) ?></td>
        <td class="num"><?= formatNum($bkk_total) ?></td>
        <td class="num"><?= formatNum($cm['pcs']) ?></td>
        <td class="num"><?= formatNum($cm_repair) ?></td>
        <td class="num"><?= formatNum($cm_amt) ?></td>
        <td class="num"><?= formatNum($cm_online_pcs) ?></td>
        <td class="num"><?= formatNum($cm_online_amt) ?></td>
        <td class="num"><?= formatNum($cm_total) ?></td>
        <td class="num"><?= formatNum($silom['pcs']) ?></td>
        <td class="num"><?= formatNum($silom_amt) ?></td>
        <td class="num"><?= formatNum($silom_online_pcs) ?></td>
        <td class="num"><?= formatNum($silom_online_amt) ?></td>
        <td class="num"><?= formatNum($silom_total) ?></td>
        <td class="num"><?= formatNum($soup['pcs']) ?></td>
        <td>-</td>
        <td class="num"><?= formatNum($soup['amount']) ?></td>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td class="num"><?= formatNum($rowAmt) ?></td>
    </tr>
    <?php endfor;
    $grandPcs = $totals['bkk_pcs'] + $totals['cm_pcs'] + $totals['silom_pcs'] + $totals['soup_pcs'];
    $grandAmt = $totals['bkk_total'] + $totals['cm_total'] + $totals['silom_total'] + $totals['soup_amt'];
    $targets = ['bkk'=>getTarget($pdo,$selectedBrand,'bkk',$selectedMonth),
                'cm'=>getTarget($pdo,$selectedBrand,'cm',$selectedMonth),
                'silom'=>getTarget($pdo,$selectedBrand,'silom',$selectedMonth),
                'soup'=>getTarget($pdo,$selectedBrand,'soup',$selectedMonth)];
    $totalTarget = array_sum($targets);
    ?>
    <tr class="total-row">
        <td colspan="2">TOTAL</td>
        <td class="num"><?= formatNum($totals['bkk_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['bkk_repair']) ?></td>
        <td class="num"><?= formatNum($totals['bkk_amt']) ?></td>
        <td class="num"><?= formatNum($totals['bkk_online_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['bkk_online_amt']) ?></td>
        <td class="num"><?= formatNum($totals['bkk_total']) ?></td>
        <td class="num"><?= formatNum($totals['cm_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['cm_repair']) ?></td>
        <td class="num"><?= formatNum($totals['cm_amt']) ?></td>
        <td class="num"><?= formatNum($totals['cm_online_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['cm_online_amt']) ?></td>
        <td class="num"><?= formatNum($totals['cm_total']) ?></td>
        <td class="num"><?= formatNum($totals['silom_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['silom_amt']) ?></td>
        <td class="num"><?= formatNum($totals['silom_online_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['silom_online_amt']) ?></td>
        <td class="num"><?= formatNum($totals['silom_total']) ?></td>
        <td class="num"><?= formatNum($totals['soup_pcs']) ?></td>
        <td>-</td>
        <td class="num"><?= formatNum($totals['soup_amt']) ?></td>
        <td class="num"><?= formatNum($grandPcs) ?></td>
        <td class="num"><?= formatNum($grandAmt) ?></td>
    </tr>
    <tr class="target-row">
        <td colspan="2">TARGET</td>
        <td colspan="5">-</td><td class="num"><?= formatNum($targets['bkk']) ?></td>
        <td colspan="5">-</td><td class="num"><?= formatNum($targets['cm']) ?></td>
        <td colspan="4">-</td><td class="num"><?= formatNum($targets['silom']) ?></td>
        <td colspan="2">-</td><td class="num"><?= formatNum($targets['soup']) ?></td>
        <td>-</td><td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <td colspan="5">-</td><td><?= calcPct($totals['bkk_total'], $targets['bkk']) ?></td>
        <td colspan="5">-</td><td><?= calcPct($totals['cm_total'], $targets['cm']) ?></td>
        <td colspan="4">-</td><td><?= calcPct($totals['silom_total'], $targets['silom']) ?></td>
        <td colspan="2">-</td><td><?= calcPct($totals['soup_amt'], $targets['soup']) ?></td>
        <td>-</td><td><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>

<?php
// ============================================
// SIMPLE BRANDS
// ============================================
elseif (in_array($selectedBrand, ['hooga', 'soup', 'sw19', 'izipizi', 'pavement', 'superdry', 'pronto'])):
    // Generic export for other brands
    echo "<p>Export for {$brandNames[$selectedBrand]} - Please use targets.php for now</p>";
endif;
?>
</body>
</html>