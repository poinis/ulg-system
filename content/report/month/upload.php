<?php
/**
 * Monthly Upload Page
 * หน้าอัพโหลดข้อมูลรายเดือน - Auto-detect เดือนจาก Publish Time
 */

require_once 'MonthlyImporter.php';

$message = '';
$messageType = '';
$result = null;

// จัดการการอัพโหลด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['datafile'])) {
    $importer = new MonthlyImporter();
    
    $file = $_FILES['datafile'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $file['tmp_name'];
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            $message = "รองรับเฉพาะไฟล์ CSV และ Excel (.xlsx, .xls) เท่านั้น";
            $messageType = "error";
        } else {
            try {
                // ส่ง original filename เพื่อให้ระบบรู้ว่าเป็นไฟล์อะไร
                $result = $importer->importFile($tmpPath, $originalName);
                
                // สร้างข้อความแสดงเดือนที่ import
                $monthsText = '';
                if (!empty($result['months_imported'])) {
                    $monthsList = [];
                    $thaiMonthsTemp = [
                        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
                        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
                        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
                    ];
                    foreach ($result['months_imported'] as $m) {
                        $monthsList[] = $thaiMonthsTemp[$m['month']] . ' ' . ($m['year'] + 543);
                    }
                    $monthsText = ' | เดือน: ' . implode(', ', array_unique($monthsList));
                }
                
                $message = "นำเข้าข้อมูลสำเร็จ! Platform: {$result['platform']}, นำเข้า: {$result['success']} รายการ" . $monthsText;
                if ($result['errors'] > 0) {
                    $message .= " | ผิดพลาด: {$result['errors']} รายการ";
                }
                $messageType = "success";
            } catch (Exception $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } else {
        $message = "เกิดข้อผิดพลาดในการอัพโหลดไฟล์";
        $messageType = "error";
    }
}

// จัดการการลบข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_month'])) {
    $importer = new MonthlyImporter();
    $month = intval($_POST['delete_month']);
    $year = intval($_POST['delete_year']);
    $platform = $_POST['delete_platform'] ?? null;
    
    try {
        $deleted = $importer->deleteByMonth($month, $year, $platform ?: null);
        $message = "ลบข้อมูลสำเร็จ! ($deleted รายการ)";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $messageType = "error";
    }
}

// ดึงข้อมูลเดือนที่มี
$importer = new MonthlyImporter();
$availableMonths = $importer->getAvailableMonths();

// จัดกลุ่มตามเดือน/ปี
$groupedMonths = [];
foreach ($availableMonths as $m) {
    $key = $m['report_year'] . '-' . str_pad($m['report_month'], 2, '0', STR_PAD_LEFT);
    if (!isset($groupedMonths[$key])) {
        $groupedMonths[$key] = [
            'month' => $m['report_month'],
            'year' => $m['report_year'],
            'platforms' => []
        ];
    }
    $groupedMonths[$key]['platforms'][$m['social']] = $m['post_count'];
}
krsort($groupedMonths);

$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัพโหลดข้อมูลรายเดือน | Monthly Compare</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }
        
        .container { max-width: 900px; margin: 0 auto; }
        
        .header {
            text-align: center;
            padding: 30px 0;
        }
        
        .header h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .header p { color: rgba(255,255,255,0.6); }
        
        .nav-links {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            padding: 10px 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .card h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-area {
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover { border-color: #667eea; background: rgba(102, 126, 234, 0.1); }
        .upload-area.dragover { border-color: #667eea; background: rgba(102, 126, 234, 0.2); }
        .upload-area i { font-size: 3rem; color: #667eea; margin-bottom: 15px; }
        .upload-area p { color: rgba(255,255,255,0.7); margin-bottom: 10px; }
        .upload-area .file-types { font-size: 0.85rem; color: rgba(255,255,255,0.5); }
        .upload-area input[type="file"] { display: none; }
        
        .file-selected {
            margin-top: 15px;
            padding: 10px 15px;
            background: rgba(102, 126, 234, 0.2);
            border-radius: 8px;
            display: none;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.4);
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: rgba(46, 213, 115, 0.2);
            border: 1px solid rgba(46, 213, 115, 0.5);
            color: #2ed573;
        }
        
        .message.error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.5);
            color: #e74c3c;
        }
        
        .month-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .month-item:hover { background: rgba(255,255,255,0.06); }
        
        .month-info { display: flex; align-items: center; gap: 15px; }
        .month-info .month-name { font-weight: 500; }
        
        .platform-tags { display: flex; gap: 8px; flex-wrap: wrap; }
        
        .platform-tag {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .platform-tag.facebook { background: rgba(24, 119, 242, 0.2); color: #1877F2; }
        .platform-tag.instagram { background: rgba(228, 64, 95, 0.2); color: #E4405F; }
        .platform-tag.tiktok { background: rgba(0, 0, 0, 0.3); color: #fff; }
        
        .platforms-preview {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .platform-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .platform-badge.facebook { background: rgba(24, 119, 242, 0.2); color: #1877F2; }
        .platform-badge.instagram { background: rgba(228, 64, 95, 0.2); color: #E4405F; }
        .platform-badge.tiktok { background: rgba(37, 244, 238, 0.2); color: #25F4EE; }
        
        .help-text {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            margin-top: 8px;
        }
        
        .auto-detect-info {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .auto-detect-info i { color: #667eea; margin-right: 8px; }
        
        .delete-actions { display: flex; gap: 5px; }
        
        @media (max-width: 768px) {
            .month-item { flex-direction: column; gap: 15px; align-items: flex-start; }
            .delete-actions { width: 100%; justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-alt"></i> ระบบเปรียบเทียบข้อมูลรายเดือน</h1>
            <p>นำเข้าข้อมูล Social Media เพื่อเปรียบเทียบประสิทธิภาพแต่ละเดือน</p>
        </div>
        
        <div class="nav-links">
            <a href="../index.php"><i class="fas fa-home"></i> หน้าหลัก</a>
            <a href="upload.php"><i class="fas fa-upload"></i> อัพโหลด</a>
            <a href="manage.php"><i class="fas fa-edit"></i> จัดการโพสต์</a>
            <a href="index.php"><i class="fas fa-chart-bar"></i> รายงาน</a>
            <a href="ad_analysis.php" class="active"><i class="fas fa-bullhorn"></i> วิเคราะห์โฆษณา</a>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($result && !empty($result['error_details'])): ?>
        <div class="card">
            <h2><i class="fas fa-exclamation-triangle"></i> รายละเอียดข้อผิดพลาด</h2>
            <ul style="padding-left: 20px; color: rgba(255,255,255,0.7);">
                <?php foreach (array_slice($result['error_details'], 0, 10) as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
                <?php if (count($result['error_details']) > 10): ?>
                <li>... และอีก <?php echo count($result['error_details']) - 10; ?> รายการ</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-cloud-upload-alt"></i> อัพโหลดข้อมูล</h2>
            
            <div class="auto-detect-info">
                <i class="fas fa-magic"></i>
                <strong>Auto-detect เดือน:</strong> ระบบจะดึงเดือนจาก Publish Time ในแต่ละโพสต์โดยอัตโนมัติ 
                (รองรับไฟล์ที่มีหลายเดือนในไฟล์เดียว)
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="dropZone">
                    <i class="fas fa-file-upload"></i>
                    <p>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</p>
                    <p class="file-types">รองรับ: CSV (Facebook, Instagram) | Excel (TikTok)</p>
                    <input type="file" name="datafile" id="fileInput" accept=".csv,.xlsx,.xls" required>
                    <div class="file-selected" id="fileSelected">
                        <i class="fas fa-file"></i> <span id="fileName"></span>
                    </div>
                </div>
                
                <div class="platforms-preview">
                    <span class="platform-badge facebook"><i class="fab fa-facebook"></i> Facebook</span>
                    <span class="platform-badge instagram"><i class="fab fa-instagram"></i> Instagram</span>
                    <span class="platform-badge tiktok"><i class="fab fa-tiktok"></i> TikTok</span>
                </div>
                <p class="help-text">ระบบจะตรวจจับ Platform อัตโนมัติจากรูปแบบไฟล์</p>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> อัพโหลดข้อมูล
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($groupedMonths)): ?>
        <div class="card">
            <h2><i class="fas fa-database"></i> ข้อมูลที่มีในระบบ</h2>
            
            <?php foreach ($groupedMonths as $key => $data): ?>
            <div class="month-item">
                <div class="month-info">
                    <i class="fas fa-calendar" style="color: #667eea;"></i>
                    <span class="month-name"><?php echo $thaiMonths[$data['month']]; ?> <?php echo $data['year'] + 543; ?></span>
                    <div class="platform-tags">
                        <?php foreach ($data['platforms'] as $platform => $count): ?>
                        <span class="platform-tag <?php echo strtolower($platform); ?>">
                            <?php echo $platform; ?>: <?php echo number_format($count); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="delete-actions">
                    <?php foreach ($data['platforms'] as $platform => $count): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('ยืนยันลบข้อมูล <?php echo $platform; ?> เดือน <?php echo $thaiMonths[$data['month']]; ?> <?php echo $data['year'] + 543; ?>?');">
                        <input type="hidden" name="delete_month" value="<?php echo $data['month']; ?>">
                        <input type="hidden" name="delete_year" value="<?php echo $data['year']; ?>">
                        <input type="hidden" name="delete_platform" value="<?php echo $platform; ?>">
                        <button type="submit" class="btn btn-danger" title="ลบ <?php echo $platform; ?>">
                            <i class="fas fa-trash"></i> <?php echo $platform; ?>
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card" style="text-align: center; color: rgba(255,255,255,0.5);">
            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px;"></i>
            <p>ยังไม่มีข้อมูลในระบบ กรุณาอัพโหลดไฟล์เพื่อเริ่มต้นใช้งาน</p>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileSelected = document.getElementById('fileSelected');
        const fileName = document.getElementById('fileName');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileName();
            }
        });
        
        fileInput.addEventListener('change', updateFileName);
        
        function updateFileName() {
            if (fileInput.files.length > 0) {
                fileName.textContent = fileInput.files[0].name;
                fileSelected.style.display = 'block';
            }
        }
    </script>
</body>
</html>