<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แปลง Token เป็น Long-lived</title>
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
        
        .alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-left-color: #0c5460;
        }
        
        .alert-success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
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
        
        .copy-btn {
            background: #28a745;
            font-size: 13px;
            padding: 8px 16px;
        }
        
        .token-box {
            background: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            word-break: break-all;
            margin: 10px 0;
        }
        
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #1877f2;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 แปลง Token เป็น Long-lived (ไม่หมดอายุ)</h1>
        <p style="color: #666;">ทำให้ Token ไม่หมดอายุ และเป็น Page Token อัตโนมัติ</p>
        
        <div class="alert alert-info">
            <strong>ℹ️ ความแตกต่าง:</strong>
            <table>
                <tr>
                    <th>ประเภท Token</th>
                    <th>อายุ</th>
                    <th>ใช้กับ</th>
                </tr>
                <tr>
                    <td>Short-lived User Token</td>
                    <td>1-2 ชั่วโมง</td>
                    <td>ทดสอบเท่านั้น</td>
                </tr>
                <tr>
                    <td>Long-lived User Token</td>
                    <td>60 วัน</td>
                    <td>ใช้แปลงเป็น Page Token</td>
                </tr>
                <tr>
                    <td><strong>Page Token</strong></td>
                    <td><strong>ไม่หมดอายุ</strong></td>
                    <td><strong>ใช้กับระบบจริง ✅</strong></td>
                </tr>
            </table>
        </div>
        
        <?php
        $appId = '862629473011673';
        $appSecret = 'ffdd223393467333a53f384aeae0609f';
        ?>
        
        <div class="step">
            <h2>📝 ขั้นตอนที่ 1: ขอ Short-lived User Token</h2>
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
                <li>คัดลอก Token (เป็น Short-lived Token)</li>
            </ol>
        </div>
        
        <div class="step">
            <h2>🔄 ขั้นตอนที่ 2: แปลงเป็น Long-lived Token</h2>
            
            <form method="POST">
                <label><strong>วาง Short-lived User Token:</strong></label>
                <input type="text" 
                       name="short_token" 
                       placeholder="EAAMQ..." 
                       required
                       value="<?php echo isset($_POST['short_token']) ? htmlspecialchars($_POST['short_token']) : ''; ?>">
                
                <button type="submit" name="step" value="extend">🔄 แปลงเป็น Long-lived</button>
            </form>
        </div>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
            
            if ($_POST['step'] === 'extend' && !empty($_POST['short_token'])) {
                $shortToken = trim($_POST['short_token']);
                
                echo '<div class="step">';
                echo '<h2>⏳ กำลังแปลง Token...</h2>';
                
                // แปลง Short-lived → Long-lived User Token
                $url = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'fb_exchange_token' => $shortToken
                ]);
                
                $response = @file_get_contents($url);
                
                if ($response === false) {
                    echo '<div class="alert alert-danger">';
                    echo '<strong>❌ ไม่สามารถเชื่อมต่อ Facebook API ได้</strong>';
                    echo '</div>';
                } else {
                    $data = json_decode($response, true);
                    
                    if (isset($data['error'])) {
                        echo '<div class="alert alert-danger">';
                        echo '<strong>❌ Error:</strong> ' . htmlspecialchars($data['error']['message']);
                        echo '<br><br><strong>วิธีแก้:</strong>';
                        echo '<ul>';
                        echo '<li>ตรวจสอบว่า Token ถูกต้อง</li>';
                        echo '<li>ขอ Token ใหม่จาก Graph API Explorer</li>';
                        echo '<li>Token อาจหมดอายุแล้ว (ต้องขอใหม่ทุกครั้ง)</li>';
                        echo '</ul>';
                        echo '</div>';
                    } else if (isset($data['access_token'])) {
                        $longLivedToken = $data['access_token'];
                        $expiresIn = isset($data['expires_in']) ? $data['expires_in'] : 0;
                        
                        echo '<div class="alert alert-success">';
                        echo '<strong>✅ แปลงสำเร็จ!</strong><br>';
                        echo 'Token จะหมดอายุใน: <strong>' . round($expiresIn / 86400) . ' วัน</strong>';
                        echo '</div>';
                        
                        echo '<p><strong>Long-lived User Token:</strong></p>';
                        echo '<div class="token-box" id="longtoken">' . htmlspecialchars($longLivedToken) . '</div>';
                        echo '<button class="copy-btn" onclick="copyToken(\'longtoken\')">📋 คัดลอก</button>';
                        
                        // ขั้นตอนต่อไป: ดึง Page Tokens
                        echo '<div class="step">';
                        echo '<h2>🎯 ขั้นตอนที่ 3: ดึง Page Token (ไม่หมดอายุ)</h2>';
                        
                        echo '<form method="POST">';
                        echo '<input type="hidden" name="long_token" value="' . htmlspecialchars($longLivedToken) . '">';
                        echo '<button type="submit" name="step" value="getpages">🔍 ดึง Page Tokens</button>';
                        echo '</form>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
            }
            
            if ($_POST['step'] === 'getpages' && !empty($_POST['long_token'])) {
                $longToken = trim($_POST['long_token']);
                
                echo '<div class="step">';
                echo '<h2>📄 Page Access Tokens</h2>';
                
                // ดึงรายการเพจ
                $url = "https://graph.facebook.com/v21.0/me/accounts?access_token=" . urlencode($longToken);
                $response = @file_get_contents($url);
                
                if ($response === false) {
                    echo '<div class="alert alert-danger">';
                    echo '<strong>❌ ไม่สามารถดึงข้อมูลเพจได้</strong>';
                    echo '</div>';
                } else {
                    $data = json_decode($response, true);
                    
                    if (isset($data['error'])) {
                        echo '<div class="alert alert-danger">';
                        echo '<strong>❌ Error:</strong> ' . htmlspecialchars($data['error']['message']);
                        echo '</div>';
                    } else if (isset($data['data']) && count($data['data']) > 0) {
                        echo '<div class="alert alert-success">';
                        echo '<strong>✅ พบ ' . count($data['data']) . ' เพจ</strong>';
                        echo '</div>';
                        
                        foreach ($data['data'] as $page) {
                            echo '<div style="background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid #dee2e6;">';
                            echo '<h3>📄 ' . htmlspecialchars($page['name']) . '</h3>';
                            echo '<p><strong>Page ID:</strong> <code>' . $page['id'] . '</code></p>';
                            
                            if (isset($page['category'])) {
                                echo '<p><strong>Category:</strong> ' . htmlspecialchars($page['category']) . '</p>';
                            }
                            
                            echo '<div class="alert alert-success">';
                            echo '<strong>🎉 Page Access Token นี้:</strong>';
                            echo '<ul>';
                            echo '<li>✅ <strong>ไม่หมดอายุ</strong> (Never expires)</li>';
                            echo '<li>✅ ใช้ได้ตลอดไป</li>';
                            echo '<li>✅ พร้อมใช้งานในระบบจริง</li>';
                            echo '</ul>';
                            echo '</div>';
                            
                            $tokenId = 'pagetoken_' . $page['id'];
                            echo '<p><strong>🔑 Page Access Token:</strong></p>';
                            echo '<div class="token-box" id="' . $tokenId . '">' . htmlspecialchars($page['access_token']) . '</div>';
                            echo '<button class="copy-btn" onclick="copyToken(\'' . $tokenId . '\')">📋 คัดลอก Token นี้</button>';
                            
                            if ($page['id'] === '489861450880996') {
                                echo '<div class="alert" style="margin-top: 15px;">';
                                echo '<strong>⭐ นี่คือเพจ Ulg.official ของคุณ!</strong><br>';
                                echo 'คัดลอก Token นี้ไปใช้งานได้เลย';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                        
                        echo '<div class="alert alert-info">';
                        echo '<h3>📌 ขั้นตอนถัดไป:</h3>';
                        echo '<ol>';
                        echo '<li>คัดลอก <strong>Page Access Token</strong> ของเพจ <strong>Ulg.official</strong></li>';
                        echo '<li>ส่งให้ผมใส่ในไฟล์</li>';
                        echo '<li>Token นี้จะ<strong>ไม่หมดอายุ</strong> ใช้ได้ตลอด!</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                    } else {
                        echo '<div class="alert alert-danger">';
                        echo '<strong>⚠️ ไม่พบเพจ</strong>';
                        echo '<p>คุณยังไม่ได้เป็น Admin ของเพจใดๆ</p>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
            }
        }
        ?>
        
        <div class="alert">
            <h3>💡 คำแนะนำ:</h3>
            <ul>
                <li><strong>Short-lived Token:</strong> อายุ 1-2 ชั่วโมง (จาก Graph API Explorer)</li>
                <li><strong>Long-lived User Token:</strong> อายุ 60 วัน</li>
                <li><strong>Page Access Token:</strong> <strong>ไม่หมดอายุ</strong> (ที่ต้องการ!)</li>
                <li>ต้องทำทุกขั้นตอนในครั้งเดียว (Short → Long → Page)</li>
                <li>Page Token จะไม่หมดอายุเลย ใช้ได้ตลอด</li>
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
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ คัดลอก Token แล้ว!');
            });
        }
    </script>
</body>
</html>
