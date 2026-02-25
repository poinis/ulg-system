<?php
/**
 * Cegid Live Sales Report
 * =======================
 * ดึงข้อมูลจาก API โดยตรง ไม่ผ่าน Database
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

// =====================================================
// STORE NAMES (Static mapping)
// =====================================================
$storeNames = [
    '10000' => 'ULG DC OFFICE',
    '11010' => 'PRONTO CENTRAL LARDPRAO',
    '11020' => 'SUPERDRY LARDPRAO',
    '11040' => 'TOPOLOGIE LADPRAO',
    '12010' => 'PRONTO CENTRAL RAMA 9',
    '14020' => 'SUPERDRY CENTRAL WORLD',
    '14040' => 'TOPOLOGIE CENTRAL WORLD',
    '17020' => 'SOUP EMSPHERE',
    '19010' => 'PRONTO MEGA BANGNA',
    '20010' => 'PRONTO SIAM PARAGON',
    '02009' => 'Pronto Online',
    '02010' => 'Soup Online',
];

// =====================================================
// BRAND NAMES (Static mapping - top brands)
// =====================================================
$brandNames = [
    'FRT' => 'FREITAG',
    'NDI' => 'NUDIE',
    'SVW' => 'SERVICE WORKS',
    'TLR' => 'TELLURIDE',
    'DUS' => 'DEUS',
    'ONE' => 'ONE OF THESE DAYS',
    'SAA' => 'SAAD',
    'GPZ' => 'GRAPHZERO',
    'FIS' => 'FILSON',
    'TLG' => 'TOPOLOGIE',
    'SPD' => 'SUPERDRY',
    'FLO' => 'FLOYD',
    'KAG' => 'KANGOL',
    'NAF' => 'NAKED AND FAMOUS',
    'DUD' => 'THE DUDES',
    'PNC' => 'PRONTO & CO.',
];

// =====================================================
// MAIN LOGIC
// =====================================================
$api = new CegidAPI();
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$viewDoc = $_GET['view'] ?? null;

$headers = [];
$detail = null;
$error = null;

// If viewing specific document
if ($viewDoc) {
    list($type, $stump, $number) = explode('|', $viewDoc);
    $result = $api->getSaleDetail($type, $stump, $number);
    if ($result['success']) {
        $detail = $result['response'];
    } else {
        $error = "Failed to load document: HTTP {$result['http_code']}";
    }
} else {
    // Load headers
    $result = $api->getSaleHeaders($dateFrom, $dateTo, 1, 200);
    if ($result['success']) {
        if (preg_match_all('/<Get_Header>(.*?)<\/Get_Header>/s', $result['response'], $matches)) {
            foreach ($matches[1] as $headerXml) {
                $header = [
                    'store_id' => extractXmlValue($headerXml, 'StoreId'),
                    'date' => substr(extractXmlValue($headerXml, 'Date') ?? '', 0, 10),
                    'customer_id' => extractXmlValue($headerXml, 'CustomerId'),
                    'salesperson_id' => extractXmlValue($headerXml, 'SalesPersonId'),
                    'total_qty' => extractXmlValue($headerXml, 'TotalQuantity'),
                    'total_amount' => extractXmlValue($headerXml, 'TaxIncludedTotalAmount'),
                    'origin' => extractXmlValue($headerXml, 'Origin'),
                    'active' => extractXmlValue($headerXml, 'Active'),
                ];
                
                if (preg_match('/<Key>(.*?)<\/Key>/s', $headerXml, $keyMatch)) {
                    $header['type'] = extractXmlValue($keyMatch[1], 'Type');
                    $header['stump'] = extractXmlValue($keyMatch[1], 'Stump');
                    $header['number'] = extractXmlValue($keyMatch[1], 'Number');
                    $header['doc_key'] = $header['type'] . '|' . $header['stump'] . '|' . $header['number'];
                }
                
                if (!empty($header['doc_key']) && $header['active'] === 'true') {
                    $headers[] = $header;
                }
            }
        }
    } else {
        $error = "Failed to load data: HTTP {$result['http_code']}";
    }
}

// Calculate summary
$summary = [
    'total_bills' => count($headers),
    'total_amount' => array_sum(array_column($headers, 'total_amount')),
    'total_qty' => array_sum(array_column($headers, 'total_qty')),
    'by_store' => [],
];

foreach ($headers as $h) {
    $sid = $h['store_id'];
    if (!isset($summary['by_store'][$sid])) {
        $summary['by_store'][$sid] = ['bills' => 0, 'amount' => 0, 'qty' => 0];
    }
    $summary['by_store'][$sid]['bills']++;
    $summary['by_store'][$sid]['amount'] += $h['total_amount'];
    $summary['by_store'][$sid]['qty'] += $h['total_qty'];
}
arsort($summary['by_store']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Sales Report</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f0f2f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .nav { margin-bottom: 20px; }
        .nav a { display: inline-block; padding: 10px 20px; background: white; color: #333; text-decoration: none; border-radius: 8px; margin-right: 10px; }
        .nav a:hover, .nav a.active { background: #4361ee; color: white; }
        
        h1 { color: #1a1a2e; margin-bottom: 5px; }
        .subtitle { color: #666; margin-bottom: 20px; }
        
        .filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .filter-bar form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-bar input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .filter-bar button { padding: 10px 25px; background: #4361ee; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .filter-bar button:hover { background: #3a56d4; }
        
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card.primary { background: linear-gradient(135deg, #4361ee, #3a56d4); color: white; }
        .card h3 { margin: 0 0 8px; font-size: 13px; opacity: 0.8; font-weight: 500; }
        .card .value { font-size: 28px; font-weight: 700; }
        .card .sub { font-size: 12px; opacity: 0.7; margin-top: 5px; }
        
        .section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { margin: 0 0 15px; font-size: 18px; color: #1a1a2e; display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; font-size: 13px; color: #666; }
        tr:hover { background: #f8f9fa; }
        .text-right { text-align: right; }
        
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-shop { background: #e3f2fd; color: #1976d2; }
        .badge-online { background: #fff3e0; color: #f57c00; }
        .badge-brand { background: #f3e5f5; color: #7b1fa2; }
        
        .btn-view { padding: 6px 12px; background: #4361ee; color: white; text-decoration: none; border-radius: 6px; font-size: 12px; }
        .btn-view:hover { background: #3a56d4; }
        .btn-back { display: inline-block; margin-bottom: 15px; color: #4361ee; text-decoration: none; }
        
        .detail-header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .detail-item label { font-size: 12px; color: #666; display: block; }
        .detail-item value { font-size: 16px; font-weight: 600; color: #1a1a2e; }
        
        .line-item { display: flex; justify-content: space-between; padding: 15px; border-bottom: 1px solid #eee; }
        .line-item:hover { background: #f8f9fa; }
        .line-product { flex: 1; }
        .line-product .name { font-weight: 600; color: #1a1a2e; }
        .line-product .meta { font-size: 12px; color: #666; margin-top: 3px; }
        .line-qty { width: 80px; text-align: center; }
        .line-price { width: 120px; text-align: right; font-weight: 600; }
        
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .loading { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="report_live.php" class="active">📊 ภาพรวม</a>
        <a href="report_live_detail.php">📈 วิเคราะห์การขาย</a>
        <a href="report_live_salesperson.php">👤 พนักงานขาย</a>
    </div>
    
<?php if ($viewDoc && $detail): ?>
    <!-- DETAIL VIEW -->
    <a href="?" class="btn-back">← กลับไปรายการ</a>
    <h1>📄 รายละเอียดบิล</h1>
    <p class="subtitle"><?= htmlspecialchars($viewDoc) ?></p>
    
    <?php
    // Parse detail
    $docDate = extractXmlValue($detail, 'Date');
    $storeId = extractXmlValue($detail, 'StoreId');
    $customerId = extractXmlValue($detail, 'CustomerId');
    $salespersonId = extractXmlValue($detail, 'SalesPersonId');
    $totalAmount = extractXmlValue($detail, 'TaxIncludedTotalAmount');
    $origin = extractXmlValue($detail, 'Origin');
    
    // Parse lines
    $lines = [];
    if (preg_match_all('/<Get_Line>(.*?)<\/Get_Line>/s', $detail, $lineMatches)) {
        foreach ($lineMatches[1] as $lineXml) {
            $itemCode = extractXmlValue($lineXml, 'ItemCode');
            $brandCode = $itemCode ? strtoupper(substr($itemCode, 0, 3)) : '';
            $lines[] = [
                'item_code' => $itemCode,
                'item_id' => extractXmlValue($lineXml, 'ItemId'),
                'label' => extractXmlValue($lineXml, 'Label'),
                'color' => extractXmlValue($lineXml, 'ComplementaryDescription'),
                'brand_code' => $brandCode,
                'brand_name' => $brandNames[$brandCode] ?? $brandCode,
                'qty' => extractXmlValue($lineXml, 'Quantity'),
                'unit_price' => extractXmlValue($lineXml, 'TaxIncludedUnitPrice'),
                'net_price' => extractXmlValue($lineXml, 'TaxIncludedNetUnitPrice'),
            ];
        }
    }
    
    // Parse payments
    $payments = [];
    if (preg_match_all('/<Get_Payment>(.*?)<\/Get_Payment>/s', $detail, $payMatches)) {
        foreach ($payMatches[1] as $payXml) {
            $payments[] = [
                'code' => extractXmlValue($payXml, 'Code'),
                'label' => extractXmlValue($payXml, 'Label'),
                'amount' => extractXmlValue($payXml, 'Amount'),
            ];
        }
    }
    ?>
    
    <div class="detail-header">
        <div class="detail-grid">
            <div class="detail-item">
                <label>วันที่</label>
                <value><?= $docDate ? date('d/m/Y H:i', strtotime($docDate)) : '-' ?></value>
            </div>
            <div class="detail-item">
                <label>สาขา</label>
                <value><?= htmlspecialchars($storeNames[$storeId] ?? $storeId) ?></value>
            </div>
            <div class="detail-item">
                <label>ลูกค้า</label>
                <value><?= htmlspecialchars($customerId ?: 'Walk-in') ?></value>
            </div>
            <div class="detail-item">
                <label>พนักงานขาย</label>
                <value><?= htmlspecialchars($salespersonId ?: '-') ?></value>
            </div>
            <div class="detail-item">
                <label>ช่องทาง</label>
                <value><span class="badge <?= $origin === 'ECommerce' ? 'badge-online' : 'badge-shop' ?>"><?= $origin ?></span></value>
            </div>
            <div class="detail-item">
                <label>ยอดรวม</label>
                <value style="color: #4361ee; font-size: 24px;">฿<?= number_format($totalAmount, 2) ?></value>
            </div>
        </div>
    </div>
    
    <div class="section">
        <h2>🛍️ รายการสินค้า (<?= count($lines) ?> รายการ)</h2>
        <?php foreach ($lines as $line): ?>
        <div class="line-item">
            <div class="line-product">
                <div class="name"><?= htmlspecialchars($line['label']) ?></div>
                <div class="meta">
                    <span class="badge badge-brand"><?= htmlspecialchars($line['brand_code']) ?></span>
                    <?= htmlspecialchars($line['brand_name']) ?>
                    <?php if ($line['color']): ?> | สี: <?= htmlspecialchars($line['color']) ?><?php endif; ?>
                    <br>Code: <?= htmlspecialchars($line['item_code']) ?>
                </div>
            </div>
            <div class="line-qty">
                <div style="font-size: 12px; color: #666;">จำนวน</div>
                <div style="font-weight: 600;"><?= number_format($line['qty']) ?></div>
            </div>
            <div class="line-price">
                <div style="font-size: 12px; color: #666;">ราคา</div>
                <div>฿<?= number_format($line['net_price'], 2) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($payments): ?>
    <div class="section">
        <h2>💳 การชำระเงิน</h2>
        <table>
            <tr><th>วิธีชำระ</th><th class="text-right">จำนวนเงิน</th></tr>
            <?php foreach ($payments as $pay): ?>
            <tr>
                <td><?= htmlspecialchars($pay['label'] ?: $pay['code']) ?></td>
                <td class="text-right"><strong>฿<?= number_format($pay['amount'], 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- LIST VIEW -->
    <h1>📊 Live Sales Report</h1>
    <p class="subtitle">ดึงข้อมูลจาก Cegid API โดยตรง (ไม่ผ่าน Database)</p>
    
    <?php if ($error): ?>
    <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="filter-bar">
        <form method="GET">
            <label>จากวันที่:</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            <label>ถึงวันที่:</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            <button type="submit">🔍 ค้นหา</button>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div class="cards">
        <div class="card primary">
            <h3>💰 ยอดขายรวม</h3>
            <div class="value">฿<?= number_format($summary['total_amount']) ?></div>
            <div class="sub"><?= $dateFrom ?> - <?= $dateTo ?></div>
        </div>
        <div class="card">
            <h3>🧾 จำนวนบิล</h3>
            <div class="value"><?= number_format($summary['total_bills']) ?></div>
        </div>
        <div class="card">
            <h3>📦 จำนวนชิ้น</h3>
            <div class="value"><?= number_format($summary['total_qty']) ?></div>
        </div>
        <div class="card">
            <h3>📊 เฉลี่ย/บิล</h3>
            <div class="value">฿<?= $summary['total_bills'] ? number_format($summary['total_amount'] / $summary['total_bills']) : 0 ?></div>
        </div>
    </div>
    
    <!-- By Store -->
    <div class="section">
        <h2>🏪 ยอดขายตามสาขา</h2>
        <table>
            <thead>
                <tr><th>สาขา</th><th class="text-right">บิล</th><th class="text-right">ชิ้น</th><th class="text-right">ยอดขาย</th></tr>
            </thead>
            <tbody>
                <?php foreach ($summary['by_store'] as $sid => $data): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($storeNames[$sid] ?? $sid) ?></strong><br><small style="color:#999"><?= $sid ?></small></td>
                    <td class="text-right"><?= number_format($data['bills']) ?></td>
                    <td class="text-right"><?= number_format($data['qty']) ?></td>
                    <td class="text-right"><strong>฿<?= number_format($data['amount']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Transaction List -->
    <div class="section">
        <h2>📋 รายการบิลขาย (<?= count($headers) ?> รายการ)</h2>
        <table>
            <thead>
                <tr>
                    <th>เลขที่</th>
                    <th>วันที่</th>
                    <th>สาขา</th>
                    <th>ลูกค้า</th>
                    <th>ช่องทาง</th>
                    <th class="text-right">ชิ้น</th>
                    <th class="text-right">ยอด</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($headers as $h): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($h['number']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($h['date'])) ?></td>
                    <td><?= htmlspecialchars($storeNames[$h['store_id']] ?? $h['store_id']) ?></td>
                    <td><?= htmlspecialchars($h['customer_id'] ?: '-') ?></td>
                    <td><span class="badge <?= $h['origin'] === 'ECommerce' ? 'badge-online' : 'badge-shop' ?>"><?= $h['origin'] ?></span></td>
                    <td class="text-right"><?= number_format($h['total_qty']) ?></td>
                    <td class="text-right"><strong>฿<?= number_format($h['total_amount']) ?></strong></td>
                    <td><a href="?view=<?= urlencode($h['doc_key']) ?>" class="btn-view">ดูรายละเอียด</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($headers)): ?>
                <tr><td colspan="8" style="text-align:center; padding:40px; color:#999;">ไม่พบข้อมูล</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

</div>
</body>
</html>