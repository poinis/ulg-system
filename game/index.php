<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เกมจับเวลา</title>
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
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 2em;
        }
        
        .instruction {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .time-display {
            font-size: 4em;
            font-weight: bold;
            color: #333;
            margin: 30px 0;
            font-family: 'Courier New', monospace;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .target {
            background: #ffeaa7;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.2em;
            color: #333;
        }
        
        .target strong {
            color: #d63031;
            font-size: 1.4em;
        }
        
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 1.2em;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 10px;
        }
        
        button:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            font-size: 1.2em;
            display: none;
        }
        
        .result.success {
            background: #00b894;
            color: white;
            display: block;
        }
        
        .result.close {
            background: #fdcb6e;
            color: #333;
            display: block;
        }
        
        .result.far {
            background: #ff7675;
            color: white;
            display: block;
        }
        
        .celebration {
            font-size: 3em;
            margin: 10px 0;
        }
        
        .status {
            color: #999;
            font-size: 0.9em;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 เกมจับเวลา</h1>
        <div class="instruction">
            กด <strong>Enter</strong> เพื่อเริ่มจับเวลา<br>
            กด <strong>Enter</strong> อีกครั้งเพื่อหยุดเวลา
        </div>
        
        <div class="target">
            เป้าหมาย: <strong>12.25</strong> วินาที
        </div>
        
        <div class="time-display" id="timeDisplay">0.00</div>
        
        <div class="status" id="status">กด Enter เพื่อเริ่ม</div>
        
        <div>
            <button id="startBtn" onclick="toggleTimer()">เริ่ม (Enter)</button>
            <button id="resetBtn" onclick="resetGame()">เริ่มใหม่</button>
        </div>
        
        <div class="result" id="result"></div>
    </div>

    <script>
        let startTime = null;
        let timerInterval = null;
        let isRunning = false;
        const TARGET_TIME = 12.25;
        
        function toggleTimer() {
            if (!isRunning) {
                startTimer();
            } else {
                stopTimer();
            }
        }
        
        function startTimer() {
            startTime = Date.now();
            isRunning = true;
            document.getElementById('status').textContent = 'กำลังจับเวลา... กด Enter เพื่อหยุด';
            document.getElementById('startBtn').textContent = 'หยุด (Enter)';
            document.getElementById('result').style.display = 'none';
            document.getElementById('result').className = 'result';
            
            timerInterval = setInterval(updateDisplay, 10);
        }
        
        function stopTimer() {
            if (!isRunning) return;
            
            clearInterval(timerInterval);
            isRunning = false;
            
            const elapsed = (Date.now() - startTime) / 1000;
            document.getElementById('timeDisplay').textContent = elapsed.toFixed(2);
            document.getElementById('status').textContent = 'หยุดแล้ว';
            document.getElementById('startBtn').textContent = 'เริ่ม (Enter)';
            
            checkResult(elapsed);
        }
        
        function updateDisplay() {
            const elapsed = (Date.now() - startTime) / 1000;
            document.getElementById('timeDisplay').textContent = elapsed.toFixed(2);
        }
        
        function checkResult(time) {
            const difference = Math.abs(time - TARGET_TIME);
            const resultDiv = document.getElementById('result');
            
            // แสดงผลลัพธ์เสมอ
            resultDiv.style.display = 'block';
            
            if (time >= 12.20 && time <= 12.30) {
                // ยอดเยี่ยม! ใกล้เคียงมาก (12.20-12.30)
                resultDiv.className = 'result success';
                resultDiv.innerHTML = `
                    <div class="celebration">🎉 ยินดีด้วย! 🎊</div>
                    <div>คุณทำได้เวลา ${time.toFixed(2)} วินาที!</div>
                    <div>ใกล้เคียงเป้าหมายมาก!</div>
                `;
            } else if (difference < 1.0) {
                // ใกล้เคียง
                resultDiv.className = 'result close';
                resultDiv.innerHTML = `
                    <div class="celebration">👏</div>
                    <div>เวลาของคุณ: ${time.toFixed(2)} วินาที</div>
                    <div>ต่างจากเป้าหมาย ${difference.toFixed(2)} วินาที</div>
                    <div>ใกล้แล้ว! ลองอีกครั้ง</div>
                `;
            } else {
                // ห่างไกล
                resultDiv.className = 'result far';
                resultDiv.innerHTML = `
                    <div>เวลาของคุณ: ${time.toFixed(2)} วินาที</div>
                    <div>ต่างจากเป้าหมาย ${difference.toFixed(2)} วินาที</div>
                    <div>ลองใหม่อีกครั้ง!</div>
                `;
            }
        }
        
        function resetGame() {
            clearInterval(timerInterval);
            startTime = null;
            isRunning = false;
            document.getElementById('timeDisplay').textContent = '0.00';
            document.getElementById('status').textContent = 'กด Enter เพื่อเริ่ม';
            document.getElementById('startBtn').textContent = 'เริ่ม (Enter)';
            document.getElementById('result').style.display = 'none';
            document.getElementById('result').className = 'result';
        }
        
        // รับคำสั่งจากคีย์บอร์ด
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                toggleTimer();
            }
        });
    </script>
</body>
</html>