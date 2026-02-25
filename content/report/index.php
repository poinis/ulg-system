<?php
/**
 * Social Media Analytics - หน้าหลัก
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Analytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            text-align: center;
            max-width: 800px;
            width: 100%;
        }
        
        .logo {
            font-size: 4rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        h1 {
            font-size: 2.5rem;
            color: #fff;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .subtitle {
            color: rgba(255,255,255,0.6);
            font-size: 1.1rem;
            margin-bottom: 50px;
        }
        
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 30px;
            border: 1px solid rgba(255,255,255,0.1);
            text-decoration: none;
            color: #fff;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent, rgba(255,255,255,0.05));
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .card:hover {
            transform: translateY(-10px);
            border-color: rgba(102, 126, 234, 0.5);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3), 0 0 60px rgba(102, 126, 234, 0.2);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 25px;
            position: relative;
        }
        
        .card.weekly .card-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .card.monthly .card-icon {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .card p {
            color: rgba(255,255,255,0.6);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .card .arrow {
            margin-top: 25px;
            font-size: 1.2rem;
            color: rgba(255,255,255,0.3);
            transition: all 0.3s;
        }
        
        .card:hover .arrow {
            color: #667eea;
            transform: translateX(5px);
        }
        
        .features {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .feature-tag {
            padding: 5px 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.7);
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 30px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50px;
            color: #fff;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            margin-top: 40px;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .back-button i {
            font-size: 1.1rem;
            transition: transform 0.3s;
        }
        
        .back-button:hover i {
            transform: translateX(-3px);
        }
        
        .footer {
            margin-top: 30px;
            color: rgba(255,255,255,0.4);
            font-size: 0.85rem;
        }
        
        @media (max-width: 600px) {
            .logo { font-size: 3rem; }
            h1 { font-size: 1.8rem; }
            .cards { gap: 20px; }
            .card { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-chart-line"></i>
        </div>
        <h1>Social Media Analytics</h1>
        <p class="subtitle">ระบบวิเคราะห์ข้อมูล Social Media</p>
        
        <div class="cards">
            <a href="week/index.php" class="card weekly">
                <div class="card-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <h2>Weekly Report</h2>
                <p>รายงานประจำสัปดาห์<br>ดูภาพรวมและประสิทธิภาพรายวัน</p>
                <div class="features">
                    <span class="feature-tag">รายวัน</span>
                    <span class="feature-tag">7 วัน</span>
                    <span class="feature-tag">เปรียบเทียบ</span>
                </div>
                <div class="arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
            
            <a href="month/index.php" class="card monthly">
                <div class="card-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h2>Monthly Report</h2>
                <p>รายงานประจำเดือน<br>เปรียบเทียบข้อมูลระหว่างเดือน</p>
                <div class="features">
                    <span class="feature-tag">รายเดือน</span>
                    <span class="feature-tag">ยิ่งแอด</span>
                    <span class="feature-tag">วิเคราะห์</span>
                </div>
                <div class="arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
        </div>
        
        <a href="https://www.weedjai.com/content/" class="back-button">
            <i class="fas fa-home"></i>
            <span>กลับหน้าหลัก</span>
        </a>
        
        <div class="footer">
            <i class="fab fa-facebook"></i> Facebook &nbsp;|&nbsp;
            <i class="fab fa-instagram"></i> Instagram &nbsp;|&nbsp;
            <i class="fab fa-tiktok"></i> TikTok
        </div>
    </div>
</body>
</html>