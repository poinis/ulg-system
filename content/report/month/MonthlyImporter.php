<?php
/**
 * Monthly Social Media Importer
 * นำเข้าข้อมูลรายเดือน - Auto-detect เดือนจาก Publish Time ในแต่ละโพสต์
 * รองรับ Facebook CSV, Instagram CSV, TikTok Excel
 */

require_once 'config.php';

class MonthlyImporter {
    private $pdo;
    private $errors = [];
    private $successCount = 0;
    private $errorCount = 0;
    private $importedMonths = []; // เก็บ list ของเดือนที่ import
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * ดึง PDO connection
     */
    public function getPDO() {
        return $this->pdo;
    }
    
    /**
     * ตรวจจับ Platform จาก headers
     */
    public function detectPlatform($filePath, $headers = null) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (in_array($extension, ['xlsx', 'xls'])) {
            return 'TikTok';
        }
        
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
     * แปลงวันที่ และดึง month/year
     * Return: ['datetime' => 'Y-m-d H:i:s', 'month' => int, 'year' => int] หรือ null
     */
    private function parseDateTime($dateStr) {
        if (empty($dateStr)) {
            return null;
        }
        
        $dateStr = trim($dateStr);
        
        // TikTok format: YYYY/MM/DD HH:MM:SS
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/', $dateStr, $matches)) {
            return [
                'datetime' => "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}",
                'month' => (int)$matches[2],
                'year' => (int)$matches[1]
            ];
        }
        
        // TikTok format without seconds: YYYY/MM/DD HH:MM
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})\s+(\d{2}):(\d{2})/', $dateStr, $matches)) {
            return [
                'datetime' => "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:00",
                'month' => (int)$matches[2],
                'year' => (int)$matches[1]
            ];
        }
        
        // Facebook/Instagram format: MM/DD/YYYY HH:MM หรือ M/D/YYYY HH:MM
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})/', $dateStr, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];
            $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
            $minute = $matches[5];
            
            return [
                'datetime' => sprintf('%04d-%02d-%02d %s:%s:00', $year, $month, $day, $hour, $minute),
                'month' => $month,
                'year' => $year
            ];
        }
        
        // Try standard formats
        $formats = [
            'm/d/Y H:i' => true,
            'm/d/Y' => true,
            'Y-m-d H:i:s' => true,
            'Y-m-d' => true,
        ];
        
        foreach ($formats as $format => $dummy) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return [
                    'datetime' => $date->format('Y-m-d H:i:s'),
                    'month' => (int)$date->format('n'),
                    'year' => (int)$date->format('Y')
                ];
            }
        }
        
        return null;
    }
    
    /**
     * ทำความสะอาดตัวเลข
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
     * บันทึกเดือนที่ import
     */
    private function trackMonth($month, $year, $platform) {
        $key = "{$platform}_{$year}_{$month}";
        if (!isset($this->importedMonths[$key])) {
            $this->importedMonths[$key] = [
                'platform' => $platform,
                'month' => $month,
                'year' => $year
            ];
        }
    }
    
    /**
     * นำเข้าข้อมูลจากไฟล์
     */
    public function importFile($filePath, $originalName = null) {
        // ใช้ originalName ถ้ามี, ไม่งั้นใช้ filePath
        $fileToCheck = $originalName ?: $filePath;
        $extension = strtolower(pathinfo($fileToCheck, PATHINFO_EXTENSION));
        
        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->importTikTokExcel($filePath);
        } else {
            return $this->importCSV($filePath);
        }
    }
    
    /**
     * นำเข้าข้อมูลจาก CSV
     */
    public function importCSV($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("ไม่พบไฟล์: $filePath");
        }
        
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("ไม่สามารถเปิดไฟล์ได้");
        }
        
        // ลบ BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new Exception("ไม่สามารถอ่าน header ได้");
        }
        
        $platform = $this->detectPlatform($filePath, $headers);
        if (!$platform) {
            throw new Exception("ไม่สามารถระบุ Platform ได้");
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
        
        // อัพเดท Summary ทุกเดือนที่ import
        $this->updateAllMonthlySummaries();
        
        return [
            'platform' => $platform,
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'error_details' => $this->errors,
            'months_imported' => array_values($this->importedMonths)
        ];
    }
    
    /**
     * นำเข้าจาก TikTok Excel
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
        $headers = array_shift($rows);
        
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
        
        // อัพเดท Summary ทุกเดือนที่ import
        $this->updateAllMonthlySummaries();
        
        return [
            'platform' => 'TikTok',
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'error_details' => $this->errors,
            'months_imported' => array_values($this->importedMonths)
        ];
    }
    
    /**
     * Insert Facebook row
     */
    private function insertFacebookRow($row) {
        // Parse publish time เพื่อดึง month/year
        $publishTimeRaw = $row[6] ?? '';
        $dateInfo = $this->parseDateTime($publishTimeRaw);
        
        if (!$dateInfo) {
            throw new Exception("ไม่สามารถ parse วันที่ได้: $publishTimeRaw");
        }
        
        // Track month
        $this->trackMonth($dateInfo['month'], $dateInfo['year'], 'Facebook');
        
        $sql = "INSERT INTO monthly_posts (
            post_id, account_id, account_name, title, description, duration_sec,
            publish_time, permalink, post_type, views, reach, 
            reactions_comments_shares, reactions, comments, shares, 
            total_clicks, photo_clicks, other_clicks, link_clicks, video_clicks,
            seconds_viewed, average_seconds_viewed, likes, social,
            report_month, report_year
        ) VALUES (
            :post_id, :account_id, :account_name, :title, :description, :duration_sec,
            :publish_time, :permalink, :post_type, :views, :reach,
            :reactions_comments_shares, :reactions, :comments, :shares,
            :total_clicks, :photo_clicks, :other_clicks, :link_clicks, :video_clicks,
            :seconds_viewed, :average_seconds_viewed, :likes, 'Facebook',
            :report_month, :report_year
        )";
        
        $stmt = $this->pdo->prepare($sql);
        
        $data = [
            ':post_id' => $this->cleanText($row[0] ?? ''),
            ':account_id' => $this->cleanText($row[1] ?? ''),
            ':account_name' => $this->cleanText($row[2] ?? ''),
            ':title' => $this->cleanText($row[3] ?? ''),
            ':description' => $this->cleanText($row[4] ?? ''),
            ':duration_sec' => $this->cleanNumber($row[5] ?? 0),
            ':publish_time' => $dateInfo['datetime'],
            ':permalink' => $this->cleanText($row[8] ?? ''),
            ':post_type' => $this->cleanText($row[11] ?? ''),
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
            ':seconds_viewed' => $this->cleanNumber($row[29] ?? 0),
            ':average_seconds_viewed' => $this->cleanNumber($row[30] ?? 0),
            ':likes' => $this->cleanNumber($row[20] ?? 0),
            ':report_month' => $dateInfo['month'],
            ':report_year' => $dateInfo['year']
        ];
        
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Insert Instagram row
     */
    private function insertInstagramRow($row) {
        // Parse publish time เพื่อดึง month/year
        $publishTimeRaw = $row[6] ?? '';
        $dateInfo = $this->parseDateTime($publishTimeRaw);
        
        if (!$dateInfo) {
            throw new Exception("ไม่สามารถ parse วันที่ได้: $publishTimeRaw");
        }
        
        // Track month
        $this->trackMonth($dateInfo['month'], $dateInfo['year'], 'Instagram');
        
        $sql = "INSERT INTO monthly_posts (
            post_id, account_id, account_username, account_name, description,
            duration_sec, publish_time, permalink, post_type, views, reach, 
            likes, shares, follows, comments, saves, social,
            report_month, report_year
        ) VALUES (
            :post_id, :account_id, :account_username, :account_name, :description,
            :duration_sec, :publish_time, :permalink, :post_type, :views, :reach,
            :likes, :shares, :follows, :comments, :saves, 'Instagram',
            :report_month, :report_year
        )";
        
        $stmt = $this->pdo->prepare($sql);
        
        $data = [
            ':post_id' => $this->cleanText($row[0] ?? ''),
            ':account_id' => $this->cleanText($row[1] ?? ''),
            ':account_username' => $this->cleanText($row[2] ?? ''),
            ':account_name' => $this->cleanText($row[3] ?? ''),
            ':description' => $this->cleanText($row[4] ?? ''),
            ':duration_sec' => $this->cleanNumber($row[5] ?? 0),
            ':publish_time' => $dateInfo['datetime'],
            ':permalink' => $this->cleanText($row[7] ?? ''),
            ':post_type' => $this->cleanText($row[8] ?? ''),
            ':views' => $this->cleanNumber($row[11] ?? 0),
            ':reach' => $this->cleanNumber($row[12] ?? 0),
            ':likes' => $this->cleanNumber($row[13] ?? 0),
            ':shares' => $this->cleanNumber($row[14] ?? 0),
            ':follows' => $this->cleanNumber($row[15] ?? 0),
            ':comments' => $this->cleanNumber($row[16] ?? 0),
            ':saves' => $this->cleanNumber($row[17] ?? 0),
            ':report_month' => $dateInfo['month'],
            ':report_year' => $dateInfo['year']
        ];
        
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Insert TikTok row
     */
    private function insertTikTokRow($row) {
        // Parse publish time เพื่อดึง month/year
        $publishTimeRaw = $row[2] ?? '';
        $dateInfo = $this->parseDateTime($publishTimeRaw);
        
        if (!$dateInfo) {
            throw new Exception("ไม่สามารถ parse วันที่ได้: $publishTimeRaw");
        }
        
        // Track month
        $this->trackMonth($dateInfo['month'], $dateInfo['year'], 'TikTok');
        
        $sql = "INSERT INTO monthly_posts (
            title, permalink, publish_time, views, likes, comments, shares, favorites, social,
            report_month, report_year
        ) VALUES (
            :title, :permalink, :publish_time, :views, :likes, :comments, :shares, :favorites, 'TikTok',
            :report_month, :report_year
        )";
        
        $stmt = $this->pdo->prepare($sql);
        
        $data = [
            ':title' => $this->cleanText($row[0] ?? ''),
            ':permalink' => $this->cleanText($row[1] ?? ''),
            ':publish_time' => $dateInfo['datetime'],
            ':views' => $this->cleanNumber($row[3] ?? 0),
            ':likes' => $this->cleanNumber($row[4] ?? 0),
            ':comments' => $this->cleanNumber($row[5] ?? 0),
            ':shares' => $this->cleanNumber($row[6] ?? 0),
            ':favorites' => $this->cleanNumber($row[7] ?? 0),
            ':report_month' => $dateInfo['month'],
            ':report_year' => $dateInfo['year']
        ];
        
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * อัพเดท Summary ทุกเดือนที่ import
     */
    private function updateAllMonthlySummaries() {
        foreach ($this->importedMonths as $info) {
            $this->updateMonthlySummary($info['platform'], $info['month'], $info['year']);
        }
    }
    
    /**
     * อัพเดท Monthly Summary
     */
    public function updateMonthlySummary($platform, $month, $year) {
        $sql = "INSERT INTO monthly_summary 
                (social, report_month, report_year, total_posts, total_views, total_reach, 
                 total_likes, total_comments, total_shares, total_saves,
                 ad_posts, total_ad_spend, ad_views, ad_likes, ad_comments, ad_shares)
                SELECT 
                    social,
                    report_month,
                    report_year,
                    COUNT(*) as total_posts,
                    SUM(views) as total_views,
                    SUM(reach) as total_reach,
                    SUM(likes) as total_likes,
                    SUM(comments) as total_comments,
                    SUM(shares) as total_shares,
                    SUM(saves + favorites) as total_saves,
                    SUM(CASE WHEN is_ad = 1 THEN 1 ELSE 0 END) as ad_posts,
                    SUM(CASE WHEN is_ad = 1 THEN ad_spend ELSE 0 END) as total_ad_spend,
                    SUM(CASE WHEN is_ad = 1 THEN views ELSE 0 END) as ad_views,
                    SUM(CASE WHEN is_ad = 1 THEN likes ELSE 0 END) as ad_likes,
                    SUM(CASE WHEN is_ad = 1 THEN comments ELSE 0 END) as ad_comments,
                    SUM(CASE WHEN is_ad = 1 THEN shares ELSE 0 END) as ad_shares
                FROM monthly_posts
                WHERE social = :platform
                AND report_month = :month
                AND report_year = :year
                GROUP BY social, report_month, report_year
                ON DUPLICATE KEY UPDATE
                    total_posts = VALUES(total_posts),
                    total_views = VALUES(total_views),
                    total_reach = VALUES(total_reach),
                    total_likes = VALUES(total_likes),
                    total_comments = VALUES(total_comments),
                    total_shares = VALUES(total_shares),
                    total_saves = VALUES(total_saves),
                    ad_posts = VALUES(ad_posts),
                    total_ad_spend = VALUES(total_ad_spend),
                    ad_views = VALUES(ad_views),
                    ad_likes = VALUES(ad_likes),
                    ad_comments = VALUES(ad_comments),
                    ad_shares = VALUES(ad_shares)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':platform' => $platform,
            ':month' => $month,
            ':year' => $year
        ]);
    }
    
    /**
     * อัพเดทสถานะ Ad
     */
    public function updateAdStatus($postId, $isAd, $adSpend = 0) {
        $sql = "UPDATE monthly_posts SET is_ad = :is_ad, ad_spend = :ad_spend WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':is_ad' => $isAd ? 1 : 0,
            ':ad_spend' => floatval($adSpend),
            ':id' => $postId
        ]);
        
        // Get post info for summary update
        $stmt = $this->pdo->prepare("SELECT social, report_month, report_year FROM monthly_posts WHERE id = :id");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($post) {
            $this->updateMonthlySummary($post['social'], $post['report_month'], $post['report_year']);
        }
    }
    
    /**
     * อัพเดท Ad แบบ bulk
     */
    public function bulkUpdateAdStatus($updates) {
        $affectedMonths = [];
        
        foreach ($updates as $update) {
            $sql = "UPDATE monthly_posts SET is_ad = :is_ad, ad_spend = :ad_spend WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':is_ad' => $update['is_ad'] ? 1 : 0,
                ':ad_spend' => floatval($update['ad_spend'] ?? 0),
                ':id' => $update['id']
            ]);
            
            // Track affected months
            $stmt = $this->pdo->prepare("SELECT social, report_month, report_year FROM monthly_posts WHERE id = :id");
            $stmt->execute([':id' => $update['id']]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                $key = "{$post['social']}_{$post['report_year']}_{$post['report_month']}";
                $affectedMonths[$key] = $post;
            }
        }
        
        // Update summaries
        foreach ($affectedMonths as $post) {
            $this->updateMonthlySummary($post['social'], $post['report_month'], $post['report_year']);
        }
        
        return count($updates);
    }
    
    /**
     * ดึงโพสต์ตามเดือน
     */
    public function getPostsByMonth($month, $year, $platform = null, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM monthly_posts WHERE report_month = :month AND report_year = :year";
        $params = [':month' => $month, ':year' => $year];
        
        if ($platform) {
            $sql .= " AND social = :platform";
            $params[':platform'] = $platform;
        }
        
        $sql .= " ORDER BY publish_time DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ดึง Summary ตามเดือน
     */
    public function getMonthlySummary($month, $year, $platform = null) {
        $sql = "SELECT * FROM monthly_summary WHERE report_month = :month AND report_year = :year";
        $params = [':month' => $month, ':year' => $year];
        
        if ($platform) {
            $sql .= " AND social = :platform";
            $params[':platform'] = $platform;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ดึงเดือนที่มีข้อมูล
     */
    public function getAvailableMonths() {
        $sql = "SELECT DISTINCT report_month, report_year, social, COUNT(*) as post_count 
                FROM monthly_posts 
                GROUP BY report_month, report_year, social 
                ORDER BY report_year DESC, report_month DESC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ลบข้อมูลตามเดือน
     */
    public function deleteByMonth($month, $year, $platform = null) {
        $sql = "DELETE FROM monthly_posts WHERE report_month = :month AND report_year = :year";
        $params = [':month' => $month, ':year' => $year];
        
        if ($platform) {
            $sql .= " AND social = :platform";
            $params[':platform'] = $platform;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $deleted = $stmt->rowCount();
        
        // Delete from summary
        $sqlSum = "DELETE FROM monthly_summary WHERE report_month = :month AND report_year = :year";
        if ($platform) {
            $sqlSum .= " AND social = :platform";
        }
        $stmt = $this->pdo->prepare($sqlSum);
        $stmt->execute($params);
        
        return $deleted;
    }
    
    /**
     * ดึง error ทั้งหมด
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * ดึงสถิติการ import
     */
    public function getImportStats() {
        return [
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'months' => array_values($this->importedMonths)
        ];
    }
}