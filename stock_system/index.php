<?php
/**
 * index.php — Main Dashboard  v3.1
 * Replenishment System — TOPOLOGIE (TLG)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/SalesAnalytics.php';

// ─── Error handling: ถ้า DB หรือ table ยังไม่พร้อม ────────────
$dbError   = null;
$db        = null;
$sa        = null;
$upload    = null;
$uploadId  = null;
$uploads   = [];

try {
    $db = Database::getInstance();

    // ตรวจว่า table replenish_uploads มีแล้วหรือยัง
    $check = $db->query("SHOW TABLES LIKE 'replenish_uploads'")->fetch();
    if (!$check) {
        $dbError = 'TABLES_MISSING';
    } else {
        $uploadId = isset($_GET['upload_id']) ? (int)$_GET['upload_id'] : null;
        $sa       = new SalesAnalytics($uploadId);
        $uploadId = $sa->uploadId;
        $upload   = $sa->getUploadInfo();

        $uploads = $db->query(
            "SELECT ru.id, ru.upload_date, ru.rate_days, ru.note,
                    (SELECT COUNT(DISTINCT barcode) FROM replenish_stock WHERE upload_id=ru.id) AS sku_cnt
             FROM replenish_uploads ru ORDER BY id DESC LIMIT 15"
        )->fetchAll();
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
} catch (Exception $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --bg:#0d1117; --surf:#161b22; --surf2:#1f2937;
  --bdr:#21262d; --bdr2:#30363d;
  --blue:#79c0ff; --green:#7ee787; --red:#ffa198;
  --yellow:#f0c14b; --purple:#d2a8ff; --text:#ffffff; --muted:#c9d1d9;
}
*{box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',system-ui,sans-serif;font-size:.875rem;margin:0;}
a{color:var(--blue);text-decoration:none;}
/* Layout */
.sidebar{width:220px;background:var(--surf);border-right:1px solid var(--bdr);min-height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;z-index:100;}
.sidebar-logo{padding:1.2rem 1.2rem .8rem;border-bottom:1px solid var(--bdr);}
.sidebar-logo span{color:var(--blue);font-weight:800;font-size:1rem;}
.sidebar-logo .sub{color:var(--muted);font-size:.68rem;margin-top:2px;}
.sidebar nav{padding:.5rem 0;flex:1;overflow-y:auto;}
.nav-item{display:flex;align-items:center;gap:.6rem;padding:.55rem 1.2rem;color:var(--muted);cursor:pointer;border-left:3px solid transparent;transition:.15s;font-size:.82rem;text-decoration:none;}
.nav-item:hover{color:var(--text);background:rgba(255,255,255,.04);}
.nav-item.active{color:var(--text);background:rgba(88,166,255,.1);border-left-color:var(--blue);}
.nav-item i{width:16px;text-align:center;font-size:.85rem;}
.nav-section{padding:.6rem 1.2rem .2rem;font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;color:#8b949e;font-weight:600;}
.main{margin-left:220px;padding:1.5rem;min-height:100vh;}
/* Topbar */
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem;}
.topbar-title{font-size:1.1rem;font-weight:700;color:#fff;}
.topbar-meta{color:var(--muted);font-size:.75rem;}
/* Stat Cards */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-bottom:1.5rem;}
.stat-card{background:var(--surf);border:1px solid var(--bdr);border-radius:10px;padding:1rem 1.1rem;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.stat-card.blue::before{background:var(--blue);}
.stat-card.green::before{background:var(--green);}
.stat-card.red::before{background:var(--red);}
.stat-card.yellow::before{background:var(--yellow);}
.stat-card.purple::before{background:var(--purple);}
.stat-label{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;}
.stat-val{font-size:1.6rem;font-weight:700;line-height:1;color:#fff;}
.stat-sub{font-size:.72rem;color:var(--muted);margin-top:.3rem;}
/* Cards */
.card{background:var(--surf);border:1px solid var(--bdr);border-radius:12px;margin-bottom:1.25rem;}
.card-header{background:var(--surf2);border-bottom:1px solid var(--bdr);border-radius:12px 12px 0 0!important;padding:.7rem 1.1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;}
.card-header h6{margin:0;font-size:.85rem;font-weight:600;color:#fff;}
/* Tabs */
.tab-bar{display:flex;border-bottom:1px solid var(--bdr);margin-bottom:1rem;overflow-x:auto;}
.tab-btn{padding:.5rem 1rem;font-size:.8rem;border:none;background:none;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap;transition:.15s;}
.tab-btn:hover{color:#fff;}
.tab-btn.active{color:var(--blue);border-bottom-color:var(--blue);}
.tab-pane{display:none;}
.tab-pane.active{display:block;}
/* Table */
.data-table{width:100%;border-collapse:collapse;font-size:.8rem;}
.data-table th{background:var(--surf2);color:#fff;padding:.5rem .7rem;text-align:left;border-bottom:1px solid var(--bdr);font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;position:sticky;top:0;z-index:1;cursor:pointer;user-select:none;}
.data-table th:hover{color:var(--blue);background:rgba(255,255,255,.05);}
.data-table td{padding:.45rem .7rem;border-bottom:1px solid #1a1f27;vertical-align:middle;color:#e6edf3;}
.data-table tbody tr:hover{background:rgba(255,255,255,.04);}
.table-wrap{max-height:55vh;overflow-y:auto;border-radius:0 0 10px 10px;}
/* Badges */
.badge{display:inline-block;padding:.15rem .45rem;border-radius:4px;font-size:.7rem;font-weight:600;}
.st-critical{background:rgba(255,161,152,.22);color:#ffa198;border:1px solid rgba(255,161,152,.4);}
.st-warning{background:rgba(240,193,75,.22);color:#f0c14b;border:1px solid rgba(240,193,75,.4);}
.st-ok{background:rgba(121,192,255,.2);color:#79c0ff;border:1px solid rgba(121,192,255,.4);}
.st-good{background:rgba(126,231,135,.2);color:#7ee787;border:1px solid rgba(126,231,135,.4);}
.st-overstock{background:rgba(210,168,255,.2);color:#d2a8ff;border:1px solid rgba(210,168,255,.4);}
/* Diff */
.diff-over{color:#7ee787;font-weight:600;}
.diff-short{color:#ffa198;font-weight:700;}
.diff-ok{color:#79c0ff;font-weight:600;}
/* WC Bar */
.wc-bar{width:60px;height:5px;border-radius:3px;background:#21262d;display:inline-block;vertical-align:middle;overflow:hidden;}
.wc-fill{height:100%;border-radius:3px;}
/* Plan Cards */
.plan-card{background:var(--surf2);border:1px solid var(--bdr);border-radius:8px;padding:.85rem;margin-bottom:.6rem;display:flex;gap:.75rem;align-items:flex-start;}
.plan-icon{font-size:1.3rem;flex-shrink:0;margin-top:.1rem;}
.plan-body{flex:1;min-width:0;}
.plan-title{font-weight:600;font-size:.82rem;margin-bottom:.3rem;color:#fff;}
.plan-meta{font-size:.74rem;color:#c9d1d9;display:flex;flex-wrap:wrap;gap:.4rem .8rem;}
.plan-actions{display:flex;gap:.4rem;flex-shrink:0;}
.btn-approve{background:rgba(126,231,135,.18);border:1px solid rgba(126,231,135,.4);color:#7ee787;border-radius:5px;padding:.25rem .65rem;font-size:.72rem;cursor:pointer;}
.btn-approve:hover{background:rgba(126,231,135,.3);}
.btn-reject{background:rgba(255,161,152,.15);border:1px solid rgba(255,161,152,.35);color:#ffa198;border-radius:5px;padding:.25rem .65rem;font-size:.72rem;cursor:pointer;}
.btn-reject:hover{background:rgba(255,161,152,.25);}
/* Filters */
.filter-bar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:.75rem;}
.filter-bar input,.filter-bar select{background:var(--bg);border:1px solid var(--bdr2);color:#fff;border-radius:6px;padding:.3rem .65rem;font-size:.8rem;}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--blue);outline:none;}
.filter-bar input::placeholder{color:#8b949e;}
/* Buttons */
.btn{border-radius:7px;font-size:.8rem;padding:.38rem .9rem;cursor:pointer;border:1px solid transparent;transition:.15s;display:inline-flex;align-items:center;gap:.35rem;}
.btn-primary{background:#238636;border-color:#238636;color:#fff;}
.btn-primary:hover{background:#2ea043;}
.btn-secondary{background:transparent;border-color:var(--bdr2);color:#fff;}
.btn-secondary:hover{background:rgba(255,255,255,.08);}
.btn-blue{background:rgba(121,192,255,.18);border-color:rgba(121,192,255,.4);color:var(--blue);}
.btn-sm{padding:.25rem .65rem;font-size:.74rem;}
/* Spinner / Toast */
.spinner-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:.75rem;}
.spinner-overlay.show{display:flex;}
.toast-area{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9000;display:flex;flex-direction:column;gap:.5rem;}
.toast-item{background:var(--surf2);border:1px solid var(--bdr);border-radius:8px;padding:.65rem 1rem;font-size:.8rem;min-width:220px;animation:slideIn .2s ease;color:#fff;}
@keyframes slideIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.toast-item.success{border-left:3px solid var(--green);}
.toast-item.error{border-left:3px solid var(--red);}
/* Setup alert */
.setup-box{background:#1f2937;border:1px solid #f0c14b;border-radius:12px;padding:2rem;max-width:640px;margin:3rem auto;}
.setup-box h4{color:#f0c14b;margin-bottom:1rem;}
.setup-box code{background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:.15rem .4rem;font-size:.82rem;color:#79c0ff;}
.step{display:flex;gap:.75rem;margin-bottom:.75rem;align-items:flex-start;color:#e6edf3;}
.step-num{background:#f0c14b;color:#000;font-weight:700;font-size:.7rem;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}
/* Empty state */
.empty-state{text-align:center;padding:2.5rem;color:#c9d1d9;}
.empty-state i{font-size:2.5rem;margin-bottom:.75rem;display:block;}
/* Dropdown */
.dropdown{position:relative;}
.dropdown-menu{display:none;position:absolute;right:0;top:calc(100% + 4px);background:#1f2937;border:1px solid #30363d;border-radius:8px;padding:.4rem 0;min-width:180px;z-index:200;}
.dropdown-menu.show{display:block;}
.dropdown-menu a{display:block;padding:.45rem 1rem;color:#fff;font-size:.8rem;}
.dropdown-menu a:hover{background:rgba(255,255,255,.08);}
@media(max-width:768px){.sidebar{width:0;overflow:hidden;}.main{margin-left:0;}.stat-grid{grid-template-columns:repeat(2,1fr);}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="sidebar-logo">
    <span><i class="fa fa-boxes-stacked me-1"></i><?= APP_NAME ?></span>
    <div class="sub">v<?= APP_VERSION ?></div>
  </div>
  <nav>
    <div class="nav-section">เมนู</div>
    <a class="nav-item active" href="#" onclick="showSection('dashboard');return false">
      <i class="fa fa-gauge-high"></i>Dashboard
    </a>
    <a class="nav-item" href="#" onclick="showSection('stock');return false">
      <i class="fa fa-table"></i>Stock Overview
    </a>
    <a class="nav-item" href="#" onclick="showSection('plans');return false">
      <i class="fa fa-arrows-rotate"></i>Transfer Planner
    </a>
    <a class="nav-item" href="#" onclick="showSection('alerts');return false">
      <i class="fa fa-bell"></i>Reorder Alerts
    </a>
    <div class="nav-section">รายงาน</div>
    <a class="nav-item" href="report_sales.php<?= $uploadId ? '?upload_id='.$uploadId : '' ?>">
      <i class="fa fa-chart-line"></i>ยอดขาย SKU
    </a>
    <a class="nav-item" href="upload.php">
      <i class="fa fa-upload"></i>Upload CSV
    </a>
    <?php if ($uploads): ?>
    <div class="nav-section">Upload History</div>
    <?php foreach(array_slice($uploads,0,8) as $u): ?>
    <a class="nav-item <?= $u['id']==$uploadId?'active':'' ?>"
       href="?upload_id=<?= $u['id'] ?>" style="font-size:.72rem;padding:.4rem 1.2rem">
      <i class="fa fa-clock"></i>
      <?= $u['upload_date'] ?> <span style="color:#484f58">(<?= $u['sku_cnt'] ?>)</span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>
</div>

<!-- MAIN -->
<div class="main">

  <!-- DB / Setup Error -->
  <?php if ($dbError): ?>
  <div class="setup-box">
    <h4><i class="fa fa-triangle-exclamation me-2"></i>ตั้งค่า Database ก่อนเริ่มใช้งาน</h4>
    <?php if ($dbError === 'TABLES_MISSING'): ?>
    <p style="color:#8b949e">เชื่อมต่อ DB สำเร็จ แต่ยังไม่มีตาราง <code>replenish_*</code> ครับ</p>
    <p style="color:#8b949e;font-size:.82rem">วิธีแก้ — Import <code>schema.sql</code>:</p>
    <div style="background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:1rem;font-family:monospace;font-size:.78rem;color:#58a6ff;margin-bottom:1rem">
      mysql -u <?= DB_USER ?> -p <?= DB_NAME ?> &lt; schema.sql
    </div>
    <?php else: ?>
    <p style="color:#f85149">DB Error: <?= htmlspecialchars($dbError) ?></p>
    <p style="color:#8b949e;font-size:.82rem">ตรวจสอบ <code>config.php</code>:</p>
    <div style="background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:1rem;font-size:.78rem;margin-bottom:1rem">
      Host: <code><?= DB_HOST ?></code> | DB: <code><?= DB_NAME ?></code> | User: <code><?= DB_USER ?></code>
    </div>
    <?php endif; ?>
    <div class="step"><span class="step-num">1</span><div>Import <code>schema.sql</code> เข้า MySQL (เพิ่มตาราง replenish_* และ ALTER stores)</div></div>
    <div class="step"><span class="step-num">2</span><div>Upload Stock CSV ที่หน้า <a href="upload.php">upload.php</a></div></div>
    <div class="step"><span class="step-num">3</span><div>กลับมาหน้า Dashboard แล้วกด <strong>คำนวณแผน</strong></div></div>
  </div>

  <?php else: ?>

  <!-- TOPBAR -->
  <div class="topbar">
    <div>
      <div class="topbar-title" id="sectionTitle">
        <i class="fa fa-gauge-high me-2"></i>Dashboard
      </div>
      <div class="topbar-meta">
        <?php if ($upload): ?>
          Stock ณ <strong><?= $upload['upload_date'] ?></strong> |
          ยอดขายจาก daily_sales ย้อนหลัง <strong><?= $upload['rate_days'] ?> วัน</strong>
          <?php if ($upload['note']): ?> | <?= htmlspecialchars($upload['note']) ?><?php endif; ?>
        <?php else: ?>
          <span style="color:var(--yellow)">ยังไม่มีข้อมูล — <a href="upload.php">Upload CSV</a> เพื่อเริ่ม</span>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <?php if ($uploadId): ?>
      <button class="btn btn-primary btn-sm" onclick="calculatePlans()">
        <i class="fa fa-calculator"></i>คำนวณแผน
      </button>
      <div class="dropdown">
        <button class="btn btn-secondary btn-sm" onclick="toggleDropdown(this)">
          <i class="fa fa-download"></i>Export ▾
        </button>
        <div class="dropdown-menu">
          <a href="api.php?action=export&type=stock&upload_id=<?= $uploadId ?>">📊 Stock Overview</a>
          <a href="api.php?action=export&type=plans&upload_id=<?= $uploadId ?>">📦 Transfer Plans</a>
          <a href="api.php?action=export&type=alerts&upload_id=<?= $uploadId ?>">🔔 Reorder Alerts</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECTION: DASHBOARD -->
  <div id="sec-dashboard" class="section">
    <?php if (!$uploadId): ?>
    <div class="card"><div style="padding:3rem;text-align:center;color:var(--muted)">
      <i class="fa fa-cloud-upload-alt" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
      <p>ยังไม่มีข้อมูล Stock</p>
      <a href="upload.php" class="btn btn-primary">Upload CSV ตอนนี้</a>
    </div></div>
    <?php else: ?>
    <div class="stat-grid">
      <div class="stat-card blue"><div class="stat-label">Total SKUs</div><div class="stat-val" id="st-skus">—</div><div class="stat-sub"><?= $upload['upload_date'] ?></div></div>
      <div class="stat-card green"><div class="stat-label">Active SKUs</div><div class="stat-val" id="st-active">—</div><div class="stat-sub">มียอดขาย <?= $upload['rate_days'] ?>d</div></div>
      <div class="stat-card blue"><div class="stat-label">Total Stores</div><div class="stat-val" id="st-stores">—</div></div>
      <div class="stat-card green"><div class="stat-label">Total Stock</div><div class="stat-val" id="st-stock">—</div><div class="stat-sub">ชิ้น on hand</div></div>
      <div class="stat-card yellow"><div class="stat-label">ยอดขาย <?= $upload['rate_days'] ?>d</div><div class="stat-val" id="st-sold">—</div><div class="stat-sub">ชิ้น</div></div>
      <div class="stat-card red"><div class="stat-label">🚨 Critical</div><div class="stat-val" id="st-critical">—</div><div class="stat-sub">WC &lt; 1w</div></div>
      <div class="stat-card yellow"><div class="stat-label">⚠️ Warning</div><div class="stat-val" id="st-warning">—</div><div class="stat-sub">WC 1–1.5w</div></div>
      <div class="stat-card purple"><div class="stat-label">⛔ Overstock</div><div class="stat-val" id="st-over">—</div><div class="stat-sub">WC &gt; 2.5w</div></div>
    </div>
    <div class="card">
      <div class="card-header">
        <h6><i class="fa fa-clipboard-list me-2"></i>แผนที่รอ Approve</h6>
        <button class="btn btn-blue btn-sm" onclick="showSection('plans')"><i class="fa fa-arrow-right"></i>ดูทั้งหมด</button>
      </div>
      <div style="padding:.85rem 1.1rem" id="planSummary">
        <span style="color:var(--muted);font-size:.82rem">กด <strong>คำนวณแผน</strong> เพื่อสร้างแผนใหม่</span>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- SECTION: STOCK -->
  <div id="sec-stock" class="section" style="display:none">
    <div class="card">
      <div class="card-header">
        <h6><i class="fa fa-table me-2"></i>Stock Overview</h6>
        <span id="stockCount" style="color:var(--muted);font-size:.75rem"></span>
      </div>
      <div style="padding:.75rem 1.1rem .25rem">
        <div class="filter-bar">
          <input type="text" id="searchStock" placeholder="🔍 barcode / ชื่อ / สาขา" style="min-width:220px">
          <select id="filterStatus" onchange="loadStock()">
            <option value="">All Status</option>
            <option value="critical">🚨 Critical</option>
            <option value="warning">⚠️ Warning</option>
            <option value="ok">✅ OK</option>
            <option value="good">🟢 Good</option>
            <option value="overstock">⛔ Overstock</option>
          </select>
          <select id="filterStore" onchange="loadStock()" style="max-width:200px">
            <option value="">All Stores</option>
          </select>
          <button class="btn btn-secondary btn-sm" onclick="loadStock()"><i class="fa fa-rotate-right"></i></button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="data-table" id="stockTable">
          <thead>
            <tr>
              <th onclick="sortTable(this,0)">สาขา <span class="sort-icon">⇅</span></th>
              <th onclick="sortTable(this,1)">Barcode <span class="sort-icon">⇅</span></th>
              <th>สินค้า</th>
              <th onclick="sortTable(this,3)" style="text-align:right">Stock <span class="sort-icon">⇅</span></th>
              <th onclick="sortTable(this,4)" style="text-align:right">ขาย <?= $upload['rate_days'] ?? '28' ?>d <span class="sort-icon">⇅</span></th>
              <th onclick="sortTable(this,5)" style="text-align:right">Rate/วัน <span class="sort-icon">⇅</span></th>
              <th onclick="sortTable(this,6)">Week Cover <span class="sort-icon">⇅</span></th>
              <th style="text-align:right">Need 14d</th>
              <th onclick="sortTable(this,8)">Diff <span class="sort-icon">⇅</span></th>
              <th onclick="sortTable(this,9)" style="text-align:right">Holding <span class="sort-icon">⇅</span></th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody id="stockBody">
            <tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--muted)">
              <i class="fa fa-spinner fa-spin me-2"></i>กำลังโหลด...
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- SECTION: PLANS -->
  <div id="sec-plans" class="section" style="display:none">
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchPlanTab('tf')">
        <i class="fa fa-arrows-left-right me-1"></i>TF Best (store→store)
      </button>
      <button class="tab-btn" onclick="switchPlanTab('refill')">
        <i class="fa fa-truck me-1"></i>Refill (DC→สาขา)
      </button>
    </div>
    <div id="planTab-tf" class="tab-pane active">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem">
        <span id="tfCount" style="color:var(--muted);font-size:.8rem"></span>
        <div style="display:flex;gap:.4rem;align-items:center">
          <span style="color:var(--muted);font-size:.72rem">เรียง:</span>
          <button class="btn btn-secondary btn-sm" onclick="sortPlans('tf','product_name')">ชื่อสินค้า</button>
          <button class="btn btn-secondary btn-sm" onclick="sortPlans('tf','to_store')">สาขาปลายทาง</button>
          <button class="btn btn-secondary btn-sm" onclick="sortPlans('tf','from_store')">สาขาต้นทาง</button>
          <button class="btn btn-secondary btn-sm" onclick="sortPlans('tf','qty')">จำนวน</button>
          <button class="btn btn-primary btn-sm" onclick="approveAll('TF_BEST')">
            <i class="fa fa-check-double"></i>Approve All
          </button>
        </div>
      </div>
      <div id="tfPlans"></div>
    </div>
    <div id="planTab-refill" class="tab-pane">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem">
        <span id="refillCount" style="color:var(--muted);font-size:.8rem"></span>
        <div style="display:flex;gap:.4rem;align-items:center">
          <span style="color:var(--muted);font-size:.72rem">เรียง:</span>
          <button class="btn btn-secondary btn-sm" onclick="sortPlans('refill','product_name')">ชื่อสินค้า</button>
          <button class="btn btn-secondary btn-sm" onclick="sortPlans('refill','to_store')">สาขาปลายทาง</button>
          <button class="btn btn-secondary btn-sm" onclick="sortPlans('refill','qty')">จำนวน</button>
          <button class="btn btn-primary btn-sm" onclick="approveAll('REFILL')">
            <i class="fa fa-check-double"></i>Approve All
          </button>
        </div>
      </div>
      <div id="refillPlans"></div>
    </div>
  </div>

  <!-- SECTION: ALERTS -->
  <div id="sec-alerts" class="section" style="display:none">
    <div class="card">
      <div class="card-header">
        <h6><i class="fa fa-bell me-2" style="color:var(--yellow)"></i>Reorder Alerts — Top Sellers</h6>
        <span id="alertCount" style="color:var(--muted);font-size:.75rem"></span>
      </div>
      <div class="table-wrap">
        <table class="data-table" id="alertTable">
          <thead>
            <tr>
              <th>Rank</th><th>Tier</th><th>Barcode</th><th>สินค้า</th>
              <th style="text-align:right">Stock รวม</th>
              <th style="text-align:right">ขาย <?= $upload['rate_days'] ?? '28' ?>d</th>
              <th style="text-align:right">Rate/week</th>
              <th>Week Cover</th>
              <th>Target</th><th>Trigger</th>
            </tr>
          </thead>
          <tbody id="alertBody">
            <tr><td colspan="10" style="text-align:center;padding:2rem;color:var(--muted)">
              <i class="fa fa-spinner fa-spin me-2"></i>กำลังโหลด...
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php endif; /* dbError */ ?>
</div><!-- /main -->

<!-- Spinner overlay -->
<div class="spinner-overlay" id="spinner">
  <i class="fa fa-spinner fa-spin" style="font-size:2rem;color:#58a6ff"></i>
  <div id="spinnerMsg" style="color:#8b949e;font-size:.82rem">กำลังคำนวณ...</div>
</div>
<!-- Toast area -->
<div class="toast-area" id="toastArea"></div>

<script>
const UPLOAD_ID = <?= $uploadId ?: 'null' ?>;
const API = 'api.php';
const RATE_DAYS = <?= $upload['rate_days'] ?? DEFAULT_RATE_DAYS ?>;

// ── Navigation ────────────────────────────────────────────────
const SEC_TITLES = {
  dashboard: '<i class="fa fa-gauge-high me-2"></i>Dashboard',
  stock:     '<i class="fa fa-table me-2"></i>Stock Overview',
  plans:     '<i class="fa fa-arrows-rotate me-2"></i>Transfer Planner',
  alerts:    '<i class="fa fa-bell me-2"></i>Reorder Alerts',
};

function showSection(name) {
  document.querySelectorAll('.section').forEach(s => s.style.display='none');
  const el = document.getElementById('sec-'+name);
  if (el) el.style.display='block';
  document.querySelectorAll('.nav-item').forEach(a => {
    a.classList.toggle('active', a.getAttribute('onclick')?.includes("'"+name+"'"));
  });
  if (document.getElementById('sectionTitle'))
    document.getElementById('sectionTitle').innerHTML = SEC_TITLES[name] || name;
  if (name==='stock')  loadStock();
  if (name==='plans')  { loadTFPlans(); loadRefillPlans(); }
  if (name==='alerts') loadAlerts();
}

// ── Dropdown ──────────────────────────────────────────────────
function toggleDropdown(btn) {
  btn.nextElementSibling.classList.toggle('show');
}
document.addEventListener('click', e => {
  document.querySelectorAll('.dropdown-menu.show').forEach(m => {
    if (!m.parentElement.contains(e.target)) m.classList.remove('show');
  });
});

// ── Stats ─────────────────────────────────────────────────────
function loadStats() {
  if (!UPLOAD_ID) return;
  fetch(`${API}?action=dashboard_stats&upload_id=${UPLOAD_ID}`)
    .then(r=>r.json()).then(d=>{
      if (!d.ok) return;
      const s = d.stats;
      setText('st-skus',    fmt(s.total_skus));
      setText('st-active',  fmt(s.active_skus));
      setText('st-stores',  fmt(s.total_stores));
      setText('st-stock',   fmt(s.total_stock));
      setText('st-sold',    fmt(s.total_sold));
      const plans = d.plans || {};
      const tfP = plans.TF_BEST?.PENDING?.cnt  || 0;
      const rP  = plans.REFILL?.PENDING?.cnt   || 0;
      const tfQ = plans.TF_BEST?.PENDING?.qty  || 0;
      const rQ  = plans.REFILL?.PENDING?.qty   || 0;
      let h = '';
      if (tfP) h += `<span class="badge st-ok" style="margin-right:.5rem">🔄 TF Best: ${tfP} แผน | ${tfQ} ชิ้น</span>`;
      if (rP)  h += `<span class="badge st-warning">📦 Refill: ${rP} แผน | ${rQ} ชิ้น</span>`;
      if (!h)  h = '<span style="color:var(--muted);font-size:.8rem">ไม่มีแผน PENDING — กด <strong>คำนวณแผน</strong></span>';
      setText('planSummary', h, true);
    }).catch(err => console.error('stats error:', err));
}

// ── Stock Table ───────────────────────────────────────────────
let allStockData = [];
function loadStock() {
  if (!UPLOAD_ID) return;
  const store  = document.getElementById('filterStore')?.value  || '';
  const status = document.getElementById('filterStatus')?.value || '';
  document.getElementById('stockBody').innerHTML =
    '<tr><td colspan="11" style="text-align:center;padding:2rem;color:#c9d1d9"><i class="fa fa-spinner fa-spin me-2"></i>กำลังโหลด...</td></tr>';

  fetch(`${API}?action=stock_summary&upload_id=${UPLOAD_ID}&store=${store}&status=${status}`)
    .then(r=>r.json()).then(d=>{
      if (!d.ok) { showToast('Error loading stock: '+(d.error||'unknown'),'error'); return; }
      allStockData = d.data;
      renderStock(allStockData);
      // Build store filter once
      const sel = document.getElementById('filterStore');
      if (sel && sel.options.length <= 1) {
        const map = {};
        d.data.forEach(r => { if (r.store_code_new) map[r.store_code_new] = r.store_name; });
        Object.entries(map).sort((a,b)=>a[1].localeCompare(b[1]))
          .forEach(([k,v]) => sel.append(new Option(v,k)));
      }
      setText('stockCount', d.count+' rows');
    }).catch(err => showToast('Network error: '+err,'error'));
}

function renderStock(data) {
  const q = (document.getElementById('searchStock')?.value || '').toLowerCase();
  const rows = q
    ? data.filter(r => (r.barcode||'').toLowerCase().includes(q)
        || (r.product_name||'').toLowerCase().includes(q)
        || (r.store_name||'').toLowerCase().includes(q)
        || (r.store_short||'').toLowerCase().includes(q))
    : data;

  setText('stockCount', rows.length+' rows');
  if (!rows.length) {
    document.getElementById('stockBody').innerHTML =
      '<tr><td colspan="11" style="text-align:center;padding:2rem;color:#c9d1d9">ไม่พบข้อมูล</td></tr>';
    return;
  }
  const WC_COLOR = s => ({critical:'#ffa198',warning:'#f0c14b',ok:'#79c0ff',good:'#7ee787',overstock:'#d2a8ff'}[s]||'#fff');
  const ST_LABEL = {critical:'🚨 เติมด่วน',warning:'⚠️ เติมทันที',ok:'✅ ปกติ',good:'🟢 ดี',overstock:'⛔ เกิน'};

  document.getElementById('stockBody').innerHTML = rows.map(r => {
    const wc    = r.week_cover >= 999 ? '∞' : (+r.week_cover).toFixed(1)+'w';
    const hld   = r.holding_days >= 999 ? '∞' : r.holding_days+'d';
    const wcPct = Math.min(100, r.week_cover >= 999 ? 100 : r.week_cover/4*100);
    const col   = WC_COLOR(r.status);
    const dCls  = r.diff < 0 ? 'diff-short' : (r.diff > 0 ? 'diff-over' : 'diff-ok');
    const hCls  = r.holding_days < 14 ? 'diff-short' : '';
    return `<tr>
      <td><strong>${esc(r.store_short||r.store_code_new)}</strong><br><small style="color:#c9d1d9">${esc(r.store_name||'')}</small></td>
      <td style="font-family:monospace;font-size:.75rem;color:#fff">${r.barcode}</td>
      <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.78rem;color:#fff">${esc(r.product_name||'')}</td>
      <td style="text-align:right;font-weight:600">${r.stock}</td>
      <td style="text-align:right">${r.qty_sold}</td>
      <td style="text-align:right">${(+r.daily_rate).toFixed(2)}</td>
      <td><span style="color:${col};font-weight:600">${wc}</span>
          <span class="wc-bar"><span class="wc-fill" style="width:${wcPct}%;background:${col}"></span></span></td>
      <td style="text-align:right">${r.need_14d}</td>
      <td class="${dCls}" style="text-align:center">${esc(r.diff_label)}</td>
      <td class="${hCls}" style="text-align:right">${hld}</td>
      <td><span class="badge st-${r.status}">${ST_LABEL[r.status]||r.status}</span></td>
    </tr>`;
  }).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  const si = document.getElementById('searchStock');
  if (si) si.addEventListener('input', () => renderStock(allStockData));
});

// ── Plan Cards ────────────────────────────────────────────────
function planCardHtml(p) {
  const isTF    = p.plan_type === 'TF_BEST';
  const toWC    = p.to_week_cover == null ? '—' : (+p.to_week_cover >= 999 ? '∞w' : (+p.to_week_cover).toFixed(1)+'w');
  const fromWC  = p.from_week_cover == null ? '—' : (+p.from_week_cover >= 999 ? '∞w' : (+p.from_week_cover).toFixed(1)+'w');
  const dist    = p.distance_km ? `<span>📍 ${p.distance_km} km</span>` : '';
  const canXfer = p.can_xfer_all ? `<span style="color:#ffa198">🔥 Holding ${p.company_holding}d &lt;30d</span>` : '';
  return `
  <div class="plan-card" id="plan-${p.id}">
    <div class="plan-icon">${isTF ? '🔄' : '📦'}</div>
    <div class="plan-body">
      <div class="plan-title">
        <span style="font-family:monospace;font-size:.75rem;color:#c9d1d9">${p.barcode}</span>
        ${esc(p.product_name||'')}
      </div>
      <div class="plan-meta">
        <span><strong style="color:var(--blue)">${p.qty} ชิ้น</strong>
          ${isTF
            ? ` ${esc(p.from_store_name||p.from_store_code)} <span style="color:#c9d1d9">(${fromWC})</span> → <strong>${esc(p.to_store_name||p.to_store_code)}</strong>`
            : ` DC → <strong>${esc(p.to_store_name||p.to_store_code)}</strong>`}
        </span>
        <span>ปลายทาง WC: <strong style="color:#ffa198">${toWC}</strong></span>
        <span>Rate: ${(+p.to_daily_rate||0).toFixed(2)}/d</span>
        <span>${esc(p.to_diff_label||'')}</span>
        ${dist}${canXfer}
      </div>
    </div>
    <div class="plan-actions">
      <button class="btn-approve" onclick="approvePlan(${p.id})">✓</button>
      <button class="btn-reject"  onclick="rejectPlan(${p.id})">✗</button>
    </div>
  </div>`;
}

const EMPTY_PLAN = '<div class="empty-state"><i class="fa fa-check-circle"></i><p>ไม่มีแผน — กด <strong>คำนวณแผน</strong></p></div>';

let tfPlansData = [];
let refillPlansData = [];

function loadTFPlans() {
  if (!UPLOAD_ID) return;
  document.getElementById('tfPlans').innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted)"><i class="fa fa-spinner fa-spin"></i></div>';
  fetch(`${API}?action=transfer_plans&upload_id=${UPLOAD_ID}&plan_type=TF_BEST&status=PENDING`)
    .then(r=>r.json()).then(d=>{
      setText('tfCount', d.ok ? `${d.count} แผน PENDING` : '');
      tfPlansData = d.ok ? d.data : [];
      document.getElementById('tfPlans').innerHTML = tfPlansData.length ? tfPlansData.map(planCardHtml).join('') : EMPTY_PLAN;
    });
}
function loadRefillPlans() {
  if (!UPLOAD_ID) return;
  document.getElementById('refillPlans').innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted)"><i class="fa fa-spinner fa-spin"></i></div>';
  fetch(`${API}?action=transfer_plans&upload_id=${UPLOAD_ID}&plan_type=REFILL&status=PENDING`)
    .then(r=>r.json()).then(d=>{
      setText('refillCount', d.ok ? `${d.count} แผน PENDING` : '');
      refillPlansData = d.ok ? d.data : [];
      document.getElementById('refillPlans').innerHTML = refillPlansData.length ? refillPlansData.map(planCardHtml).join('') : EMPTY_PLAN;
    });
}

function sortPlans(type, key) {
  let data = type === 'tf' ? tfPlansData : refillPlansData;
  if (!data.length) return;
  
  data.sort((a, b) => {
    let va, vb;
    switch(key) {
      case 'product_name':
        va = (a.product_name || '').toLowerCase();
        vb = (b.product_name || '').toLowerCase();
        break;
      case 'to_store':
        va = (a.to_store_name || a.to_store_code || '').toLowerCase();
        vb = (b.to_store_name || b.to_store_code || '').toLowerCase();
        break;
      case 'from_store':
        va = (a.from_store_name || a.from_store_code || '').toLowerCase();
        vb = (b.from_store_name || b.from_store_code || '').toLowerCase();
        break;
      case 'qty':
        va = +(a.qty || 0);
        vb = +(b.qty || 0);
        return vb - va; // descending
      default:
        return 0;
    }
    return va.localeCompare(vb, 'th');
  });
  
  const container = type === 'tf' ? 'tfPlans' : 'refillPlans';
  document.getElementById(container).innerHTML = data.map(planCardHtml).join('');
}

// ── Alerts ────────────────────────────────────────────────────
function loadAlerts() {
  if (!UPLOAD_ID) return;
  document.getElementById('alertBody').innerHTML =
    '<tr><td colspan="10" style="text-align:center;padding:2rem;color:#c9d1d9"><i class="fa fa-spinner fa-spin"></i></td></tr>';
  fetch(`${API}?action=reorder_alerts&upload_id=${UPLOAD_ID}`)
    .then(r=>r.json()).then(d=>{
      setText('alertCount', d.ok ? `${d.count} SKU` : '');
      if (!d.ok || !d.count) {
        document.getElementById('alertBody').innerHTML =
          '<tr><td colspan="10" style="text-align:center;padding:2rem;color:#c9d1d9">ไม่มี Reorder Alert</td></tr>';
        return;
      }
      const TC = {TOP1_20:'#ffa198',TOP21_40:'#f0c14b',TOP41_60:'#7ee787'};
      const TL = {TOP1_20:'< 6.5w',TOP21_40:'< 5w',TOP41_60:'< 3w'};
      document.getElementById('alertBody').innerHTML = d.data.map(a => {
        const wc = a.total_week_cover >= 999 ? '∞' : (+a.total_week_cover).toFixed(1)+'w';
        return `<tr>
          <td style="text-align:center"><strong style="color:#ffa198">#${a.rank_position}</strong></td>
          <td><span style="background:rgba(0,0,0,.3);border:1px solid ${TC[a.tier]};color:${TC[a.tier]};border-radius:3px;padding:.1rem .35rem;font-size:.7rem">${a.tier}</span></td>
          <td style="font-family:monospace;font-size:.75rem;color:#fff">${a.barcode}</td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#fff">${esc(a.product_name)}</td>
          <td style="text-align:right;color:#fff">${fmt(a.total_stock)}</td>
          <td style="text-align:right;color:#fff">${fmt(a.total_qty_sold)}</td>
          <td style="text-align:right;color:#fff">${(+a.total_weekly_rate).toFixed(2)}</td>
          <td><strong style="color:#ffa198">${wc}</strong></td>
          <td style="text-align:center;color:#fff">${a.target_weeks}w</td>
          <td style="text-align:center;color:#ffa198;font-size:.74rem">${TL[a.tier]||''}</td>
        </tr>`;
      }).join('');
    });
}

// ── Calculate ─────────────────────────────────────────────────
function calculatePlans() {
  if (!UPLOAD_ID) { showToast('ไม่มี Upload ID','error'); return; }
  if (!confirm('คำนวณและบันทึกแผนใหม่?\nแผน PENDING เดิมจะถูกลบทั้งหมด')) return;
  showSpinner('กำลังคำนวณแผนทั้งหมด...');
  fetch(`${API}?action=calculate&upload_id=${UPLOAD_ID}`, {method:'POST'})
    .then(r=>r.json()).then(d=>{
      hideSpinner();
      if (d.ok) {
        const s = d.summary;
        showToast(`✅ สร้างแผนสำเร็จ!  TF Best: ${s.tf_best} | Refill: ${s.refill} | รวม ${s.total_qty} ชิ้น`,'success');
        loadStats();
      } else showToast('❌ '+(d.error||'Unknown error'),'error');
    }).catch(e=>{ hideSpinner(); showToast('Network error: '+e,'error'); });
}

// ── Approve / Reject ──────────────────────────────────────────
function approvePlan(id) {
  fetch(`${API}?action=approve&upload_id=${UPLOAD_ID}`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ids:[id]})
  }).then(r=>r.json()).then(d=>{
    if (d.ok) { document.getElementById('plan-'+id)?.remove(); showToast('Approved ✓','success'); loadStats(); }
    else showToast('Error: '+(d.error||''),'error');
  });
}
function rejectPlan(id) {
  const reason = prompt('เหตุผล (optional):');
  if (reason === null) return;
  fetch(`${API}?action=reject&upload_id=${UPLOAD_ID}`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id, reason})
  }).then(r=>r.json()).then(d=>{
    if (d.ok) { document.getElementById('plan-'+id)?.remove(); showToast('Rejected','success'); }
  });
}
function approveAll(type) {
  if (!confirm(`Approve แผน ${type} ทั้งหมดที่รอดำเนินการ?`)) return;
  fetch(`${API}?action=approve&upload_id=${UPLOAD_ID}`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({all:true, plan_type:type})
  }).then(r=>r.json()).then(d=>{
    if (d.ok) {
      showToast(`Approved ${d.approved} แผน ✓`,'success');
      if (type==='TF_BEST') loadTFPlans(); else loadRefillPlans();
      loadStats();
    }
  });
}

// ── Plan Tabs ─────────────────────────────────────────────────
function switchPlanTab(tab) {
  ['tf','refill'].forEach(t => {
    document.getElementById('planTab-'+t)?.classList.toggle('active', t===tab);
  });
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    b.classList.toggle('active', (i===0&&tab==='tf')||(i===1&&tab==='refill'));
  });
}

// ── Sort Table ────────────────────────────────────────────────
let sortDir = {};
function sortTable(th, idx) {
  const asc = sortDir[idx] !== 1;
  sortDir = {}; sortDir[idx] = asc ? 1 : -1;
  document.querySelectorAll('#stockTable th').forEach(h => {
    const sp = h.querySelector('.sort-icon');
    if (sp) sp.textContent = h===th ? (asc?'▲':'▼') : '⇅';
  });
  const keys = ['store_short','barcode','product_name','stock','qty_sold','daily_rate','week_cover','need_14d','diff','holding_days'];
  allStockData.sort((a,b) => {
    const va = a[keys[idx]], vb = b[keys[idx]];
    if (typeof va === 'number') return asc ? va-vb : vb-va;
    return asc ? String(va||'').localeCompare(String(vb||'')) : String(vb||'').localeCompare(String(va||''));
  });
  renderStock(allStockData);
}

// ── Helpers ───────────────────────────────────────────────────
function showSpinner(msg='') { document.getElementById('spinnerMsg').textContent=msg; document.getElementById('spinner').classList.add('show'); }
function hideSpinner() { document.getElementById('spinner').classList.remove('show'); }
function showToast(msg, type='success') {
  const t = document.createElement('div');
  t.className = 'toast-item '+type;
  t.textContent = msg;
  document.getElementById('toastArea').appendChild(t);
  setTimeout(() => t.remove(), 5000);
}
function fmt(n) { return parseInt(n||0).toLocaleString('th'); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function setText(id, html, asHTML=false) {
  const el = document.getElementById(id);
  if (el) asHTML ? el.innerHTML=html : el.textContent=html;
}

// ── Init ──────────────────────────────────────────────────────
if (UPLOAD_ID) {
  loadStats();
  // Status counts
  ['critical','warning','overstock'].forEach(status => {
    fetch(`${API}?action=stock_summary&upload_id=${UPLOAD_ID}&status=${status}`)
      .then(r=>r.json()).then(d => {
        if (d.ok) {
          const ids = {critical:'st-critical',warning:'st-warning',overstock:'st-over'};
          setText(ids[status], d.count);
        }
      });
  });
}
</script>
</body>
</html>