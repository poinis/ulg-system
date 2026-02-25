<?php
/**
 * API Settings & Sync - Meta Graph API (Facebook & Instagram)
 * Changes:
 * - แลก Long‑lived User Token ตอนบันทึก
 * - รีเฟรช Page Access Token สดก่อน sync ด้วย pages_manage_metadata
 * - ตรวจสอบ token ด้วย /debug_token และแจ้ง error code 190 ชัดเจน
 * - ปรับรายการ permissions แนะนำ
 * - เลิกใช้ json_encode/json_decode สำหรับ settings เพื่อลด Deprecated warnings บน PHP 8.1+
 */

require_once 'config.php';
require_once 'MetaAPI.php';

$pdo = getDBConnection();
$settings = new APISettingsManager($pdo);

$message = '';
$messageType = '';
$pages = [];
$igAccounts = [];
$tokenInfo = null;

// ========================================
// Handle form submissions
// ========================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Save Access Token (แลก Long‑lived ถ้าทำได้)
    if (isset($_POST['savetoken'])) {
        $token = trim($_POST['accesstoken']);

        if (!empty($token)) {
            $finalToken = $token;
            try {
                $api = new MetaAPI($token);
                // พยายามแลกเป็น Long‑lived
                $ex = $api->exchangeLongLivedUserToken($token);
                if (!empty($ex['access_token'])) {
                    $finalToken = $ex['access_token'];
                }
            } catch (Exception $e) {
                error_log('Warn: cannot exchange to long-lived token: ' . $e->getMessage());
            }

            $settings->set('meta', 'accesstoken', $finalToken);
            $message = 'บันทึก Access Token สำเร็จ! (พยายามแลกเป็น Long‑lived แล้ว)';
            $messageType = 'success';
        }
    }

    // Save Page Settings
    if (isset($_POST['savepages'])) {
        $selectedPages = $_POST['pages'] ?? [];
        // เก็บ array ลง settings โดยตรง (ไม่ต้อง json_encode)
        $settings->set('meta', 'selected_pages', $selectedPages);

        // Save Page Access Tokens & Names (เผื่อ fallback)
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'page_token_') === 0) {
                $pageId = str_replace('page_token_', '', $key);
                $settings->set('meta', 'page_token_' . $pageId, $value);
            }
            if (strpos($key, 'page_name_') === 0) {
                $pageId = str_replace('page_name_', '', $key);
                $settings->set('meta', 'page_name_' . $pageId, $value);
            }
        }

        $message = 'บันทึกการตั้งค่า Pages สำเร็จ!';
        $messageType = 'success';
    }

    // Save Instagram Settings
    if (isset($_POST['saveinstagram'])) {
        $selectedIG = $_POST['instagram'] ?? [];
        // เก็บ array ลง settings โดยตรง (ไม่ต้อง json_encode)
        $settings->set('meta', 'selected_instagram', $selectedIG);

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'ig_name_') === 0) {
                $igId = str_replace('ig_name_', '', $key);
                $settings->set('meta', 'ig_name_' . $igId, $value);
            }
            if (strpos($key, 'ig_username_') === 0) {
                $igId = str_replace('ig_username_', '', $key);
                $settings->set('meta', 'ig_username_' . $igId, $value);
            }
        }

        $message = 'บันทึกการตั้งค่า Instagram สำเร็จ!';
        $messageType = 'success';
    }

    // Sync Data
    if (isset($_POST['syncdata'])) {
        $syncPlatform = $_POST['syncplatform'];
        $syncSince = $_POST['syncsince'];
        $syncUntil = $_POST['syncuntil'];

        try {
            $accessToken = $settings->get('meta', 'accesstoken');

            if (empty($accessToken)) {
                throw new Exception('กรุณาบันทึก Access Token ก่อน');
            }

            $sync = new MetaDataSync($pdo, $accessToken);
            $totalImported = 0;
            $errors = [];
            $successDetails = [];

            // Prepare API helper
            $api = new MetaAPI($accessToken);

            // Sync Facebook pages
            if ($syncPlatform == 'facebook' || $syncPlatform == 'all') {
                // อ่าน selected_pages เป็น array โดยตรง
                $selectedPages = $settings->get('meta', 'selected_pages', []);
                if (!is_array($selectedPages)) { $selectedPages = []; }

                if (!empty($selectedPages)) {
                    // ดึงรายการเพจและ page token ปัจจุบัน (ถ้ามี) เพื่อเป็น fallback
                    $pageTokensMap = [];
                    try {
                        $allPagesList = $api->getPages();
                        foreach ($allPagesList as $p) {
                            if (!empty($p['id']) && !empty($p['access_token'])) {
                                $pageTokensMap[$p['id']] = $p['access_token'];
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Warning: Could not fetch fresh page list: " . $e->getMessage());
                    }

                    foreach ($selectedPages as $pageId) {
                        $pageName = $settings->get('meta', 'page_name_' . $pageId, 'Unknown Page');

                        try {
                            // 1) พยายามดึง Page Token สดจาก API (ต้องมี pages_manage_metadata)
                            $pageToken = null;
                            try {
                                $pageToken = $api->getPageAccessToken($pageId);
                            } catch (Exception $e) {
                                // เงียบไว้ก่อน ค่อย fallback
                                error_log("Info: getPageAccessToken failed for {$pageId}: " . $e->getMessage());
                            }

                            // 2) ถ้ายังไม่ได้ ใช้จากรายการ pages ที่ดึงมาก่อนหน้า
                            if (!$pageToken && isset($pageTokensMap[$pageId])) {
                                $pageToken = $pageTokensMap[$pageId];
                            }

                            // 3) ถ้ายังไม่ได้ ใช้ตัวที่เคยบันทึกไว้ใน DB เป็นตัวสุดท้าย
                            if (!$pageToken) {
                                $savedToken = $settings->get('meta', 'page_token_' . $pageId);
                                if (!empty($savedToken)) {
                                    $pageToken = $savedToken;
                                }
                            }

                            if (!$pageToken) {
                                throw new Exception("ไม่พบ Page Access Token (กรุณาให้สิทธิ์ pages_manage_metadata, จากนั้นกดบันทึก Pages ใหม่)");
                            }

                            // ตรวจสอบ token ด้วย debug_token
                            try {
                                $dbg = $api->debugToken($pageToken);
                                $isValid = !empty($dbg['data']['is_valid']);
                                if (!$isValid) {
                                    throw new Exception("Page Token ไม่ถูกต้อง (code 190) กรุณาออก Long‑lived User Token ใหม่และบันทึก Pages อีกครั้ง");
                                }
                            } catch (Exception $e) {
                                // ถ้า debug ล้มเหลว ให้ลองไปต่อ แต่ถ้า error มี code 190 ชัดเจน ให้โยนทิ้ง
                                if (stripos($e->getMessage(), 'code 190') !== false || stripos($e->getMessage(), 'Invalid OAuth') !== false) {
                                    throw $e;
                                }
                                error_log("Warn: debugToken failed for page {$pageId}: " . $e->getMessage());
                            }

                            // Log เพื่อ debug
                            error_log("🔄 Syncing Facebook: {$pageName} (ID: {$pageId})");

                            $imported = $sync->syncFacebookPage($pageId, $pageToken, $pageName, $syncSince, $syncUntil);
                            $totalImported += $imported;

                            $successDetails[] = "✅ Facebook ({$pageName}): {$imported} โพสต์";
                            error_log("✅ {$pageName}: Imported {$imported} posts");
                        } catch (Exception $e) {
                            $errMsg = $e->getMessage();
                            if (stripos($errMsg, 'code 190') !== false || stripos($errMsg, 'Invalid OAuth') !== false) {
                                $errMsg .= ' — Token หมดอายุ/ไม่ถูกต้อง กรุณา:
- ออก Long‑lived User Token ใหม่จากแอปเดียวกับ backend
- ให้สิทธิ์ pages_manage_metadata
- กลับมาหน้านี้กดบันทึก Token และบันทึก Pages ใหม่';
                            }
                            $errorMsg = "❌ Facebook ({$pageName}): " . $errMsg;
                            $errors[] = $errorMsg;
                            error_log($errorMsg);
                        }
                    }
                } else {
                    $errors[] = "⚠️ ไม่ได้เลือก Facebook Pages";
                }
            }

            // Sync Instagram accounts
            if ($syncPlatform == 'instagram' || $syncPlatform == 'all') {
                // อ่าน selected_instagram เป็น array โดยตรง
                $selectedIG = $settings->get('meta', 'selected_instagram', []);
                if (!is_array($selectedIG)) { $selectedIG = []; }

                if (!empty($selectedIG)) {
                    foreach ($selectedIG as $igId) {
                        $igName = $settings->get('meta', 'ig_name_' . $igId, 'Unknown');
                        $igUsername = $settings->get('meta', 'ig_username_' . $igId, '');

                        try {
                            error_log("🔄 Syncing Instagram: {$igName} (@{$igUsername})");

                            $imported = $sync->syncInstagramAccount($igId, $igUsername, $igName, $syncSince, $syncUntil);
                            $totalImported += $imported;

                            $successDetails[] = "✅ Instagram ({$igName}): {$imported} โพสต์";
                            error_log("✅ {$igName}: Imported {$imported} posts");
                        } catch (Exception $e) {
                            $errorMsg = "❌ Instagram ({$igName}): " . $e->getMessage();
                            $errors[] = $errorMsg;
                            error_log($errorMsg);
                        }
                    }
                } else {
                    if ($syncPlatform == 'instagram') {
                        $errors[] = "⚠️ ไม่ได้เลือก Instagram Accounts";
                    }
                }
            }

            // สร้างข้อความแสดงผล
            if ($totalImported > 0) {
                $message = "✅ <strong>Sync สำเร็จ! นำเข้าข้อมูลทั้งหมด {$totalImported} โพสต์</strong><br><br>";

                // แสดงรายละเอียดที่สำเร็จ
                if (!empty($successDetails)) {
                    $message .= "<strong>รายละเอียด:</strong><br>";
                    $message .= implode("<br>", $successDetails);
                }

                // ถ้ามี error แสดงเป็น warning
                if (!empty($errors)) {
                    $message .= "<br><br><strong>⚠️ บางรายการมีปัญหา:</strong><br>";
                    $message .= implode("<br>", $errors);
                }

                $messageType = 'success';
            } else {
                // ไม่มีข้อมูลเลย
                if (!empty($errors)) {
                    $message = "<strong>❌ ไม่สามารถ sync ข้อมูลได้</strong><br><br>";
                    $message .= implode("<br>", $errors);
                } else {
                    $message = "⚠️ ไม่พบข้อมูลใหม่ในช่วงเวลาที่เลือก";
                }
                $messageType = 'error';
            }

        } catch (Exception $e) {
            $message = "❌ <strong>Error:</strong> " . $e->getMessage();
            $messageType = 'error';
            error_log("Fatal Error in Sync: " . $e->getMessage());
        }
    }
}

// ========================================
// Load current token and verify
// ========================================

$currentToken = $settings->get('meta', 'accesstoken', '');

// โหลดค่าที่ใช้กับ checkbox ให้เป็น array (ไม่ json_decode)
$selectedPages = $settings->get('meta', 'selected_pages', []);
if (!is_array($selectedPages)) { $selectedPages = []; }

$selectedIG = $settings->get('meta', 'selected_instagram', []);
if (!is_array($selectedIG)) { $selectedIG = []; }

// Verify Token and load Pages/IG
if (!empty($currentToken)) {
    try {
        $api = new MetaAPI($currentToken);
        $tokenInfo = $api->verifyToken();

        if ($tokenInfo['valid']) {
            // Get Facebook Pages
            $pages = $api->getPages();

            // Get Instagram accounts for each page
            foreach ($pages as $page) {
                try {
                    $igAccount = $api->getInstagramAccount($page['id']);
                    if ($igAccount) {
                        $igAccounts[] = array_merge($igAccount, [
                            'page_id' => $page['id'],
                            'page_name' => $page['name']
                        ]);
                    }
                } catch (Exception $e) {
                    // Some pages don't have IG account
                    error_log("No IG account for page {$page['name']}: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        $tokenInfo = ['valid' => false, 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Settings - Social Media Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0d0d2b 100%); min-height: 100vh; padding: 20px; color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); padding: 25px 35px; border-radius: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 26px; display: flex; align-items: center; gap: 12px; }
        .nav-links { display: flex; gap: 12px; }
        .nav-link { color: white; text-decoration: none; padding: 10px 20px; background: rgba(255,255,255,0.1); border-radius: 10px; transition: all 0.3s; font-weight: 500; font-size: 14px; }
        .nav-link:hover { background: rgba(255,255,255,0.2); }
        .card { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 25px; margin-bottom: 20px; }
        .card-title { font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .message { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; line-height: 1.6; }
        .message.success { background: rgba(0, 245, 160, 0.2); border: 1px solid rgba(0, 245, 160, 0.3); color: #00f5a0; }
        .message.error { background: rgba(255, 82, 82, 0.2); border: 1px solid rgba(255, 82, 82, 0.3); color: #ff5252; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; color: rgba(255,255,255,0.7); }
        .form-group input[type="text"], .form-group input[type="date"], .form-group textarea, .form-group select { width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: white; font-size: 14px; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        .form-group textarea { min-height: 100px; font-family: monospace; }
        .btn { padding: 12px 25px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 14px; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(135deg, #00f5a0, #00d9f5); color: #000; }
        .btn-facebook { background: #1877F2; color: white; }
        .btn-instagram { background: linear-gradient(135deg, #E4405F, #C13584); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        .token-status { display: flex; align-items: center; gap: 10px; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .token-status.valid { background: rgba(0, 245, 160, 0.1); border: 1px solid rgba(0, 245, 160, 0.2); }
        .token-status.invalid { background: rgba(255, 82, 82, 0.1); border: 1px solid rgba(255, 82, 82, 0.2); }
        .status-dot { width: 12px; height: 12px; border-radius: 50%; }
        .status-dot.valid { background: #00f5a0; }
        .status-dot.invalid { background: #ff5252; }
        .page-list { display: grid; gap: 15px; }
        .page-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; display: flex; align-items: flex-start; gap: 15px; }
        .page-item input[type="checkbox"] { width: 20px; height: 20px; margin-top: 3px; }
        .page-info { flex: 1; }
        .page-name { font-size: 16px; font-weight: 600; margin-bottom: 5px; }
        .page-meta { font-size: 13px; color: rgba(255,255,255,0.5); }
        .sync-section { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .info-box { background: rgba(102, 126, 234, 0.1); border: 1px solid rgba(102, 126, 234, 0.2); border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .info-box h4 { color: #667eea; margin-bottom: 10px; }
        .info-box ul { margin-left: 20px; font-size: 13px; color: rgba(255,255,255,0.7); }
        .info-box li { margin-bottom: 5px; }
        .info-box code { background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; }
            .sync-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 API Settings</h1>
            <div class="nav-links">
                <a href="index.php" class="nav-link">📊 Dashboard</a>
                <a href="upload.php" class="nav-link">📤 Upload</a>
                <a href="view_data.php" class="nav-link">📋 Data</a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">🔑 Meta Access Token</h2>

            <div class="info-box">
                <h4>📌 วิธีสร้าง Access Token:</h4>
                <ul>
                    <li>ไปที่ <a href="https://developers.facebook.com/tools/explorer" target="_blank" style="color: #667eea;">Graph API Explorer</a></li>
                    <li>เลือก App ของคุณ (ต้องเป็นแอปเดียวกับที่ backend ใช้)</li>
                    <li>เลือก Permissions:
                        <code>pages_read_engagement</code>,
                        <code>pages_read_user_content</code>,
                        <code>pages_manage_metadata</code>,
                        <code>read_insights</code>,
                        <code>instagram_basic</code>,
                        <code>instagram_manage_insights</code>
                    </li>
                    <li>Generate Access Token แล้วแลกเป็น Long‑lived</li>
                    <li>สำหรับ Production แนะนำ System User Token และผูกเพจเป็น Asset ให้ครบ</li>
                </ul>
            </div>

            <?php if ($tokenInfo): ?>
            <div class="token-status <?php echo $tokenInfo['valid'] ? 'valid' : 'invalid'; ?>">
                <span class="status-dot <?php echo $tokenInfo['valid'] ? 'valid' : 'invalid'; ?>"></span>
                <?php if ($tokenInfo['valid']): ?>
                    <span>Token ใช้งานได้ - <strong><?php echo htmlspecialchars($tokenInfo['name']); ?></strong></span>
                <?php else: ?>
                    <span>Token ไม่ถูกต้อง: <?php echo htmlspecialchars($tokenInfo['error']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Access Token</label>
                    <textarea name="accesstoken" placeholder="วาง Access Token ที่นี่..."><?php echo htmlspecialchars($currentToken); ?></textarea>
                </div>
                <button type="submit" name="savetoken" class="btn btn-primary">💾 บันทึก Token</button>
            </form>
        </div>

        <?php if ($tokenInfo && $tokenInfo['valid']): ?>

        <div class="card">
            <h2 class="card-title">📘 Facebook Pages</h2>

            <?php if (empty($pages)): ?>
                <p style="color: rgba(255,255,255,0.5);">ไม่พบ Pages หรือไม่มี Permissions</p>
            <?php else: ?>
                <form method="POST">
                    <div class="page-list">
                        <?php foreach ($pages as $page): ?>
                        <div class="page-item">
                            <input type="checkbox"
                                   name="pages[]"
                                   value="<?php echo $page['id']; ?>"
                                   <?php echo in_array($page['id'], $selectedPages) ? 'checked' : ''; ?>>
                            <div class="page-info">
                                <div class="page-name"><?php echo htmlspecialchars($page['name']); ?></div>
                                <div class="page-meta">
                                    ID: <?php echo $page['id']; ?> |
                                    Followers: <?php echo number_format($page['followers_count'] ?? $page['fan_count'] ?? 0); ?>
                                </div>
                                <input type="hidden" name="page_name_<?php echo $page['id']; ?>" value="<?php echo htmlspecialchars($page['name']); ?>">
                                <input type="hidden" name="page_token_<?php echo $page['id']; ?>" value="<?php echo htmlspecialchars($page['access_token'] ?? ''); ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" name="savepages" class="btn btn-facebook">💾 บันทึก Facebook Pages</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title">📷 Instagram Business Accounts</h2>

            <?php if (empty($igAccounts)): ?>
                <p style="color: rgba(255,255,255,0.5);">ไม่พบ Instagram Business Account ที่เชื่อมกับ Facebook Pages</p>
                <p style="color: rgba(255,255,255,0.4); font-size: 13px; margin-top: 10px;">
                    💡 Instagram Account ต้องเป็น Business หรือ Creator Account และเชื่อมกับ Facebook Page
                </p>
            <?php else: ?>
                <form method="POST">
                    <div class="page-list">
                        <?php foreach ($igAccounts as $ig): ?>
                        <div class="page-item">
                            <input type="checkbox"
                                   name="instagram[]"
                                   value="<?php echo $ig['id']; ?>"
                                   <?php echo in_array($ig['id'], $selectedIG) ? 'checked' : ''; ?>>
                            <div class="page-info">
                                <div class="page-name">@<?php echo htmlspecialchars($ig['username']); ?></div>
                                <div class="page-meta">
                                    <?php echo htmlspecialchars($ig['name'] ?? ''); ?> |
                                    Followers: <?php echo number_format($ig['followers_count'] ?? 0); ?> |
                                    Posts: <?php echo number_format($ig['media_count'] ?? 0); ?>
                                </div>
                                <div class="page-meta" style="margin-top: 5px;">
                                    📘 Page: <?php echo htmlspecialchars($ig['page_name']); ?>
                                </div>
                                <input type="hidden" name="ig_name_<?php echo $ig['id']; ?>" value="<?php echo htmlspecialchars($ig['name'] ?? $ig['username']); ?>">
                                <input type="hidden" name="ig_username_<?php echo $ig['id']; ?>" value="<?php echo htmlspecialchars($ig['username']); ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" name="saveinstagram" class="btn btn-instagram">💾 บันทึก Instagram Accounts</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title">🔄 Sync Data จาก API</h2>

            <form method="POST">
                <div class="sync-section">
                    <div class="form-group">
                        <label>Platform</label>
                        <select name="syncplatform">
                            <option value="all">ทั้งหมด (Facebook + Instagram)</option>
                            <option value="facebook">Facebook เท่านั้น</option>
                            <option value="instagram">Instagram เท่านั้น</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>วันที่เริ่มต้น</label>
                        <input type="date" name="syncsince" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>

                    <div class="form-group">
                        <label>วันที่สิ้นสุด</label>
                        <input type="date" name="syncuntil" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <button type="submit" name="syncdata" class="btn btn-success">🔄 เริ่ม Sync ข้อมูล</button>

                <p style="margin-top: 15px; font-size: 13px; color: rgba(255,255,255,0.5);">
                    💡 ระบบจะดึงข้อมูลโพสต์และ Insights จาก API มาเก็บในฐานข้อมูล
                </p>
            </form>
        </div>

        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">🎵 TikTok API</h2>
            <div class="info-box" style="background: rgba(254, 44, 85, 0.1); border-color: rgba(254, 44, 85, 0.2);">
                <h4 style="color: #FE2C55;">⚠️ TikTok API ยังไม่พร้อมใช้งาน</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 14px;">
                    TikTok API ต้องผ่านการ Developer Review จาก TikTok และมีข้อจำกัดในการใช้งาน
                    ปัจจุบันแนะนำให้ใช้วิธี Export TikTok Analytics ออกมาเป็น Excel แล้วอัพโหลดผ่านหน้า Upload
                    <br><br>
                    <strong>สำหรับนักพัฒนา:</strong>
                </p>
                <ul>
                    <li>สมัครที่ TikTok for Developers: <a href="https://developers.tiktok.com" target="_blank" style="color: #FE2C55;">developers.tiktok.com</a></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>