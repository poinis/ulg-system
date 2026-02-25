<?php
/**
 * API Endpoint สำหรับดึงข้อมูล Facebook Insights
 * ใช้เชื่อมต่อระหว่าง Dashboard กับ Facebook API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// นำเข้า Class FacebookInsights
require_once 'facebook_insights.php';

// กำหนดค่าการเชื่อมต่อ
define('FB_ACCESS_TOKEN', 'EAAMQjpNPx9kBQOXvfAyNW3HM6bdflMEg6R6fPcFT21uBLybdNT7O71oOJe3qqfay8DB3gu1qOb2KhSXvsnul4RQavkIEA4LSFyiNelFCjs6k07Rk0Hhj6zk4qYFLwNifYfVO5y8ldFFLBZAZCmSzDPRgkZCUkkUDU5zuIml5ZAqtPPiCmIc6Uf8yZAaLPnlyrlc5P');
define('FB_PAGE_ID', '489861450880996');

// สร้าง instance
$fb = new FacebookInsights(FB_ACCESS_TOKEN, FB_PAGE_ID);

// รับ action จาก query string
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'getInsights':
            getInsights($fb);
            break;
            
        case 'getPosts':
            getPosts($fb);
            break;
            
        case 'getPostDetails':
            getPostDetails($fb);
            break;
            
        case 'getSummary':
            getSummary($fb);
            break;
            
        default:
            sendError('Invalid action', 400);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * ดึงข้อมูล Insights ตามช่วงวันที่
 */
function getInsights($fb) {
    $start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-30 days'));
    $end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
    
    $since = strtotime($start);
    $until = strtotime($end);
    
    // ดึงข้อมูลทั้งหมด
    $reachData = $fb->getReach($since, $until);
    $followersData = $fb->getFollowers($since, $until);
    $engagementData = $fb->getEngagement($since, $until);
    
    // จัดรูปแบบข้อมูล
    $formattedData = [
        'success' => true,
        'period' => [
            'start' => $start,
            'end' => $end
        ],
        'reach' => processMetricData($reachData, 'page_impressions_unique'),
        'followers' => processMetricData($followersData, 'page_fans'),
        'engagement' => processMetricData($engagementData, 'page_post_engagements'),
        'dates' => extractDates($reachData)
    ];
    
    sendSuccess($formattedData);
}

/**
 * ดึงรายการโพสต์
 */
function getPosts($fb) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 25;
    
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
}

/**
 * ดึงรายละเอียดโพสต์พร้อม Insights
 */
function getPostDetails($fb) {
    $postId = isset($_GET['post_id']) ? $_GET['post_id'] : '';
    
    if (empty($postId)) {
        sendError('Post ID is required', 400);
    }
    
    $postInsights = $fb->getPostInsights($postId);
    
    $details = [
        'success' => true,
        'post_id' => $postId,
        'insights' => []
    ];
    
    if (isset($postInsights['data'])) {
        foreach ($postInsights['data'] as $metric) {
            $details['insights'][$metric['name']] = $metric['values'][0]['value'];
        }
    }
    
    sendSuccess($details);
}

/**
 * ดึงข้อมูลสรุปรวม
 */
function getSummary($fb) {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    
    $until = strtotime('today');
    $since = strtotime("-{$days} days", $until);
    
    // ดึงข้อมูล
    $reachData = $fb->getReach($since, $until);
    $followersData = $fb->getFollowers($since, $until);
    $engagementData = $fb->getEngagement($since, $until);
    
    // คำนวณค่าสรุป
    $reachMetrics = processMetricData($reachData, 'page_impressions_unique');
    $followerMetrics = processMetricData($followersData, 'page_fans');
    $engagementMetrics = processMetricData($engagementData, 'page_post_engagements');
    
    $summary = [
        'success' => true,
        'period_days' => $days,
        'summary' => [
            'reach' => [
                'total' => $reachMetrics['total'],
                'average' => $reachMetrics['average'],
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
                'average' => $engagementMetrics['average'],
                'change' => $engagementMetrics['change']
            ]
        ]
    ];
    
    sendSuccess($summary);
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
            
            // คำนวณเปอร์เซ็นต์การเปลี่ยนแปลง
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
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ===================================
 * ตัวอย่างการเรียกใช้งาน API
 * ===================================
 * 
 * 1. ดึงข้อมูล Insights:
 *    api.php?action=getInsights&start=2025-10-01&end=2025-10-31
 * 
 * 2. ดึงรายการโพสต์:
 *    api.php?action=getPosts&limit=25
 * 
 * 3. ดึงรายละเอียดโพสต์:
 *    api.php?action=getPostDetails&post_id=123456789
 * 
 * 4. ดึงข้อมูลสรุป:
 *    api.php?action=getSummary&days=30
 */
?>
