<?php
/**
 * Export to Excel (CSV)
 */
require_once 'config.php';

$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? null;

$pdo = getDB();

// Set headers for Excel download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="sales_report_' . ($month ?: $date) . '.csv"');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

if ($month) {
    // Monthly export
    $stmt = $pdo->prepare("
        SELECT * FROM daily_summary 
        WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?
        ORDER BY sale_date
    ");
    $stmt->execute([$month]);
    $data = $stmt->fetchAll();
    
    // Headers
    fputcsv($output, ['วันที่', 'SPD-Offline', 'Pronto-Offline', 'Pronto-Online', 'Freitag', 
                      'Pavement-Online', 'Topo-Offline', 'Topo-Online', 'IZIPIZI', 'Hooga', 
                      'Soup', 'SW19', 'SW19-Lazada', 'รวม Offline', 'รวม Online', 'รวมทั้งหมด']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            formatDate($row['sale_date']),
            $row['spd_offline'],
            $row['pronto_offline'],
            $row['pronto_online'],
            $row['freitag'],
            $row['pavement_online'],
            $row['topo_offline'],
            $row['topo_online'],
            $row['izipizi'],
            $row['hooga'],
            $row['soup'],
            $row['sw19'],
            $row['sw19_lazada'],
            $row['total_offline'],
            $row['total_online'],
            $row['grand_total'],
        ]);
    }
} else {
    // Daily export
    $stmt = $pdo->prepare("SELECT * FROM daily_summary WHERE sale_date = ?");
    $stmt->execute([$date]);
    $summary = $stmt->fetch();
    
    if ($summary) {
        fputcsv($output, ['รายงานยอดขายวันที่', formatDate($date)]);
        fputcsv($output, []);
        fputcsv($output, ['Brand', 'ยอดขาย (บาท)', 'ประเภท']);
        fputcsv($output, ['SPD-Offline', $summary['spd_offline'], 'Offline']);
        fputcsv($output, ['Pronto-Offline', $summary['pronto_offline'], 'Offline']);
        fputcsv($output, ['Pronto-Online', $summary['pronto_online'], 'Online']);
        fputcsv($output, ['Freitag', $summary['freitag'], 'Offline']);
        fputcsv($output, ['Pavement-Online', $summary['pavement_online'], 'Online']);
        fputcsv($output, ['Topo-Offline', $summary['topo_offline'], 'Offline']);
        fputcsv($output, ['Topo-Online', $summary['topo_online'], 'Online']);
        fputcsv($output, ['IZIPIZI', $summary['izipizi'], 'Offline']);
        fputcsv($output, ['Hooga', $summary['hooga'], 'Offline']);
        fputcsv($output, ['Soup', $summary['soup'], 'Offline']);
        fputcsv($output, ['SW19', $summary['sw19'], 'Offline']);
        fputcsv($output, ['SW19-Lazada', $summary['sw19_lazada'], 'Online']);
        fputcsv($output, []);
        fputcsv($output, ['รวม Offline', $summary['total_offline']]);
        fputcsv($output, ['รวม Online', $summary['total_online']]);
        fputcsv($output, ['รวมทั้งหมด', $summary['grand_total']]);
    }
}

fclose($output);
