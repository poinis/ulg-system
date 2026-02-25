<?php
/**
 * Import CSV to Database
 */
require_once 'config.php';

$pdo = getDB();
$results = [];
$saleDate = null;
$debug = [];

$debug[] = "📥 Import started";

// Import Payment CSV
if (isset($_GET['payment'])) {
    $filepath = UPLOAD_DIR . $_GET['payment'];
    if (file_exists($filepath)) {
        $debug[] = "✅ Payment file exists, size: " . filesize($filepath);
        $result = importPaymentCSV($pdo, $filepath, $debug);
        $results['payment'] = $result;
        if ($result['success']) {
            $saleDate = $result['date'];
        }
    } else {
        $results['payment'] = ['success' => false, 'error' => 'File not found'];
    }
}

// Import Sales CSV
if (isset($_GET['sales'])) {
    $filepath = UPLOAD_DIR . $_GET['sales'];
    if (file_exists($filepath)) {
        $debug[] = "✅ Sales file exists, size: " . filesize($filepath);
        $result = importSalesCSV($pdo, $filepath, $debug);
        $results['sales'] = $result;
        if ($result['success']) {
            $saleDate = $result['date'];
        }
    } else {
        $results['sales'] = ['success' => false, 'error' => 'File not found'];
    }
}

// Calculate summary
if ($saleDate) {
    $debug[] = "🧮 Calculating summary for: $saleDate";
    require_once 'calculate.php';
    $calcResult = calculateDailySummary($pdo, $saleDate);
    $results['calculation'] = $calcResult;
}

function importPaymentCSV($pdo, $filepath, &$debug) {
    try {
        $content = file_get_contents($filepath);
        $content = @mb_convert_encoding($content, 'UTF-8', 'UTF-16');
        $lines = explode("\n", $content);
        $debug[] = "Total lines: " . count($lines);
        
        if (count($lines) < 2) {
            throw new Exception("File appears empty");
        }
        
        $header = str_getcsv(trim($lines[0]), ",", "\"", "");
        $colMap = array_flip($header);
        $debug[] = "Columns: " . count($header);
        
        $saleDate = null;
        $rowCount = 0;
        $firstRow = true;
        
        $stmt = $pdo->prepare("
            INSERT INTO payments (sale_date, store, register, payment_method, bill_number, 
                                  customer, first_name, last_name, amount, ticket_cancelled, 
                                  sales_rep, posting_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            $row = str_getcsv($line, ",", "\"", "");
            if (count($row) < 10) continue;
            
            $dateStr = $row[$colMap['GPE_DATEPIECE']] ?? '';
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $dateStr, $m)) {
                $saleDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            
            if (!$saleDate) continue;
            
            if ($firstRow) {
                $pdo->prepare("DELETE FROM payments WHERE sale_date = ?")->execute([$saleDate]);
                $debug[] = "Cleared old data for: $saleDate";
                $firstRow = false;
            }
            
            $amount = floatval(str_replace([',', ' '], '', $row[$colMap['GPE_MONTANTECHE']] ?? 0));
            
            $stmt->execute([
                $saleDate,
                intval($row[$colMap['GP_ETABLISSEMENT']] ?? 0),
                $row[$colMap['GPE_CAISSE']] ?? '',
                $row[$colMap['C5']] ?? '',
                intval($row[$colMap['GPE_NUMERO']] ?? 0),
                $row[$colMap['GPE_TIERS']] ?? '',
                $row[$colMap['T_PRENOM']] ?? '',
                $row[$colMap['T_LIBELLE']] ?? '',
                $amount,
                $row[$colMap['GP_TICKETANNULE']] ?? '',
                $row[$colMap['GP_REPRESENTANT']] ?? '',
                $row[$colMap['C17']] ?? '',
            ]);
            $rowCount++;
        }
        
        $debug[] = "✅ Payment imported: $rowCount rows";
        
        $pdo->prepare("
            INSERT INTO import_logs (import_date, sale_date, file_type, filename, rows_imported, status)
            VALUES (NOW(), ?, 'payment', ?, ?, 'success')
        ")->execute([$saleDate, basename($filepath), $rowCount]);
        
        return ['success' => true, 'rows' => $rowCount, 'date' => $saleDate];
        
    } catch (Exception $e) {
        $debug[] = "❌ Error: " . $e->getMessage();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function importSalesCSV($pdo, $filepath, &$debug) {
    try {
        $content = file_get_contents($filepath);
        $content = @mb_convert_encoding($content, 'UTF-8', 'UTF-16');
        $lines = explode("\n", $content);
        $debug[] = "Total lines: " . count($lines);
        
        if (count($lines) < 2) {
            throw new Exception("File appears empty");
        }
        
        $header = str_getcsv(trim($lines[0]), ",", "\"", "");
        $colMap = array_flip($header);
        $debug[] = "Columns: " . count($header);
        
        $saleDate = null;
        $rowCount = 0;
        $firstRow = true;
        
        $stmt = $pdo->prepare("
            INSERT INTO sales (sale_date, warehouse, bill_number, line_number, item_code, 
                               item_barcode, item_description, brand_code, brand, item_group, 
                               class, season, size, qty, unit_price, discount, 
                               total_incl_tax, total_excl_tax, sales_rep)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            $row = str_getcsv($line, ",", "\"", "");
            if (count($row) < 30) continue;
            
            $dateStr = $row[$colMap['GL_DATEPIECE']] ?? '';
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $dateStr, $m)) {
                $saleDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            
            if (!$saleDate) continue;
            
            if ($firstRow) {
                $pdo->prepare("DELETE FROM sales WHERE sale_date = ?")->execute([$saleDate]);
                $debug[] = "Cleared old data for: $saleDate";
                $firstRow = false;
            }
            
            $totalTTC = floatval(str_replace([',', ' '], '', $row[$colMap['GL_TOTALTTC']] ?? 0));
            $totalHT = floatval(str_replace([',', ' '], '', $row[$colMap['GL_TOTALHT']] ?? 0));
            $unitPrice = floatval(str_replace([',', ' '], '', $row[$colMap['GL_PUTTCBASE']] ?? 0));
            $discount = floatval(str_replace([',', ' '], '', $row[$colMap['GL_REMISELIBRE2']] ?? 0));
            
            $stmt->execute([
                $saleDate,
                intval($row[$colMap['GL_ETABLISSEMENT']] ?? 0),
                intval($row[$colMap['GL_NUMERO']] ?? 0),
                intval($row[$colMap['GL_NUMLIGNE']] ?? 0),
                $row[$colMap['GL_CODEARTICLE']] ?? '',
                $row[$colMap['GL_REFARTBARRE']] ?? '',
                $row[$colMap['GL_LIBELLE']] ?? '',
                $row[$colMap['C21']] ?? '',
                $row[$colMap['C22']] ?? '',
                $row[$colMap['C24']] ?? '',
                $row[$colMap['C25']] ?? '',
                $row[$colMap['C23']] ?? '',
                $row[$colMap['LIBDIM2']] ?? '',
                intval($row[$colMap['GL_QTEFACT']] ?? 0),
                $unitPrice,
                $discount,
                $totalTTC,
                $totalHT,
                $row[$colMap['GL_REPRESENTANT']] ?? '',
            ]);
            $rowCount++;
        }
        
        $debug[] = "✅ Sales imported: $rowCount rows";
        
        $pdo->prepare("
            INSERT INTO import_logs (import_date, sale_date, file_type, filename, rows_imported, status)
            VALUES (NOW(), ?, 'sales', ?, ?, 'success')
        ")->execute([$saleDate, basename($filepath), $rowCount]);
        
        return ['success' => true, 'rows' => $rowCount, 'date' => $saleDate];
        
    } catch (Exception $e) {
        $debug[] = "❌ Error: " . $e->getMessage();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Results - Sales Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-graph-up"></i> Sales Dashboard</a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">🔧 Debug</div>
                    <div class="card-body">
                        <pre style="font-size: 11px; margin: 0;"><?= implode("\n", $debug) ?></pre>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-check-circle"></i> Import Results</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($results['payment'])): ?>
                        <div class="alert <?= $results['payment']['success'] ? 'alert-success' : 'alert-danger' ?>">
                            <strong>Payment:</strong>
                            <?= $results['payment']['success'] 
                                ? "✅ {$results['payment']['rows']} rows (" . formatDate($results['payment']['date']) . ")"
                                : "❌ {$results['payment']['error']}" ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($results['sales'])): ?>
                        <div class="alert <?= $results['sales']['success'] ? 'alert-success' : 'alert-danger' ?>">
                            <strong>Sales:</strong>
                            <?= $results['sales']['success'] 
                                ? "✅ {$results['sales']['rows']} rows (" . formatDate($results['sales']['date']) . ")"
                                : "❌ {$results['sales']['error']}" ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($results['calculation'])): ?>
                        <div class="alert alert-info">
                            <strong>คำนวณ:</strong>
                            <?= $results['calculation']['success'] 
                                ? "✅ รวม: <strong>" . formatNumber($results['calculation']['grand_total']) . " บาท</strong>"
                                : "❌ Error" ?>
                        </div>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <?php if ($saleDate): ?>
                            <a href="index.php?date=<?= $saleDate ?>" class="btn btn-primary btn-lg">
                                <i class="bi bi-eye"></i> ดูผลลัพธ์
                            </a>
                            <?php endif; ?>
                            <a href="upload.php" class="btn btn-secondary btn-lg ms-2">Upload เพิ่ม</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>