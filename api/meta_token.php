<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meta Token Exchange</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 600px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"],
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
            font-family: monospace;
        }
        
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 10px;
            word-break: break-all;
        }
        
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .result h3 {
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .token-box {
            background: rgba(0, 0, 0, 0.1);
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
            margin-top: 10px;
            position: relative;
        }
        
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            font-size: 12px;
            background: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: auto;
        }
        
        .copy-btn:hover {
            background: #555;
            transform: none;
            box-shadow: none;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b6d4fe;
            color: #084298;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 13px;
        }
        
        .info-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        .expires-info {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 5px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Meta Token Exchange</h1>
        <p class="subtitle">แลกเปลี่ยน Short-lived Token เป็น Long-lived Token</p>
        
        <div class="info-box">
            <strong>📌 วิธีหา App ID และ App Secret:</strong>
            <ul>
                <li>ไปที่ <a href="https://developers.facebook.com" target="_blank">developers.facebook.com</a></li>
                <li>เลือก App ของคุณ → Settings → Basic</li>
                <li>คัดลอก App ID และ App Secret</li>
            </ul>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="app_id">📱 App ID</label>
                <input type="text" id="app_id" name="app_id" placeholder="เช่น 123456789012345" 
                       value="<?php echo isset($_POST['app_id']) ? htmlspecialchars($_POST['app_id']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="app_secret">🔑 App Secret</label>
                <input type="text" id="app_secret" name="app_secret" placeholder="เช่น abc123def456..." 
                       value="<?php echo isset($_POST['app_secret']) ? htmlspecialchars($_POST['app_secret']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="short_token">🎫 Short-lived Token</label>
                <textarea id="short_token" name="short_token" placeholder="วาง Short-lived Token ที่นี่..." required><?php echo isset($_POST['short_token']) ? htmlspecialchars($_POST['short_token']) : ''; ?></textarea>
            </div>
            
            <button type="submit">🚀 แลกเปลี่ยน Token</button>
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $app_id = trim($_POST['app_id'] ?? '');
            $app_secret = trim($_POST['app_secret'] ?? '');
            $short_token = trim($_POST['short_token'] ?? '');
            
            if (!empty($app_id) && !empty($app_secret) && !empty($short_token)) {
                // สร้าง URL สำหรับแลกเปลี่ยน token
                $url = "https://graph.facebook.com/v19.0/oauth/access_token?" . http_build_query([
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $app_id,
                    'client_secret' => $app_secret,
                    'fb_exchange_token' => $short_token
                ]);
                
                // เรียก API
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    echo '<div class="result error">';
                    echo '<h3>❌ เกิดข้อผิดพลาด</h3>';
                    echo '<p>ไม่สามารถเชื่อมต่อกับ Meta API ได้: ' . htmlspecialchars($error) . '</p>';
                    echo '</div>';
                } else {
                    $data = json_decode($response, true);
                    
                    if (isset($data['access_token'])) {
                        $long_token = $data['access_token'];
                        $expires_in = $data['expires_in'] ?? null;
                        $token_type = $data['token_type'] ?? 'bearer';
                        
                        echo '<div class="result success">';
                        echo '<h3>✅ สำเร็จ! ได้รับ Long-lived Token แล้ว</h3>';
                        echo '<div class="token-box">';
                        echo '<button type="button" class="copy-btn" onclick="copyToken()">📋 คัดลอก</button>';
                        echo '<span id="long-token">' . htmlspecialchars($long_token) . '</span>';
                        echo '</div>';
                        
                        if ($expires_in) {
                            $expires_date = date('d/m/Y H:i:s', time() + $expires_in);
                            $expires_days = round($expires_in / 86400);
                            echo '<div class="expires-info">';
                            echo '<strong>⏰ หมดอายุใน:</strong> ' . $expires_days . ' วัน<br>';
                            echo '<strong>📅 วันที่หมดอายุ:</strong> ' . $expires_date;
                            echo '</div>';
                        }
                        
                        echo '</div>';
                        
                        // แสดงขั้นตอนถัดไป
                        echo '<div class="info-box" style="margin-top: 20px; background: #d1e7dd; border-color: #badbcc; color: #0f5132;">';
                        echo '<strong>🎯 ขั้นตอนถัดไป (สำหรับ Page Token ไม่หมดอายุ):</strong>';
                        echo '<p style="margin-top: 10px;">ใช้ Long-lived Token นี้เรียก API:</p>';
                        echo '<code style="display: block; background: rgba(0,0,0,0.1); padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 12px; word-break: break-all;">';
                        echo 'GET https://graph.facebook.com/v19.0/me/accounts?access_token=[LONG_TOKEN]';
                        echo '</code>';
                        echo '<p style="margin-top: 10px;">Page Access Token ที่ได้จะไม่มีวันหมดอายุ!</p>';
                        echo '</div>';
                        
                    } else {
                        $error_msg = $data['error']['message'] ?? 'ไม่ทราบสาเหตุ';
                        $error_type = $data['error']['type'] ?? '';
                        $error_code = $data['error']['code'] ?? '';
                        
                        echo '<div class="result error">';
                        echo '<h3>❌ เกิดข้อผิดพลาด</h3>';
                        echo '<p><strong>ข้อความ:</strong> ' . htmlspecialchars($error_msg) . '</p>';
                        if ($error_type) {
                            echo '<p><strong>ประเภท:</strong> ' . htmlspecialchars($error_type) . '</p>';
                        }
                        if ($error_code) {
                            echo '<p><strong>รหัสข้อผิดพลาด:</strong> ' . htmlspecialchars($error_code) . '</p>';
                        }
                        echo '</div>';
                    }
                }
            }
        }
        ?>
    </div>
    
    <script>
        function copyToken() {
            const token = document.getElementById('long-token').innerText;
            navigator.clipboard.writeText(token).then(() => {
                const btn = document.querySelector('.copy-btn');
                btn.innerText = '✓ คัดลอกแล้ว!';
                setTimeout(() => {
                    btn.innerText = '📋 คัดลอก';
                }, 2000);
            });
        }
    </script>
</body>
</html>