<?php
/**
 * API Endpoint ปรับปรุง - รองรับการเปลี่ยนเดือนและดึงข้อมูลหลายช่วงเวลา
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// จัดการ OPTIONS request สำหรับ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error reporting สำหรับ debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดการแสดง error ไปหน้า output

// นำเข้า Class FacebookInsights
// ใช้เวอร์ชัน clean ที่ไม่มี example code
if (file_exists('facebook_insights_clean.php')) {
    require_once 'facebook_insights_clean.php';
} else if (file_exists('facebook_insights.php')) {
    require_once 'facebook_insights.php';
} else {
    sendError('ไม่พบไฟล์ facebook_insights.php', 500);
}

// กำหนดค่าการเชื่อมต่อ
define('FB_ACCESS_TOKEN', 'EAATEt12ZCH1YBQMoGgzvWTb4gCuWI5DkeMGwipffwumMw2pZAcwpYSWH1xGpmJ1MzFDZCyOoHwVDl0rgBG7F7INCoDPd88lWLmN47enPEPEdiQR3e7oa1ZCmFHmzU1VvKtNcIcsJ3WUhfyyZCfKk80AdwZB2jxuZCjyypmrajCftG3XQvI5BuBntTNMaCh7eMUZAQwZDZD');
define('FB_PAGE_ID', '489861450880996');

// ตรวจสอบว่ามีการกำหนดค่า Token หรือยัง
if (FB_ACCESS_TOKEN === 'YOUR_PAGE_ACCESS_TOKEN_HERE' || FB_PAGE_ID === 'YOUR_PAGE_ID_HERE') {
    sendError('กรุณากำหนดค่า FB_ACCESS_TOKEN และ FB_PAGE_ID ในไฟล์ api.php', 500);
}

// สร้าง instance
try {
    $fb = new FacebookInsights(FB_ACCESS_TOKEN, FB_PAGE_ID);
} catch (Exception $e) {
    sendError('ไม่สามารถเชื่อมต่อ Facebook API: ' . $e->getMessage(), 500);
}

// รับ action จาก query string
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'getInsights':
            getInsights($fb);
            break;
            
        case 'getMonthlyComparison':
            getMonthlyComparison($fb);
            break;
            
        case 'getMultipleMonths':
            getMultipleMonths($fb);
            break;
            
        case 'getPosts':
            getPosts($fb);
            break;
            
        case 'getSummary':
            getSummary($fb);
            break;
            
        case 'testConnection':
            testConnection($fb);
            break;
            
        default:
            sendError('Invalid action. Available actions: getInsights, getMonthlyComparison, getMultipleMonths, getPosts, getSummary, testConnection', 400);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * ทดสอบการเชื่อมต่อ
 */
function testConnection($fb) {
    try {
        // ทดสอบดึงข้อมูลเพจ
        $url = "https://graph.facebook.com/v21.0/" . FB_PAGE_ID . "?fields=name,fan_count&access_token=" . FB_ACCESS_TOKEN;
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            sendError('การเชื่อมต่อล้มเหลว: ' . $data['error']['message'], 500);
        }
        
        sendSuccess([
            'success' => true,
            'message' => 'เชื่อมต่อสำเร็จ',
            'page_name' => $data['name'],
            'fan_count' => $data['fan_count'],
            'page_id' => FB_PAGE_ID
        ]);
    } catch (Exception $e) {
        sendError('ไม่สามารถเชื่อมต่อได้: ' . $e->getMessage(), 500);
    }
}

/**
 * ดึงข้อมูล Insights ตามช่วงวันที่ - ปรับปรุงให้รองรับเดือนต่างๆ
 */
function getInsights($fb) {
    // รับพารามิเตอร์วันที่
    $start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01'); // วันแรกของเดือน
    $end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d'); // วันนี้
    
    // แปลงเป็น Unix timestamp
    $since = strtotime($start);
    $until = strtotime($end);
    
    // ตรวจสอบความถูกต้องของวันที่
    if ($since === false || $until === false) {
        sendError('รูปแบบวันที่ไม่ถูกต้อง กรุณาใช้รูปแบบ YYYY-MM-DD', 400);
    }
    
    if ($since > $until) {
        sendError('วันที่เริ่มต้นต้องน้อยกว่าวันที่สิ้นสุด', 400);
    }
    
    try {
        // ดึงข้อมูลทั้งหมด
        $reachData = $fb->getReach($since, $until);
        $followersData = $fb->getFollowers($since, $until);
        $engagementData = $fb->getEngagement($since, $until);
        
        // จัดรูปแบบข้อมูล
        $formattedData = [
            'success' => true,
            'period' => [
                'start' => $start,
                'end' => $end,
                'days' => ceil(($until - $since) / 86400)
            ],
            'reach' => processMetricData($reachData, 'page_impressions_unique'),
            'followers' => processMetricData($followersData, 'page_fans'),
            'follower_adds' => processMetricData($followersData, 'page_fan_adds'),
            'follower_removes' => processMetricData($followersData, 'page_fan_removes'),
            'engagement' => processMetricData($engagementData, 'page_post_engagements'),
            'engaged_users' => processMetricData($engagementData, 'page_engaged_users'),
            'dates' => extractDates($reachData)
        ];
        
        sendSuccess($formattedData);
    } catch (Exception $e) {
        sendError('ไม่สามารถดึงข้อมูลได้: ' . $e->getMessage(), 500);
    }
}

/**
 * ดึงข้อมูลเปรียบเทียบหลายเดือน (ตามรูปที่ส่งมา)
 */
function getMultipleMonths($fb) {
    // รับจำนวนเดือนที่ต้องการ (default 3 เดือน)
    $monthCount = isset($_GET['months']) ? intval($_GET['months']) : 3;
    
    $results = [];
    
    // วนลูปดึงข้อมูลแต่ละเดือน
    for ($i = $monthCount - 1; $i >= 0; $i--) {
        $monthStart = strtotime("first day of -{$i} month");
        $monthEnd = strtotime("last day of -{$i} month");
        
        // ถ้าเป็นเดือนปัจจุบัน ให้ใช้วันนี้แทน
        if ($i === 0) {
            $monthEnd = strtotime('today');
        }
        
        try {
            $monthData = getMonthData($fb, $monthStart, $monthEnd);
            $results[] = $monthData;
        } catch (Exception $e) {
            // ถ้าดึงข้อมูลเดือนใดไม่ได้ ให้ข้าม
            $results[] = [
                'month' => date('M Y', $monthStart),
                'error' => $e->getMessage()
            ];
        }
    }
    
    // คำนวณการเปลี่ยนแปลง
    $comparison = [];
    for ($i = 1; $i < count($results); $i++) {
        if (isset($results[$i]['error']) || isset($results[$i-1]['error'])) {
            continue;
        }
        
        $comparison[] = [
            'from_month' => $results[$i-1]['month'],
            'to_month' => $results[$i]['month'],
            'reach_change' => calculateChange($results[$i-1]['total_reach'], $results[$i]['total_reach']),
            'engagement_change' => calculateChange($results[$i-1]['total_engagement'], $results[$i]['total_engagement']),
            'followers_change' => calculateChange($results[$i-1]['current_followers'], $results[$i]['current_followers'])
        ];
    }
    
    sendSuccess([
        'success' => true,
        'months' => $results,
        'comparison' => $comparison
    ]);
}

/**
 * ดึงข้อมูลรายเดือนแบบละเอียด (ตามตารางในรูป)
 */
function getMonthlyComparison($fb) {
    // รับเดือนที่ต้องการเปรียบเทียบ
    $months = isset($_GET['months']) ? explode(',', $_GET['months']) : [
        date('Y-m', strtotime('-2 months')),
        date('Y-m', strtotime('-1 month')),
        date('Y-m')
    ];
    
    $monthlyData = [];
    
    foreach ($months as $monthStr) {
        $year = substr($monthStr, 0, 4);
        $month = substr($monthStr, 5, 2);
        
        $firstDay = strtotime("{$year}-{$month}-01");
        $lastDay = strtotime("last day of {$year}-{$month}");
        
        // ถ้าเป็นเดือนปัจจุบัน ใช้วันนี้
        if ($monthStr === date('Y-m')) {
            $lastDay = strtotime('today');
        }
        
        try {
            $data = getDetailedMonthData($fb, $firstDay, $lastDay);
            $monthlyData[$monthStr] = $data;
        } catch (Exception $e) {
            $monthlyData[$monthStr] = [
                'error' => $e->getMessage()
            ];
        }
    }
    
    sendSuccess([
        'success' => true,
        'monthly_data' => $monthlyData
    ]);
}

/**
 * ดึงข้อมูลเดือนแบบสรุป
 */
function getMonthData($fb, $since, $until) {
    $reachData = $fb->getReach($since, $until);
    $followersData = $fb->getFollowers($since, $until);
    $engagementData = $fb->getEngagement($since, $until);
    
    $reach = processMetricData($reachData, 'page_impressions_unique');
    $followers = processMetricData($followersData, 'page_fans');
    $engagement = processMetricData($engagementData, 'page_post_engagements');
    
    return [
        'month' => date('M Y', $since),
        'month_code' => date('Y-m', $since),
        'total_reach' => $reach['total'],
        'avg_reach' => round($reach['average']),
        'total_engagement' => $engagement['total'],
        'avg_engagement' => round($engagement['average']),
        'current_followers' => end($followers['daily']),
        'follower_growth' => end($followers['daily']) - reset($followers['daily'])
    ];
}

/**
 * ดึงข้อมูลเดือนแบบละเอียด (ตามตาราง)
 */
function getDetailedMonthData($fb, $since, $until) {
    $reachData = $fb->getReach($since, $until);
    $followersData = $fb->getFollowers($since, $until);
    $engagementData = $fb->getEngagement($since, $until);
    
    $reach = processMetricData($reachData, 'page_impressions_unique');
    $impressions = processMetricData($reachData, 'page_impressions');
    $followers = processMetricData($followersData, 'page_fans');
    $followerAdds = processMetricData($followersData, 'page_fan_adds');
    $followerRemoves = processMetricData($followersData, 'page_fan_removes');
    $engagement = processMetricData($engagementData, 'page_post_engagements');
    $engagedUsers = processMetricData($engagementData, 'page_engaged_users');
    
    // คำนวณ Engagement Rate
    $totalReach = $reach['total'];
    $totalEngagement = $engagement['total'];
    $engagementRate = $totalReach > 0 ? ($totalEngagement / $totalReach) * 100 : 0;
    
    return [
        'total_reach' => $reach['total'],
        'engagement_rate' => round($engagementRate, 2),
        'conversion_engagement_link' => $engagement['total'], // ใช้แทน linkclick
        'conversion_engagement_profile' => $engagedUsers['total'],
        'net_new_followers' => $followerAdds['total'] - $followerRemoves['total'],
        'unfollows' => $followerRemoves['total'],
        'daily' => [
            'reach' => $reach['daily'],
            'engagement' => $engagement['daily'],
            'followers' => $followers['daily']
        ]
    ];
}

/**
 * ดึงรายการโพสต์
 */
function getPosts($fb) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 25;
    
    try {
        $postsData = $fb->getPosts($limit);
        
        $posts = [];
        if (isset($postsData['data'])) {
            foreach ($postsData['data'] as $post) {
                $posts[] = [
                    'id' => $post['id'],
                    'message' => isset($post['message']) ? $post['message'] : '',
                    'created_time' => $post['created_time'],
                    'permalink_url' => isset($post['permalink_url']) ? $post['permalink_url'] : '',
                    'likes' => isset($post['likes']['summary']['total_count']) ? $post['likes']['summary']['total_count'] : 0,
                    'comments' => isset($post['comments']['summary']['total_count']) ? $post['comments']['summary']['total_count'] : 0,
                    'shares' => isset($post['shares']['count']) ? $post['shares']['count'] : 0
                ];
            }
        }
        
        sendSuccess([
            'success' => true,
            'count' => count($posts),
            'posts' => $posts
        ]);
    } catch (Exception $e) {
        sendError('ไม่สามารถดึงรายการโพสต์ได้: ' . $e->getMessage(), 500);
    }
}

/**
 * ดึงข้อมูลสรุป
 */
function getSummary($fb) {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    
    $until = strtotime('today');
    $since = strtotime("-{$days} days", $until);
    
    try {
        $reachData = $fb->getReach($since, $until);
        $followersData = $fb->getFollowers($since, $until);
        $engagementData = $fb->getEngagement($since, $until);
        
        $reachMetrics = processMetricData($reachData, 'page_impressions_unique');
        $followerMetrics = processMetricData($followersData, 'page_fans');
        $engagementMetrics = processMetricData($engagementData, 'page_post_engagements');
        
        $summary = [
            'success' => true,
            'period_days' => $days,
            'summary' => [
                'reach' => [
                    'total' => $reachMetrics['total'],
                    'average' => round($reachMetrics['average']),
                    'change' => $reachMetrics['change'],
                    'highest' => max($reachMetrics['daily']),
                    'lowest' => min($reachMetrics['daily'])
                ],
                'followers' => [
                    'current' => end($followerMetrics['daily']),
                    'start' => reset($followerMetrics['daily']),
                    'gained' => end($followerMetrics['daily']) - reset($followerMetrics['daily']),
                    'change' => $followerMetrics['change']
                ],
                'engagement' => [
                    'total' => $engagementMetrics['total'],
                    'average' => round($engagementMetrics['average']),
                    'change' => $engagementMetrics['change']
                ]
            ]
        ];
        
        sendSuccess($summary);
    } catch (Exception $e) {
        sendError('ไม่สามารถดึงข้อมูลสรุปได้: ' . $e->getMessage(), 500);
    }
}

/**
 * คำนวณเปอร์เซ็นต์การเปลี่ยนแปลง
 */
function calculateChange($old, $new) {
    if ($old == 0) {
        return $new > 0 ? 100 : 0;
    }
    return round((($new - $old) / $old) * 100, 1);
}

/**
 * ประมวลผลข้อมูล metric
 */
function processMetricData($data, $metricName) {
    $result = [
        'total' => 0,
        'average' => 0,
        'change' => 0,
        'daily' => []
    ];
    
    if (!isset($data['data'])) {
        return $result;
    }
    
    foreach ($data['data'] as $metric) {
        if ($metric['name'] === $metricName) {
            $values = array_column($metric['values'], 'value');
            $result['daily'] = $values;
            $result['total'] = array_sum($values);
            $result['average'] = count($values) > 0 ? $result['total'] / count($values) : 0;
            
            if (count($values) >= 2) {
                $firstHalf = array_slice($values, 0, floor(count($values) / 2));
                $secondHalf = array_slice($values, floor(count($values) / 2));
                
                $firstAvg = array_sum($firstHalf) / count($firstHalf);
                $secondAvg = array_sum($secondHalf) / count($secondHalf);
                
                if ($firstAvg > 0) {
                    $result['change'] = round((($secondAvg - $firstAvg) / $firstAvg) * 100, 1);
                }
            }
            
            break;
        }
    }
    
    return $result;
}

/**
 * ดึงรายการวันที่จากข้อมูล
 */
function extractDates($data) {
    $dates = [];
    
    if (isset($data['data'][0]['values'])) {
        foreach ($data['data'][0]['values'] as $value) {
            if (isset($value['end_time'])) {
                $dates[] = date('j M', strtotime($value['end_time']));
            }
        }
    }
    
    return $dates;
}

/**
 * ส่งข้อมูล JSON สำเร็จ
 */
function sendSuccess($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * ส่งข้อความ error
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'code' => $code
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>