<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเดตสต็อกสินค้าจาก CSV</title>
    <style>
        body {
            font-family: 'Sarabun', Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .upload-form {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="file"] {
            padding: 10px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            width: 100%;
            cursor: pointer;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .progress-container {
            display: none;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background-color: #f0f0f0;
            border-radius: 15px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-box h3 {
            margin: 0 0 5px 0;
            font-size: 28px;
        }
        .stat-box p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        .log {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            margin-top: 10px;
        }
        .log div {
            padding: 2px 0;
        }
        .phase {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #4CAF50;
            background: #f0f8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 ระบบอัปเดตสต็อกสินค้าจาก CSV</h2>        <div class="nav-content">
            <a href="download_excel.php" class="nav-btn"> Download Stock</a>            
        </div>
        
        <div class="upload-form">
            <div class="form-group">
                <label>เลือกไฟล์ CSV:</label>
                <input type="file" id="csvFile" accept=".csv" required>
                <small style="color: #666;">รองรับไฟล์ขนาดใหญ่ ประมวลผลทีละ Batch (UTF-8, UTF-16, TIS-620)</small>
            </div>
            <button onclick="startUpload()" id="uploadBtn">อัปโหลดและอัปเดตสต็อก</button>
        </div>

        <div class="progress-container" id="progressContainer">
            <h3>กำลังประมวลผล...</h3>
            
            <div class="phase" id="phase1" style="display:none;">
                <strong>Phase 1:</strong> อ่านและประมวลผลไฟล์ CSV
            </div>
            <div class="phase" id="phase2" style="display:none;">
                <strong>Phase 2:</strong> รวมข้อมูลตาม Barcode
            </div>
            <div class="phase" id="phase3" style="display:none;">
                <strong>Phase 3:</strong> อัปเดตฐานข้อมูล
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill">0%</div>
            </div>
            <p id="progressText">เตรียมข้อมูล...</p>
            
            <div class="stats">
                <div class="stat-box" style="background:#e7f3ff;">
                    <h3 id="liveRead">0</h3>
                    <p>📖 อ่านแล้ว (แถว)</p>
                </div>
                <div class="stat-box" style="background:#fff3cd;">
                    <h3 id="liveUnique">0</h3>
                    <p>🔢 Barcode ไม่ซ้ำ</p>
                </div>
                <div class="stat-box" style="background:#d4edda;">
                    <h3 id="liveUpdated">0</h3>
                    <p>✅ อัปเดตสำเร็จ</p>
                </div>
                <div class="stat-box" style="background:#f8d7da;">
                    <h3 id="liveNotFound">0</h3>
                    <p>⚠️ ไม่พบ Barcode</p>
                </div>
            </div>
            
            <div class="log" id="logContainer"></div>
        </div>

        <div id="resultContainer"></div>
        
        <div class="result info">
            <h4>📋 คำแนะนำ:</h4>
            <ol>
                <li><strong>รูปแบบไฟล์:</strong> CSV ที่มีคอลัมน์ warehouse, barcode, physical</li>
                <li><strong>การประมวลผล:</strong>
                    <ul>
                        <li>Phase 1: อ่านไฟล์ CSV (รองรับ UTF-16, UTF-8, TIS-620)</li>
                        <li>Phase 2: รวมจำนวนของ barcode ที่ซ้ำกัน</li>
                        <li>Phase 3: อัปเดตฐานข้อมูลทีละ 100 รายการ</li>
                    </ul>
                </li>
                <li><strong>ข้อดี:</strong> ประหยัด memory สำหรับไฟล์ขนาดใหญ่ (50MB+)</li>
                <li><strong>ตัวอย่าง:</strong> ถ้ามี barcode เดียวกัน 3 แถว จำนวน 10, 5, 3 = จะอัปเดตเป็น 18</li>
            </ol>
        </div>
    </div>

    <script>
        let startTime = 0;
        let totalRead = 0;
        let totalUpdated = 0;
        let totalNotFound = 0;
        let totalErrors = 0;

        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const time = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#6c757d';
            logContainer.innerHTML += `<div style="color:${color}">[${time}] ${message}</div>`;
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        // ฟังก์ชันตรวจสอบและแปลง encoding
        function detectAndConvertEncoding(text) {
            // ตรวจสอบ BOM
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.substring(1); // ลบ BOM
            }
            return text;
        }

        // ฟังก์ชันแยก CSV line (รองรับ quotes)
        function parseCSVLine(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            result.push(current.trim());
            return result;
        }

        async function startUpload() {
            const fileInput = document.getElementById('csvFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('กรุณาเลือกไฟล์');
                return;
            }
            
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('กรุณาเลือกไฟล์ CSV เท่านั้น');
                return;
            }
            
            // Reset
            totalRead = 0;
            totalUpdated = 0;
            totalNotFound = 0;
            totalErrors = 0;
            
            document.getElementById('uploadBtn').disabled = true;
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('logContainer').innerHTML = '';
            document.getElementById('resultContainer').innerHTML = '';
            startTime = Date.now();
            
            try {
                // Phase 1: อ่านไฟล์
                document.getElementById('phase1').style.display = 'block';
                addLog('Phase 1: เริ่มอ่านไฟล์ CSV...');
                updateProgress(10, 'กำลังอ่านไฟล์...');
                
                const text = await readFile(file);
                const cleanText = detectAndConvertEncoding(text);
                const lines = cleanText.split(/\r?\n/);
                
                addLog(`อ่านไฟล์สำเร็จ พบ ${lines.length.toLocaleString()} แถว`, 'success');
                updateProgress(30, 'อ่านไฟล์เสร็จสิ้น');
                
                // Phase 2: ประมวลผลและรวม barcode
                document.getElementById('phase2').style.display = 'block';
                addLog('Phase 2: กำลังประมวลผลและรวมข้อมูล...');
                updateProgress(40, 'กำลังประมวลผลข้อมูล...');
                
                const aggregatedData = await processAndAggregate(lines);
                
                document.getElementById('liveRead').textContent = totalRead.toLocaleString();
                document.getElementById('liveUnique').textContent = Object.keys(aggregatedData).length.toLocaleString();
                
                addLog(`รวมข้อมูลเสร็จสิ้น: ${Object.keys(aggregatedData).length.toLocaleString()} barcode ไม่ซ้ำ`, 'success');
                updateProgress(60, 'ประมวลผลข้อมูลเสร็จสิ้น');
                
                // Phase 3: อัปเดตฐานข้อมูล
                document.getElementById('phase3').style.display = 'block';
                addLog('Phase 3: เริ่มอัปเดตฐานข้อมูล...');
                
                await updateDatabase(aggregatedData);
                
                finishProcess();
                
            } catch (error) {
                addLog(`เกิดข้อผิดพลาด: ${error.message}`, 'error');
                document.getElementById('uploadBtn').disabled = false;
            }
        }

        function readFile(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = () => reject(new Error('ไม่สามารถอ่านไฟล์ได้'));
                reader.readAsText(file, 'UTF-8');
            });
        }

        async function processAndAggregate(lines) {
            const aggregated = {};
            let barcodeIndex = -1;
            let physicalIndex = -1;
            
            // หา header
            if (lines.length > 0) {
                const headers = parseCSVLine(lines[0].toLowerCase());
                barcodeIndex = headers.findIndex(h => h.includes('barcode'));
                physicalIndex = headers.findIndex(h => h.includes('physical'));
                
                if (barcodeIndex === -1 || physicalIndex === -1) {
                    throw new Error('ไม่พบคอลัมน์ barcode หรือ physical');
                }
                
                addLog(`พบ header: barcode ที่คอลัมน์ ${barcodeIndex + 1}, physical ที่คอลัมน์ ${physicalIndex + 1}`);
            }
            
            // ประมวลผลข้อมูล
            for (let i = 1; i < lines.length; i++) {
                const line = lines[i].trim();
                if (!line) continue;
                
                const cols = parseCSVLine(line);
                if (cols.length <= Math.max(barcodeIndex, physicalIndex)) continue;
                
                const barcode = cols[barcodeIndex]?.trim();
                const physical = cols[physicalIndex]?.trim();
                
                if (!barcode || physical === '' || physical === 'N/A') continue;
                
                const qty = parseInt(physical) || 0;
                
                // รวมจำนวน
                if (aggregated[barcode]) {
                    aggregated[barcode] += qty;
                } else {
                    aggregated[barcode] = qty;
                }
                
                totalRead++;
                
                // Update UI ทุก 1000 แถว
                if (totalRead % 1000 === 0) {
                    document.getElementById('liveRead').textContent = totalRead.toLocaleString();
                    document.getElementById('liveUnique').textContent = Object.keys(aggregated).length.toLocaleString();
                    await sleep(0); // ให้ UI update
                }
            }
            
            return aggregated;
        }

        async function updateDatabase(aggregatedData) {
            const barcodes = Object.keys(aggregatedData);
            const batchSize = 100;
            const totalBatches = Math.ceil(barcodes.length / batchSize);
            
            for (let i = 0; i < totalBatches; i++) {
                const start = i * batchSize;
                const end = Math.min(start + batchSize, barcodes.length);
                const batchBarcodes = barcodes.slice(start, end);
                
                const batch = batchBarcodes.map(barcode => ({
                    barcode: barcode,
                    physical: aggregatedData[barcode]
                }));
                
                const progress = 60 + Math.round((i / totalBatches) * 30);
                updateProgress(progress, `อัปเดตฐานข้อมูล ${end}/${barcodes.length} (${Math.round((end/barcodes.length)*100)}%)`);
                
                try {
                    const response = await fetch('process_batch.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ batch: batch })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        totalUpdated += data.updated;
                        totalNotFound += data.not_found;
                        totalErrors += data.errors;
                        
                        document.getElementById('liveUpdated').textContent = totalUpdated.toLocaleString();
                        document.getElementById('liveNotFound').textContent = totalNotFound.toLocaleString();
                        
                        if (i % 5 === 0 || i === totalBatches - 1) {
                            addLog(`Batch ${i + 1}/${totalBatches}: อัปเดต ${data.updated}, ไม่พบ ${data.not_found}`, 'success');
                        }
                    } else {
                        addLog(`Batch ${i + 1} ล้มเหลว: ${data.message}`, 'error');
                    }
                } catch (error) {
                    addLog(`Batch ${i + 1} เกิดข้อผิดพลาด: ${error.message}`, 'error');
                }
                
                await sleep(50); // หน่วงเวลาเล็กน้อย
            }
        }

        function updateProgress(percent, text) {
            document.getElementById('progressFill').style.width = percent + '%';
            document.getElementById('progressFill').textContent = percent + '%';
            document.getElementById('progressText').textContent = text;
        }

        function finishProcess() {
            updateProgress(100, 'เสร็จสิ้น!');
            
            const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
            const speed = (totalRead / elapsed).toFixed(2);
            
            addLog('='.repeat(50), 'success');
            addLog('✅ เสร็จสิ้นการประมวลผล!', 'success');
            addLog(`เวลาที่ใช้: ${elapsed} วินาที`, 'success');
            addLog(`ความเร็ว: ${speed} แถว/วินาที`, 'success');
            
            const resultHTML = `
                <div class="result success">
                    <h3>✅ เสร็จสิ้น!</h3>
                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; margin-top:15px;">
                        <div>
                            <strong>📖 อ่านทั้งหมด:</strong> ${totalRead.toLocaleString()} แถว<br>
                            <strong>🔢 Barcode ไม่ซ้ำ:</strong> ${document.getElementById('liveUnique').textContent} รายการ<br>
                            <strong>✅ อัปเดตสำเร็จ:</strong> ${totalUpdated.toLocaleString()} รายการ
                        </div>
                        <div>
                            <strong>⚠️ ไม่พบ Barcode:</strong> ${totalNotFound.toLocaleString()} รายการ<br>
                            <strong>⏱️ เวลาที่ใช้:</strong> ${elapsed} วินาที<br>
                            <strong>🚀 ความเร็ว:</strong> ${speed} แถว/วินาที
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('resultContainer').innerHTML = resultHTML;
            document.getElementById('uploadBtn').disabled = false;
            document.getElementById('uploadBtn').textContent = 'อัปโหลดไฟล์ใหม่';
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    </script>
</body>
</html>