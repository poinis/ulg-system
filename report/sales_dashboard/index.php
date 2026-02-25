<?php
/**
 * Sales Dashboard - Main Page
 */
require_once 'config.php';

$pdo = getDB();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Get daily summary
$stmt = $pdo->prepare("SELECT * FROM daily_summary WHERE sale_date = ?");
$stmt->execute([$selectedDate]);
$dailySummary = $stmt->fetch();

// Get monthly data
$stmt = $pdo->prepare("
    SELECT * FROM daily_summary 
    WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?
    ORDER BY sale_date
");
$stmt->execute([$selectedMonth]);
$monthlyData = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-graph-up"></i> Sales Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="upload.php"><i class="bi bi-cloud-upload"></i> Upload</a>
                <a class="nav-link" href="targets.php"><i class="bi bi-table"></i> Targets</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Date Selector -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-auto">
                                <label class="col-form-label">วันที่:</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" name="date" class="form-control" 
                                       value="<?= $selectedDate ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-auto">
                                <label class="col-form-label">เดือน:</label>
                            </div>
                            <div class="col-auto">
                                <input type="month" name="month" class="form-control" 
                                       value="<?= $selectedMonth ?>" onchange="this.form.submit()">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <?php if ($dailySummary): ?>
                <button class="btn btn-success" onclick="sendEmail('<?= $selectedDate ?>')">
                    <i class="bi bi-envelope"></i> ส่ง Email
                </button>
                <a href="export.php?date=<?= $selectedDate ?>" class="btn btn-primary">
                    <i class="bi bi-download"></i> Export
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($dailySummary): ?>
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6>รวม Offline</h6>
                        <h3><?= number_format($dailySummary['total_offline'], 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>รวม Online</h6>
                        <h3><?= number_format($dailySummary['total_online'], 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6>รวมทั้งหมด</h6>
                        <h3><?= number_format($dailySummary['grand_total'], 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h6>วันที่</h6>
                        <h3><?= date('d/m/Y', strtotime($dailySummary['sale_date'])) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Brand Summary -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">สรุปยอดขายแต่ละ Brand - <?= date('d/m/Y', strtotime($selectedDate)) ?></h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>Brand</th><th class="text-end">ยอดขาย</th><th>ประเภท</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>🏪 SPD-Offline</td><td class="text-end"><?= number_format($dailySummary['spd_offline'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🏪 Pronto-Offline</td><td class="text-end"><?= number_format($dailySummary['pronto_offline'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🌐 Pronto-Online</td><td class="text-end"><?= number_format($dailySummary['pronto_online'], 0) ?></td><td><span class="badge bg-success">Online</span></td></tr>
                        <tr><td>🏪 Freitag</td><td class="text-end"><?= number_format($dailySummary['freitag'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🌐 Pavement</td><td class="text-end"><?= number_format($dailySummary['pavement_online'], 0) ?></td><td><span class="badge bg-success">Online</span></td></tr>
                        <tr><td>🏪 Topo-Offline</td><td class="text-end"><?= number_format($dailySummary['topo_offline'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🌐 Topo-Online</td><td class="text-end"><?= number_format($dailySummary['topo_online'], 0) ?></td><td><span class="badge bg-success">Online</span></td></tr>
                        <tr><td>🏪 IZIPIZI</td><td class="text-end"><?= number_format($dailySummary['izipizi'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🏪 Hooga</td><td class="text-end"><?= number_format($dailySummary['hooga'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🏪 Soup</td><td class="text-end"><?= number_format($dailySummary['soup'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🏪 SW19</td><td class="text-end"><?= number_format($dailySummary['sw19'], 0) ?></td><td><span class="badge bg-primary">Offline</span></td></tr>
                        <tr><td>🌐 SW19-Lazada</td><td class="text-end"><?= number_format($dailySummary['sw19_lazada'], 0) ?></td><td><span class="badge bg-success">Online</span></td></tr>
                    </tbody>
                    <tfoot class="table-warning">
                        <tr><th>รวม Offline</th><th class="text-end"><?= number_format($dailySummary['total_offline'], 0) ?></th><th></th></tr>
                        <tr><th>รวม Online</th><th class="text-end"><?= number_format($dailySummary['total_online'], 0) ?></th><th></th></tr>
                        <tr class="table-dark"><th>รวมทั้งหมด</th><th class="text-end"><?= number_format($dailySummary['grand_total'], 0) ?></th><th></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> 
            ไม่พบข้อมูล กรุณา <a href="upload.php">Upload CSV</a>
        </div>
        <?php endif; ?>

        <!-- Monthly Table -->
        <?php if (!empty($monthlyData)): ?>
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">ยอดขายรายเดือน - <?= date('F Y', strtotime($selectedMonth . '-01')) ?></h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>วัน</th><th class="text-end">SPD</th><th class="text-end">Pronto</th>
                                <th class="text-end">Pronto-On</th><th class="text-end">Freitag</th>
                                <th class="text-end">Topo</th><th class="text-end">Topo-On</th>
                                <th class="text-end">IZIPIZI</th><th class="text-end">Hooga</th>
                                <th class="text-end">Soup</th><th class="text-end">SW19</th>
                                <th class="text-end">SW19-Lzd</th><th class="text-end bg-info">รวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyData as $row): ?>
                            <tr>
                                <td><?= date('d', strtotime($row['sale_date'])) ?></td>
                                <td class="text-end"><?= number_format($row['spd_offline'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['pronto_offline'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['pronto_online'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['freitag'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['topo_offline'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['topo_online'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['izipizi'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['hooga'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['soup'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['sw19'], 0) ?></td>
                                <td class="text-end"><?= number_format($row['sw19_lazada'], 0) ?></td>
                                <td class="text-end bg-light fw-bold"><?= number_format($row['grand_total'], 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function sendEmail(date) {
        if (confirm('ส่ง Email รายงานวันที่ ' + date + ' ?')) {
            fetch('send_email.php?date=' + date)
                .then(r => r.json())
                .then(data => alert(data.success ? '✅ ส่งเรียบร้อย!' : '❌ ' + data.message))
                .catch(err => alert('❌ ' + err));
        }
    }
    </script>
</body>
</html>