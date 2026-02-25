<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SFTP File Browser</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .controls {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .file-list {
            padding: 0;
            list-style: none;
        }
        
        .file-item {
            padding: 15px 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .file-date {
            color: #6c757d;
            font-size: 13px;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .message {
            padding: 15px 30px;
            margin: 20px 30px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: #6c757d;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            margin-left: auto;
            margin-right: 10px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .status.downloading {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.completed {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 SFTP File Browser</h1>
            <p>เลือกไฟล์ Stock Online ที่ต้องการดาวน์โหลด</p>
        </div>
        <div class="nav-content">
            <a href="upload.php" class="nav-btn"> Upload Stock</a>            
        </div>
        
        <div class="controls">
            <button class="btn btn-primary" onclick="loadFiles()" id="loadBtn">
                <span>🔄</span> โหลดรายการไฟล์
            </button>
            <button class="btn btn-success" onclick="downloadSelected()" id="downloadBtn" style="display:none;">
                <span>⬇️</span> ดาวน์โหลดที่เลือก (<span id="selectedCount">0</span>)
            </button>
            <button class="btn btn-secondary" onclick="selectAll()" id="selectAllBtn" style="display:none;">
                เลือกทั้งหมด
            </button>

        </div>
        
        <div id="content">
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
                <p>คลิกปุ่ม "โหลดรายการไฟล์" เพื่อดูไฟล์ทั้งหมด</p>
            </div>
        </div>
    </div>

    <script>
        let allFiles = [];
        
        function loadFiles() {
            const content = document.getElementById('content');
            const loadBtn = document.getElementById('loadBtn');
            
            loadBtn.disabled = true;
            
            content.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>กำลังเชื่อมต่อ SFTP และโหลดรายการไฟล์...</p>
                </div>
            `;
            
            fetch('list_files.php')
                .then(response => response.json())
                .then(data => {
                    loadBtn.disabled = false;
                    
                    if (data.success) {
                        allFiles = data.files;
                        displayFiles(data.files);
                        document.getElementById('downloadBtn').style.display = 'inline-flex';
                        document.getElementById('selectAllBtn').style.display = 'inline-flex';
                    } else {
                        content.innerHTML = `
                            <div class="message error">
                                <strong>เกิดข้อผิดพลาด:</strong> ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    loadBtn.disabled = false;
                    content.innerHTML = `
                        <div class="message error">
                            <strong>เกิดข้อผิดพลาด:</strong> ${error.message}
                        </div>
                    `;
                });
        }
        
        function displayFiles(files) {
            const content = document.getElementById('content');
            
            if (files.length === 0) {
                content.innerHTML = `
                    <div class="empty-state">
                        <p>ไม่พบไฟล์ที่ตรงตามเงื่อนไข</p>
                    </div>
                `;
                return;
            }
            
            let html = '<ul class="file-list">';
            
            files.forEach((file, index) => {
                html += `
                    <li class="file-item">
                        <div class="file-info">
                            <div class="file-name">📄 ${file.name}</div>
                            <div class="file-date">วันที่: ${file.date}</div>
                        </div>
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="file-${index}" value="${file.name}" onchange="updateSelectedCount()">
                        </div>
                        <div class="file-actions">
                            <button class="btn btn-primary" onclick="downloadFile('${file.name}', ${index})">
                                ⬇️ ดาวน์โหลด
                            </button>
                        </div>
                    </li>
                `;
            });
            
            html += '</ul>';
            content.innerHTML = html;
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('downloadBtn').style.display = count > 0 ? 'inline-flex' : 'none';
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            
            updateSelectedCount();
        }
        
        function downloadFile(filename, index) {
            const fileItem = document.querySelectorAll('.file-item')[index];
            const actions = fileItem.querySelector('.file-actions');
            
            actions.innerHTML = '<span class="status downloading">กำลังดาวน์โหลด...</span>';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'download_file.php';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.name = 'filename';
            input.value = filename;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            
            setTimeout(() => {
                actions.innerHTML = `
                    <span class="status completed">✓ เสร็จสิ้น</span>
                    <button class="btn btn-primary" onclick="downloadFile('${filename}', ${index})">
                        ⬇️ ดาวน์โหลดอีกครั้ง
                    </button>
                `;
            }, 2000);
        }
        
        function downloadSelected() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            const filenames = Array.from(checkboxes).map(cb => cb.value);
            
            if (filenames.length === 0) {
                alert('กรุณาเลือกไฟล์อย่างน้อย 1 ไฟล์');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'download_multiple.php';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.name = 'filenames';
            input.value = JSON.stringify(filenames);
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>