<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปโหลด Excel เข้า Database</title>
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
            border-bottom: 3px solid #FF5722;
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
            background-color: #FF5722;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background-color: #E64A19;
        }
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .warning h4 {
            margin-top: 0;
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
            background: linear-gradient(90deg, #FF5722, #E64A19);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .log {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            margin-top: 10px;
        }
        .log div {
            padding: 2px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
    <div class="container">
        <h2>📤 อัปโหลด Excel เข้า Database</h2>
        
        <div class="warning">
            <h4>⚠️ คำเตือนสำคัญ:</h4>
            <ul>
                <li><strong>ข้อมูลเดิมจะถูกลบทั้งหมด</strong> ก่อนอัปโหลดข้อมูลใหม่</li>
                <li>จะอัปโหลดเฉพาะแถวที่มี <strong>Status = "Active"</strong> เท่านั้น</li>
                <li>ระบบจะแมปคอลัมน์ที่ตรงกับ Database อัตโนมัติ</li>
                <li>กรุณาตรวจสอบไฟล์ให้แน่ใจก่อนอัปโหลด</li>
            </ul>
        </div>

        <div class="upload-form">
            <div class="form-group">
                <label>เลือกไฟล์ Excel (.xlsx, .xls):</label>
                <input type="file" id="excelFile" accept=".xlsx,.xls" required>
                <small style="color: #666;">รองรับไฟล์ Excel ขนาดใหญ่</small>
            </div>
            <button onclick="uploadExcel()" id="uploadBtn">🚀 อัปโหลดและแทนที่ข้อมูล</button>
        </div>

        <div class="progress-container" id="progressContainer">
            <h3>กำลังประมวลผล...</h3>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill">0%</div>
            </div>
            <p id="progressText">เตรียมข้อมูล...</p>
            <div class="log" id="logContainer"></div>
        </div>

        <div id="resultContainer"></div>
        
        <div class="result info">
            <h4>📋 คำแนะนำ:</h4>
            <ol>
                <li><strong>รูปแบบไฟล์:</strong> Excel (.xlsx หรือ .xls)</li>
                <li><strong>แถวแรก:</strong> ต้องเป็นชื่อคอลัมน์ (Header)</li>
                <li><strong>Status:</strong> มีคอลัมน์ "Status" และเลือกเฉพาะ "Active"</li>
                <li><strong>คอลัมน์:</strong> ระบบจะแมปคอลัมน์ที่ตรงกับ Database อัตโนมัติ</li>
            </ol>
        </div>
    </div>

    <script>
        let excelData = [];

        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const time = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#6c757d';
            logContainer.innerHTML += `<div style="color:${color}">[${time}] ${message}</div>`;
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        function uploadExcel() {
            const fileInput = document.getElementById('excelFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('กรุณาเลือกไฟล์');
                return;
            }
            
            if (!confirm('⚠️ คำเตือน: ข้อมูลเดิมทั้งหมดจะถูกลบ!\n\nคุณแน่ใจหรือไม่ที่จะดำเนินการต่อ?')) {
                return;
            }
            
            document.getElementById('uploadBtn').disabled = true;
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('logContainer').innerHTML = '';
            document.getElementById('resultContainer').innerHTML = '';
            
            addLog('เริ่มอ่านไฟล์ Excel...');
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    
                    // อ่าน Sheet แรก
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet);
                    
                    addLog(`อ่านไฟล์สำเร็จ พบข้อมูล ${jsonData.length} แถว`, 'success');
                    
                    // กรองเฉพาะ Status = Active
                    excelData = jsonData.filter(row => {
                        return row.Status === 'Active' || row.status === 'Active' || 
                               row.STATUS === 'Active' || row.STATUS === 'ACTIVE';
                    });
                    
                    addLog(`กรองเฉพาะ Status = Active: ${excelData.length} แถว`, 'success');
                    
                    if (excelData.length === 0) {
                        addLog('ไม่พบข้อมูลที่มี Status = Active', 'error');
                        document.getElementById('uploadBtn').disabled = false;
                        return;
                    }
                    
                    // ส่งข้อมูลไปยัง Server
                    processUpload();
                    
                } catch (error) {
                    addLog('เกิดข้อผิดพลาดในการอ่านไฟล์: ' + error.message, 'error');
                    document.getElementById('uploadBtn').disabled = false;
                }
            };
            
            reader.onerror = function() {
                addLog('ไม่สามารถอ่านไฟล์ได้', 'error');
                document.getElementById('uploadBtn').disabled = false;
            };
            
            reader.readAsArrayBuffer(file);
        }

        function processUpload() {
            addLog('กำลังส่งข้อมูลไปยังเซิร์ฟเวอร์...');
            
            document.getElementById('progressFill').style.width = '50%';
            document.getElementById('progressFill').textContent = '50%';
            document.getElementById('progressText').textContent = 'กำลังล้างข้อมูลเดิมและอัปโหลด...';
            
            fetch('process_excel_upload.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ data: excelData })
            })
            .then(response => response.json())
            .then(result => {
                document.getElementById('progressFill').style.width = '100%';
                document.getElementById('progressFill').textContent = '100%';
                
                if (result.success) {
                    addLog('='.repeat(50), 'success');
                    addLog('เสร็จสิ้นการอัปโหลด!', 'success');
                    addLog(`ลบข้อมูลเดิม: ${result.deleted} แถว`, 'success');
                    addLog(`อัปโหลดสำเร็จ: ${result.inserted} แถว`, 'success');
                    addLog(`ข้ามไป: ${result.skipped} แถว`, 'success');
                    
                    const resultHTML = `
                        <div class="result success">
                            <h3>✅ อัปโหลดสำเร็จ!</h3>
                            <p><strong>ลบข้อมูลเดิม:</strong> ${result.deleted.toLocaleString()} แถว</p>
                            <p><strong>อัปโหลดสำเร็จ:</strong> ${result.inserted.toLocaleString()} แถว</p>
                            <p><strong>ข้ามไป:</strong> ${result.skipped.toLocaleString()} แถว</p>
                        </div>
                    `;
                    
                    document.getElementById('resultContainer').innerHTML = resultHTML;
                } else {
                    addLog(`เกิดข้อผิดพลาด: ${result.message}`, 'error');
                    
                    const resultHTML = `
                        <div class="result error">
                            <h3>❌ เกิดข้อผิดพลาด</h3>
                            <p>${result.message}</p>
                        </div>
                    `;
                    
                    document.getElementById('resultContainer').innerHTML = resultHTML;
                }
                
                document.getElementById('uploadBtn').disabled = false;
            })
            .catch(error => {
                addLog(`เกิดข้อผิดพลาดในการส่งข้อมูล: ${error}`, 'error');
                document.getElementById('uploadBtn').disabled = false;
            });
        }
    </script>
</body>
</html>