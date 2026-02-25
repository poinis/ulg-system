<?php
/**
 * Social Media CSV/Excel Upload & Import
 * รองรับ Facebook CSV, Instagram CSV, TikTok Excel
 */

require_once 'SocialMediaImporter.php';

$message = '';
$messageType = '';
$importResults = [];

// ประมวลผลเมื่อมีการอัพโหลดไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['data_files'])) {
    try {
        $importer = new SocialMediaImporter();
        
        // ลบข้อมูลเก่าถ้าเลือก
        if (isset($_POST['truncate']) && $_POST['truncate'] === '1') {
            $importer->truncateTable();
        }
        
        $files = $_FILES['data_files'];
        $totalSuccess = 0;
        $totalErrors = 0;
        
        // Upload directory
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Process each file
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $fileName = $files['name'][$i];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                $importResults[] = [
                    'file' => $fileName,
                    'status' => 'error',
                    'message' => 'รองรับเฉพาะไฟล์ CSV และ Excel เท่านั้น'
                ];
                continue;
            }
            
            // Move file
            $uploadPath = $uploadDir . uniqid() . '_' . $fileName;
            if (!move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
                $importResults[] = [
                    'file' => $fileName,
                    'status' => 'error',
                    'message' => 'ไม่สามารถอัพโหลดไฟล์ได้'
                ];
                continue;
            }
            
            // Import
            $importer->resetCounters();
            $result = $importer->importFile($uploadPath);
            
            $importResults[] = [
                'file' => $fileName,
                'status' => 'success',
                'platform' => $result['platform'],
                'success' => $result['success'],
                'errors' => $result['errors']
            ];
            
            $totalSuccess += $result['success'];
            $totalErrors += $result['errors'];
            
            // Delete temp file
            unlink($uploadPath);
        }
        
        if ($totalSuccess > 0) {
            $message = "นำเข้าข้อมูลสำเร็จ $totalSuccess รายการ";
            if ($totalErrors > 0) {
                $message .= ", ผิดพลาด $totalErrors รายการ";
            }
            $messageType = 'success';
        } else {
            $message = 'ไม่สามารถนำเข้าข้อมูลได้';
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = 'ข้อผิดพลาด: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ดึงสถิติปัจจุบัน
try {
    $importer = new SocialMediaImporter();
    $currentStats = $importer->getCountBySocial();
    $statsSummary = $importer->getStatsSummary();
} catch (Exception $e) {
    $currentStats = [];
    $statsSummary = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Data Importer</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }
        
        h1 {
            color: #1a1a2e;
            text-align: center;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .platform-badges {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .platform-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            color: white;
        }
        
        .platform-badge.facebook { background: linear-gradient(135deg, #1877F2, #166FE5); }
        .platform-badge.instagram { background: linear-gradient(135deg, #E4405F, #C13584, #833AB4); }
        .platform-badge.tiktok { background: linear-gradient(135deg, #000000, #25F4EE, #FE2C55); }
        
        .upload-area {
            border: 3px dashed #ddd;
            border-radius: 16px;
            padding: 50px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f3ff 0%, #e8ecff 100%);
            transform: translateY(-2px);
        }
        
        .upload-area.dragover {
            border-color: #667eea;
            background: linear-gradient(135deg, #e8ecff 0%, #dde3ff 100%);
        }
        
        .upload-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .upload-text {
            color: #666;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .upload-hint {
            color: #999;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        input[type="file"] {
            display: none;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .checkbox-group {
            margin: 25px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            color: #666;
            cursor: pointer;
            font-size: 15px;
        }
        
        .message {
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
        }
        
        .message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #b1dfbb;
        }
        
        .message.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .import-results {
            margin-top: 20px;
        }
        
        .import-result-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .import-result-item .icon {
            font-size: 24px;
            margin-right: 15px;
        }
        
        .import-result-item .details {
            flex: 1;
        }
        
        .import-result-item .platform-tag {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .stat-card.facebook { 
            border-color: #1877F2;
            background: linear-gradient(135deg, #ffffff 0%, #f0f5ff 100%);
        }
        .stat-card.instagram { 
            border-color: #E4405F;
            background: linear-gradient(135deg, #ffffff 0%, #fff0f3 100%);
        }
        .stat-card.tiktok { 
            border-color: #000000;
            background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
        }
        
        .stat-card .platform-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-card .platform-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .stat-card.facebook .platform-name { color: #1877F2; }
        .stat-card.instagram .platform-name { color: #E4405F; }
        .stat-card.tiktok .platform-name { color: #000000; }
        
        .stat-number {
            font-size: 42px;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .stat-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .stat-detail {
            text-align: center;
        }
        
        .stat-detail-value {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-detail-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
        }
        
        .file-list {
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: #e8f5e9;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .file-item .file-icon {
            font-size: 24px;
            margin-right: 12px;
        }
        
        .file-item .file-name {
            flex: 1;
            font-weight: 500;
            color: #2e7d32;
        }
        
        .file-item .file-size {
            color: #666;
            font-size: 13px;
        }
        
        .file-item .remove-btn {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }
        
        .file-item .remove-btn:hover {
            color: #f44336;
        }
        
        .nav-links {
            text-align: center;
            margin-top: 25px;
        }
        
        .nav-links a {
            display: inline-block;
            padding: 12px 30px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid rgba(255,255,255,0.2);
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 30px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .format-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .format-info h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .format-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .format-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .format-item .icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .format-item.facebook .icon { background: #e7f0ff; }
        .format-item.instagram .icon { background: #ffe7ec; }
        .format-item.tiktok .icon { background: #f0f0f0; }
        
        .format-item .text {
            font-size: 13px;
            color: #666;
        }
        
        .format-item .text strong {
            display: block;
            color: #333;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>📊 Social Media Data Importer</h1>
            <p class="subtitle">นำเข้าข้อมูลจาก Facebook, Instagram และ TikTok</p>
            
            <div class="platform-badges">
                <span class="platform-badge facebook">📘 Facebook CSV</span>
                <span class="platform-badge instagram">📸 Instagram CSV</span>
                <span class="platform-badge tiktok">🎵 TikTok Excel</span>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                
                <?php if (!empty($importResults)): ?>
                <div class="import-results">
                    <?php foreach ($importResults as $result): ?>
                        <div class="import-result-item">
                            <span class="icon"><?php echo $result['status'] === 'success' ? '✅' : '❌'; ?></span>
                            <div class="details">
                                <strong><?php echo htmlspecialchars($result['file']); ?></strong>
                                <?php if ($result['status'] === 'success'): ?>
                                    <br><small>นำเข้า <?php echo $result['success']; ?> รายการ 
                                    <?php if ($result['errors'] > 0): ?>, ผิดพลาด <?php echo $result['errors']; ?><?php endif; ?></small>
                                <?php else: ?>
                                    <br><small><?php echo htmlspecialchars($result['message']); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if (isset($result['platform'])): ?>
                                <span class="platform-tag" style="background: <?php 
                                    echo $result['platform'] === 'Facebook' ? '#1877F2' : 
                                        ($result['platform'] === 'Instagram' ? '#E4405F' : '#000'); 
                                ?>"><?php echo $result['platform']; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="dropZone">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">คลิกหรือลากไฟล์มาวางที่นี่</div>
                    <div class="upload-hint">รองรับไฟล์ CSV (Facebook, Instagram) และ Excel (TikTok) - สามารถเลือกหลายไฟล์ได้</div>
                    <input type="file" name="data_files[]" id="data_files" accept=".csv,.xlsx,.xls" multiple required>
                    <button type="button" class="btn" onclick="document.getElementById('data_files').click()">
                        เลือกไฟล์
                    </button>
                </div>
                
                <div class="file-list" id="fileList"></div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="truncate" value="1" id="truncate">
                    <label for="truncate">🗑️ ลบข้อมูลเก่าทั้งหมดก่อนนำเข้า</label>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn" id="submitBtn">
                        📤 อัพโหลดและนำเข้าข้อมูล
                    </button>
                </div>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <div>กำลังนำเข้าข้อมูล กรุณารอสักครู่...</div>
                </div>
            </form>
            
            <div class="format-info">
                <h3>📋 รูปแบบไฟล์ที่รองรับ</h3>
                <div class="format-list">
                    <div class="format-item facebook">
                        <div class="icon">📘</div>
                        <div class="text">
                            <strong>Facebook</strong>
                            ไฟล์ CSV จาก Meta Business Suite
                        </div>
                    </div>
                    <div class="format-item instagram">
                        <div class="icon">📸</div>
                        <div class="text">
                            <strong>Instagram</strong>
                            ไฟล์ CSV จาก Meta Business Suite
                        </div>
                    </div>
                    <div class="format-item tiktok">
                        <div class="icon">🎵</div>
                        <div class="text">
                            <strong>TikTok</strong>
                            ไฟล์ Excel (.xlsx) จาก TikTok Analytics
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($statsSummary)): ?>
        <div class="card">
            <h2 style="text-align: center; margin-bottom: 25px; color: #333;">📈 สถิติข้อมูลในระบบ</h2>
            <div class="stats-grid">
                <?php 
                $platforms = [
                    'Facebook' => ['icon' => '📘', 'class' => 'facebook'],
                    'Instagram' => ['icon' => '📸', 'class' => 'instagram'],
                    'TikTok' => ['icon' => '🎵', 'class' => 'tiktok']
                ];
                
                $statsMap = [];
                foreach ($statsSummary as $stat) {
                    $statsMap[$stat['social']] = $stat;
                }
                
                foreach ($platforms as $platform => $info):
                    $stat = $statsMap[$platform] ?? null;
                ?>
                <div class="stat-card <?php echo $info['class']; ?>">
                    <div class="platform-icon"><?php echo $info['icon']; ?></div>
                    <div class="platform-name"><?php echo $platform; ?></div>
                    <div class="stat-number"><?php echo $stat ? number_format($stat['post_count']) : '0'; ?></div>
                    <div class="stat-label">โพสต์ทั้งหมด</div>
                    
                    <?php if ($stat): ?>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <div class="stat-detail-value"><?php echo number_format($stat['total_views']); ?></div>
                            <div class="stat-detail-label">Views</div>
                        </div>
                        <div class="stat-detail">
                            <div class="stat-detail-value"><?php echo number_format($stat['total_likes']); ?></div>
                            <div class="stat-detail-label">Likes</div>
                        </div>
                        <div class="stat-detail">
                            <div class="stat-detail-value"><?php echo number_format($stat['total_comments']); ?></div>
                            <div class="stat-detail-label">Comments</div>
                        </div>
                        <div class="stat-detail">
                            <div class="stat-detail-value"><?php echo number_format($stat['total_shares']); ?></div>
                            <div class="stat-detail-label">Shares</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="nav-links">
            <a href="view_data.php">📋 ดูข้อมูลทั้งหมด</a>
            <a href="index.php">📊 รายงาน & Engagement</a>
        </div>
    </div>
    
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('data_files');
        const fileList = document.getElementById('fileList');
        const uploadForm = document.getElementById('uploadForm');
        const submitBtn = document.getElementById('submitBtn');
        const loading = document.getElementById('loading');
        
        let selectedFiles = [];
        
        // Drag and Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('dragover');
            });
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('dragover');
            });
        });
        
        dropZone.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer.files);
            addFiles(files);
        });
        
        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            addFiles(files);
        });
        
        function addFiles(files) {
            files.forEach(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                if (['csv', 'xlsx', 'xls'].includes(ext)) {
                    if (!selectedFiles.find(f => f.name === file.name)) {
                        selectedFiles.push(file);
                    }
                }
            });
            updateFileList();
            updateFileInput();
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            updateFileInput();
        }
        
        function updateFileList() {
            if (selectedFiles.length === 0) {
                fileList.innerHTML = '';
                return;
            }
            
            let html = '';
            selectedFiles.forEach((file, index) => {
                const size = (file.size / 1024).toFixed(1);
                const ext = file.name.split('.').pop().toLowerCase();
                let icon = '📄';
                let platform = '';
                
                if (ext === 'csv') {
                    icon = '📊';
                    platform = 'CSV';
                } else if (['xlsx', 'xls'].includes(ext)) {
                    icon = '📗';
                    platform = 'Excel';
                }
                
                html += `
                    <div class="file-item">
                        <span class="file-icon">${icon}</span>
                        <span class="file-name">${file.name}</span>
                        <span class="file-size">${size} KB (${platform})</span>
                        <button type="button" class="remove-btn" onclick="removeFile(${index})">✕</button>
                    </div>
                `;
            });
            fileList.innerHTML = html;
        }
        
        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }
        
        uploadForm.addEventListener('submit', (e) => {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('กรุณาเลือกไฟล์ที่ต้องการนำเข้า');
                return;
            }
            submitBtn.disabled = true;
            loading.style.display = 'block';
        });
    </script>
</body>
</html>
