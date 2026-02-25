<?php
/**
 * ทดสอบดึงข้อมูลแบบง่าย - ดูว่า Facebook API ส่งอะไรกลับมา
 */

header('Content-Type: text/html; charset=utf-8');

$accessToken = 'EAATEt12ZCH1YBQMoGgzvWTb4gCuWI5DkeMGwipffwumMw2pZAcwpYSWH1xGpmJ1MzFDZCyOoHwVDl0rgBG7F7INCoDPd88lWLmN47enPEPEdiQR3e7oa1ZCmFHmzU1VvKtNcIcsJ3WUhfyyZCfKk80AdwZB2jxuZCjyypmrajCftG3XQvI5BuBntTNMaCh7eMUZAQwZDZD';
$pageId = '489861450880996';

echo "<h1>🔍 ทดสอบ Facebook Insights API</h1>";
echo "<hr>";

// ทดสอบ 1: ดูว่ามี Metric อะไรบ้าง
echo "<h2>1. ตรวจสอบ Metrics ที่ใช้ได้</h2>";

$testMetrics = [
    'page_impressions_unique' => 'ยอดมูล (Reach)',
    'page_impressions' => 'Impressions',
    'page_fans' => 'จำนวนแฟน',
    'page_fan_adds' => 'แฟนเพิ่ม',
    'page_fan_removes' => 'แฟนลด',
    'page_post_engagements' => 'Engagement รวม',
    'page_engaged_users' => 'ผู้ใช้ที่ Engage',
    'page_consumptions' => 'Consumptions',
    'page_views_total' => 'Page Views'
];

// ทดสอบแต่ละ metric
$since = strtotime('-7 days');
$until = strtotime('today');

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>";
echo "<th>Metric</th>";
echo "<th>ชื่อ</th>";
echo "<th>สถานะ</th>";
echo "<th>ข้อมูลที่ได้</th>";
echo "</tr>";

foreach ($testMetrics as $metric => $name) {
    echo "<tr>";
    echo "<td><code>{$metric}</code></td>";
    echo "<td>{$name}</td>";
    
    $url = "https://graph.facebook.com/v21.0/{$pageId}/insights?metric={$metric}&period=day&since={$since}&until={$until}&access_token={$accessToken}";
    
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            echo "<td style='color: red;'>❌ Error</td>";
            echo "<td style='color: red; font-size: 12px;'>{$data['error']['message']}</td>";
        } else if (isset($data['data']) && count($data['data']) > 0) {
            echo "<td style='color: green;'>✅ ใช้ได้</td>";
            
            $metricData = $data['data'][0];
            $values = isset($metricData['values']) ? $metricData['values'] : [];
            
            if (count($values) > 0) {
                $latestValue = end($values);
                echo "<td style='font-size: 12px;'>";
                echo "ค่าล่าสุด: <strong>" . number_format($latestValue['value']) . "</strong><br>";
                echo "จำนวนวัน: " . count($values);
                echo "</td>";
            } else {
                echo "<td style='color: orange;'>ไม่มีข้อมูล</td>";
            }
        } else {
            echo "<td style='color: orange;'>⚠️ ไม่มีข้อมูล</td>";
            echo "<td>-</td>";
        }
    } else {
        echo "<td style='color: red;'>❌ Error</td>";
        echo "<td>ไม่สามารถเชื่อมต่อได้</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

// ทดสอบ 2: ดึงข้อมูลช่วงเดือน
echo "<h2>2. ทดสอบดึงข้อมูลรายเดือน</h2>";

$months = [
    '2025-10' => 'ตุลาคม 2568',
    '2025-11' => 'พฤศจิกายน 2568',
    '2025-12' => 'ธันวาคม 2568 (ปัจจุบัน)'
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>";
echo "<th>เดือน</th>";
echo "<th>ช่วงวันที่</th>";
echo "<th>Reach</th>";
echo "<th>Engagement</th>";
echo "<th>Followers</th>";
echo "</tr>";

foreach ($months as $monthStr => $monthName) {
    list($year, $month) = explode('-', $monthStr);
    
    $firstDay = strtotime("{$year}-{$month}-01");
    $lastDay = strtotime("last day of {$year}-{$month}");
    
    // ถ้าเป็นเดือนปัจจุบัน ใช้วันนี้
    if ($monthStr === date('Y-m')) {
        $lastDay = strtotime('today');
    }
    
    echo "<tr>";
    echo "<td><strong>{$monthName}</strong></td>";
    echo "<td>" . date('d/m/Y', $firstDay) . " - " . date('d/m/Y', $lastDay) . "</td>";
    
    // ดึง Reach
    $reachUrl = "https://graph.facebook.com/v21.0/{$pageId}/insights?metric=page_impressions_unique&period=day&since={$firstDay}&until={$lastDay}&access_token={$accessToken}";
    $reachResponse = @file_get_contents($reachUrl);
    
    if ($reachResponse) {
        $reachData = json_decode($reachResponse, true);
        if (isset($reachData['data'][0]['values'])) {
            $totalReach = array_sum(array_column($reachData['data'][0]['values'], 'value'));
            echo "<td style='text-align: right;'>" . number_format($totalReach) . "</td>";
        } else if (isset($reachData['error'])) {
            echo "<td style='color: red; font-size: 11px;'>{$reachData['error']['message']}</td>";
        } else {
            echo "<td style='color: orange;'>ไม่มีข้อมูล</td>";
        }
    } else {
        echo "<td style='color: red;'>Error</td>";
    }
    
    // ดึง Engagement
    $engagementUrl = "https://graph.facebook.com/v21.0/{$pageId}/insights?metric=page_post_engagements&period=day&since={$firstDay}&until={$lastDay}&access_token={$accessToken}";
    $engagementResponse = @file_get_contents($engagementUrl);
    
    if ($engagementResponse) {
        $engagementData = json_decode($engagementResponse, true);
        if (isset($engagementData['data'][0]['values'])) {
            $totalEngagement = array_sum(array_column($engagementData['data'][0]['values'], 'value'));
            echo "<td style='text-align: right;'>" . number_format($totalEngagement) . "</td>";
        } else if (isset($engagementData['error'])) {
            echo "<td style='color: red; font-size: 11px;'>{$engagementData['error']['message']}</td>";
        } else {
            echo "<td style='color: orange;'>ไม่มีข้อมูล</td>";
        }
    } else {
        echo "<td style='color: red;'>Error</td>";
    }
    
    // ดึง Followers
    $followersUrl = "https://graph.facebook.com/v21.0/{$pageId}/insights?metric=page_fans&period=day&since={$firstDay}&until={$lastDay}&access_token={$accessToken}";
    $followersResponse = @file_get_contents($followersUrl);
    
    if ($followersResponse) {
        $followersData = json_decode($followersResponse, true);
        if (isset($followersData['data'][0]['values'])) {
            $values = $followersData['data'][0]['values'];
            $currentFollowers = end($values)['value'];
            $startFollowers = reset($values)['value'];
            $growth = $currentFollowers - $startFollowers;
            
            echo "<td style='text-align: right;'>";
            echo number_format($currentFollowers);
            echo " <span style='color: " . ($growth >= 0 ? 'green' : 'red') . "; font-size: 11px;'>";
            echo "(" . ($growth >= 0 ? '+' : '') . number_format($growth) . ")";
            echo "</span>";
            echo "</td>";
        } else if (isset($followersData['error'])) {
            echo "<td style='color: red; font-size: 11px;'>{$followersData['error']['message']}</td>";
        } else {
            echo "<td style='color: orange;'>ไม่มีข้อมูล</td>";
        }
    } else {
        echo "<td style='color: red;'>Error</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

// ทดสอบ 3: ดู Raw Response
echo "<h2>3. Raw Response ตัวอย่าง</h2>";
echo "<p>ดึงข้อมูล Reach 7 วันย้อนหลัง:</p>";

$testUrl = "https://graph.facebook.com/v21.0/{$pageId}/insights?metric=page_impressions_unique&period=day&since=" . strtotime('-7 days') . "&until=" . strtotime('today') . "&access_token={$accessToken}";

echo "<p><small><code>" . substr($testUrl, 0, 100) . "...</code></small></p>";

$testResponse = @file_get_contents($testUrl);

if ($testResponse) {
    $testData = json_decode($testResponse, true);
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 400px; overflow-y: auto;'>";
    echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>ไม่สามารถดึงข้อมูลได้</p>";
}

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>Generated at: " . date('Y-m-d H:i:s') . "</p>";

?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 1400px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    
    h1 {
        color: #1877f2;
    }
    
    h2 {
        color: #333;
        margin-top: 30px;
        border-bottom: 2px solid #1877f2;
        padding-bottom: 10px;
    }
    
    table {
        background: white;
        margin: 20px 0;
    }
    
    th {
        background: #1877f2;
        color: white;
        padding: 12px;
    }
    
    td {
        padding: 10px;
    }
    
    code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
    }
</style>