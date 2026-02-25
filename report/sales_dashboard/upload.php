<?php
/**
 * Upload CSV Page
 */
require_once 'config.php';

$message = '';
$messageType = '';
$debug = [];

// Test database connection
try {
    $pdo = getDB();
    $debug[] = "✅ Database connected";
} catch (Exception $e) {
    $debug[] = "❌ Database error: " . $e->getMessage();
}

// Check uploads directory
if (is_writable(UPLOAD_DIR)) {
    $debug[] = "✅ Uploads directory writable: " . UPLOAD_DIR;
} else {
    $debug[] = "❌ Uploads directory NOT writable: " . UPLOAD_DIR;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug[] = "📥 POST request received";
    $debug[] = "FILES: " . print_r($_FILES, true);
    
    $uploadedFiles = [];
    
    // Check Payment file
    if (isset($_FILES['payment_file'])) {
        if ($_FILES['payment_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFiles['payment'] = $_FILES['payment_file'];
            $debug[] = "✅ Payment file received: " . $_FILES['payment_file']['name'];
        } else {
            $debug[] = "❌ Payment file error code: " . $_FILES['payment_file']['error'];
        }
    }
    
    // Check Sales file
    if (isset($_FILES['sales_file'])) {
        if ($_FILES['sales_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFiles['sales'] = $_FILES['sales_file'];
            $debug[] = "✅ Sales file received: " . $_FILES['sales_file']['name'];
        } else {
            $debug[] = "❌ Sales file error code: " . $_FILES['sales_file']['error'];
        }
    }
    
    if (empty($uploadedFiles)) {
        $message = 'กรุณาเลือกไฟล์อย่างน้อย 1 ไฟล์';
        $messageType = 'danger';
    } else {
        // Save files
        $savedFiles = [];
        foreach ($uploadedFiles as $type => $file) {
            $filename = $type . '_' . date('YmdHis') . '.csv';
            $filepath = UPLOAD_DIR . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $savedFiles[$type] = $filename;
                $debug[] = "✅ Saved: $filepath";
            } else {
                $debug[] = "❌ Failed to save: $filepath";
            }
        }
        
        if (!empty($savedFiles)) {
            $params = http_build_query($savedFiles);
            $debug[] = "🔄 Redirecting to: import.php?$params";
            
            // Redirect
            header("Location: import.php?$params");
            exit;
        } else {
            $message = 'ไม่สามารถบันทึกไฟล์ได้ กรุณาตรวจสอบ Permission ของโฟลเดอร์ uploads/';
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload CSV - Sales Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-graph-up"></i> Sales Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="upload.php">
                    <i class="bi bi-cloud-upload"></i> Upload CSV
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <!-- Debug Info -->
                <?php if (!empty($debug)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">🔧 Debug Info</h5>
                    </div>
                    <div class="card-body">
                        <pre style="font-size: 12px; margin: 0;"><?php echo implode("\n", $debug); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-cloud-upload"></i> Upload CSV Files</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <!-- Payment CSV -->
                                <div class="col-md-6 mb-3">
                                    <div class="upload-box">
                                        <i class="bi bi-credit-card display-4 text-primary"></i>
                                        <h5 class="mt-3">Payment CSV</h5>
                                        <p class="text-muted small">sale_payment_daily*.CSV</p>
                                        <input type="file" name="payment_file" id="paymentFile" 
                                               class="form-control" accept=".csv,.CSV">
                                        <div id="paymentFileName" class="mt-2 text-success small"></div>
                                    </div>
                                </div>
                                
                                <!-- Sales CSV -->
                                <div class="col-md-6 mb-3">
                                    <div class="upload-box">
                                        <i class="bi bi-receipt display-4 text-success"></i>
                                        <h5 class="mt-3">Sale Transaction CSV</h5>
                                        <p class="text-muted small">sale_transaction_daily*.CSV</p>
                                        <input type="file" name="sales_file" id="salesFile" 
                                               class="form-control" accept=".csv,.CSV">
                                        <div id="salesFileName" class="mt-2 text-success small"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-upload"></i> Upload & Import
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg ms-2">
                                    <i class="bi bi-x"></i> ยกเลิก
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> วิธีใช้งาน</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li>Export ไฟล์ CSV จากระบบ POS</li>
                            <li>เลือกไฟล์ <strong>Payment</strong> และ <strong>Sale Transaction</strong></li>
                            <li>กด <strong>Upload & Import</strong></li>
                            <li>ระบบจะคำนวณยอดขายอัตโนมัติ</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('paymentFile').addEventListener('change', function(e) {
        document.getElementById('paymentFileName').textContent = e.target.files[0]?.name || '';
    });
    document.getElementById('salesFile').addEventListener('change', function(e) {
        document.getElementById('salesFileName').textContent = e.target.files[0]?.name || '';
    });
    </script>
</body>
</html>
