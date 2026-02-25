<?php
/**
 * Cegid Live Salesperson Report
 * ==============================
 * รายงานยอดขายตามพนักงานขาย
 */

// =====================================================
// CONFIGURATION
// =====================================================
define('CEGID_BASE_URL', 'https://90643827-test-retail-ondemand.cegid.cloud');
define('CEGID_DOMAIN', '90643827_002_TEST');
define('CEGID_USERNAME', 'frt');
define('CEGID_PASSWORD', 'adgjm');
define('CEGID_FULL_USERNAME', CEGID_DOMAIN . '\\' . CEGID_USERNAME);

date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

// =====================================================
// SOAP CLIENT
// =====================================================
class CegidAPI {
    private $baseUrl;
    private $credentials;
    
    public function __construct() {
        $this->baseUrl = CEGID_BASE_URL;
        $this->credentials = CEGID_FULL_USERNAME . ':' . CEGID_PASSWORD;
    }
    
    private function request($endpoint, $soapAction, $soapBody) {
        $url = $this->baseUrl . $endpoint;
        $soapEnvelope = '<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://www.cegid.fr/Retail/1.0">
    <soapenv:Header/>
    <soapenv:Body>' . $soapBody . '</soapenv:Body>
</soapenv:Envelope>';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: "' . $soapAction . '"'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->credentials,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ['success' => ($httpCode >= 200 && $httpCode < 300), 'response' => $response, 'http_code' => $httpCode];
    }
    
    public function getSaleHeaders($dateFrom, $dateTo, $pageIndex = 1, $pageSize = 100) {
        $soapBody = '
        <ns:GetHeaderList>
            <ns:searchRequest>
                <ns:BeginDate>' . $dateFrom . '</ns:BeginDate>
                <ns:EndDate>' . $dateTo . '</ns:EndDate>
                <ns:DocumentTypes><ns:SaleDocumentType>Receipt</ns:SaleDocumentType></ns:DocumentTypes>
                <ns:Pager><ns:PageIndex>' . $pageIndex . '</ns:PageIndex><ns:PageSize>' . $pageSize . '</ns:PageSize></ns:Pager>
            </ns:searchRequest>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetHeaderList>';
        return $this->request('/Y2/SaleDocumentService.svc', 'http://www.cegid.fr/Retail/1.0/ISaleDocumentService/GetHeaderList', $soapBody);
    }
    
    public function getSaleDetail($type, $stump, $number) {
        $soapBody = '
        <ns:GetByKey>
            <ns:searchRequest>
                <ns:Key>
                    <ns:Number>' . htmlspecialchars($number) . '</ns:Number>
                    <ns:Stump>' . htmlspecialchars($stump) . '</ns:Stump>
                    <ns:Type>' . htmlspecialchars($type) . '</ns:Type>
                </ns:Key>
            </ns:searchRequest>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetByKey>';
        return $this->request('/Y2/SaleDocumentService.svc', 'http://www.cegid.fr/Retail/1.0/ISaleDocumentService/GetByKey', $soapBody);
    }
}

function extractXmlValue($xml, $tag) {
    if (preg_match('/<(?:[^:>]*:)?' . $tag . '[^>]*>([^<]*)<\/(?:[^:>]*:)?' . $tag . '>/s', $xml, $m)) {
        return trim($m[1]);
    }
    return null;
}

$storeNames = [
    '10000' => 'ULG DC OFFICE', '11010' => 'PRONTO CENTRAL LARDPRAO', '11020' => 'SUPERDRY LARDPRAO',
    '11040' => 'TOPOLOGIE LADPRAO', '12010' => 'PRONTO CENTRAL RAMA 9', '14020' => 'SUPERDRY CENTRAL WORLD',
    '14040' => 'TOPOLOGIE CENTRAL WORLD', '17020' => 'SOUP EMSPHERE', '19010' => 'PRONTO MEGA BANGNA',
    '20010' => 'PRONTO SIAM PARAGON', '02009' => 'Pronto Online', '02010' => 'Soup Online',
];

$brandNames = [
    'FRT' => 'FREITAG', 'NDI' => 'NUDIE', 'SVW' => 'SERVICE WORKS', 'TLR' => 'TELLURIDE',
    'DUS' => 'DEUS', 'ONE' => 'ONE OF THESE DAYS', 'SAA' => 'SAAD', 'GPZ' => 'GRAPHZERO',
    'FIS' => 'FILSON', 'TLG' => 'TOPOLOGIE', 'SPD' => 'SUPERDRY', 'FLO' => 'FLOYD',
];

// =====================================================
// MAIN LOGIC
// =====================================================
$api = new CegidAPI();
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$maxDocs = min((int)($_GET['limit'] ?? 50), 100);
$viewPerson = $_GET['view'] ?? null;

$bySalesperson = [];
$personDetails = [];
$totalSales = 0;
$totalBills = 0;
$docsProcessed = 0;
$error = null;

// Step 1: Get Headers
$result = $api->getSaleHeaders($dateFrom, $dateTo, 1, $maxDocs);
$headers = [];

if ($result['success']) {
    if (preg_match_all('/<Get_Header>(.*?)<\/Get_Header>/s', $result['response'], $matches)) {
        foreach ($matches[1] as $headerXml) {
            if (extractXmlValue($headerXml, 'Active') !== 'true') continue;
            
            $header = [
                'store_id' => extractXmlValue($headerXml, 'StoreId'),
                'date' => substr(extractXmlValue($headerXml, 'Date') ?? '', 0, 10),
                'salesperson_id' => extractXmlValue($headerXml, 'SalesPersonId'),
                'customer_id' => extractXmlValue($headerXml, 'CustomerId'),
                'total_amount' => (float)extractXmlValue($headerXml, 'TaxIncludedTotalAmount') ?: 0,
                'total_qty' => (float)extractXmlValue($headerXml, 'TotalQuantity') ?: 0,
            ];
            
            if (preg_match('/<Key>(.*?)<\/Key>/s', $headerXml, $keyMatch)) {
                $header['type'] = extractXmlValue($keyMatch[1], 'Type');
                $header['stump'] = extractXmlValue($keyMatch[1], 'Stump');
                $header['number'] = extractXmlValue($keyMatch[1], 'Number');
            }
            
            if (!empty($header['type'])) {
                $headers[] = $header;
                
                // Aggregate by salesperson (from header)
                $spId = $header['salesperson_id'] ?: 'ไม่ระบุ';
                if (!isset($bySalesperson[$spId])) {
                    $bySalesperson[$spId] = [
                        'bills' => 0, 
                        'sales' => 0, 
                        'qty' => 0, 
                        'customers' => [],
                        'stores' => [],
                        'brands' => [],
                    ];
                }
                $bySalesperson[$spId]['bills']++;
                $bySalesperson[$spId]['sales'] += $header['total_amount'];
                $bySalesperson[$spId]['qty'] += $header['total_qty'];
                if ($header['customer_id']) {
                    $bySalesperson[$spId]['customers'][$header['customer_id']] = true;
                }
                $bySalesperson[$spId]['stores'][$header['store_id']] = true;
                
                $totalSales += $header['total_amount'];
                $totalBills++;
            }
        }
    }
} else {
    $error = "Failed to load headers: HTTP {$result['http_code']}";
}

// If viewing specific salesperson, get their transaction details
if ($viewPerson && !$error) {
    foreach ($headers as $h) {
        if ($h['salesperson_id'] !== $viewPerson) continue;
        
        $detailResult = $api->getSaleDetail($h['type'], $h['stump'], $h['number']);
        if (!$detailResult['success']) continue;
        
        $xml = $detailResult['response'];
        $docLines = [];
        
        if (preg_match_all('/<Get_Line>(.*?)<\/Get_Line>/s', $xml, $lineMatches)) {
            foreach ($lineMatches[1] as $lineXml) {
                $itemCode = extractXmlValue($lineXml, 'ItemCode');
                $brandCode = $itemCode ? strtoupper(substr($itemCode, 0, 3)) : 'OTH';
                $qty = (float)extractXmlValue($lineXml, 'Quantity') ?: 0;
                $price = (float)extractXmlValue($lineXml, 'TaxIncludedNetUnitPrice') ?: 0;
                
                $docLines[] = [
                    'item_code' => $itemCode,
                    'label' => extractXmlValue($lineXml, 'Label'),
                    'brand_code' => $brandCode,
                    'brand_name' => $brandNames[$brandCode] ?? $brandCode,
                    'color' => extractXmlValue($lineXml, 'ComplementaryDescription'),
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $qty * $price,
                ];
                
                // Brand stats
                if (!isset($bySalesperson[$viewPerson]['brands'][$brandCode])) {
                    $bySalesperson[$viewPerson]['brands'][$brandCode] = ['name' => $brandNames[$brandCode] ?? $brandCode, 'qty' => 0, 'sales' => 0];
                }
                $bySalesperson[$viewPerson]['brands'][$brandCode]['qty'] += $qty;
                $bySalesperson[$viewPerson]['brands'][$brandCode]['sales'] += $qty * $price;
            }
        }
        
        $personDetails[] = [
            'doc_number' => $h['number'],
            'date' => $h['date'],
            'store' => $storeNames[$h['store_id']] ?? $h['store_id'],
            'customer' => $h['customer_id'] ?: 'Walk-in',
            'amount' => $h['total_amount'],
            'lines' => $docLines,
        ];
        
        $docsProcessed++;
        usleep(50000);
    }
    
    // Sort brands
    if (isset($bySalesperson[$viewPerson]['brands'])) {
        uasort($bySalesperson[$viewPerson]['brands'], fn($a, $b) => $b['sales'] <=> $a['sales']);
    }
}

// Sort salespersons by sales
uasort($bySalesperson, fn($a, $b) => $b['sales'] <=> $a['sales']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salesperson Report</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f0f2f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #1a1a2e; margin-bottom: 5px; }
        .subtitle { color: #666; margin-bottom: 20px; }
        
        .nav { margin-bottom: 20px; }
        .nav a { display: inline-block; padding: 10px 20px; background: white; color: #333; text-decoration: none; border-radius: 8px; margin-right: 10px; }
        .nav a:hover, .nav a.active { background: #4361ee; color: white; }
        
        .filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .filter-bar form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-bar input, .filter-bar select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; }
        .filter-bar button { padding: 10px 25px; background: #4361ee; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card.primary { background: linear-gradient(135deg, #4361ee, #3a56d4); color: white; }
        .card.success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .card.purple { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: white; }
        .card h3 { margin: 0 0 8px; font-size: 13px; opacity: 0.8; }
        .card .value { font-size: 26px; font-weight: 700; }
        
        .section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { margin: 0 0 15px; font-size: 18px; color: #1a1a2e; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; font-size: 12px; color: #666; }
        .text-right { text-align: right; }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-brand { background: #f3e5f5; color: #7b1fa2; }
        .badge-store { background: #e3f2fd; color: #1976d2; }
        
        .bar { background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden; }
        .bar-fill { height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 4px; }
        
        .rank { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
        .rank-1 { background: #ffd700; color: #333; }
        .rank-2 { background: #c0c0c0; color: #333; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-default { background: #f0f0f0; color: #666; }
        
        .person-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; gap: 20px; align-items: center; }
        .person-avatar { width: 80px; height: 80px; background: linear-gradient(135deg, #4361ee, #7c3aed); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 32px; font-weight: bold; }
        .person-info h2 { margin: 0 0 5px; }
        .person-info p { margin: 0; color: #666; }
        .person-stats { margin-left: auto; display: flex; gap: 30px; text-align: center; }
        .person-stats .stat-value { font-size: 24px; font-weight: bold; color: #4361ee; }
        .person-stats .stat-label { font-size: 12px; color: #666; }
        
        .btn-view { padding: 6px 12px; background: #4361ee; color: white; text-decoration: none; border-radius: 6px; font-size: 12px; }
        .btn-back { display: inline-block; margin-bottom: 15px; color: #4361ee; text-decoration: none; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
        
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="report_live.php">📊 ภาพรวม</a>
        <a href="report_live_detail.php">📈 วิเคราะห์การขาย</a>
        <a href="report_live_salesperson.php" class="active">👤 พนักงานขาย</a>
    </div>
    
<?php if ($viewPerson): ?>
    <!-- DETAIL VIEW -->
    <a href="?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&limit=<?= $maxDocs ?>" class="btn-back">← กลับไปรายการ</a>
    
    <?php $personData = $bySalesperson[$viewPerson] ?? ['bills' => 0, 'sales' => 0, 'qty' => 0, 'brands' => []]; ?>
    
    <div class="person-card">
        <div class="person-avatar"><?= strtoupper(substr($viewPerson, 0, 1)) ?></div>
        <div class="person-info">
            <h2><?= htmlspecialchars($viewPerson) ?></h2>
            <p>รายละเอียดยอดขาย <?= $dateFrom ?> - <?= $dateTo ?></p>
        </div>
        <div class="person-stats">
            <div><div class="stat-value"><?= number_format($personData['bills']) ?></div><div class="stat-label">บิล</div></div>
            <div><div class="stat-value"><?= number_format($personData['qty']) ?></div><div class="stat-label">ชิ้น</div></div>
            <div><div class="stat-value">฿<?= number_format($personData['sales']) ?></div><div class="stat-label">ยอดขาย</div></div>
        </div>
    </div>
    
    <div class="grid-2">
        <!-- Brand breakdown -->
        <div class="section">
            <h2>🏷️ แบรนด์ที่ขาย</h2>
            <?php if (!empty($personData['brands'])): ?>
            <table>
                <thead><tr><th>แบรนด์</th><th class="text-right">ชิ้น</th><th class="text-right">ยอด</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($personData['brands'], 0, 10, true) as $code => $data): ?>
                <tr>
                    <td><span class="badge badge-brand"><?= htmlspecialchars($code) ?></span> <?= htmlspecialchars($data['name']) ?></td>
                    <td class="text-right"><?= number_format($data['qty']) ?></td>
                    <td class="text-right"><strong>฿<?= number_format($data['sales']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#999; text-align:center; padding:20px;">ไม่มีข้อมูล</p>
            <?php endif; ?>
        </div>
        
        <!-- Transactions -->
        <div class="section">
            <h2>🧾 รายการบิล (<?= count($personDetails) ?> บิล)</h2>
            <table>
                <thead><tr><th>เลขที่</th><th>วันที่</th><th>สาขา</th><th class="text-right">ยอด</th></tr></thead>
                <tbody>
                <?php foreach ($personDetails as $doc): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($doc['doc_number']) ?></strong></td>
                    <td><?= date('d/m', strtotime($doc['date'])) ?></td>
                    <td><small><?= htmlspecialchars($doc['store']) ?></small></td>
                    <td class="text-right"><strong>฿<?= number_format($doc['amount']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- LIST VIEW -->
    <h1>👤 Salesperson Report</h1>
    <p class="subtitle">รายงานยอดขายตามพนักงานขาย (Live API)</p>
    
    <?php if ($error): ?>
    <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="filter-bar">
        <form method="GET">
            <label>จากวันที่:</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            <label>ถึงวันที่:</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            <label>จำนวนบิล:</label>
            <select name="limit">
                <option value="20" <?= $maxDocs == 20 ? 'selected' : '' ?>>20 บิล</option>
                <option value="50" <?= $maxDocs == 50 ? 'selected' : '' ?>>50 บิล</option>
                <option value="100" <?= $maxDocs == 100 ? 'selected' : '' ?>>100 บิล</option>
            </select>
            <button type="submit">🔍 ค้นหา</button>
        </form>
    </div>
    
    <!-- Summary -->
    <div class="cards">
        <div class="card primary">
            <h3>💰 ยอดขายรวม</h3>
            <div class="value">฿<?= number_format($totalSales) ?></div>
        </div>
        <div class="card success">
            <h3>🧾 จำนวนบิล</h3>
            <div class="value"><?= number_format($totalBills) ?></div>
        </div>
        <div class="card purple">
            <h3>👥 พนักงานขาย</h3>
            <div class="value"><?= count($bySalesperson) ?></div>
        </div>
        <div class="card">
            <h3>📊 เฉลี่ย/คน</h3>
            <div class="value">฿<?= count($bySalesperson) ? number_format($totalSales / count($bySalesperson)) : 0 ?></div>
        </div>
    </div>
    
    <!-- Salesperson Ranking -->
    <div class="section">
        <h2>🏆 อันดับพนักงานขาย</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>พนักงาน</th>
                    <th class="text-right">บิล</th>
                    <th class="text-right">ชิ้น</th>
                    <th class="text-right">ลูกค้า</th>
                    <th class="text-right">ยอดขาย</th>
                    <th style="width:120px">% Share</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php $rank = 1; foreach ($bySalesperson as $spId => $data): 
                $pct = $totalSales > 0 ? ($data['sales'] / $totalSales * 100) : 0;
                $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-default";
            ?>
            <tr>
                <td><div class="rank <?= $rankClass ?>"><?= $rank ?></div></td>
                <td>
                    <strong><?= htmlspecialchars($spId) ?></strong>
                    <br><small style="color:#999"><?= count($data['stores']) ?> สาขา</small>
                </td>
                <td class="text-right"><?= number_format($data['bills']) ?></td>
                <td class="text-right"><?= number_format($data['qty']) ?></td>
                <td class="text-right"><?= count($data['customers']) ?></td>
                <td class="text-right"><strong>฿<?= number_format($data['sales']) ?></strong></td>
                <td>
                    <div class="bar"><div class="bar-fill" style="width:<?= max($pct, 2) ?>%"></div></div>
                    <small><?= number_format($pct, 1) ?>%</small>
                </td>
                <td>
                    <a href="?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&limit=<?= $maxDocs ?>&view=<?= urlencode($spId) ?>" class="btn-view">ดูรายละเอียด</a>
                </td>
            </tr>
            <?php $rank++; endforeach; ?>
            <?php if (empty($bySalesperson)): ?>
            <tr><td colspan="8" style="text-align:center; padding:40px; color:#999;">ไม่พบข้อมูล</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

</div>
</body>
</html>