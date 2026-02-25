<?php
// admin/export.php - Export to Excel
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION)) {
    header('Location: ../login.php');
    exit;
}

$yearMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// If download requested
if (isset($_GET['download'])) {
    $payrollData = calculatePayroll($conn, $yearMonth);
    $targetPoints = (int) getSetting($conn, 'target_points', 100);
    
    // Generate CSV (Excel-compatible)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="incentive_payroll_' . $yearMonth . '.csv"');
    
    // BOM for Excel UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, [
        'สาขา',
        'รหัสสาขา', 
        'คะแนนรวม',
        'เป้าหมาย',
        '% ของเป้า',
        'Base Incentive (บาท)',
        'Trophy Bonus (บาท)',
        'รวมจ่าย/คน (บาท)',
        'รางวัล Trophy'
    ]);
    
    // Data rows
    $totalBase = 0;
    $totalTrophy = 0;
    $totalPayout = 0;
    
    foreach ($payrollData as $p) {
        fputcsv($output, [
            $p['branch_name'],
            $p['branch_code'],
            $p['total_points'],
            $targetPoints,
            $p['payout_percent'] . '%',
            number_format($p['base_incentive'], 2),
            number_format($p['trophy_bonus'], 2),
            number_format($p['total_payout'], 2),
            implode(', ', $p['trophy_list'])
        ]);
        
        $totalBase += $p['base_incentive'];
        $totalTrophy += $p['trophy_bonus'];
        $totalPayout += $p['total_payout'];
    }
    
    // Total row
    fputcsv($output, []);
    fputcsv($output, [
        'รวมทั้งหมด',
        '',
        '',
        '',
        '',
        number_format($totalBase, 2),
        number_format($totalTrophy, 2),
        number_format($totalPayout, 2),
        ''
    ]);
    
    fclose($output);
    exit;
}

// Export submissions detail
if (isset($_GET['download_detail'])) {
    $filters = ['year_month' => $yearMonth];
    if (isset($_GET['status']) && $_GET['status']) {
        $filters['status'] = $_GET['status'];
    }
    $submissions = getSubmissions($conn, $filters);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="incentive_detail_' . $yearMonth . '.csv"');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'วันที่',
        'เวลา',
        'สาขา',
        'ชื่อพนักงาน',
        'ประเภทงาน',
        'คะแนน',
        'สถานะ',
        'Link/รูป',
        'ผู้ตรวจสอบ',
        'เหตุผลที่ไม่อนุมัติ'
    ]);
    
    foreach ($submissions as $s) {
        $statusTh = ['pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติ', 'rejected' => 'ไม่อนุมัติ'];
        fputcsv($output, [
            $s['submission_date'],
            date('H:i', strtotime($s['created_at'])),
            $s['branch_name'],
            $s['user_name'] ?? $s['username'],
            $s['task_name_th'],
            $s['points_earned'],
            $statusTh[$s['status']] ?? $s['status'],
            $s['link_url'] ?? $s['image_path'],
            $s['reviewer_name'] ?? '-',
            $s['reject_reason'] ?? '-'
        ]);
    }
    
    fclose($output);
    exit;
}

// Get pending count
$pendingResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_submissions WHERE status = 'pending'");
$pendingCount = $pendingResult->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Excel | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f7fa; min-height: 100vh; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: #fff; padding: 20px 0; z-index: 100;
        }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 20px; font-weight: 600; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.6); margin-top: 4px; }
        .nav-menu { list-style: none; }
        .nav-menu a {
            display: flex; align-items: center; gap: 12px; padding: 14px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1); color: #fff; border-left-color: #667eea;
        }
        .nav-menu a i { width: 20px; text-align: center; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge.pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        
        .main-content { margin-left: 260px; padding: 24px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .top-bar h1 { font-size: 24px; color: #1a1a2e; }
        
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
        .card h3 {
            font-size: 18px;
            color: #1a1a2e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .export-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .export-card {
            background: #f9f9f9;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s;
        }
        .export-card:hover {
            background: #f0f0f0;
        }
        .export-card .icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #2ed573 0%, #1abc9c 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            color: #fff;
        }
        .export-card h4 {
            font-size: 16px;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .export-card p {
            font-size: 13px;
            color: #999;
            margin-bottom: 16px;
        }
        
        .form-row {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
        .form-row input, .form-row select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-success {
            background: linear-gradient(135deg, #2ed573 0%, #1abc9c 100%);
            color: #fff;
        }
        .btn-success:hover { opacity: 0.9; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .export-options { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🎯 Incentive</h2>
            <p>Admin Panel</p>
        </div>
        <ul class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="approve.php">
                <i class="fas fa-clipboard-check"></i> ตรวจสอบงาน
                <?php if ($pendingCount > 0): ?>
                <span class="badge pending" style="margin-left: auto;"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="payroll.php"><i class="fas fa-calculator"></i> คำนวณเงิน</a>
            <a href="trophy.php"><i class="fas fa-trophy"></i> Trophy Bonus</a>
            <a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a>
            <a href="export.php" class="active"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="../checklist.php" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-arrow-left"></i> กลับหน้า Checklist
            </a>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-file-excel"></i> Export Excel</h1>
        </div>
        
        <div class="card">
            <h3><i class="fas fa-download"></i> ดาวน์โหลดข้อมูล</h3>
            <p>เลือกประเภทรายงานที่ต้องการ Export เพื่อนำไปใช้กับระบบ Payroll</p>
            
            <div class="export-options">
                <!-- Payroll Summary -->
                <div class="export-card">
                    <div class="icon"><i class="fas fa-calculator"></i></div>
                    <h4>สรุป Payroll รายสาขา</h4>
                    <p>รายงานคะแนนและเงิน Incentive แยกตามสาขา</p>
                    <form method="GET" class="form-row">
                        <input type="month" name="month" value="<?= $yearMonth ?>" required>
                        <input type="hidden" name="download" value="1">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> ดาวน์โหลด
                        </button>
                    </form>
                </div>
                
                <!-- Detail Report -->
                <div class="export-card">
                    <div class="icon"><i class="fas fa-list-alt"></i></div>
                    <h4>รายละเอียดการส่งงาน</h4>
                    <p>รายการงานที่ส่งทั้งหมดแยกตามวัน/คน</p>
                    <form method="GET" class="form-row">
                        <input type="month" name="month" value="<?= $yearMonth ?>" required>
                        <select name="status">
                            <option value="">ทุกสถานะ</option>
                            <option value="approved">อนุมัติแล้ว</option>
                            <option value="pending">รอตรวจสอบ</option>
                            <option value="rejected">ไม่อนุมัติ</option>
                        </select>
                        <input type="hidden" name="download_detail" value="1">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> ดาวน์โหลด
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="card" style="background: #f9f9f9;">
            <h4 style="margin-bottom: 12px;"><i class="fas fa-info-circle"></i> วิธีใช้งาน</h4>
            <ul style="margin-left: 20px; color: #666; line-height: 1.8;">
                <li>ไฟล์ที่ Export จะเป็น CSV ซึ่งสามารถเปิดด้วย Excel ได้ทันที</li>
                <li>รองรับภาษาไทยแบบ UTF-8</li>
                <li><strong>สรุป Payroll</strong> - ใช้สำหรับส่งให้ฝ่ายบัญชี/HR คำนวณเงินเดือน</li>
                <li><strong>รายละเอียดการส่งงาน</strong> - ใช้สำหรับตรวจสอบย้อนหลังหรือ Audit</li>
            </ul>
        </div>
    </div>
</body>
</html>
