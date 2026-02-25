<?php
/**
 * Facebook Insights API Class
 * Class สำหรับดึงข้อมูล Insights จาก Facebook Page
 */

class FacebookInsights {
    private $accessToken;
    private $pageId;
    private $apiVersion = 'v21.0';
    
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
            'period' => $period,
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
            ['page_impressions_unique'],
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
            ['page_post_engagements', 'page_engaged_users'],
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
            ['page_consumptions'],
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

// ไม่มี example code ที่จะรบกวน output
// ถ้าต้องการทดสอบ ให้ใช้ไฟล์ test_connection.php แทน
?>