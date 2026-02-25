<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ดึง Page Access Token</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #1877f2;
            margin-bottom: 10px;
        }
        
        .step {
            background: #e7f3ff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #1877f2;
        }
        
        .step h2 {
            color: #1877f2;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        input[type="text"], textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        button {
            background: #1877f2;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            margin: 10px 5px 10px 0;
        }
        
        button:hover {
            background: #166fe5;
        }
        
        .result {
            background: #d4edda;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background: #f8d7da;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        
        .page-card {
            background: #f8f9fa;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .page-card h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .token-box {
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            word-break: break-all;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .copy-btn {
            background: #28a745;
            font-size: 13px;
            padding: 8px 16px;
        }
        
        .copy-btn:hover {
            background: #218838;
        }
        
        .instruction {
            background: #fff3cd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        
        a {
            color: #1877f2;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 ดึง Page Access Token</h1>
        <p style="color: #666;">เครื่องมือช่วยแปลง User Token เป็น Page Token</p>
        
        <div class="step">
            <h2>📝 ขั้นตอนที่ 1: ขอ User Access Token</h2>
            <ol>
                <li>เปิด <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
                <li>เลือกแอป <strong>ULGreport</strong></li>
                <li>คลิก <strong>"Generate Access Token"</strong></li>
                <li>เลือก Permissions:
                    <ul>
                        <li><code>pages_show_list</code></li>
                        <li><code>pages_read_engagement</code></li>
                        <li><code>pages_read_user_content</code></li>
                        <li><code>business_management</code></li>
                    </ul>
                </li>
                <li>คัดลอก Token ที่ได้</li>
            </ol>
        </div>
        
        <div class="step">
            <h2>🔄 ขั้นตอนที่ 2: แปลงเป็น Page Token</h2>
            <p>วาง User Access Token ที่ได้จากขั้นตอนที่ 1:</p>
            
            <form method="POST">
                <input type="text" 
                       name="user_token" 
                       placeholder="EAAMQjpNPx9k..." 
                       required
                       value="<?php echo isset($_POST['user_token']) ? htmlspecialchars($_POST['user_token']) : ''; ?>">
                
                <button type="submit">🔍 ดึง Page Tokens</button>
            </form>
        </div>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_token'])) {
            $userToken = trim($_POST['user_token']);
            
            echo '<div class="step">';
            echo '<h2>✅ ผลลัพธ์</h2>';
            
            // ดึงรายการเพจ
            $url = "https://graph.facebook.com/v21.0/me/accounts?access_token=" . urlencode($userToken);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                echo '<div class="error">';
                echo '<strong>❌ ไม่สามารถเชื่อมต่อ Facebook API ได้</strong>';
                echo '<p>ตรวจสอบ:</p>';
                echo '<ul>';
                echo '<li>Internet connection</li>';
                echo '<li>Token ถูกต้องหรือไม่</li>';
                echo '<li>allow_url_fopen เปิดอยู่หรือไม่</li>';
                echo '</ul>';
                echo '</div>';
            } else {
                $data = json_decode($response, true);
                
                if (isset($data['error'])) {
                    echo '<div class="error">';
                    echo '<strong>❌ Facebook API Error:</strong><br>';
                    echo htmlspecialchars($data['error']['message']);
                    
                    if (strpos($data['error']['message'], 'token') !== false || 
                        strpos($data['error']['message'], 'expired') !== false) {
                        echo '<br><br><strong>วิธีแก้:</strong> Token หมดอายุหรือไม่ถูกต้อง กรุณา Generate ใหม่';
                    }
                    
                    if (strpos($data['error']['message'], 'permission') !== false) {
                        echo '<br><br><strong>วิธีแก้:</strong> ขาด Permissions กรุณาขอ permissions ที่จำเป็น';
                    }
                    
                    echo '</div>';
                    
                } else if (isset($data['data']) && count($data['data']) > 0) {
                    echo '<div class="result">';
                    echo '<p><strong>✅ พบ ' . count($data['data']) . ' เพจ</strong></p>';
                    echo '</div>';
                    
                    foreach ($data['data'] as $page) {
                        echo '<div class="page-card">';
                        echo '<h3>📄 ' . htmlspecialchars($page['name']) . '</h3>';
                        echo '<p><strong>Page ID:</strong> <code>' . $page['id'] . '</code></p>';
                        
                        if (isset($page['category'])) {
                            echo '<p><strong>Category:</strong> ' . htmlspecialchars($page['category']) . '</p>';
                        }
                        
                        if (isset($page['tasks'])) {
                            echo '<p><strong>Permissions:</strong> ' . implode(', ', $page['tasks']) . '</p>';
                        }
                        
                        echo '<p><strong>🔑 Page Access Token:</strong></p>';
                        
                        $tokenId = 'token_' . $page['id'];
                        echo '<div class="token-box" id="' . $tokenId . '">' . htmlspecialchars($page['access_token']) . '</div>';
                        
                        echo '<button class="copy-btn" onclick="copyToken(\'' . $tokenId . '\')">📋 คัดลอก Token</button>';
                        
                        // ทดสอบ Token
                        echo '<button onclick="testToken(\'' . htmlspecialchars($page['access_token']) . '\', \'' . $page['id'] . '\')">🧪 ทดสอบ Token</button>';
                        
                        echo '</div>';
                    }
                    
                    // คำแนะนำ
                    echo '<div class="instruction">';
                    echo '<h3>📌 ขั้นตอนถัดไป:</h3>';
                    echo '<ol>';
                    echo '<li>คัดลอก <strong>Page Access Token</strong> ของเพจ <strong>Ulg.official</strong></li>';
                    echo '<li>นำไปใส่ในไฟล์:';
                    echo '<ul>';
                    echo '<li><code>api_improved.php</code> (บรรทัด 24)</li>';
                    echo '<li><code>facebook_insights_clean.php</code></li>';
                    echo '<li><code>simple_report.html</code> (บรรทัด ~167)</li>';
                    echo '</ul>';
                    echo '</li>';
                    echo '<li>Upload ไฟล์ทับเดิม</li>';
                    echo '<li>ทดสอบใหม่</li>';
                    echo '</ol>';
                    echo '</div>';
                    
                } else {
                    echo '<div class="error">';
                    echo '<strong>⚠️ ไม่พบเพจ</strong>';
                    echo '<p>เป็นไปได้ว่า:</p>';
                    echo '<ul>';
                    echo '<li>คุณยังไม่ได้เป็น Admin ของเพจใดๆ</li>';
                    echo '<li>Token ไม่มี permission <code>pages_show_list</code></li>';
                    echo '<li>ต้อง Login ด้วย Facebook Account ที่เป็น Admin ของเพจ</li>';
                    echo '</ul>';
                    echo '</div>';
                }
            }
            
            echo '</div>';
        }
        ?>
        
        <div class="instruction">
            <h3>💡 Tips:</h3>
            <ul>
                <li><strong>User Token</strong> = Token ของคุณ (Sithiphong) - ใช้ดึงข้อมูล personal</li>
                <li><strong>Page Token</strong> = Token ของเพจ (Ulg.official) - ใช้ดึง Insights</li>
                <li>Page Token <strong>ไม่หมดอายุ</strong> (ถ้าเป็น Long-lived)</li>
                <li>ต้องใช้ <strong>Page Token</strong> เท่านั้นถึงจะดึง Insights ได้</li>
            </ul>
        </div>
    </div>
    
    <script>
        function copyToken(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('✅ คัดลอก Token แล้ว!');
            }).catch(err => {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ คัดลอก Token แล้ว!');
            });
        }
        
        function testToken(token, pageId) {
            const testUrl = `https://graph.facebook.com/v21.0/${pageId}?fields=id,name,fan_count&access_token=${encodeURIComponent(token)}`;
            
            fetch(testUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('❌ Token ไม่ถูกต้อง:\n' + data.error.message);
                    } else {
                        alert('✅ Token ใช้งานได้!\n\n' +
                              'Page: ' + data.name + '\n' +
                              'Fans: ' + data.fan_count.toLocaleString());
                    }
                })
                .catch(error => {
                    alert('❌ ไม่สามารถทดสอบได้: ' + error.message);
                });
        }
    </script>
</body>
</html>