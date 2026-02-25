<?php
/**
 * ตัวอย่างการดึงข้อมูล Facebook Page Insights ด้วย PHP
 * 
 * ความต้องการ:
 * 1. Facebook App (สร้างที่ https://developers.facebook.com)
 * 2. Access Token ที่มีสิทธิ์ pages_read_engagement, pages_show_list
 * 3. Page ID ของเพจที่ต้องการดึงข้อมูล
 */

class FacebookInsights {
    private $accessToken;
    private $pageId;
    private $apiVersion = 'v21.0'; // เวอร์ชันล่าสุด
    
    public function __construct($accessToken, $pageId) {
        $this->accessToken = $accessToken;
        $this->pageId = $pageId;
    }
    
    /**
     * ดึงข้อมูล Insights จาก Facebook
     */
    public function getPageInsights($metrics, $period = 'day', $since = null, $until = null) {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->pageId}/insights";
        
        $params = [
            'access_token' => $this->accessToken,
            'metric' => implode(',', $metrics),
            'period' => $period, // day, week, days_28, lifetime
        ];
        
        if ($since) {
            $params['since'] = $since;
        }
        
        if ($until) {
            $params['until'] = $until;
        }
        
        $response = $this->makeRequest($url, $params);
        return $response;
    }
    
    /**
     * ดึงข้อมูลยอดมูล (Reach)
     */
    public function getReach($since, $until) {
        return $this->getPageInsights(
            ['page_impressions', 'page_impressions_unique'],
            'day',
            $since,
            $until
        );
    }
    
    /**
     * ดึงข้อมูลผู้ชม (Followers)
     */
    public function getFollowers($since, $until) {
        return $this->getPageInsights(
            ['page_fans', 'page_fan_adds', 'page_fan_removes'],
            'day',
            $since,
            $until
        );
    }
    
    /**
     * ดึงข้อมูลการโต้ตอบกับเนื้อหา (Engagement)
     */
    public function getEngagement($since, $until) {
        return $this->getPageInsights(
            [
                'page_post_engagements',
                'page_engaged_users',
                'page_consumptions',
                'page_negative_feedback'
            ],
            'day',
            $since,
            $until
        );
    }
    
    /**
     * ดึงข้อมูลการคลิกลิงก์
     */
    public function getClicks($since, $until) {
        return $this->getPageInsights(
            ['page_consumptions_by_consumption_type'],
            'day',
            $since,
            $until
        );
    }
    
    /**
     * ดึงข้อมูลโพสต์ทั้งหมด
     */
    public function getPosts($limit = 25) {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->pageId}/posts";
        
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,message,created_time,permalink_url,shares,likes.summary(true),comments.summary(true)',
            'limit' => $limit
        ];
        
        return $this->makeRequest($url, $params);
    }
    
    /**
     * ดึงข้อมูล Insights ของโพสต์เฉพาะ
     */
    public function getPostInsights($postId) {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$postId}/insights";
        
        $params = [
            'access_token' => $this->accessToken,
            'metric' => 'post_impressions,post_engaged_users,post_reactions_by_type_total'
        ];
        
        return $this->makeRequest($url, $params);
    }
    
    /**
     * ทำการ Request ไปยัง Facebook API
     */
    private function makeRequest($url, $params) {
        $queryString = http_build_query($params);
        $fullUrl = $url . '?' . $queryString;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: " . $error);
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMsg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            throw new Exception("Facebook API Error ({$httpCode}): " . $errorMsg);
        }
        
        return $data;
    }
}

/*
// ========================================
// ตัวอย่างการใช้งาน (ปิดไว้ เพราะจะรบกวน API output)
// ถ้าต้องการทดสอบ ให้ uncomment และรันไฟล์นี้โดยตรง
// ========================================

// กำหนดค่า Access Token และ Page ID
$accessToken = 'EAAMQjpNPx9kBQOXvfAyNW3HM6bdflMEg6R6fPcFT21uBLybdNT7O71oOJe3qqfay8DB3gu1qOb2KhSXvsnul4RQavkIEA4LSFyiNelFCjs6k07Rk0Hhj6zk4qYFLwNifYfVO5y8ldFFLBZAZCmSzDPRgkZCUkkUDU5zuIml5ZAqtPPiCmIc6Uf8yZAaLPnlyrlc5P';
$pageId = '489861450880996';

// สร้าง instance
$fb = new FacebookInsights($accessToken, $pageId);

try {
    // กำหนดช่วงวันที่ (Unix timestamp)
    $since = strtotime('2025-10-01'); // 1 ต.ค. 2025
    $until = strtotime('2025-10-31'); // 31 ต.ค. 2025
    
    // ====== ดึงข้อมูลยอดมูล (Reach) ======
    echo "<h2>ยอดมูล (Reach)</h2>\n";
    $reachData = $fb->getReach($since, $until);
    echo "<pre>" . print_r($reachData, true) . "</pre>\n";
    
    // ====== ดึงข้อมูลผู้ชม (Followers) ======
    echo "<h2>ผู้ชม (Followers)</h2>\n";
    $followersData = $fb->getFollowers($since, $until);
    echo "<pre>" . print_r($followersData, true) . "</pre>\n";
    
    // ====== ดึงข้อมูลการโต้ตอบ (Engagement) ======
    echo "<h2>การโต้ตอบกับเนื้อหา</h2>\n";
    $engagementData = $fb->getEngagement($since, $until);
    echo "<pre>" . print_r($engagementData, true) . "</pre>\n";
    
    // ====== ดึงโพสต์ล่าสุด ======
    echo "<h2>โพสต์ล่าสุด</h2>\n";
    $posts = $fb->getPosts(10);
    
    if (isset($posts['data'])) {
        foreach ($posts['data'] as $post) {
            echo "Post ID: {$post['id']}\n";
            echo "Created: {$post['created_time']}\n";
            echo "Message: " . (isset($post['message']) ? $post['message'] : 'N/A') . "\n";
            echo "Likes: " . (isset($post['likes']['summary']['total_count']) ? $post['likes']['summary']['total_count'] : 0) . "\n";
            echo "Comments: " . (isset($post['comments']['summary']['total_count']) ? $post['comments']['summary']['total_count'] : 0) . "\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/

// ========================================
// ฟังก์ชันช่วยเหลือเพิ่มเติม
// ========================================

/**
 * แปลงข้อมูล Insights เป็นรูปแบบที่อ่านง่าย
 */
function formatInsightsData($insightsData) {
    $formatted = [];
    
    if (isset($insightsData['data'])) {
        foreach ($insightsData['data'] as $metric) {
            $name = $metric['name'];
            $values = $metric['values'];
            
            $formatted[$name] = [];
            
            foreach ($values as $value) {
                $date = isset($value['end_time']) ? date('Y-m-d', strtotime($value['end_time'])) : 'N/A';
                $formatted[$name][$date] = $value['value'];
            }
        }
    }
    
    return $formatted;
}

/**
 * คำนวณเปอร์เซ็นต์การเปลี่ยนแปลง
 */
function calculatePercentageChange($oldValue, $newValue) {
    if ($oldValue == 0) {
        return $newValue > 0 ? 100 : 0;
    }
    
    return (($newValue - $oldValue) / $oldValue) * 100;
}

/**
 * สร้างรายงานสรุป
 */
function generateSummaryReport($reachData, $followersData, $engagementData) {
    $reach = formatInsightsData($reachData);
    $followers = formatInsightsData($followersData);
    $engagement = formatInsightsData($engagementData);
    
    echo "<h2>รายงานสรุป</h2>\n";
    
    // ยอดมูล
    if (isset($reach['page_impressions_unique'])) {
        $reachValues = array_values($reach['page_impressions_unique']);
        $totalReach = array_sum($reachValues);
        $avgReach = $totalReach / count($reachValues);
        
        echo "ยอดมูลเฉลี่ย: " . number_format($avgReach, 0) . " คน/วัน\n";
    }
    
    // ผู้ชม
    if (isset($followers['page_fans'])) {
        $followerValues = array_values($followers['page_fans']);
        $currentFollowers = end($followerValues);
        $startFollowers = reset($followerValues);
        $growth = $currentFollowers - $startFollowers;
        
        echo "ผู้ติดตามปัจจุบัน: " . number_format($currentFollowers, 0) . " คน\n";
        echo "เพิ่มขึ้น: " . number_format($growth, 0) . " คน\n";
    }
    
    // การโต้ตอบ
    if (isset($engagement['page_post_engagements'])) {
        $engagementValues = array_values($engagement['page_post_engagements']);
        $totalEngagement = array_sum($engagementValues);
        
        echo "การโต้ตอบรวม: " . number_format($totalEngagement, 0) . "\n";
    }
}

?>