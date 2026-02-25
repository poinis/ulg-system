<?php
/**
 * Cegid Live Sales Detail Report
 * ===============================
 * วิเคราะห์ยอดขายตาม Brand, สินค้า
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

// Brand Names
$brandNames = [
    'FRT' => 'FREITAG', 'NDI' => 'NUDIE', 'SVW' => 'SERVICE WORKS', 'TLR' => 'TELLURIDE',
    'DUS' => 'DEUS', 'ONE' => 'ONE OF THESE DAYS', 'SAA' => 'SAAD', 'GPZ' => 'GRAPHZERO',
    'FIS' => 'FILSON', 'TLG' => 'TOPOLOGIE', 'SPD' => 'SUPERDRY', 'FLO' => 'FLOYD',
    'KAG' => 'KANGOL', 'NAF' => 'NAKED AND FAMOUS', 'DUD' => 'THE DUDES', 'PNC' => 'PRONTO & CO.',
];

$storeNames = [
    '10000' => 'ULG DC OFFICE', '11010' => 'PRONTO CENTRAL LARDPRAO', '11020' => 'SUPERDRY LARDPRAO',
    '11040' => 'TOPOLOGIE LADPRAO', '12010' => 'PRONTO CENTRAL RAMA 9', '14020' => 'SUPERDRY CENTRAL WORLD',
    '14040' => 'TOPOLOGIE CENTRAL WORLD', '17020' => 'SOUP EMSPHERE', '19010' => 'PRONTO MEGA BANGNA',
    '20010' => 'PRONTO SIAM PARAGON', '02009' => 'Pronto Online', '02010' => 'Soup Online',
];

// =====================================================
// MAIN LOGIC
// =====================================================
$api = new CegidAPI();
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$maxDocs = min((int)($_GET['limit'] ?? 50), 100);

$allLines = [];
$byBrand = [];
$byProduct = [];
$byStore = [];
$totalSales = 0;
$totalQty = 0;
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
            ];
            
            if (preg_match('/<Key>(.*?)<\/Key>/s', $headerXml, $keyMatch)) {
                $header['type'] = extractXmlValue($keyMatch[1], 'Type');
                $header['stump'] = extractXmlValue($keyMatch[1], 'Stump');
                $header['number'] = extractXmlValue($keyMatch[1], 'Number');
            }
            
            if (!empty($header['type'])) $headers[] = $header;
        }
    }
} else {
    $error = "Failed to load headers: HTTP {$result['http_code']}";
}

// Step 2: Get Details for each document
if (!$error) {
    foreach ($headers as $i => $h) {
        $detailResult = $api->getSaleDetail($h['type'], $h['stump'], $h['number']);
        if (!$detailResult['success']) continue;
        
        $xml = $detailResult['response'];
        
        // Parse lines
        if (preg_match_all('/<Get_Line>(.*?)<\/Get_Line>/s', $xml, $lineMatches)) {
            foreach ($lineMatches[1] as $lineXml) {
                $itemCode = extractXmlValue($lineXml, 'ItemCode');
                $brandCode = $itemCode ? strtoupper(substr($itemCode, 0, 3)) : 'OTH';
                $qty = (float)extractXmlValue($lineXml, 'Quantity') ?: 0;
                $price = (float)extractXmlValue($lineXml, 'TaxIncludedNetUnitPrice') ?: 0;
                $lineTotal = $qty * $price;
                
                $line = [
                    'store_id' => $h['store_id'],
                    'date' => $h['date'],
                    'item_code' => $itemCode,
                    'item_id' => extractXmlValue($lineXml, 'ItemId'),
                    'label' => extractXmlValue($lineXml, 'Label'),
                    'color' => extractXmlValue($lineXml, 'ComplementaryDescription'),
                    'brand_code' => $brandCode,
                    'brand_name' => $brandNames[$brandCode] ?? $brandCode,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $lineTotal,
                ];
                
                $allLines[] = $line;
                $totalSales += $lineTotal;
                $totalQty += $qty;
                
                // By Brand
                if (!isset($byBrand[$brandCode])) {
                    $byBrand[$brandCode] = ['name' => $brandNames[$brandCode] ?? $brandCode, 'qty' => 0, 'sales' => 0, 'items' => 0];
                }
                $byBrand[$brandCode]['qty'] += $qty;
                $byBrand[$brandCode]['sales'] += $lineTotal;
                $byBrand[$brandCode]['items']++;
                
                // By Product
                $productKey = $itemCode ?: $line['label'];
                if (!isset($byProduct[$productKey])) {
                    $byProduct[$productKey] = ['code' => $itemCode, 'name' => $line['label'], 'brand' => $brandCode, 'color' => $line['color'], 'qty' => 0, 'sales' => 0];
                }
                $byProduct[$productKey]['qty'] += $qty;
                $byProduct[$productKey]['sales'] += $lineTotal;
                
                // By Store
                $storeId = $h['store_id'];
                if (!isset($byStore[$storeId])) {
                    $byStore[$storeId] = ['name' => $storeNames[$storeId] ?? $storeId, 'qty' => 0, 'sales' => 0, 'lines' => 0];
                }
                $byStore[$storeId]['qty'] += $qty;
                $byStore[$storeId]['sales'] += $lineTotal;
                $byStore[$storeId]['lines']++;
            }
        }
        
        $docsProcessed++;
        usleep(50000); // 50ms delay
    }
}

// Sort
uasort($byBrand, fn($a, $b) => $b['sales'] <=> $a['sales']);
uasort($byProduct, fn($a, $b) => $b['sales'] <=> $a['sales']);
uasort($byStore, fn($a, $b) => $b['sales'] <=> $a['sales']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Detail Analysis</title>
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
        .card h3 { margin: 0 0 8px; font-size: 13px; opacity: 0.8; }
        .card .value { font-size: 26px; font-weight: 700; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
        
        .section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { margin: 0 0 15px; font-size: 18px; color: #1a1a2e; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; font-size: 12px; color: #666; }
        .text-right { text-align: right; }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-brand { background: #f3e5f5; color: #7b1fa2; }
        
        .bar { background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden; }
        .bar-fill { height: 100%; background: linear-gradient(90deg, #4361ee, #7c3aed); border-radius: 4px; }
        
        .rank { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }
        .rank-1 { background: #ffd700; color: #333; }
        .rank-2 { background: #c0c0c0; color: #333; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-default { background: #f0f0f0; color: #666; }
        
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info { background: #e3f2fd; color: #1565c0; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="report_live.php">📊 ภาพรวม</a>
        <a href="report_live_detail.php" class="active">📈 วิเคราะห์การขาย</a>
        <a href="report_live_salesperson.php">👤 พนักงานขาย</a>
    </div>
    
    <h1>📈 Sales Detail Analysis</h1>
    <p class="subtitle">วิเคราะห์ยอดขายตาม Brand และสินค้า (Live API)</p>
    
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
            <button type="submit">🔍 วิเคราะห์</button>
        </form>
    </div>
    
    <div class="info">
        ℹ️ ประมวลผลแล้ว <?= $docsProcessed ?> บิล | <?= count($allLines) ?> รายการสินค้า | ใช้เวลาโหลดประมาณ <?= $docsProcessed * 0.1 ?>+ วินาที
    </div>
    
    <!-- Summary Cards -->
    <div class="cards">
        <div class="card primary">
            <h3>💰 ยอดขายรวม</h3>
            <div class="value">฿<?= number_format($totalSales) ?></div>
        </div>
        <div class="card success">
            <h3>📦 จำนวนชิ้น</h3>
            <div class="value"><?= number_format($totalQty) ?></div>
        </div>
        <div class="card">
            <h3>🏷️ แบรนด์</h3>
            <div class="value"><?= count($byBrand) ?></div>
        </div>
        <div class="card">
            <h3>📋 SKU</h3>
            <div class="value"><?= count($byProduct) ?></div>
        </div>
    </div>
    
    <div class="grid-2">
        <!-- By Brand -->
        <div class="section">
            <h2>🏷️ ยอดขายตามแบรนด์</h2>
            <table>
                <thead><tr><th>#</th><th>แบรนด์</th><th class="text-right">ชิ้น</th><th class="text-right">ยอดขาย</th><th style="width:100px">%</th></tr></thead>
                <tbody>
                <?php $rank = 1; foreach (array_slice($byBrand, 0, 15, true) as $code => $data): 
                    $pct = $totalSales > 0 ? ($data['sales'] / $totalSales * 100) : 0;
                    $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-default";
                ?>
                <tr>
                    <td><div class="rank <?= $rankClass ?>"><?= $rank ?></div></td>
                    <td><span class="badge badge-brand"><?= htmlspecialchars($code) ?></span> <?= htmlspecialchars($data['name']) ?></td>
                    <td class="text-right"><?= number_format($data['qty']) ?></td>
                    <td class="text-right"><strong>฿<?= number_format($data['sales']) ?></strong></td>
                    <td>
                        <div class="bar"><div class="bar-fill" style="width:<?= max($pct, 2) ?>%"></div></div>
                        <small><?= number_format($pct, 1) ?>%</small>
                    </td>
                </tr>
                <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- By Store -->
        <div class="section">
            <h2>🏪 ยอดขายตามสาขา</h2>
            <table>
                <thead><tr><th>#</th><th>สาขา</th><th class="text-right">ชิ้น</th><th class="text-right">ยอดขาย</th><th style="width:100px">%</th></tr></thead>
                <tbody>
                <?php $rank = 1; foreach (array_slice($byStore, 0, 10, true) as $storeId => $data): 
                    $pct = $totalSales > 0 ? ($data['sales'] / $totalSales * 100) : 0;
                    $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-default";
                ?>
                <tr>
                    <td><div class="rank <?= $rankClass ?>"><?= $rank ?></div></td>
                    <td><?= htmlspecialchars($data['name']) ?><br><small style="color:#999"><?= $storeId ?></small></td>
                    <td class="text-right"><?= number_format($data['qty']) ?></td>
                    <td class="text-right"><strong>฿<?= number_format($data['sales']) ?></strong></td>
                    <td>
                        <div class="bar"><div class="bar-fill" style="width:<?= max($pct, 2) ?>%"></div></div>
                        <small><?= number_format($pct, 1) ?>%</small>
                    </td>
                </tr>
                <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="section">
        <h2>🏆 สินค้าขายดี Top 20</h2>
        <table>
            <thead><tr><th>#</th><th>สินค้า</th><th>แบรนด์</th><th>สี</th><th class="text-right">ชิ้น</th><th class="text-right">ยอดขาย</th></tr></thead>
            <tbody>
            <?php $rank = 1; foreach (array_slice($byProduct, 0, 20, true) as $key => $data): 
                $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-default";
            ?>
            <tr>
                <td><div class="rank <?= $rankClass ?>"><?= $rank ?></div></td>
                <td>
                    <strong><?= htmlspecialchars($data['name'] ?: $data['code']) ?></strong>
                    <br><small style="color:#999"><?= htmlspecialchars($data['code']) ?></small>
                </td>
                <td><span class="badge badge-brand"><?= htmlspecialchars($data['brand']) ?></span></td>
                <td><?= htmlspecialchars($data['color'] ?: '-') ?></td>
                <td class="text-right"><?= number_format($data['qty']) ?></td>
                <td class="text-right"><strong>฿<?= number_format($data['sales']) ?></strong></td>
            </tr>
            <?php $rank++; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>