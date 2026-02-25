<?php
require_once 'config.php';

// =============================================
// ตรวจสอบ login - ปรับ session ให้ตรงกับระบบ login ของคุณ
// ถ้าระบบเดิมใช้ชื่อ session อื่น เช่น $_SESSION['login_user'] ให้แก้ตรงนี้
// =============================================
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    // ลองอ่านจาก session ที่เป็นไปได้
    // ถ้าระบบเดิม set session ชื่ออื่น ให้ map ตรงนี้
    // เช่น: $_SESSION['user_id'] = $_SESSION['login_id'];
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';

// ดึง role จาก DB
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$role = $stmt->fetchColumn() ?: '';

// หา shop name จาก username
$shop_name = getShopName($username);

if (!$shop_name) {
    die('<div style="text-align:center;padding:50px;font-size:20px;color:red;">
        ❌ ไม่พบร้านค้าสำหรับ user: ' . htmlspecialchars($username) . '<br>
        กรุณาติดต่อ Admin เพื่อตั้งค่า shop_mapping ใน config.php
    </div>');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧧 กงล้อจับรางวัลตรุษจีน 2569</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700;900&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 30%, #FF4500 60%, #FFD700 100%);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Floating lanterns animation */
        .lantern {
            position: fixed;
            font-size: 40px;
            animation: floatLantern 6s ease-in-out infinite;
            opacity: 0.6;
            z-index: 0;
        }
        .lantern:nth-child(1) { left: 5%; animation-delay: 0s; top: 10%; }
        .lantern:nth-child(2) { left: 15%; animation-delay: 1s; top: 30%; }
        .lantern:nth-child(3) { right: 5%; animation-delay: 2s; top: 15%; }
        .lantern:nth-child(4) { right: 15%; animation-delay: 0.5s; top: 40%; }
        .lantern:nth-child(5) { left: 50%; animation-delay: 1.5s; top: 5%; }
        
        @keyframes floatLantern {
            0%, 100% { transform: translateY(0) rotate(-5deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            padding: 20px 0;
        }
        .header h1 {
            font-size: 28px;
            color: #FFD700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            margin-bottom: 5px;
        }
        .header .shop-name {
            font-size: 18px;
            color: #FFF;
            background: rgba(0,0,0,0.3);
            padding: 5px 20px;
            border-radius: 20px;
            display: inline-block;
        }

        /* Wheel Container */
        .wheel-wrapper {
            position: relative;
            width: 340px;
            height: 340px;
            margin: 20px auto;
        }

        .wheel-pointer {
            position: absolute;
            top: -18px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            font-size: 40px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
        }

        .wheel-border {
            position: absolute;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: conic-gradient(#FFD700, #FFA500, #FFD700, #FFA500, #FFD700, #FFA500);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.6), inset 0 0 20px rgba(0,0,0,0.2);
        }

        canvas#wheelCanvas {
            width: 310px;
            height: 310px;
            border-radius: 50%;
        }

        .wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: radial-gradient(circle, #FFD700, #B8860B);
            border: 3px solid #FFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            z-index: 5;
        }

        /* Bill form */
        .bill-form {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .bill-form label {
            font-weight: 700;
            color: #8B0000;
            font-size: 16px;
            display: block;
            margin-bottom: 8px;
        }
        .bill-form input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #DC143C;
            border-radius: 10px;
            font-size: 18px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.3s;
        }
        .bill-form input:focus {
            border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255,215,0,0.3);
        }

        .spin-btn {
            display: block;
            width: 100%;
            padding: 16px;
            margin-top: 15px;
            background: linear-gradient(135deg, #DC143C, #8B0000);
            color: #FFD700;
            border: 3px solid #FFD700;
            border-radius: 12px;
            font-size: 22px;
            font-weight: 900;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        .spin-btn:hover {
            background: linear-gradient(135deg, #FF1744, #B71C1C);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220,20,60,0.5);
        }
        .spin-btn:disabled {
            background: #999;
            border-color: #666;
            color: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Result modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }

        .modal {
            background: linear-gradient(135deg, #FFF8DC, #FFFACD);
            border: 4px solid #FFD700;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            animation: modalPop 0.5s ease;
            box-shadow: 0 0 50px rgba(255,215,0,0.5);
        }
        @keyframes modalPop {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .modal .congrats {
            font-size: 60px;
            margin-bottom: 10px;
        }
        .modal h2 {
            color: #8B0000;
            font-size: 22px;
            margin-bottom: 15px;
        }
        .modal .prize-result {
            font-size: 36px;
            font-weight: 900;
            color: #DC143C;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 15px 0;
        }
        .modal .bill-info {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        .modal .close-btn {
            margin-top: 20px;
            padding: 12px 40px;
            background: #DC143C;
            color: #FFF;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }

        /* Confetti */
        .confetti-piece {
            position: fixed;
            width: 10px;
            height: 10px;
            top: -10px;
            z-index: 200;
            animation: confettiFall 3s linear forwards;
        }
        @keyframes confettiFall {
            0% { top: -10px; transform: rotate(0deg); opacity: 1; }
            100% { top: 110vh; transform: rotate(720deg); opacity: 0; }
        }

        .admin-link {
            text-align: center;
            margin-top: 15px;
        }
        .admin-link a {
            color: #FFE4B5;
            text-decoration: none;
            font-size: 14px;
        }
        .admin-link a:hover { text-decoration: underline; }

        .logout-link {
            text-align: center;
            margin-top: 10px;
        }
        .logout-link a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <!-- Floating lanterns -->
    <div class="lantern">🏮</div>
    <div class="lantern">🧧</div>
    <div class="lantern">🏮</div>
    <div class="lantern">🧧</div>
    <div class="lantern">🏮</div>

    <div class="container">
        <div class="header">
            <h1>🧧 กงล้อจับรางวัลตรุษจีน 🧧</h1>
            <div class="shop-name">🏪 <?= htmlspecialchars($shop_name) ?></div>
        </div>

        <!-- Wheel -->
        <div class="wheel-wrapper">
            <div class="wheel-pointer">📍</div>
            <div class="wheel-border">
                <canvas id="wheelCanvas" width="620" height="620"></canvas>
            </div>
            <div class="wheel-center">🐍</div>
        </div>

        <!-- Bill Number Form -->
        <div class="bill-form">
            <label>📋 กรุณากรอกเลขที่บิลก่อนหมุน</label>
            <input type="text" id="billNumber" placeholder="เลขที่บิล เช่น INV-2569001" autocomplete="off">
            <button class="spin-btn" id="spinBtn" onclick="startSpin()">
                🎰 หมุนวงล้อ! 🎰
            </button>
        </div>

        <?php if (isAdmin($role)): ?>
        <div class="admin-link">
            <a href="admin.php">🔧 หน้าสรุปรางวัล (Admin)</a>
        </div>
        <?php endif; ?>

        <div class="admin-link">
            <a href="redeem.php">🎫 ตรวจสอบ / ใช้สิทธิ์</a>
        </div>
        
        <div class="logout-link">
            <a href="logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="modal-overlay" id="resultModal">
        <div class="modal">
            <div class="congrats">🎊</div>
            <h2>ยินดีด้วย! คุณได้รับรางวัล</h2>
            <div class="prize-result" id="prizeResult"></div>
            <div class="bill-info" id="billInfo"></div>
            <button class="close-btn" onclick="closeModal()">ปิด</button>
        </div>
    </div>

    <script>
    // Wheel segments
    const segments = [
        { label: 'ส่วนลด 50%', color: '#DC143C' },
        { label: 'เสื้อ', color: '#FF8C00' },
        { label: 'ส่วนลด 20%', color: '#228B22' },
        { label: 'หมวก', color: '#4169E1' },
        { label: 'ส่วนลด 30%', color: '#8B008B' },
        { label: 'ส่วนลด 15%', color: '#DAA520' },
    ];

    // Map prize name to segment index
    const prizeToSegment = {
        '50%': 0,
        'เสื้อ': 1,
        '20%': 2,
        'หมวก': 3,
        '30%': 4,
        '15%': 5,
    };

    const canvas = document.getElementById('wheelCanvas');
    const ctx = canvas.getContext('2d');
    const numSeg = segments.length;
    const arc = (2 * Math.PI) / numSeg;
    let currentAngle = 0;
    let spinning = false;

    function drawWheel(angle) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const cx = canvas.width / 2;
        const cy = canvas.height / 2;
        const radius = cx - 10;

        for (let i = 0; i < numSeg; i++) {
            const startAngle = angle + i * arc;
            const endAngle = startAngle + arc;

            // Draw segment
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = segments[i].color;
            ctx.fill();
            ctx.strokeStyle = '#FFD700';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Draw text
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(startAngle + arc / 2);
            ctx.textAlign = 'right';
            ctx.fillStyle = '#FFF';
            ctx.font = 'bold 26px "Noto Sans Thai", sans-serif';
            ctx.shadowColor = 'rgba(0,0,0,0.5)';
            ctx.shadowBlur = 3;
            ctx.fillText(segments[i].label, radius - 20, 8);
            ctx.restore();
        }

        // Inner circle
        ctx.beginPath();
        ctx.arc(cx, cy, 40, 0, 2 * Math.PI);
        ctx.fillStyle = 'rgba(255,215,0,0.1)';
        ctx.fill();
    }

    drawWheel(currentAngle);

    function startSpin() {
        const billNumber = document.getElementById('billNumber').value.trim();
        if (!billNumber) {
            alert('กรุณากรอกเลขที่บิลก่อนหมุน!');
            return;
        }
        if (spinning) return;
        spinning = true;

        document.getElementById('spinBtn').disabled = true;

        // Call API to get prize
        fetch('spin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bill_number: billNumber })
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                spinning = false;
                document.getElementById('spinBtn').disabled = false;
                return;
            }

            // Determine target segment
            const segIndex = prizeToSegment[data.prize] ?? 5; // default to 15%
            
            // Calculate target angle: the pointer is at the top (3π/2 or -π/2)
            // We need the target segment's center to end up at the top
            const targetSegCenter = segIndex * arc + arc / 2;
            // The wheel needs to rotate so that targetSegCenter aligns with top (3π/2)
            const targetAngle = (3 * Math.PI / 2) - targetSegCenter;
            
            // Add multiple full rotations + target
            const totalRotation = (Math.floor(Math.random() * 3) + 5) * 2 * Math.PI + targetAngle - currentAngle;
            
            animateSpin(totalRotation, data);
        })
        .catch(err => {
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
            spinning = false;
            document.getElementById('spinBtn').disabled = false;
        });
    }

    function animateSpin(totalRotation, data) {
        const startAngle = currentAngle;
        const duration = 5000; // 5 seconds
        const startTime = performance.now();

        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }

        function animate(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = easeOutCubic(progress);

            currentAngle = startAngle + totalRotation * eased;
            drawWheel(currentAngle);

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                // Done spinning
                spinning = false;
                document.getElementById('spinBtn').disabled = false;
                showResult(data);
            }
        }

        requestAnimationFrame(animate);
    }

    function showResult(data) {
        let displayPrize = data.prize;
        if (['50%', '30%', '20%', '15%'].includes(data.prize)) {
            displayPrize = 'ส่วนลด ' + data.prize;
        }
        
        document.getElementById('prizeResult').textContent = displayPrize;
        document.getElementById('billInfo').textContent = 'บิล: ' + data.bill_number;
        document.getElementById('resultModal').classList.add('show');
        
        // Confetti!
        createConfetti();
        
        // Clear bill input
        document.getElementById('billNumber').value = '';
    }

    function closeModal() {
        document.getElementById('resultModal').classList.remove('show');
        // Reload page
        location.reload();
    }

    function createConfetti() {
        const colors = ['#FFD700', '#DC143C', '#FF4500', '#FF69B4', '#00FF00', '#1E90FF'];
        for (let i = 0; i < 60; i++) {
            const piece = document.createElement('div');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDelay = Math.random() * 2 + 's';
            piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
            piece.style.width = (Math.random() * 8 + 5) + 'px';
            piece.style.height = (Math.random() * 8 + 5) + 'px';
            document.body.appendChild(piece);
            setTimeout(() => piece.remove(), 5000);
        }
    }
    </script>
</body>
</html>
