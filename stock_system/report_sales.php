<?php
/**
 * report_sales.php — Report ยอดขาย สไตล์ Auto___Ai.xlsx
 * ยอดขาย: จาก daily_sales  |  Stock: จาก replenish_stock
 * แสดง: สาขา | Nov | Dec | Jan | ขาย(ชิ้น) | Rate/วัน | Stock | Need 14d | Diff | Holding | WC | สถานะ
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/SalesAnalytics.php';

$db       = Database::getInstance();
$uploadId = isset($_GET['upload_id']) ? (int)$_GET['upload_id'] : null;
$sa       = new SalesAnalytics($uploadId);
$uploadId = $sa->uploadId;
$upload   = $sa->getUploadInfo();

// ── SKU list (มียอดขายใน daily_sales ช่วงนี้)
$barcodeStmt = $db->prepare(
    "SELECT DISTINCT rs.barcode, rp.product_name,
            COALESCE(SUM(ds.qty),0) AS total_sold
     FROM replenish_stock rs
     JOIN replenish_products rp ON rs.barcode = rp.barcode
     JOIN stores st ON rs.store_code_new = st.store_code_new
     LEFT JOIN daily_sales ds
         ON ds.line_barcode = rs.barcode
         AND ds.brand = :brand
         AND ds.qty > 0
         AND ds.sale_date > DATE_SUB(:ud, INTERVAL :rd DAY)
         AND ds.sale_date <= :ud2
         AND ds.store_code = st.store_code
     WHERE rs.upload_id = :uid AND rp.family = :fam
       AND st.store_type NOT IN ('DC','WEBSITE')
     GROUP BY rs.barcode, rp.product_name
     ORDER BY total_sold DESC"
);
$barcodeStmt->execute([
    ':uid'   => $uploadId, ':fam'   => CSV_FAMILY,
    ':brand' => DS_BRAND,  ':ud'    => $upload['upload_date'] ?? date('Y-m-d'),
    ':ud2'   => $upload['upload_date'] ?? date('Y-m-d'),
    ':rd'    => $upload['rate_days'] ?? DEFAULT_RATE_DAYS,
]);
$allBarcodes = $barcodeStmt->fetchAll();

$selectedBC = $_GET['barcode'] ?? ($allBarcodes[0]['barcode'] ?? '');
$selectedName = '';
foreach ($allBarcodes as $b) {
    if ($b['barcode'] === $selectedBC) { $selectedName = $b['product_name']; break; }
}

// ── Ranking + Monthly breakdown
$ranking     = $selectedBC ? $sa->getStoreRanking($selectedBC, REFILL_TARGET_DAYS) : [];
$companyHold = $selectedBC ? $sa->getCompanyHolding($selectedBC) : [];
$dcStock     = $selectedBC ? $sa->getDCStock($selectedBC) : 0;
$monthly     = $selectedBC ? $sa->getMonthlyBreakdown($selectedBC, 3) : ['months'=>[],'by_store'=>[]];
$months      = $monthly['months'];
$mByStore    = $monthly['by_store'];

$totalSold  = array_sum(array_column($ranking, 'qty_sold'));
$totalStock = array_sum(array_column($ranking, 'stock'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Report ยอดขาย — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--bg:#0d1117;--surf:#161b22;--surf2:#1f2937;--bdr:#21262d;--bdr2:#30363d;--blue:#79c0ff;--green:#7ee787;--red:#ffa198;--yellow:#f0c14b;--purple:#d2a8ff;}
body{background:var(--bg);color:#f0f6fc;font-family:'Segoe UI',sans-serif;font-size:.875rem;}
.navbar{background:var(--surf)!important;border-bottom:1px solid var(--bdr);}
.navbar-brand{color:var(--blue)!important;font-weight:700;}
.nav-link{color:#b1bac4!important;}.nav-link:hover,.nav-link.active{color:#ffffff!important;}
.card{background:var(--surf);border:1px solid var(--bdr);border-radius:12px;}
.card-header{background:var(--surf2);border-bottom:1px solid var(--bdr);border-radius:12px 12px 0 0!important;padding:.7rem 1.1rem;color:#f0f6fc;}
.form-control,.form-select{background:var(--bg);border:1px solid var(--bdr2);color:#f0f6fc;border-radius:8px;}
.form-control:focus,.form-select:focus{background:var(--bg);border-color:var(--blue);color:#f0f6fc;box-shadow:0 0 0 3px rgba(121,192,255,.15);}
/* Table */
.rep-table{width:100%;border-collapse:collapse;font-size:.8rem;}
.rep-table th{background:var(--surf2);color:#d0d7de;text-align:center;padding:.5rem .6rem;border:1px solid var(--bdr2);font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;position:sticky;top:0;z-index:1;}
.rep-table td{border:1px solid var(--bdr);padding:.45rem .6rem;vertical-align:middle;color:#e6edf3;}
.rep-table tbody tr:hover{background:rgba(255,255,255,.05);}
.rep-table tfoot td{background:var(--surf2);font-weight:700;border-top:2px solid var(--bdr2);color:#f0f6fc;}
.table-wrap{max-height:65vh;overflow-y:auto;border-radius:0 0 12px 12px;}
.rep-table th:first-child{text-align:left;}
.rep-table td:first-child{text-align:left;}
/* Diff */
.diff-over{color:#7ee787;font-weight:600}.diff-short{color:#ffa198;font-weight:700}.diff-ok{color:#79c0ff;font-weight:600}
/* Holding */
.hold-warn{color:#ffa198;font-weight:600}.hold-ok{color:#7ee787}
/* Status */
.st-critical{background:rgba(255,161,152,.2);color:#ffa198;border:1px solid rgba(255,161,152,.4);border-radius:4px;padding:.12rem .45rem;font-size:.7rem;font-weight:700}
.st-warning{background:rgba(240,193,75,.2);color:#f0c14b;border:1px solid rgba(240,193,75,.4);border-radius:4px;padding:.12rem .45rem;font-size:.7rem;font-weight:700}
.st-ok{background:rgba(121,192,255,.18);color:#79c0ff;border:1px solid rgba(121,192,255,.35);border-radius:4px;padding:.12rem .45rem;font-size:.7rem;font-weight:700}
.st-good{background:rgba(126,231,135,.18);color:#7ee787;border:1px solid rgba(126,231,135,.35);border-radius:4px;padding:.12rem .45rem;font-size:.7rem;font-weight:700}
.st-overstock{background:rgba(210,168,255,.18);color:#d2a8ff;border:1px solid rgba(210,168,255,.35);border-radius:4px;padding:.12rem .45rem;font-size:.7rem;font-weight:700}
/* Info bar */
.info-bar{background:var(--surf2);border:1px solid var(--bdr2);border-radius:8px;padding:.65rem 1rem;font-size:.8rem;}
.info-item{display:inline-block;margin-right:1.5rem;margin-bottom:.3rem;}
.info-label{color:#b1bac4;font-size:.72rem}
.info-value{font-weight:600;color:#f0f6fc;}
/* Store note */
.store-note{color:#8b949e;font-size:.7rem;font-style:italic;}
/* WC bar */
.wc-bar{width:50px;height:5px;border-radius:3px;background:#21262d;display:inline-block;vertical-align:middle;overflow:hidden;margin-left:4px;}
.wc-fill{height:100%;border-radius:3px;}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg mb-3">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php<?= $uploadId ? '?upload_id='.$uploadId : '' ?>">
      <i class="fa fa-boxes-stacked me-2"></i><?= APP_NAME ?>
    </a>
    <div class="navbar-nav ms-auto gap-3">
      <a class="nav-link" href="index.php<?= $uploadId ? '?upload_id='.$uploadId : '' ?>">
        <i class="fa fa-chart-bar me-1"></i>Dashboard
      </a>
      <a class="nav-link" href="upload.php"><i class="fa fa-upload me-1"></i>Upload</a>
      <a class="nav-link active" href="#"><i class="fa fa-chart-line me-1"></i>Report ยอดขาย</a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h5 class="mb-0 fw-bold"><i class="fa fa-chart-line me-2 text-info"></i>Report ยอดขาย — TOPOLOGIE</h5>
      <p class="text-muted small mb-0">
        <?php if ($upload): ?>
        Stock ณ <?= $upload['upload_date'] ?> |
        ยอดขายจาก daily_sales ย้อนหลัง <strong><?= $upload['rate_days'] ?> วัน</strong>
        <?php endif; ?>
        <?php if ($companyHold && $selectedBC): ?>
         | Company Holding:
         <strong class="<?= $companyHold['holding_days'] < 30 ? 'text-danger' : '' ?>">
           <?= $companyHold['holding_days'] >= 999 ? '∞' : $companyHold['holding_days'] ?> วัน
         </strong>
         <?= $companyHold['can_xfer_all'] ? '<span class="badge ms-1" style="background:rgba(248,81,73,.2);color:#f85149;font-size:.68rem">โอนออกหมดได้</span>' : '' ?>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($selectedBC): ?>
    <a href="api.php?action=export&type=stock&upload_id=<?= $uploadId ?>&barcode=<?= urlencode($selectedBC) ?>"
       class="btn btn-sm" style="background:rgba(88,166,255,.1);border:1px solid rgba(88,166,255,.3);color:#58a6ff;border-radius:6px">
      <i class="fa fa-file-excel me-1"></i>Export CSV
    </a>
    <?php endif; ?>
  </div>

  <!-- SKU Selector -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
        <?php if ($uploadId): ?><input type="hidden" name="upload_id" value="<?= $uploadId ?>"><?php endif; ?>
        <label class="text-muted small fw-semibold text-nowrap">📦 SKU:</label>
        <select name="barcode" class="form-select" style="max-width:520px" onchange="this.form.submit()">
          <?php foreach ($allBarcodes as $b): ?>
          <option value="<?= $b['barcode'] ?>" <?= $b['barcode']==$selectedBC?'selected':'' ?>>
            <?= $b['barcode'] ?> — <?= htmlspecialchars(substr($b['product_name']??'',0,50)) ?>
            (ขาย: <?= $b['total_sold'] ?>)
          </option>
          <?php endforeach; ?>
          <?php if (!$allBarcodes): ?><option>— ไม่มีข้อมูล —</option><?php endif; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($selectedBC && !empty($ranking)): ?>

  <!-- Info bar -->
  <div class="info-bar mb-3">
    <div class="info-item">
      <div class="info-label">Barcode</div>
      <div class="info-value"><code style="color:#58a6ff"><?= $selectedBC ?></code></div>
    </div>
    <div class="info-item">
      <div class="info-label">Product</div>
      <div class="info-value"><?= htmlspecialchars(substr($selectedName,0,60)) ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">Rate Period</div>
      <div class="info-value"><?= $upload['rate_days'] ?> วัน ← daily_sales</div>
    </div>
    <div class="info-item">
      <div class="info-label">ขายรวม</div>
      <div class="info-value"><?= number_format($totalSold) ?> ชิ้น</div>
    </div>
    <div class="info-item">
      <div class="info-label">Stock รวม (สาขา)</div>
      <div class="info-value"><?= number_format($totalStock) ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">DC Stock</div>
      <div class="info-value"><?= number_format($dcStock) ?> ชิ้น</div>
    </div>
    <?php if ($companyHold): ?>
    <div class="info-item">
      <div class="info-label">Company Holding</div>
      <div class="info-value">
        <?php $hd = $companyHold['holding_days']; ?>
        <span class="<?= $hd < 30 ? 'hold-warn' : 'hold-ok' ?>">
          <?= $hd >= 999 ? '∞' : $hd ?> วัน
          <?= $hd < 30 ? '← โอนออกหมดได้' : '' ?>
        </span>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Report Table -->
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="fw-semibold" style="font-size:.85rem">
        <i class="fa fa-table me-2"></i>รายละเอียดต่อสาขา — เรียงตาม Rate/วัน สูงสุด
      </span>
      <span class="text-muted small"><?= count($ranking) ?> สาขา</span>
    </div>
    <div class="table-wrap">
      <table class="rep-table">
        <thead>
          <tr>
            <th style="text-align:left">สาขา</th>
            <?php foreach ($months as $m): ?>
            <th><?= htmlspecialchars($m) ?></th>
            <?php endforeach; ?>
            <?php if (empty($months)): ?>
            <th colspan="3" style="color:#484f58">Monthly (N/A)</th>
            <?php endif; ?>
            <th>ขาย (ชิ้น)</th>
            <th>Rate/วัน</th>
            <th>Stock</th>
            <th>Need 14d</th>
            <th>Diff</th>
            <th>Holding</th>
            <th>Week Cover</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ranking as $s):
            $sm        = $mByStore[$s['store_code_new']] ?? [];
            $wc        = $s['week_cover'];
            $wcDisp    = $wc >= 999 ? '∞' : number_format($wc,1).'w';
            $wcColor   = ['critical'=>'#f85149','warning'=>'#d29922','ok'=>'#58a6ff','good'=>'#3fb950','overstock'=>'#bc8cff'][$s['status']] ?? '#8b949e';
            $diffCls   = $s['diff'] < 0 ? 'diff-short' : ($s['diff'] > 0 ? 'diff-over' : 'diff-ok');
            $holdDisp  = $s['holding_days'] >= 999 ? '∞' : $s['holding_days'].'d';
            $holdCls   = $s['holding_days'] < 30 ? 'hold-warn' : 'hold-ok';
            $wcPct     = min(100, $wc >= 999 ? 100 : ($wc / 4 * 100));
            $stLabel   = ['critical'=>'🚨 เติมด่วน','warning'=>'⚠️ เติมทันที','ok'=>'✅ ปกติ','good'=>'🟢 ดี','overstock'=>'⛔ เกิน'][$s['status']] ?? $s['status'];
          ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($s['store_short']) ?></strong>
              <?php if ($s['note']): ?><br><span class="store-note"><?= htmlspecialchars($s['note']) ?></span><?php endif; ?>
            </td>
            <?php if (!empty($months)): ?>
              <?php foreach ($months as $m): ?>
              <td class="text-center"><?= $sm[$m] ?? '—' ?></td>
              <?php endforeach; ?>
            <?php else: ?>
              <td colspan="3" class="text-center" style="color:#484f58;font-size:.7rem">ดูใน daily_sales</td>
            <?php endif; ?>
            <td class="text-center fw-semibold"><?= number_format($s['qty_sold']) ?></td>
            <td class="text-center"><?= number_format($s['daily_rate'],2) ?></td>
            <td class="text-center fw-semibold"><?= number_format($s['stock']) ?></td>
            <td class="text-center"><?= number_format($s['need_qty']) ?></td>
            <td class="text-center fw-semibold <?= $diffCls ?>"><?= htmlspecialchars($s['diff_label']) ?></td>
            <td class="text-center <?= $holdCls ?>"><?= $holdDisp ?></td>
            <td class="text-center">
              <span style="color:<?= $wcColor ?>;font-weight:600"><?= $wcDisp ?></span>
              <span class="wc-bar"><span class="wc-fill" style="width:<?= $wcPct ?>%;background:<?= $wcColor ?>"></span></span>
            </td>
            <td class="text-center"><span class="st-<?= $s['status'] ?>"><?= $stLabel ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td><strong>รวม</strong></td>
            <?php foreach ($months as $m): ?>
            <td class="text-center">
              <strong><?= array_sum(array_column(array_map(fn($ms) => [$ms[$m] ?? 0], array_values($mByStore)), 0)) ?></strong>
            </td>
            <?php endforeach; ?>
            <?php if (empty($months)): ?><td colspan="3"></td><?php endif; ?>
            <td class="text-center"><strong><?= number_format($totalSold) ?></strong></td>
            <td class="text-center"><strong><?= number_format(array_sum(array_column($ranking,'daily_rate')),2) ?></strong></td>
            <td class="text-center"><strong><?= number_format($totalStock) ?></strong></td>
            <td colspan="5"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <?php elseif ($uploadId): ?>
  <div class="text-center py-5 text-muted">
    <i class="fa fa-chart-bar fa-3x mb-3 d-block"></i>
    <p>เลือก SKU จาก dropdown ด้านบน</p>
  </div>
  <?php else: ?>
  <div class="text-center py-5 text-muted">
    <i class="fa fa-upload fa-3x mb-3 d-block"></i>
    <p>กรุณา <a href="upload.php" class="text-info">Upload CSV</a> ก่อน</p>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>