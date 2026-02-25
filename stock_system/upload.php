<?php
/**
 * upload.php — Import Stock CSV from CEGID
 *   - กรองเฉพาะ brand = CSV_BRAND (TOPOLOGIE)
 *   - บันทึกเฉพาะ Physical (Stock On Hand)
 *   - Upload ใหม่ → ลบข้อมูล replenish เก่าทั้งหมดก่อน
 *   - ยอดขายดึงจาก daily_sales (ไม่เก็บจาก CSV)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/SalesAnalytics.php';

$db = Database::getInstance();
$msg = $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file       = $_FILES['csv_file'];
    $uploadDate = $_POST['upload_date']  ?? date('Y-m-d');
    $rateDays   = max(1, (int)($_POST['rate_days'] ?? DEFAULT_RATE_DAYS));
    $note       = trim($_POST['note'] ?? '');

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = '❌ Upload error: ' . $file['error'];
        $msgType = 'danger';
    } else {
        try {
            // ─── Save file ────────────────────────────────
            $filename = 'stock_' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9._-]/i','_', basename($file['name']));
            $dest     = UPLOAD_DIR . $filename;
            move_uploaded_file($file['tmp_name'], $dest);

            // ─── Detect encoding (UTF-16 LE / UTF-8) ─────
            $raw = file_get_contents($dest);
            $enc = mb_detect_encoding($raw, ['UTF-16LE','UTF-16BE','UTF-8'], true);
            if ($enc && $enc !== 'UTF-8') {
                $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
                file_put_contents($dest, $raw);
            }
            // Remove UTF-8 BOM if present
            $raw = ltrim($raw, "\xEF\xBB\xBF");

            // ─── Parse CSV ────────────────────────────────
            $lines   = preg_split('/\r\n|\n|\r/', trim($raw));
            $headers = str_getcsv(array_shift($lines));
            $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

            // Map column names → index
            $col = [];
            foreach ($headers as $i => $h) $col[$h] = $i;

            // ─── Required columns ──────────────────────────
            // brand column ใช้ filter แทน ga_familleniv1
            // New CSV format: Store,Barcode,GQ_ARTICLE,GQ_CLOTURE,GA_STATUTART,GA_FOURNPRINC,
            //                  Brand,GQ_DATECLOTURE,Physical,Sale,Discrepancy,Transfer,Sup Reci,Notice
            $required = ['store', 'barcode', 'brand', 'physical'];
            $missing  = array_diff($required, array_keys($col));
            if ($missing) throw new Exception('หาคอลัมน์ไม่พบ: ' . implode(', ', $missing) . ' | Header ที่เจอ: ' . implode(', ', array_keys($col)));

            // ─── ลบข้อมูล replenish เก่าทั้งหมดก่อน ──────
            $db->exec("DELETE FROM replenish_plans       WHERE 1");
            $db->exec("DELETE FROM replenish_top_sellers WHERE 1");
            $db->exec("DELETE FROM replenish_stock       WHERE 1");
            $db->exec("DELETE FROM replenish_uploads     WHERE 1");
            $db->exec("DELETE FROM replenish_products    WHERE 1");

            // ─── Insert upload record ─────────────────────
            $db->prepare(
                "INSERT INTO replenish_uploads (filename,upload_date,rate_days,note,rows_imported)
                 VALUES (:fn,:dt,:rd,:no,0)"
            )->execute([':fn'=>$filename,':dt'=>$uploadDate,':rd'=>$rateDays,':no'=>$note]);
            $uploadId = (int)$db->lastInsertId();

            // ─── Prepared statements ──────────────────────
            $insProd  = $db->prepare(
                "INSERT INTO replenish_products (barcode,article_code,product_name,family,price)
                 VALUES (:bc,:art,:nm,:fam,:px)
                 ON DUPLICATE KEY UPDATE
                     article_code=VALUES(article_code),
                     product_name=VALUES(product_name),
                     price=COALESCE(VALUES(price),price)"
            );
            $insStock = $db->prepare(
                "INSERT INTO replenish_stock
                 (upload_id,store_code_new,barcode,physical,transfer_col,sup_reci,notice,upload_date)
                 VALUES (:uid,:scn,:bc,:ph,:tr,:sr,:no,:dt)
                 ON DUPLICATE KEY UPDATE physical=VALUES(physical)"
            );

            // ─── Check if store_code_new exists in stores ─
            $storeCheck = $db->prepare(
                "SELECT store_code FROM stores WHERE store_code_new=:scn LIMIT 1"
            );

            $db->beginTransaction();
            $imported = 0; $skipped = 0; $noStore = 0;

            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $cols   = str_getcsv($line);
                // กรองด้วย brand column
                $csvBrand = trim($cols[$col['brand']] ?? '');
                if (strcasecmp($csvBrand, CSV_BRAND) !== 0) { $skipped++; continue; }

                $storeCodeNew = trim($cols[$col['store']] ?? '');
                $barcode      = trim($cols[$col['barcode']] ?? '');
                if (!$storeCodeNew || !$barcode) { $skipped++; continue; }

                // ตรวจว่า store_code_new มีใน stores table
                $storeCheck->execute([':scn' => $storeCodeNew]);
                if (!$storeCheck->fetchColumn()) {
                    // Auto-insert store with minimal data (admin ควร update store_type ทีหลัง)
                    $db->prepare(
                        "INSERT IGNORE INTO stores (store_code, store_code_new, store_name)
                         VALUES (:sc,:scn,:nm)"
                    )->execute([
                        ':sc'  => 'NEW_' . $storeCodeNew,
                        ':scn' => $storeCodeNew,
                        ':nm'  => 'Store ' . $storeCodeNew,
                    ]);
                    $noStore++;
                }

                // Product — CSV ใหม่ไม่มี item_description / prix
                // ชื่อสินค้าจะ update จาก daily_sales.item_description หลัง import
                $article = trim($cols[$col['gq_article']] ?? '');

                $insProd->execute([
                    ':bc'  => $barcode,
                    ':art' => $article,
                    ':nm'  => $article,   // placeholder
                    ':fam' => CSV_FAMILY,
                    ':px'  => null,
                ]);

                // Stock — เก็บเฉพาะ Physical (ไม่เก็บ Sale จาก CSV)
                $physical = max(0, (int)str_replace([' ',"\u{00a0}",''],'',
                                trim($cols[$col['physical']] ?? '0')));
                $transfer = isset($col['transfer'])
                    ? (int)str_replace([' ',"\u{00a0}"],'', trim($cols[$col['transfer']] ?? '0'))
                    : 0;
                $supReci  = isset($col['sup reci'])
                    ? (int)trim($cols[$col['sup reci']] ?? '0')
                    : 0;
                $notice   = isset($col['notice'])
                    ? (int)trim($cols[$col['notice']] ?? '0')
                    : 0;

                $insStock->execute([
                    ':uid' => $uploadId,
                    ':scn' => $storeCodeNew,
                    ':bc'  => $barcode,
                    ':ph'  => $physical,
                    ':tr'  => $transfer,
                    ':sr'  => $supReci,
                    ':no'  => $notice,
                    ':dt'  => $uploadDate,
                ]);
                $imported++;
            }

            $db->prepare("UPDATE replenish_uploads SET rows_imported=:cnt,rows_skipped=:sk WHERE id=:id")
               ->execute([':cnt'=>$imported,':sk'=>$skipped,':id'=>$uploadId]);
            $db->commit();

            // ─── Update product names from daily_sales ────────
            // daily_sales.item_description คือ source of truth สำหรับชื่อสินค้า
            $db->prepare(
                "UPDATE replenish_products rp
                 JOIN (
                     SELECT line_barcode,
                            MAX(item_description) AS item_desc,
                            MAX(base_price)       AS base_price
                     FROM daily_sales
                     WHERE brand = :brand
                       AND item_description IS NOT NULL
                       AND item_description != ''
                     GROUP BY line_barcode
                 ) ds ON rp.barcode = ds.line_barcode
                 SET rp.product_name = ds.item_desc,
                     rp.price = COALESCE(rp.price, ds.base_price)
                 WHERE rp.family = :fam"
            )->execute([':brand' => DS_BRAND, ':fam' => CSV_FAMILY]);

            // ─── Auto-calculate top sellers ───────────────
            $sa = new SalesAnalytics($uploadId);
            $ts = $sa->calculateTopSellers();

            $warnStore = $noStore ? " | ⚠️ {$noStore} store_code_new ใหม่" : '';
            $msg     = "✅ นำเข้าสำเร็จ {$imported} rows (TOPOLOGIE) | ข้าม {$skipped}{$warnStore} | Top Sellers: " . count($ts) . " SKU";
            $msgType = 'success';
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $msg     = '❌ ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Upload history
$uploads = $db->query(
    "SELECT ru.*, 
            (SELECT COUNT(DISTINCT barcode) FROM replenish_stock WHERE upload_id=ru.id) AS sku_count
     FROM replenish_uploads ru ORDER BY id DESC LIMIT 15"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Upload CSV — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --bg:#0d1117; --surface:#161b22; --border:#21262d; --accent:#79c0ff; --green:#7ee787; --red:#ffa198; }
* { box-sizing:border-box; }
body { background:var(--bg); color:#ffffff; font-family:'Segoe UI',sans-serif; font-size:.875rem; margin:0; }
.navbar { background:var(--surface)!important; border-bottom:1px solid var(--border); }
.navbar-brand { color:var(--accent)!important; font-weight:700; }
.nav-link { color:#c9d1d9!important; }
.nav-link:hover,.nav-link.active { color:#ffffff!important; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:12px; }
.card-header { background:#1f2937; border-bottom:1px solid var(--border); border-radius:12px 12px 0 0!important; padding:.75rem 1.25rem; color:#fff; }
.form-control,.form-select { background:var(--bg); border:1px solid #30363d; color:#ffffff; border-radius:8px; }
.form-control:focus,.form-select:focus { background:var(--bg); border-color:var(--accent); color:#ffffff; box-shadow:0 0 0 3px rgba(121,192,255,.15); }
.form-label { font-size:.85rem; color:#ffffff; margin-bottom:.3rem; }
.upload-zone { border:2px dashed #30363d; border-radius:12px; padding:3rem 2rem; text-align:center; cursor:pointer; transition:.2s; color:#fff; }
.upload-zone:hover,.upload-zone.dragover { border-color:var(--accent); background:rgba(121,192,255,.08); }
.upload-zone .icon { font-size:3rem; color:var(--accent); margin-bottom:1rem; }
.upload-zone p { color:#c9d1d9; }
.btn-upload { background:#238636; border-color:#238636; color:#fff; border-radius:8px; }
.btn-upload:hover { background:#2ea043; border-color:#2ea043; color:#fff; }
.table { --bs-table-bg:transparent; --bs-table-color:#ffffff; font-size:.85rem; }
.table th { background:#1f2937; color:#ffffff; border-color:var(--border); font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; }
.table td { border-color:var(--border); vertical-align:middle; color:#e6edf3; }
.badge-pending  { background:rgba(121,192,255,.22);  color:var(--accent); }
.badge-success  { background:rgba(126,231,135,.22);   color:var(--green); }
.badge-warning  { background:rgba(240,193,75,.22);  color:#f0c14b; }
.info-box { background:#1f2937; border:1px solid var(--border); border-radius:10px; padding:1rem 1.25rem; color:#fff; }
.section-sep { color:#c9d1d9; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; font-weight:600; margin:.75rem 0 .4rem; }
.text-muted { color:#c9d1d9!important; }
.alert { color:#fff; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><i class="fa fa-rotate me-2"></i><?= APP_NAME ?></a>
    <div class="navbar-nav ms-auto d-flex flex-row gap-3">
      <a class="nav-link" href="index.php"><i class="fa fa-gauge me-1"></i>Dashboard</a>
      <a class="nav-link active" href="upload.php"><i class="fa fa-upload me-1"></i>Upload</a>
      <a class="nav-link" href="report_sales.php"><i class="fa fa-chart-line me-1"></i>Report ยอดขาย</a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4" style="max-width:1200px">
  <h5 class="fw-bold mb-4"><i class="fa fa-file-csv me-2 text-success"></i>Import Stock CSV
    <span class="badge ms-2" style="background:#238636;font-size:.7rem"><?= DS_BRAND ?></span>
  </h5>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?> alert-dismissible fade show mb-4">
    <?= $msg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Upload Form -->
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="fa fa-cloud-upload-alt me-2"></i>อัพโหลดไฟล์ CEGID CSV</h6></div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data" id="upForm">

            <!-- Drop Zone -->
            <div class="upload-zone mb-4" id="dropZone" onclick="document.getElementById('csvFile').click()">
              <div class="icon"><i class="fa fa-file-csv"></i></div>
              <p class="fw-semibold mb-1">ลาก CSV มาวาง หรือคลิกเพื่อเลือก</p>
              <p class="text-muted small">รองรับ UTF-8 และ UTF-16 LE (CEGID export)</p>
              <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt" class="d-none" required>
              <div id="fileInfo" class="mt-2 small text-success d-none"></div>
            </div>

            <!-- Settings -->
            <div class="section-sep">📅 ตั้งค่าการนำเข้า</div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">วันที่ Stock Snapshot</label>
                <input type="date" name="upload_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                <div class="form-text text-muted">วันที่ Physical Stock ใน CSV (ณ วันที่)</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">ช่วงดึง Sell Rate จาก daily_sales</label>
                <select name="rate_days" class="form-select">
                  <option value="7">7 วัน (1 สัปดาห์)</option>
                  <option value="14">14 วัน (2 สัปดาห์)</option>
                  <option value="28" selected>28 วัน (4 สัปดาห์) ← แนะนำ</option>
                  <option value="30">30 วัน</option>
                  <option value="60">60 วัน</option>
                  <option value="90">90 วัน (3 เดือน)</option>
                </select>
                <div class="form-text text-muted">ดึง daily_sales ย้อนหลัง N วัน นับจากวันที่ข้างบน</div>
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label">หมายเหตุ (ถ้ามี)</label>
              <input type="text" name="note" class="form-control" placeholder="เช่น Jan 2026 stock count">
            </div>

            <div class="info-box mb-3 small">
              <p class="fw-semibold mb-2"><i class="fa fa-circle-info me-1 text-info"></i>CSV Header ที่รองรับ</p>
              <code style="font-size:.72rem;color:#58a6ff;display:block;background:#0d1117;border-radius:6px;padding:.4rem .6rem;margin-bottom:.75rem">
                Store, Barcode, GQ_ARTICLE, GQ_CLOTURE, GA_STATUTART, GA_FOURNPRINC,<br>
                Brand, GQ_DATECLOTURE, Physical, Sale, Discrepancy, Transfer, Sup Reci, Notice
              </code>
              <div class="row">
                <div class="col-6">
                  <span class="text-success">✅ บันทึกจาก CSV:</span>
                  <ul class="mb-0 ps-3">
                    <li>Physical (Stock On Hand)</li>
                    <li>Barcode, GQ_Article</li>
                    <li>Store (CEGID code)</li>
                    <li>Transfer, Sup Reci, Notice</li>
                  </ul>
                </div>
                <div class="col-6">
                  <span class="text-warning">⚡ ดึงจาก daily_sales:</span>
                  <ul class="mb-0 ps-3">
                    <li><strong>ชื่อสินค้า</strong> (item_description)</li>
                    <li>Sell Rate, Week Cover</li>
                    <li>Monthly Breakdown</li>
                    <li>Top Sellers Ranking</li>
                  </ul>
                </div>
              </div>
              <p class="text-muted mt-2 mb-0" style="font-size:.7rem">
                ⚠️ Upload ใหม่จะ <strong>ลบข้อมูล replenish เก่าทั้งหมด</strong> แล้ว insert ใหม่
              </p>
            </div>

            <button type="submit" class="btn btn-upload w-100" id="submitBtn">
              <i class="fa fa-cloud-upload-alt me-2"></i>Import CSV
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Info + History -->
    <div class="col-lg-5">
      <!-- Formula -->
      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="fa fa-calculator me-2"></i>สูตรคำนวณ</h6></div>
        <div class="card-body small">
          <table class="table table-sm mb-0">
            <tbody>
              <tr><td class="text-muted">Sell Rate</td>
                  <td>= SUM(daily_sales.qty) ÷ effective_days</td></tr>
              <tr><td class="text-muted">Effective Days</td>
                  <td>= วันจริง ถ้าสาขาเปิดใหม่ < rate_days</td></tr>
              <tr><td class="text-muted">Week Cover</td>
                  <td>= Stock ÷ (Rate/day × 7)</td></tr>
              <tr><td class="text-muted fw-semibold">Need (Refill)</td>
                  <td class="fw-semibold">= Rate × 14d − Stock</td></tr>
              <tr><td class="text-muted fw-semibold">Need (TF Best)</td>
                  <td class="fw-semibold">= Rate × 10d − Stock</td></tr>
              <tr><td class="text-muted fw-semibold">Diff</td>
                  <td class="fw-semibold">= Stock − Need → เกิน/ขาด/พอดี</td></tr>
              <tr><td class="text-muted">Holding</td>
                  <td>= Stock ÷ Rate (วัน)</td></tr>
            </tbody>
          </table>
          <hr class="my-2" style="border-color:#30363d">
          <p class="fw-semibold mb-1">Business Rules</p>
          <ul class="small text-muted mb-0 ps-3">
            <li>Holding รวม &lt; 30d → โอนออกหมดได้</li>
            <li>WC &lt; 4w → โอนออกได้ทั้งหมด ไม่มีขั้นต่ำ</li>
            <li>WC &gt; 2.5w → Donor (ส่วนที่เกิน)</li>
            <li>Top Seller → เก็บ 2w ก่อนโอน</li>
            <li>ห้ามโอนออกจาก DC / DISPLAY</li>
            <li>ห้ามโอนเข้า DC</li>
            <li>Receiving store ต้องเคยขาย SKU นั้น</li>
          </ul>
        </div>
      </div>

      <!-- Upload History -->
      <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="fa fa-history me-2"></i>ประวัติ Upload</h6></div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead>
              <tr><th>#</th><th>วันที่ Stock</th><th>Rate Days</th><th>SKUs</th><th>Rows</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($uploads as $u): ?>
              <tr>
                <td><span class="badge badge-pending">#<?= $u['id'] ?></span></td>
                <td><?= $u['upload_date'] ?></td>
                <td><?= $u['rate_days'] ?>d</td>
                <td><?= $u['sku_count'] ?? '—' ?></td>
                <td><span class="badge badge-success"><?= number_format($u['rows_imported']) ?></span></td>
                <td><a href="index.php?upload_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">View</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$uploads): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
const drop = document.getElementById('dropZone');
const inp  = document.getElementById('csvFile');
const info = document.getElementById('fileInfo');
drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('dragover'); });
drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
drop.addEventListener('drop',      e => { e.preventDefault(); drop.classList.remove('dragover'); if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]); });
inp.addEventListener('change', () => { if (inp.files[0]) setFile(inp.files[0]); });
function setFile(f) {
    const dt = new DataTransfer(); dt.items.add(f); inp.files = dt.files;
    info.textContent = `✅ ${f.name} (${(f.size/1024).toFixed(1)} KB)`;
    info.classList.remove('d-none');
    // Auto-detect date from filename
    const m = f.name.match(/(\d{4})(\d{2})(\d{2})/);
    if (m) document.querySelector('[name=upload_date]').value = `${m[1]}-${m[2]}-${m[3]}`;
}
document.getElementById('upForm').addEventListener('submit', () => {
    document.getElementById('submitBtn').innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>กำลัง import...';
});
</script>
</body>
</html>