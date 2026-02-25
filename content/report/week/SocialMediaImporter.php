<?php
/**
 * Social Media CSV/Excel Importer
 * รองรับการนำเข้าข้อมูลจาก Facebook, Instagram, TikTok
 */

require_once 'config.php';

class SocialMediaImporter {
    private $pdo;
    private $errors = [];
    private $successCount = 0;
    private $errorCount = 0;
    
    // Column mappings สำหรับแต่ละ platform
    private $facebookColumns = [
        'Post ID', 'Page ID', 'Page name', 'Title', 'Description', 'Duration (sec)',
        'Publish time', 'Caption type', 'Permalink', 'Is crosspost', 'Is share',
        'Post type', 'Languages', 'Custom labels', 'Funded content status',
        'Data comment', 'Date', 'Views', 'Reach', 'Reactions, Comments and Shares',
        'Reactions', 'Comments', 'Shares', 'Total clicks', 'Matched Audience Targeting Consumption (Photo Click)',
        'Other Clicks', 'Link Clicks', 'Matched Audience Targeting Consumption (Video Click)',
        'Negative feedback from users: Hide all', 'Seconds viewed', 'Average Seconds viewed',
        'Estimated earnings (USD)', 'Ad CPM (USD)', 'Ad impressions'
    ];
    
    private $instagramColumns = [
        'Post ID', 'Account ID', 'Account username', 'Account name', 'Description',
        'Duration (sec)', 'Publish time', 'Permalink', 'Post type', 'Data comment',
        'Date', 'Views', 'Reach', 'Likes', 'Shares', 'Follows', 'Comments', 'Saves'
    ];
    
    private $tiktokColumns = [
        'Video title', 'Video link', 'Post time', 'Video views', 'Likes',
        'Comments', 'Shares', 'Add to Favorites'
    ];
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * ตรวจจับประเภทไฟล์และ Platform
     */
    public function detectPlatform($filePath, $headers = null) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // TikTok มักเป็น Excel
        if (in_array($extension, ['xlsx', 'xls'])) {
            return 'TikTok';
        }
        
        // ถ้าเป็น CSV ให้ดูจาก headers
        if ($headers) {
            $headerStr = implode(',', array_map('strtolower', $headers));
            
            if (strpos($headerStr, 'page id') !== false && strpos($headerStr, 'page name') !== false) {
                return 'Facebook';
            }
            if (strpos($headerStr, 'account id') !== false && strpos($headerStr, 'account username') !== false) {
                return 'Instagram';
            }
            if (strpos($headerStr, 'video title') !== false || strpos($headerStr, 'video link') !== false) {
                return 'TikTok';
            }
        }
        
        return null;
    }
    
    /**
     * แปลงวันที่จากหลายรูปแบบเป็น MySQL format
     */
    private function parseDateTime($dateStr) {
        if (empty($dateStr)) {
            return null;
        }
        
        $formats = [
            'm/d/Y H:i',      // Facebook/Instagram: 11/10/2025 21:00
            'm/d/Y',
            'Y/m/d H:i:s',    // TikTok: 2025/12/12 11:30:00
            'Y/m/d H:i',
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y H:i',
            'd/m/Y'
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, trim($dateStr));
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        return null;
    }
    
    /**
     * ทำความสะอาดค่าตัวเลข
     */
    private function cleanNumber($value) {
        if (empty($value) || $value === 'N/A' || $value === '' || $value === null) {
            return 0;
        }
        return floatval(str_replace(',', '', $value));
    }
    
    /**
     * ทำความสะอาดข้อความ
     */
    private function cleanText($value) {
        if ($value === 'N/A' || $value === null) {
            return '';
        }
        return trim($value);
    }
    
    /**
     * นำเข้าข้อมูลจากไฟล์ (auto-detect)
     */
    public function importFile($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->importTikTokExcel($filePath);
        } else {
            return $this->importCSV($filePath);
        }
    }
    
    /**
     * นำเข้าข้อมูลจากไฟล์ CSV (Facebook/Instagram)
     */
    public function importCSV($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("ไม่พบไฟล์: $filePath");
        }
        
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("ไม่สามารถเปิดไฟล์ได้");
        }
        
        // ลบ BOM ถ้ามี
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        // อ่าน Header
        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new Exception("ไม่สามารถอ่าน header ของไฟล์ได้");
        }
        
        // ตรวจจับ Platform
        $platform = $this->detectPlatform($filePath, $headers);
        if (!$platform) {
            throw new Exception("ไม่สามารถระบุประเภท Platform ได้ กรุณาตรวจสอบรูปแบบไฟล์");
        }
        
        $lineNumber = 1;
        
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            if (count($row) < 5) {
                continue;
            }
            
            try {
                if ($platform === 'Facebook') {
                    $this->insertFacebookRow($row);
                } elseif ($platform === 'Instagram') {
                    $this->insertInstagramRow($row);
                }
                $this->successCount++;
            } catch (Exception $e) {
                $this->errorCount++;
                $this->errors[] = "แถวที่ $lineNumber: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        return [
            'platform' => $platform,
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'error_details' => $this->errors
        ];
    }
    
    /**
     * นำเข้าข้อมูลจาก TikTok Excel
     */
    public function importTikTokExcel($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("ไม่พบไฟล์: $filePath");
        }
        
        require_once 'SimpleXLSX.php';
        
        $xlsx = SimpleXLSX::parse($filePath);
        if (!$xlsx) {
            throw new Exception("ไม่สามารถอ่านไฟล์ Excel ได้: " . SimpleXLSX::parseError());
        }
        
        $rows = $xlsx->rows();
        $headers = array_shift($rows); // Remove header row
        
        $lineNumber = 1;
        
        foreach ($rows as $row) {
            $lineNumber++;
            
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }
            
            try {
                $this->insertTikTokRow($row);
                $this->successCount++;
            } catch (Exception $e) {
                $this->errorCount++;
                $this->errors[] = "แถวที่ $lineNumber: " . $e->getMessage();
            }
        }
        
        return [
            'platform' => 'TikTok',
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'error_details' => $this->errors
        ];
    }
    
    /**
     * Insert Facebook row
     */
    private function insertFacebookRow($row) {
        $sql = "INSERT INTO social_posts (
            post_id, account_id, account_name, title, description, duration_sec,
            publish_time, caption_type, permalink, is_crosspost, is_share,
            post_type, languages, custom_labels, funded_content_status,
            data_comment, date_type, views, reach, reactions_comments_shares,
            reactions, comments, shares, total_clicks, photo_clicks,
            other_clicks, link_clicks, video_clicks, negative_feedback,
            seconds_viewed, average_seconds_viewed, estimated_earnings_usd,
            ad_cpm_usd, ad_impressions, likes, social, created_at
        ) VALUES (
            :post_id, :account_id, :account_name, :title, :description, :duration_sec,
            :publish_time, :caption_type, :permalink, :is_crosspost, :is_share,
            :post_type, :languages, :custom_labels, :funded_content_status,
            :data_comment, :date_type, :views, :reach, :reactions_comments_shares,
            :reactions, :comments, :shares, :total_clicks, :photo_clicks,
            :other_clicks, :link_clicks, :video_clicks, :negative_feedback,
            :seconds_viewed, :average_seconds_viewed, :estimated_earnings_usd,
            :ad_cpm_usd, :ad_impressions, :likes, 'Facebook', NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        
        $data = [
            ':post_id' => $this->cleanText($row[0] ?? ''),
            ':account_id' => $this->cleanText($row[1] ?? ''),
            ':account_name' => $this->cleanText($row[2] ?? ''),
            ':title' => $this->cleanText($row[3] ?? ''),
            ':description' => $this->cleanText($row[4] ?? ''),
            ':duration_sec' => $this->cleanNumber($row[5] ?? 0),
            ':publish_time' => $this->parseDateTime($row[6] ?? ''),
            ':caption_type' => $this->cleanText($row[7] ?? ''),
            ':permalink' => $this->cleanText($row[8] ?? ''),
            ':is_crosspost' => $this->cleanNumber($row[9] ?? 0),
            ':is_share' => $this->cleanNumber($row[10] ?? 0),
            ':post_type' => $this->cleanText($row[11] ?? ''),
            ':languages' => $this->cleanText($row[12] ?? ''),
            ':custom_labels' => $this->cleanText($row[13] ?? ''),
            ':funded_content_status' => $this->cleanText($row[14] ?? ''),
            ':data_comment' => $this->cleanText($row[15] ?? ''),
            ':date_type' => $this->cleanText($row[16] ?? ''),
            ':views' => $this->cleanNumber($row[17] ?? 0),
            ':reach' => $this->cleanNumber($row[18] ?? 0),
            ':reactions_comments_shares' => $this->cleanNumber($row[19] ?? 0),
            ':reactions' => $this->cleanNumber($row[20] ?? 0),
            ':comments' => $this->cleanNumber($row[21] ?? 0),
            ':shares' => $this->cleanNumber($row[22] ?? 0),
            ':total_clicks' => $this->cleanNumber($row[23] ?? 0),
            ':photo_clicks' => $this->cleanNumber($row[24] ?? 0),
            ':other_clicks' => $this->cleanNumber($row[25] ?? 0),
            ':link_clicks' => $this->cleanNumber($row[26] ?? 0),
            ':video_clicks' => $this->cleanNumber($row[27] ?? 0),
            ':negative_feedback' => $this->cleanNumber($row[28] ?? 0),
            ':seconds_viewed' => $this->cleanNumber($row[29] ?? 0),
            ':average_seconds_viewed' => $this->cleanNumber($row[30] ?? 0),
            ':estimated_earnings_usd' => $this->cleanNumber($row[31] ?? 0),
            ':ad_cpm_usd' => $this->cleanNumber($row[32] ?? 0),
            ':ad_impressions' => $this->cleanNumber($row[33] ?? 0),
            ':likes' => $this->cleanNumber($row[20] ?? 0)
        ];
        
        $stmt->execute($data);
    }
    
    /**
     * Insert Instagram row
     */
    private function insertInstagramRow($row) {
        $sql = "INSERT INTO social_posts (
            post_id, account_id, account_username, account_name, description,
            duration_sec, publish_time, permalink, post_type, data_comment,
            date_type, views, reach, likes, shares, follows, comments, saves, social, created_at
        ) VALUES (
            :post_id, :account_id, :account_username, :account_name, :description,
            :duration_sec, :publish_time, :permalink, :post_type, :data_comment,
            :date_type, :views, :reach, :likes, :shares, :follows, :comments, :saves, 'Instagram', NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        
        $data = [
            ':post_id' => $this->cleanText($row[0] ?? ''),
            ':account_id' => $this->cleanText($row[1] ?? ''),
            ':account_username' => $this->cleanText($row[2] ?? ''),
            ':account_name' => $this->cleanText($row[3] ?? ''),
            ':description' => $this->cleanText($row[4] ?? ''),
            ':duration_sec' => $this->cleanNumber($row[5] ?? 0),
            ':publish_time' => $this->parseDateTime($row[6] ?? ''),
            ':permalink' => $this->cleanText($row[7] ?? ''),
            ':post_type' => $this->cleanText($row[8] ?? ''),
            ':data_comment' => $this->cleanText($row[9] ?? ''),
            ':date_type' => $this->cleanText($row[10] ?? ''),
            ':views' => $this->cleanNumber($row[11] ?? 0),
            ':reach' => $this->cleanNumber($row[12] ?? 0),
            ':likes' => $this->cleanNumber($row[13] ?? 0),
            ':shares' => $this->cleanNumber($row[14] ?? 0),
            ':follows' => $this->cleanNumber($row[15] ?? 0),
            ':comments' => $this->cleanNumber($row[16] ?? 0),
            ':saves' => $this->cleanNumber($row[17] ?? 0)
        ];
        
        $stmt->execute($data);
    }
    
    /**
     * Insert TikTok row
     */
    private function insertTikTokRow($row) {
        $sql = "INSERT INTO social_posts (
            title, permalink, publish_time, views, likes, comments, shares, favorites, social, created_at
        ) VALUES (
            :title, :permalink, :publish_time, :views, :likes, :comments, :shares, :favorites, 'TikTok', NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        
        $data = [
            ':title' => $this->cleanText($row[0] ?? ''),
            ':permalink' => $this->cleanText($row[1] ?? ''),
            ':publish_time' => $this->parseDateTime($row[2] ?? ''),
            ':views' => $this->cleanNumber($row[3] ?? 0),
            ':likes' => $this->cleanNumber($row[4] ?? 0),
            ':comments' => $this->cleanNumber($row[5] ?? 0),
            ':shares' => $this->cleanNumber($row[6] ?? 0),
            ':favorites' => $this->cleanNumber($row[7] ?? 0)
        ];
        
        $stmt->execute($data);
    }
    
    /**
     * ดึงข้อมูลทั้งหมด พร้อมวันที่อัพโหลด
     */
    public function getAllPosts($limit = 100, $offset = 0) {
        $sql = "SELECT *, created_at FROM social_posts ORDER BY created_at DESC, publish_time DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * ดึงข้อมูลตาม Social Platform พร้อมวันที่อัพโหลด
     */
    public function getPostsBySocial($social, $limit = 100, $offset = 0) {
        $sql = "SELECT *, created_at FROM social_posts WHERE social = :social ORDER BY created_at DESC, publish_time DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':social', $social, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * นับจำนวนโพสต์ตาม Social Platform
     */
    public function getCountBySocial() {
        $sql = "SELECT social, COUNT(*) as count FROM social_posts GROUP BY social";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * นับจำนวนทั้งหมด
     */
    public function getTotalCount() {
        $sql = "SELECT COUNT(*) as total FROM social_posts";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * สถิติรวม
     */
    public function getStatsSummary() {
        $sql = "SELECT 
                    social,
                    COUNT(*) as post_count,
                    SUM(views) as total_views,
                    SUM(likes) as total_likes,
                    SUM(comments) as total_comments,
                    SUM(shares) as total_shares
                FROM social_posts 
                GROUP BY social";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * ลบโพสต์ตาม ID
     */
    public function deletePost($id) {
        $sql = "DELETE FROM social_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * ลบโพสต์หลายรายการพร้อมกัน
     */
    public function deletePosts($ids) {
        if (empty($ids)) {
            return false;
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "DELETE FROM social_posts WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($ids);
    }
    
    /**
     * ลบข้อมูลทั้งหมด
     */
    public function truncateTable() {
        $this->pdo->exec("TRUNCATE TABLE social_posts");
    }
    
    /**
     * ลบข้อมูลตาม Platform
     */
    public function deleteByPlatform($platform) {
        $sql = "DELETE FROM social_posts WHERE social = :platform";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':platform' => $platform]);
        return $stmt->rowCount();
    }
    
    /**
     * Reset counters
     */
    public function resetCounters() {
        $this->successCount = 0;
        $this->errorCount = 0;
        $this->errors = [];
    }
}
?>