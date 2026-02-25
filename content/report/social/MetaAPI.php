<?php
/**
 * MetaAPI.php
 * Utility class for Meta Graph API (Facebook & Instagram)
 */

class MetaAPI
{
    private $appId;
    private $appSecret;
    private $userAccessToken; // ควรเป็น Long-lived
    private $graphBase;

    public function __construct($userAccessToken, $appId = null, $appSecret = null, $graphVersion = 'v19.0')
    {
        // รองรับค่าจากคอนฟิก (ถ้ามีการ define ไว้)
        $this->appId         = $appId ?: (defined('META_APP_ID') ? META_APP_ID : null);
        $this->appSecret     = $appSecret ?: (defined('META_APP_SECRET') ? META_APP_SECRET : null);
        $this->userAccessToken = $userAccessToken;
        $this->graphBase     = 'https://graph.facebook.com/' . $graphVersion;
    }

    // ============== HTTP Helpers ==============
    private function request($method, $path, $params = [], $accessToken = null)
    {
        $url = $this->graphBase . $path;

        // ใส่ access_token ถ้ายังไม่ได้ส่งเข้ามา
        if (!isset($params['access_token'])) {
            $params['access_token'] = $accessToken ?: $this->userAccessToken;
        }

        $ch = curl_init();

        if (strtoupper($method) === 'GET') {
            $url .= '?' . http_build_query($params);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception('HTTP Error: ' . $err);
        }

        $resp = json_decode($raw, true);
        if (isset($resp['error'])) {
            $msg = $resp['error']['message'] ?? 'Unknown API Error';
            $code = isset($resp['error']['code']) ? ' (code ' . $resp['error']['code'] . ')' : '';
            $subcode = isset($resp['error']['error_subcode']) ? ' (subcode ' . $resp['error']['error_subcode'] . ')' : '';
            throw new Exception('API Error: ' . $msg . $code . $subcode);
        }

        return $resp;
    }

    private function get($path, $params = [], $accessToken = null)
    {
        return $this->request('GET', $path, $params, $accessToken);
    }

    private function post($path, $params = [], $accessToken = null)
    {
        return $this->request('POST', $path, $params, $accessToken);
    }

    // ============== Token Utilities ==============

    // ตรวจสอบ user/page token ด้วย /debug_token
    public function debugToken($inputToken)
    {
        $appToken = $this->getAppAccessToken();
        $resp = $this->get('/debug_token', [
            'input_token'  => $inputToken,
            'access_token' => $appToken
        ], $appToken);

        return $resp;
    }

    private function getAppAccessToken()
    {
        if (!$this->appId || !$this->appSecret) {
            // ใช้รูปแบบแบบง่าย app_id|app_secret
            // หมายเหตุ: ควรเก็บเป็นความลับ
            return $this->appId . '|' . $this->appSecret;
        }
        return $this->appId . '|' . $this->appSecret;
    }

    // แลก Short‑lived User Token เป็น Long‑lived User Token
    public function exchangeLongLivedUserToken($shortLivedToken)
    {
        if (!$this->appId || !$this->appSecret) {
            throw new Exception('App ID/Secret not configured for token exchange');
        }

        $resp = $this->get('/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->appId,
            'client_secret'     => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken
        ], null);

        return $resp; // array: access_token, token_type, expires_in
    }

    // ตรวจสอบ token ปัจจุบันว่า valid และอ่านชื่อผู้ใช้
    public function verifyToken()
    {
        try {
            $dbg = $this->debugToken($this->userAccessToken);
            $valid = !empty($dbg['data']['is_valid']);

            if ($valid) {
                // อ่านชื่อผู้ใช้
                $me = $this->get('/me', ['fields' => 'name'], $this->userAccessToken);
                return [
                    'valid' => true,
                    'name'  => $me['name'] ?? 'User'
                ];
            } else {
                return [
                    'valid' => false,
                    'error' => 'Invalid token'
                ];
            }
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // ============== Facebook Pages ==============

    // ดึงรายชื่อเพจที่ผู้ใช้เข้าถึง พร้อม access_token
    public function getPages()
    {
        $pages = [];
        $after = null;

        do {
            $params = [
                'fields' => 'id,name,fan_count,followers_count,access_token',
                'limit'  => 200
            ];
            if ($after) {
                $params['after'] = $after;
            }

            $resp = $this->get('/me/accounts', $params, $this->userAccessToken);
            if (!empty($resp['data'])) {
                foreach ($resp['data'] as $p) {
                    $pages[] = $p;
                }
            }
            $after = $resp['paging']['cursors']['after'] ?? null;
        } while ($after);

        return $pages;
    }

    // ขอ Page Access Token สด ต้องมีสิทธิ์ pages_manage_metadata
    public function getPageAccessToken($pageId)
    {
        $resp = $this->get('/' . $pageId, [
            'fields' => 'access_token'
        ], $this->userAccessToken);

        return $resp['access_token'] ?? null;
    }

    // ============== Instagram ==============

    // ดึง Instagram Business Account ที่ผูกกับเพจ
    public function getInstagramAccount($pageId)
    {
        // Step 1: ดึง ig id
        $resp = $this->get('/' . $pageId, [
            'fields' => 'instagram_business_account'
        ], $this->userAccessToken);

        $ig = $resp['instagram_business_account']['id'] ?? null;
        if (!$ig) {
            return null;
        }

        // Step 2: ดึงข้อมูล IG เพิ่มเติม
        $igData = $this->get('/' . $ig, [
            'fields' => 'id,username,name,followers_count,media_count'
        ], $this->userAccessToken);

        return $igData;
    }
}