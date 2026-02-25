<?php
// ตั้งค่า HTTP Status 503 Service Unavailable
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
header('Retry-After: 3600'); // บอกให้ลองใหม่ใน 1 ชั่วโมง

// ถ้าต้องการให้ Admin เข้าได้ (ใส่ IP ของคุณ)
$allowed_ips = array('YOUR_IP_ADDRESS'); // เปลี่ยนเป็น IP ของคุณ
if(in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    return; // ให้ Admin ผ่านไปได้
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อยู่ระหว่างปรับปรุง</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #fff;
        }
        
        .container {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            max-width: 600px;
            margin: 20px;
        }
        
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        p {
            font-size: 1.2em;
            line-height: 1.6;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .info {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 2em;
            }
            p {
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>เว็บไซต์อยู่ระหว่างปรับปรุง</h1>
        <p>ขออภัยในความไม่สะดวก<br>
        เรากำลังปรับปรุงระบบเพื่อให้บริการที่ดีขึ้น</p>
        
        <div class="info">
            <p style="margin: 0;">กรุณาลองเข้าใช้งานใหม่ภายหลัง<br>
            </p>
        </div>
    </div>
</body>
</html>
