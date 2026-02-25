<?php
/**
 * Sale Targets - Excel Style View (All Brands)
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

// Handle target save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_target'])) {
    $targetData = $_POST['target'] ?? [];
    foreach ($targetData as $key => $value) {
        if ($value !== '') {
            $stmt = $pdo->prepare("INSERT INTO targets (brand, store_key, month, target_value) 
                                   VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE target_value = ?");
            $stmt->execute([$selectedBrand, $key, $selectedMonth, floatval(str_replace(',', '', $value)), floatval(str_replace(',', '', $value))]);
        }
    }
    header("Location: targets.php?brand=$selectedBrand&month=$selectedMonth&saved=1");
    exit;
}

// Helper functions
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
function calcPct($actual, $target) {
    if (!$target) return '-';
    return round($actual / $target * 100) . '%';
}
function pctClass($actual, $target) {
    if (!$target) return '';
    return ($actual / $target * 100) >= 100 ? 'percent-good' : 'percent-bad';
}

$brands = [
    'topologie' => 'TOPOLOGIE',
    'superdry' => 'SUPERDRY', 
    'pronto' => 'PRONTO',
    'freitag' => 'FREITAG',
    'hooga' => 'HOOGA',
    'soup' => 'SOUP',
    'sw19' => 'SW19',
    'izipizi' => 'IZIPIZI',
    'pavement' => 'PAVEMENT',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Target - <?= $brands[$selectedBrand] ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Calibri', Arial, sans-serif; font-size: 11px; background: #e0e0e0; }
        .navbar { background: #217346; color: white; padding: 8px 15px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; }
        .navbar a:hover { text-decoration: underline; }
        .sheet-tabs { background: #f0f0f0; padding: 5px 10px; border-bottom: 1px solid #ccc; display: flex; gap: 2px; overflow-x: auto; }
        .sheet-tab { padding: 6px 15px; background: #e0e0e0; border: 1px solid #ccc; border-bottom: none; cursor: pointer; font-size: 11px; white-space: nowrap; }
        .sheet-tab.active { background: white; border-bottom: 1px solid white; margin-bottom: -1px; }
        .sheet-tab:hover { background: #d0d0d0; }
        .toolbar { background: #f3f3f3; padding: 5px 10px; border-bottom: 1px solid #ccc; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .toolbar input[type="month"] { padding: 3px 8px; border: 1px solid #ccc; font-size: 11px; }
        .toolbar button { padding: 4px 12px; background: #217346; color: white; border: none; cursor: pointer; font-size: 11px; }
        .toolbar button:hover { background: #1a5c38; }
        .toolbar .btn-export { padding: 4px 12px; background: #0066cc; color: white; text-decoration: none; font-size: 11px; }
        .toolbar .btn-export:hover { background: #0052a3; }
        .toolbar .saved { color: green; font-weight: bold; }
        .excel-wrapper { background: white; margin: 0; overflow: auto; height: calc(100vh - 120px); }
        .excel { border-collapse: collapse; background: white; }
        .excel td, .excel th { border: 1px solid #d0d0d0; padding: 3px 6px; text-align: center; vertical-align: middle; height: 22px; white-space: nowrap; }
        .excel .brand-header { background: #4472c4; color: white; font-weight: bold; font-size: 11px; }
        .excel .store-header { background: #5b9bd5; color: white; font-weight: bold; }
        .excel .col-title { background: #4472c4; color: white; font-weight: bold; font-size: 10px; }
        .excel .date-cell { background: #ffc000; font-weight: bold; }
        .excel .data-row:nth-child(odd) td { background: #e9ecf1; }
        .excel .data-row:nth-child(even) td { background: #d6dce5; }
        .excel .data-row td.date-cell { background: #ffc000; }
        .excel .total-row td { background: #92d050; font-weight: bold; }
        .excel .target-row td { background: #ffc000; font-weight: bold; }
        .excel .percent-row td { background: #f4b183; font-weight: bold; }
        .excel .num { text-align: right; font-family: 'Calibri', monospace; }
        .excel .num-blue { color: #0066cc; }
        .excel .empty { background: white; }
        .excel input.target-input { width: 70px; padding: 2px 4px; text-align: right; border: 1px solid #999; font-family: 'Calibri', monospace; font-size: 11px; }
        .excel .percent-good { color: #006600; }
        .excel .percent-bad { color: #cc0000; }
    </style>
</head>
<body>
    <div class="navbar">
        <div><strong>📊 Sales Dashboard</strong><a href="index.php">หน้าหลัก</a><a href="upload.php">Upload</a></div>
        <div>Sale_target_<?= $brands[$selectedBrand] ?>_<?= $year ?>.xlsx</div>
    </div>
    <form method="POST">
    <div class="toolbar">
        <input type="hidden" name="save_target" value="1">
        <label>เดือน:</label>
        <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="location.href='?brand=<?= $selectedBrand ?>&month='+this.value">
        <span style="font-weight: bold;"><?= $sheetName ?></span>
        <button type="submit">💾 บันทึก Target</button>
        <a href="export_target.php?brand=<?= $selectedBrand ?>&month=<?= $selectedMonth ?>" class="btn-export">📥 Export Excel</a>
        <?php if (isset($_GET['saved'])): ?><span class="saved">✅ บันทึกแล้ว!</span><?php endif; ?>
    </div>
    <div class="sheet-tabs">
        <?php foreach ($brands as $key => $name): ?>
        <div class="sheet-tab <?= $selectedBrand === $key ? 'active' : '' ?>" onclick="location.href='?brand=<?= $key ?>&month=<?= $selectedMonth ?>'"><?= $name ?></div>
        <?php endforeach; ?>
    </div>
    <div class="excel-wrapper">

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
<table class="excel">
    <tr><td colspan="16" style="text-align:left; font-size:14px; font-weight:bold; padding:10px; background:#fff;">Sale Target Topologie</td></tr>
    <tr><td colspan="16" class="empty" style="height:10px;"></td></tr>
    <tr>
        <td colspan="2" class="empty"></td>
        <?php foreach ($stores as $s): ?><td colspan="2" class="brand-header">TOPOLOGIE</td><?php endforeach; ?>
        <td colspan="2" class="empty"></td>
    </tr>
    <tr>
        <td colspan="2" class="store-header"><?= $sheetName ?></td>
        <?php foreach ($stores as $s): ?><td colspan="2" class="store-header"><?= $s['name'] ?></td><?php endforeach; ?>
        <td colspan="2" class="store-header">TOTAL</td>
    </tr>
    <tr>
        <td class="col-title">DATE</td><td class="col-title"></td>
        <?php foreach ($stores as $s): ?><td class="col-title">PCS.</td><td class="col-title">Amount</td><?php endforeach; ?>
        <td class="col-title">PCS.</td><td class="col-title">SALE AMOUNT</td>
    </tr>
    <?php 
    $totals = []; foreach ($stores as $s) { $totals[$s['key'].'_pcs']=0; $totals[$s['key'].'_amt']=0; }
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
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
    <tr class="data-row">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <?php foreach ($stores as $s): ?>
        <td class="num"><?= formatNum($rowData[$s['key']]['pcs']) ?></td>
        <td class="num num-blue"><?= formatNum($rowData[$s['key']]['amt']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td class="num num-blue"><?= formatNum($rowAmt) ?></td>
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
        <td>-</td>
        <td><input type="text" name="target[<?= $s['key'] ?>]" class="target-input" value="<?= $targets[$s['key']] ? number_format($targets[$s['key']],0) : '' ?>"></td>
        <?php endforeach; ?>
        <td>-</td><td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <?php foreach ($stores as $s): ?>
        <td>-</td>
        <td class="<?= pctClass($totals[$s['key'].'_amt'], $targets[$s['key']]) ?>"><?= calcPct($totals[$s['key'].'_amt'], $targets[$s['key']]) ?></td>
        <?php endforeach; ?>
        <td>-</td><td class="<?= pctClass($grandAmt, $totalTarget) ?>"><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>

<?php
// ============================================
// SUPERDRY
// ============================================
elseif ($selectedBrand === 'superdry'):
    $stores = [
        ['key'=>'paragon', 'name'=>'PARAGON', 'code'=>8010, 'hasBag'=>false],
        ['key'=>'ctw', 'name'=>'CENTRALWORLD', 'code'=>9030, 'hasBag'=>true],
        ['key'=>'ladprao', 'name'=>'LADPRAO', 'code'=>9010, 'hasBag'=>true],
        ['key'=>'t21', 'name'=>'TERMINAL 21', 'code'=>9110, 'hasBag'=>true],
        ['key'=>'pkt', 'name'=>'PKT', 'code'=>4120, 'hasBag'=>true],
        ['key'=>'pattaya', 'name'=>'PATTAYA', 'code'=>9130, 'hasBag'=>true],
        ['key'=>'jungceylon', 'name'=>'JUNGCEYLON', 'code'=>9100, 'hasBag'=>true],
        ['key'=>'mega', 'name'=>'MEGA', 'code'=>9160, 'hasBag'=>true],
        ['key'=>'village', 'name'=>'VILLAGE', 'code'=>9140, 'hasBag'=>true],
    ];
?>
<table class="excel">
    <tr><td colspan="30" style="text-align:left; font-size:14px; font-weight:bold; padding:10px; background:#fff;">Sale Target Superdry</td></tr>
    <tr><td colspan="30" class="empty" style="height:10px;"></td></tr>
    <tr>
        <td colspan="2" class="empty"></td>
        <?php foreach ($stores as $s): ?><td colspan="<?= $s['hasBag'] ? 3 : 2 ?>" class="brand-header"><?= $s['name'] ?></td><?php endforeach; ?>
        <td colspan="2" class="empty"></td>
    </tr>
    <tr>
        <td colspan="2" class="store-header"><?= $sheetName ?></td>
        <?php foreach ($stores as $s): ?><td colspan="<?= $s['hasBag'] ? 3 : 2 ?>" class="store-header"><?= $s['name'] ?></td><?php endforeach; ?>
        <td colspan="2" class="store-header">TOTAL</td>
    </tr>
    <tr>
        <td class="col-title">DATE</td><td class="col-title"></td>
        <?php foreach ($stores as $s): ?>
        <td class="col-title">PCS.</td>
        <?php if ($s['hasBag']): ?><td class="col-title">Bag</td><?php endif; ?>
        <td class="col-title">SALE</td>
        <?php endforeach; ?>
        <td class="col-title">PCS.</td><td class="col-title">SALE</td>
    </tr>
    <?php 
    $totals = []; foreach ($stores as $s) { $totals[$s['key'].'_pcs']=0; $totals[$s['key'].'_bag']=0; $totals[$s['key'].'_amt']=0; }
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
        $rowData = []; $rowPcs = 0; $rowAmt = 0;
        foreach ($stores as $s) {
            $sales = getSalesSum($pdo, $date, $s['code']);
            $pcs = intval($sales['pcs']);
            $bag = $s['hasBag'] ? getSalesBag($pdo, $date, $s['code']) : 0;
            $amt = getPaymentSum($pdo, $date, $s['code']);
            $rowData[$s['key']] = ['pcs'=>$pcs, 'bag'=>$bag, 'amt'=>$amt];
            $rowPcs += $pcs; $rowAmt += $amt;
            $totals[$s['key'].'_pcs'] += $pcs; $totals[$s['key'].'_bag'] += $bag; $totals[$s['key'].'_amt'] += $amt;
        }
    ?>
    <tr class="data-row">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <?php foreach ($stores as $s): ?>
        <td class="num"><?= formatNum($rowData[$s['key']]['pcs']) ?></td>
        <?php if ($s['hasBag']): ?><td class="num"><?= formatNum($rowData[$s['key']]['bag']) ?></td><?php endif; ?>
        <td class="num num-blue"><?= formatNum($rowData[$s['key']]['amt']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td class="num num-blue"><?= formatNum($rowAmt) ?></td>
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
        <?php if ($s['hasBag']): ?><td class="num"><?= formatNum($totals[$s['key'].'_bag']) ?></td><?php endif; ?>
        <td class="num"><?= formatNum($totals[$s['key'].'_amt']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($grandPcs) ?></td>
        <td class="num"><?= formatNum($grandAmt) ?></td>
    </tr>
    <tr class="target-row">
        <td colspan="2">TARGET</td>
        <?php foreach ($stores as $s): ?>
        <td>-</td>
        <?php if ($s['hasBag']): ?><td>-</td><?php endif; ?>
        <td><input type="text" name="target[<?= $s['key'] ?>]" class="target-input" value="<?= $targets[$s['key']] ? number_format($targets[$s['key']],0) : '' ?>"></td>
        <?php endforeach; ?>
        <td>-</td><td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <?php foreach ($stores as $s): ?>
        <td>-</td>
        <?php if ($s['hasBag']): ?><td>-</td><?php endif; ?>
        <td class="<?= pctClass($totals[$s['key'].'_amt'], $targets[$s['key']]) ?>"><?= calcPct($totals[$s['key'].'_amt'], $targets[$s['key']]) ?></td>
        <?php endforeach; ?>
        <td>-</td><td class="<?= pctClass($grandAmt, $totalTarget) ?>"><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>

<?php
// ============================================
// PRONTO
// ============================================
elseif ($selectedBrand === 'pronto'):
    $stores = [
        ['key'=>'ladprao', 'name'=>'LADPRAO', 'code'=>2010],
        ['key'=>'rama9', 'name'=>'RAMA 9', 'code'=>2030],
        ['key'=>'mega', 'name'=>'MEGA BANGNA', 'code'=>2080],
        ['key'=>'festival', 'name'=>'FESTIVAL', 'code'=>2090],
        ['key'=>'paragon', 'name'=>'PARAGON', 'code'=>2020],
        ['key'=>'one', 'name'=>'ONE BANGKOK', 'code'=>7020],
        ['key'=>'think', 'name'=>'THINK', 'code'=>7030],
        ['key'=>'online', 'name'=>'ONLINE', 'code'=>2009],
    ];
?>
<table class="excel">
    <tr><td colspan="50" style="text-align:left; font-size:14px; font-weight:bold; padding:10px; background:#fff;">Sale Target Pronto</td></tr>
    <tr><td colspan="50" class="empty" style="height:10px;"></td></tr>
    <tr>
        <td colspan="2" class="empty"></td>
        <?php foreach ($stores as $s): ?><td colspan="5" class="brand-header"><?= $s['name'] ?></td><?php endforeach; ?>
        <td colspan="2" class="empty"></td>
    </tr>
    <tr>
        <td colspan="2" class="store-header"><?= $sheetName ?></td>
        <?php foreach ($stores as $s): ?><td colspan="5" class="store-header"><?= $s['name'] ?></td><?php endforeach; ?>
        <td colspan="2" class="store-header">TOTAL</td>
    </tr>
    <tr>
        <td class="col-title">DATE</td><td class="col-title"></td>
        <?php foreach ($stores as $s): ?>
        <td class="col-title">FULL</td><td class="col-title">SALE</td><td class="col-title">Freitag</td><td class="col-title">S-Bag</td><td class="col-title">Total</td>
        <?php endforeach; ?>
        <td class="col-title">PCS.</td><td class="col-title">Total</td>
    </tr>
    <?php 
    $totals = []; foreach ($stores as $s) { 
        $totals[$s['key'].'_full']=0; $totals[$s['key'].'_sale']=0; $totals[$s['key'].'_freitag']=0; 
        $totals[$s['key'].'_sbag']=0; $totals[$s['key'].'_total']=0; 
    }
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
        $rowData = []; $rowPcs = 0; $rowAmt = 0;
        foreach ($stores as $s) {
            $all = getSalesSum($pdo, $date, $s['code']);
            $freitag = getSalesSum($pdo, $date, $s['code'], 'FREITAG');
            $sbag = getSalesSBag($pdo, $date, $s['code']);
            $sale = getSalesNegative($pdo, $date, $s['code']);
            $full = intval($all['pcs']) - intval($freitag['pcs']) - $sbag;
            if ($full < 0) $full = 0;
            $total = getPaymentSum($pdo, $date, $s['code']);
            if ($s['key'] === 'online') $total = getPaymentSum($pdo, $date, $s['code'], 'OMISE');
            $rowData[$s['key']] = ['full'=>$full, 'sale'=>$sale, 'freitag'=>intval($freitag['pcs']), 'sbag'=>$sbag, 'total'=>$total];
            $rowPcs += $full + $sale + intval($freitag['pcs']) + $sbag; $rowAmt += $total;
            $totals[$s['key'].'_full'] += $full; $totals[$s['key'].'_sale'] += $sale;
            $totals[$s['key'].'_freitag'] += intval($freitag['pcs']); $totals[$s['key'].'_sbag'] += $sbag;
            $totals[$s['key'].'_total'] += $total;
        }
    ?>
    <tr class="data-row">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <?php foreach ($stores as $s): ?>
        <td class="num"><?= formatNum($rowData[$s['key']]['full']) ?></td>
        <td class="num"><?= formatNum($rowData[$s['key']]['sale']) ?></td>
        <td class="num"><?= formatNum($rowData[$s['key']]['freitag']) ?></td>
        <td class="num"><?= formatNum($rowData[$s['key']]['sbag']) ?></td>
        <td class="num num-blue"><?= formatNum($rowData[$s['key']]['total']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td class="num num-blue"><?= formatNum($rowAmt) ?></td>
    </tr>
    <?php endfor;
    $grandPcs = 0; $grandAmt = 0;
    foreach ($stores as $s) { 
        $grandPcs += $totals[$s['key'].'_full'] + $totals[$s['key'].'_sale'] + $totals[$s['key'].'_freitag'] + $totals[$s['key'].'_sbag']; 
        $grandAmt += $totals[$s['key'].'_total']; 
    }
    $targets = []; foreach ($stores as $s) { $targets[$s['key']] = getTarget($pdo, $selectedBrand, $s['key'], $selectedMonth); }
    $totalTarget = array_sum($targets);
    ?>
    <tr class="total-row">
        <td colspan="2">TOTAL</td>
        <?php foreach ($stores as $s): ?>
        <td class="num"><?= formatNum($totals[$s['key'].'_full']) ?></td>
        <td class="num"><?= formatNum($totals[$s['key'].'_sale']) ?></td>
        <td class="num"><?= formatNum($totals[$s['key'].'_freitag']) ?></td>
        <td class="num"><?= formatNum($totals[$s['key'].'_sbag']) ?></td>
        <td class="num"><?= formatNum($totals[$s['key'].'_total']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($grandPcs) ?></td>
        <td class="num"><?= formatNum($grandAmt) ?></td>
    </tr>
    <tr class="target-row">
        <td colspan="2">TARGET</td>
        <?php foreach ($stores as $s): ?>
        <td colspan="4">-</td>
        <td><input type="text" name="target[<?= $s['key'] ?>]" class="target-input" value="<?= $targets[$s['key']] ? number_format($targets[$s['key']],0) : '' ?>"></td>
        <?php endforeach; ?>
        <td>-</td><td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <?php foreach ($stores as $s): ?>
        <td colspan="4">-</td>
        <td class="<?= pctClass($totals[$s['key'].'_total'], $targets[$s['key']]) ?>"><?= calcPct($totals[$s['key'].'_total'], $targets[$s['key']]) ?></td>
        <?php endforeach; ?>
        <td>-</td><td class="<?= pctClass($grandAmt, $totalTarget) ?>"><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>

<?php
// ============================================
// FREITAG (ไม่มี SOUP)
// ============================================
elseif ($selectedBrand === 'freitag'):
?>
<table class="excel">
    <tr><td colspan="22" style="text-align:left; font-size:14px; font-weight:bold; padding:10px; background:#fff;">Sale Target Freitag</td></tr>
    <tr><td colspan="22" class="empty" style="height:10px;"></td></tr>
    <tr>
        <td colspan="2" class="empty"></td>
        <td colspan="6" class="brand-header">U001-FRT</td>
        <td colspan="6" class="brand-header">U003-CHM</td>
        <td colspan="5" class="brand-header">U004-SIL</td>
        <td colspan="3" class="empty"></td>
    </tr>
    <tr>
        <td colspan="2" class="store-header"><?= $sheetName ?></td>
        <td colspan="6" class="store-header">FREITAG - BKK</td>
        <td colspan="6" class="store-header">FREITAG - CM</td>
        <td colspan="5" class="store-header">FREITAG - SILOM</td>
        <td colspan="3" class="store-header">TOTAL</td>
    </tr>
    <tr>
        <td class="col-title">DATE</td><td class="col-title"></td>
        <td class="col-title">PCS.</td><td class="col-title">REPAIR</td><td class="col-title">Amount</td><td class="col-title">ONLINE</td><td class="col-title">Amount</td><td class="col-title">Total</td>
        <td class="col-title">PCS.</td><td class="col-title">REPAIR</td><td class="col-title">Amount</td><td class="col-title">ONLINE</td><td class="col-title">Amount</td><td class="col-title">Total</td>
        <td class="col-title">PCS.</td><td class="col-title">Amount</td><td class="col-title">ONLINE</td><td class="col-title">Amount</td><td class="col-title">Total</td>
        <td class="col-title">PCS.</td><td class="col-title"></td><td class="col-title">Total</td>
    </tr>
    <?php 
    $totals = array_fill_keys(['bkk_pcs','bkk_repair','bkk_amt','bkk_online_pcs','bkk_online_amt','bkk_total',
        'cm_pcs','cm_repair','cm_amt','cm_online_pcs','cm_online_amt','cm_total',
        'silom_pcs','silom_amt','silom_online_pcs','silom_online_amt','silom_total'], 0);
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
        
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
        
        $totals['bkk_pcs'] += intval($bkk['pcs']); $totals['bkk_repair'] += $bkk_repair;
        $totals['bkk_amt'] += $bkk_amt; $totals['bkk_online_pcs'] += $bkk_online_pcs;
        $totals['bkk_online_amt'] += $bkk_online_amt; $totals['bkk_total'] += $bkk_total;
        $totals['cm_pcs'] += intval($cm['pcs']); $totals['cm_repair'] += $cm_repair;
        $totals['cm_amt'] += $cm_amt; $totals['cm_online_pcs'] += $cm_online_pcs;
        $totals['cm_online_amt'] += $cm_online_amt; $totals['cm_total'] += $cm_total;
        $totals['silom_pcs'] += intval($silom['pcs']); $totals['silom_amt'] += $silom_amt;
        $totals['silom_online_pcs'] += $silom_online_pcs; $totals['silom_online_amt'] += $silom_online_amt;
        $totals['silom_total'] += $silom_total;
        
        $rowPcs = intval($bkk['pcs']) + intval($cm['pcs']) + intval($silom['pcs']);
        $rowAmt = $bkk_total + $cm_total + $silom_total;
    ?>
    <tr class="data-row">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <td class="num"><?= formatNum($bkk['pcs']) ?></td>
        <td class="num"><?= formatNum($bkk_repair) ?></td>
        <td class="num num-blue"><?= formatNum($bkk_amt) ?></td>
        <td class="num"><?= formatNum($bkk_online_pcs) ?></td>
        <td class="num num-blue"><?= formatNum($bkk_online_amt) ?></td>
        <td class="num num-blue"><?= formatNum($bkk_total) ?></td>
        <td class="num"><?= formatNum($cm['pcs']) ?></td>
        <td class="num"><?= formatNum($cm_repair) ?></td>
        <td class="num num-blue"><?= formatNum($cm_amt) ?></td>
        <td class="num"><?= formatNum($cm_online_pcs) ?></td>
        <td class="num num-blue"><?= formatNum($cm_online_amt) ?></td>
        <td class="num num-blue"><?= formatNum($cm_total) ?></td>
        <td class="num"><?= formatNum($silom['pcs']) ?></td>
        <td class="num num-blue"><?= formatNum($silom_amt) ?></td>
        <td class="num"><?= formatNum($silom_online_pcs) ?></td>
        <td class="num num-blue"><?= formatNum($silom_online_amt) ?></td>
        <td class="num num-blue"><?= formatNum($silom_total) ?></td>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td>-</td>
        <td class="num num-blue"><?= formatNum($rowAmt) ?></td>
    </tr>
    <?php endfor;
    $grandPcs = $totals['bkk_pcs'] + $totals['cm_pcs'] + $totals['silom_pcs'];
    $grandAmt = $totals['bkk_total'] + $totals['cm_total'] + $totals['silom_total'];
    $targets = ['bkk'=>getTarget($pdo,$selectedBrand,'bkk',$selectedMonth),
                'cm'=>getTarget($pdo,$selectedBrand,'cm',$selectedMonth),
                'silom'=>getTarget($pdo,$selectedBrand,'silom',$selectedMonth)];
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
        <td class="num"><?= formatNum($grandPcs) ?></td>
        <td>-</td>
        <td class="num"><?= formatNum($grandAmt) ?></td>
    </tr>
    <tr class="target-row">
        <td colspan="2">TARGET</td>
        <td colspan="5">-</td>
        <td><input type="text" name="target[bkk]" class="target-input" value="<?= $targets['bkk'] ? number_format($targets['bkk'],0) : '' ?>"></td>
        <td colspan="5">-</td>
        <td><input type="text" name="target[cm]" class="target-input" value="<?= $targets['cm'] ? number_format($targets['cm'],0) : '' ?>"></td>
        <td colspan="4">-</td>
        <td><input type="text" name="target[silom]" class="target-input" value="<?= $targets['silom'] ? number_format($targets['silom'],0) : '' ?>"></td>
        <td colspan="2">-</td>
        <td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <td colspan="5">-</td>
        <td class="<?= pctClass($totals['bkk_total'], $targets['bkk']) ?>"><?= calcPct($totals['bkk_total'], $targets['bkk']) ?></td>
        <td colspan="5">-</td>
        <td class="<?= pctClass($totals['cm_total'], $targets['cm']) ?>"><?= calcPct($totals['cm_total'], $targets['cm']) ?></td>
        <td colspan="4">-</td>
        <td class="<?= pctClass($totals['silom_total'], $targets['silom']) ?>"><?= calcPct($totals['silom_total'], $targets['silom']) ?></td>
        <td colspan="2">-</td>
        <td class="<?= pctClass($grandAmt, $totalTarget) ?>"><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>

<?php
// ============================================
// SOUP (EMSPHERE - แยก SOUP กับ FREITAG)
// ============================================
elseif ($selectedBrand === 'soup'):
?>
<table class="excel">
    <tr><td colspan="10" style="text-align:left; font-size:14px; font-weight:bold; padding:10px; background:#fff;">Sale Target Soup</td></tr>
    <tr><td colspan="10" class="empty" style="height:10px;"></td></tr>
    <tr>
        <td colspan="2" class="empty"></td>
        <td colspan="2" class="brand-header">SOUP</td>
        <td colspan="2" class="brand-header">FREITAG</td>
        <td colspan="2" class="empty"></td>
    </tr>
    <tr>
        <td colspan="2" class="store-header"><?= $sheetName ?></td>
        <td colspan="2" class="store-header">EMSPHERE</td>
        <td colspan="2" class="store-header"></td>
        <td colspan="2" class="store-header">TOTAL</td>
    </tr>
    <tr>
        <td class="col-title">DATE</td><td class="col-title"></td>
        <td class="col-title">PCS.</td><td class="col-title">Amount</td>
        <td class="col-title">PCS.</td><td class="col-title">Amount</td>
        <td class="col-title">PCS.</td><td class="col-title">SALE AMOUNT</td>
    </tr>
    <?php 
    $totals = ['soup_pcs'=>0,'soup_amt'=>0,'freitag_pcs'=>0,'freitag_amt'=>0];
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
        
        // SOUP = ยอดทั้งหมดใน 6010 ที่ไม่ใช่ FREITAG
        $all = getSalesSum($pdo, $date, 6010);
        $freitag = getSalesSum($pdo, $date, 6010, 'FREITAG');
        
        $soup_pcs = intval($all['pcs']) - intval($freitag['pcs']);
        $soup_amt = floatval($all['amount']) - floatval($freitag['amount']);
        $freitag_pcs = intval($freitag['pcs']);
        $freitag_amt = floatval($freitag['amount']);
        
        $totals['soup_pcs'] += $soup_pcs;
        $totals['soup_amt'] += $soup_amt;
        $totals['freitag_pcs'] += $freitag_pcs;
        $totals['freitag_amt'] += $freitag_amt;
        
        $rowPcs = $soup_pcs + $freitag_pcs;
        $rowAmt = $soup_amt + $freitag_amt;
    ?>
    <tr class="data-row">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <td class="num"><?= formatNum($soup_pcs) ?></td>
        <td class="num num-blue"><?= formatNum($soup_amt) ?></td>
        <td class="num"><?= formatNum($freitag_pcs) ?></td>
        <td class="num num-blue"><?= formatNum($freitag_amt) ?></td>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td class="num num-blue"><?= formatNum($rowAmt) ?></td>
    </tr>
    <?php endfor;
    $grandPcs = $totals['soup_pcs'] + $totals['freitag_pcs'];
    $grandAmt = $totals['soup_amt'] + $totals['freitag_amt'];
    $targets = ['soup'=>getTarget($pdo,$selectedBrand,'soup',$selectedMonth),
                'freitag'=>getTarget($pdo,$selectedBrand,'freitag',$selectedMonth)];
    $totalTarget = array_sum($targets);
    ?>
    <tr class="total-row">
        <td colspan="2">TOTAL</td>
        <td class="num"><?= formatNum($totals['soup_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['soup_amt']) ?></td>
        <td class="num"><?= formatNum($totals['freitag_pcs']) ?></td>
        <td class="num"><?= formatNum($totals['freitag_amt']) ?></td>
        <td class="num"><?= formatNum($grandPcs) ?></td>
        <td class="num"><?= formatNum($grandAmt) ?></td>
    </tr>
    <tr class="target-row">
        <td colspan="2">TARGET</td>
        <td>-</td>
        <td><input type="text" name="target[soup]" class="target-input" value="<?= $targets['soup'] ? number_format($targets['soup'],0) : '' ?>"></td>
        <td>-</td>
        <td><input type="text" name="target[freitag]" class="target-input" value="<?= $targets['freitag'] ? number_format($targets['freitag'],0) : '' ?>"></td>
        <td>-</td><td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <td>-</td>
        <td class="<?= pctClass($totals['soup_amt'], $targets['soup']) ?>"><?= calcPct($totals['soup_amt'], $targets['soup']) ?></td>
        <td>-</td>
        <td class="<?= pctClass($totals['freitag_amt'], $targets['freitag']) ?>"><?= calcPct($totals['freitag_amt'], $targets['freitag']) ?></td>
        <td>-</td><td class="<?= pctClass($grandAmt, $totalTarget) ?>"><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>

<?php
// ============================================
// SIMPLE BRANDS (HOOGA, SW19, IZIPIZI, PAVEMENT)
// ============================================
elseif (in_array($selectedBrand, ['hooga', 'sw19', 'izipizi', 'pavement'])):
    $brandConfig = [
        'hooga' => ['title'=>'Hooga', 'stores'=>[['key'=>'ctw','name'=>'Central World','code'=>10010]]],
        'sw19' => ['title'=>'SW19', 'stores'=>[
            ['key'=>'ctw','name'=>'CENTRAL WORLD','code'=>13010],
            ['key'=>'lazada','name'=>'LAZADA','code'=>2009,'brand'=>'SW19']
        ]],
        'izipizi' => ['title'=>'Izipizi', 'stores'=>[['key'=>'ctw','name'=>'TOPOLOGIE CTW','code'=>3020,'brand'=>'IZIPIZI']]],
        'pavement' => ['title'=>'Pavement', 'stores'=>[['key'=>'website','name'=>'WEBSITE','code'=>5020]]],
    ];
    $config = $brandConfig[$selectedBrand];
    $stores = $config['stores'];
?>
<table class="excel">
    <tr><td colspan="10" style="text-align:left; font-size:14px; font-weight:bold; padding:10px; background:#fff;">Sale Target <?= $config['title'] ?></td></tr>
    <tr><td colspan="10" class="empty" style="height:10px;"></td></tr>
    <tr>
        <td colspan="2" class="empty"></td>
        <?php foreach ($stores as $s): ?><td colspan="2" class="brand-header"><?= strtoupper($config['title']) ?></td><?php endforeach; ?>
        <td colspan="2" class="empty"></td>
    </tr>
    <tr>
        <td colspan="2" class="store-header"><?= $sheetName ?></td>
        <?php foreach ($stores as $s): ?><td colspan="2" class="store-header"><?= $s['name'] ?></td><?php endforeach; ?>
        <td colspan="2" class="store-header">TOTAL</td>
    </tr>
    <tr>
        <td class="col-title">DATE</td><td class="col-title"></td>
        <?php foreach ($stores as $s): ?><td class="col-title">PCS.</td><td class="col-title">Amount</td><?php endforeach; ?>
        <td class="col-title">PCS.</td><td class="col-title">SALE AMOUNT</td>
    </tr>
    <?php 
    $totals = []; foreach ($stores as $s) { $totals[$s['key'].'_pcs']=0; $totals[$s['key'].'_amt']=0; }
    for ($day = 1; $day <= $daysInMonth; $day++):
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dow = date('N', strtotime($date)) - 1;
        $rowData = []; $rowPcs = 0; $rowAmt = 0;
        foreach ($stores as $s) {
            $brand = $s['brand'] ?? null;
            $sales = getSalesSum($pdo, $date, $s['code'], $brand);
            $pcs = intval($sales['pcs']);
            $amt = ($selectedBrand === 'pavement' || isset($s['brand'])) ? floatval($sales['amount']) : getPaymentSum($pdo, $date, $s['code']);
            $rowData[$s['key']] = ['pcs'=>$pcs, 'amt'=>$amt];
            $rowPcs += $pcs; $rowAmt += $amt;
            $totals[$s['key'].'_pcs'] += $pcs; $totals[$s['key'].'_amt'] += $amt;
        }
    ?>
    <tr class="data-row">
        <td class="date-cell"><?= $dayNames[$dow] ?></td><td class="date-cell"><?= $day ?></td>
        <?php foreach ($stores as $s): ?>
        <td class="num"><?= formatNum($rowData[$s['key']]['pcs']) ?></td>
        <td class="num num-blue"><?= formatNum($rowData[$s['key']]['amt']) ?></td>
        <?php endforeach; ?>
        <td class="num"><?= formatNum($rowPcs) ?></td>
        <td class="num num-blue"><?= formatNum($rowAmt) ?></td>
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
        <td>-</td>
        <td><input type="text" name="target[<?= $s['key'] ?>]" class="target-input" value="<?= $targets[$s['key']] ? number_format($targets[$s['key']],0) : '' ?>"></td>
        <?php endforeach; ?>
        <td>-</td><td class="num"><?= formatNum($totalTarget) ?></td>
    </tr>
    <tr class="percent-row">
        <td colspan="2">%</td>
        <?php foreach ($stores as $s): ?>
        <td>-</td>
        <td class="<?= pctClass($totals[$s['key'].'_amt'], $targets[$s['key']]) ?>"><?= calcPct($totals[$s['key'].'_amt'], $targets[$s['key']]) ?></td>
        <?php endforeach; ?>
        <td>-</td><td class="<?= pctClass($grandAmt, $totalTarget) ?>"><?= calcPct($grandAmt, $totalTarget) ?></td>
    </tr>
</table>
<?php endif; ?>

    </div>
    </form>
</body>
</html>