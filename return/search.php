<?php
require 'config.php';

// รับค่า filter
$search_q      = $_GET['q'] ?? '';
$store_filter  = $_GET['store'] ?? '';

// ดึงรายชื่อสาขาจาก Master
$stores = $conn->query("SELECT store_code, store_name FROM stores_return WHERE is_active = 1 ORDER BY store_code ASC");

// ทำงานเฉพาะเมื่อมีการระบุเลขบิล หรือ เลือกสาขาเท่านั้น
$result = null;
if ($search_q !== '' || $store_filter !== '') {
    $sql = "SELECT s.* 
            FROM sales_return s 
            LEFT JOIN returns r 
              ON s.GL_NUMERO COLLATE utf8mb4_general_ci = r.old_number COLLATE utf8mb4_general_ci
            WHERE r.old_number IS NULL";
    
    $params = [];
    $types  = "";

    if ($store_filter !== '') {
        $sql .= " AND s.GL_SOUCHE = ?";
        $params[] = $store_filter;
        $types    .= "s";
    }
    if ($search_q !== '') {
        $sql .= " AND s.GL_NUMERO LIKE ?";
        $params[] = "%$search_q%";
        $types    .= "s";
    }

    $sql .= " ORDER BY s.GL_NUMERO ASC, s.GP_REFINTERNE DESC LIMIT 200";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Return without Receipt - Search</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Sarabun', Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}
.container {
    max-width: 1600px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    padding: 30px;
}
h2 {
    color: #333;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 25px;
    border-left: 5px solid #667eea;
    padding-left: 15px;
}
.search-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.form-row {
    display: inline-block;
    margin-right: 20px;
    margin-bottom: 10px;
}
.form-row label {
    display: inline-block;
    font-weight: 600;
    color: #555;
    margin-right: 8px;
}
.form-row input,
.form-row select {
    padding: 8px 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s;
}
.form-row input:focus,
.form-row select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}
button, .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.btn-search {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 20px;
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 14px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
}
td {
    padding: 10px 12px;
    border-bottom: 1px solid #e9ecef;
    font-size: 13px;
}
tr:hover {
    background-color: #fffde7;
}
tr:last-child td {
    border-bottom: none;
}
/* สีพื้นหลังสลับสำหรับบิลเดียวกัน */
.bill-color-1 { background-color: #e3f2fd; }
.bill-color-2 { background-color: #f3e5f5; }
.bill-color-3 { background-color: #e8f5e9; }
.bill-color-4 { background-color: #fff3e0; }
.bill-color-5 { background-color: #fce4ec; }

.price-badge {
    background: #4caf50;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 12px;
}
.btn-change-group {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    position: sticky;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 100;
    transition: opacity 0.3s;
}
.btn-change-group:hover {
    opacity: 0.9;
    box-shadow: 0 6px 16px rgba(0,0,0,0.4);
}
.btn-change-group:disabled {
    background: #ccc;
    cursor: not-allowed;
    opacity: 0.6;
}
.sticky-footer {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 15px;
    text-align: center;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    margin: 0 -30px -30px -30px;
    border-radius: 0 0 12px 12px;
}

/* Modal Popup */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.7);
    animation: fadeIn 0.3s;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 1000px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 15px 50px rgba(0,0,0,0.3);
    animation: slideDown 0.3s;
}
@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 30px;
    position: relative;
}
.modal-header h2 {
    color: white;
    margin: 0;
    border: none;
    padding: 0;
    font-size: 24px;
}
.close {
    position: absolute;
    right: 20px;
    top: 20px;
    color: white;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}
.close:hover {
    transform: rotate(90deg);
}
.modal-body {
    padding: 30px;
    max-height: calc(90vh - 180px);
    overflow-y: auto;
}
.item-card {
    background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid #667eea;
}
.item-card strong {
    color: #667eea;
}
.price-display {
    background: #fff3e0;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 6px;
    border-left: 4px solid #ff9800;
    font-size: 14px;
    display: inline-block;
}
.price-display strong {
    color: #ff6f00;
    font-size: 16px;
}
.form-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    border: 2px solid #e9ecef;
}
.form-section h3 {
    color: #667eea;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 15px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: inline-block;
    width: 140px;
    font-weight: 600;
    color: #555;
}
.form-group label .required {
    color: red;
    font-weight: bold;
}
.form-group input {
    padding: 8px 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s;
}
.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}
.form-group input.error {
    border-color: #f44336;
}
.product-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
    border: 2px dashed #4caf50;
}
.product-section h3 {
    color: #4caf50;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 15px;
}
.product-main {
    background: linear-gradient(135deg, #e8f5e9 0%, #e1f5fe 100%);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid #4caf50;
}
.product-extra {
    background: #fff3e0;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
    border-left: 4px solid #ff9800;
    animation: slideIn 0.3s;
}
@keyframes slideIn {
    from {
        transform: translateX(-20px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
.product-extra h4 {
    color: #ff9800;
    font-size: 14px;
    margin-bottom: 10px;
}
.product-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.product-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.product-input-group label {
    font-weight: 600;
    color: #555;
    min-width: 110px;
}
.product-input-group label .required {
    color: red;
    font-weight: bold;
}
.btn-add {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    margin-top: 10px;
}
.btn-remove {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
}
.btn-submit {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    border: none;
    font-size: 16px;
    padding: 12px 30px;
    font-weight: 700;
    margin-top: 20px;
}
.btn-cancel {
    background: #6c757d;
    color: white;
    margin-left: 10px;
    padding: 12px 30px;
}
.upload-section {
    background: #fff3e0;
    border: 2px solid #ff9800;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}
.upload-section h4 {
    color: #ff6f00;
    margin-bottom: 10px;
    font-size: 16px;
}
.upload-section label {
    font-weight: 600;
    color: #555;
    display: inline-block;
    margin-right: 10px;
}
.upload-section label .required {
    color: red;
    font-weight: bold;
}
.badge {
    background: #ff9800;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}

/* ยืนยันข้อมูล */
.confirm-section {
    background: #fff9c4;
    border: 2px solid #fbc02d;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}
.confirm-section h3 {
    color: #f57f17;
    font-size: 18px;
    margin-bottom: 15px;
}
.confirm-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    color: #333;
    cursor: pointer;
    padding: 10px;
    background: white;
    border-radius: 6px;
}
.confirm-checkbox input[type="checkbox"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
}
.error-message {
    background: #ffebee;
    color: #c62828;
    padding: 10px 15px;
    border-radius: 6px;
    margin-top: 10px;
    border-left: 4px solid #f44336;
    font-size: 14px;
}
</style>
</head>
<body>

<div class="container">
    <h2>🔄 ค้นหาใบเสร็จเพื่อเปลี่ยนสินค้า</h2>

    <div class="search-box">
        <form method="get" action="">
            <div class="form-row">
                <label>เลขที่บิล:</label>
                <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" placeholder="ค้นหาเลขบิล">
            </div>
            <div class="form-row">
                <label>สาขา:</label>
                <select name="store">
                    <option value="">-- ทั้งหมด --</option>
                    <?php 
                    $stores->data_seek(0);
                    while ($st = $stores->fetch_assoc()): 
                    ?>
                    <option value="<?= $st['store_code'] ?>"
                            <?= ($store_filter == $st['store_code']) ? 'selected' : '' ?>>
                        <?= $st['store_code'] ?> - <?= htmlspecialchars($st['store_name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn-search">ค้นหา</button>
        </form>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>

    <h3 style="color:#667eea; font-size:18px; margin-bottom:15px;">
        📦 รายการใบเสร็จที่พบ <span class="badge"><?= $result->num_rows ?> รายการ</span>
    </h3>
    <p style="color:#999; margin-bottom:15px;">💡 <strong>เลือกได้หลายรายการในบิลเดียวกัน</strong> แล้วกดปุ่ม "เปลี่ยนสินค้าที่เลือก" ด้านล่าง</p>

    <form id="changeForm">
    <table>
    <thead>
    <tr>
        <th style="width:40px;">
            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
        </th>
        <th>เลขที่บิล</th>
        <th>วันที่ขาย</th>
        <th>สาขา</th>
        <th>แบรนด์</th>
        <th>บาร์โค้ดเดิม</th>
        <th>รายละเอียดสินค้า</th>
        <th style="text-align:center;">จำนวน</th>
        <th style="text-align:center;">ราคาเดิม</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    $lastBillNo = '';
    $colorIndex = 0;
    $colors = ['bill-color-1', 'bill-color-2', 'bill-color-3', 'bill-color-4', 'bill-color-5'];
    
    while ($row = $result->fetch_assoc()):
        $billNo = $row['GL_NUMERO'];
        $saleDate = $row['GL_DATEPIECE'];
        $storeCode = $row['GL_SOUCHE'];
        $brand = $row['C22'];
        $barcode = $row['GL_REFARTBARRE'];
        $itemDesc = $row['GL_LIBELLE'];
        $qty = $row['GL_QTEFACT'] ?? 1;
        $oldPrice = $row['GL_TOTALTTC'] ?? 0;
        
        // เปลี่ยนสีเมื่อเจอบิลใหม่
        if ($billNo !== $lastBillNo) {
            $colorIndex = ($colorIndex + 1) % count($colors);
            $lastBillNo = $billNo;
        }
        $rowColor = $colors[$colorIndex];
    ?>
    <tr class="<?= $rowColor ?>" data-bill="<?= htmlspecialchars($billNo) ?>">
        <td>
            <input type="checkbox" 
                   class="item-checkbox" 
                   data-bill="<?= htmlspecialchars($billNo) ?>"
                   data-date="<?= htmlspecialchars($saleDate) ?>"
                   data-store="<?= htmlspecialchars($storeCode) ?>"
                   data-brand="<?= htmlspecialchars($brand) ?>"
                   data-barcode="<?= htmlspecialchars($barcode) ?>"
                   data-desc="<?= htmlspecialchars($itemDesc) ?>"
                   data-qty="<?= $qty ?>"
                   data-price="<?= $oldPrice ?>"
                   onchange="updateChangeButton()">
        </td>
        <td><strong><?= htmlspecialchars($billNo) ?></strong></td>
        <td><?= $saleDate ?></td>
        <td><?= $storeCode ?></td>
        <td><?= htmlspecialchars($brand) ?></td>
        <td><?= htmlspecialchars($barcode) ?></td>
        <td><?= htmlspecialchars($itemDesc) ?></td>
        <td style="text-align:center;"><?= $qty ?></td>
        <td style="text-align:center;">
            <span class="price-badge"><?= number_format($oldPrice, 2) ?></span>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
    </table>
    </form>

    <div class="sticky-footer">
        <button type="button" 
                id="btnChangeSelected" 
                class="btn-change-group" 
                onclick="openChangeModal()"
                disabled>
            🔄 เปลี่ยนสินค้าที่เลือก (<span id="selectedCount">0</span> รายการ)
        </button>
    </div>

    <?php elseif ($search_q !== '' || $store_filter !== ''): ?>
        <p style="color:#999; margin-top:30px; text-align:center; font-size:16px;">ไม่พบข้อมูลใบเสร็จที่ตรงกับเงื่อนไข</p>
    <?php else: ?>
        <p style="color:#999; margin-top:30px; text-align:center; font-size:16px;">กรุณาระบุเลขบิลหรือเลือกสาขาเพื่อค้นหา</p>
    <?php endif; ?>
</div>

<!-- Modal Popup สำหรับฟอร์มเปลี่ยนสินค้า -->
<div id="returnModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🔄 ฟอร์มเปลี่ยนสินค้า (<span id="modal_item_count">0</span> รายการ)</h2>
            <span class="close" onclick="closeReturnModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <form method="post" action="return_save.php" enctype="multipart/form-data" id="returnForm" onsubmit="return validateForm()">
                
                <div id="items-container"></div>

                <div class="form-section">
                    <h3>📋 ข้อมูลการเปลี่ยน</h3>
                    <div class="form-group">
                        <label>📅 วันที่เปลี่ยน <span class="required">*</span>:</label>
                        <input type="date" 
                               name="common_ret_date" 
                               id="common_ret_date"
                               value="<?= date('Y-m-d') ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label>🧾 เลขที่บิลใหม่ <span class="required">*</span>:</label>
                        <input type="text" 
                               name="common_new_number" 
                               id="common_new_number"
                               placeholder="เลขบิลใหม่" 
                               style="width:200px;"
                               required>
                    </div>
                </div>

                <div class="upload-section">
                    <h4>📷 แนบรูปภาพ (บังคับทั้ง 2 รูป)</h4>
                    <div style="margin-bottom:10px;">
                        <label>รูปบิลเดิม <span class="required">*</span>:</label>
                        <input type="file" 
                               name="common_img_old" 
                               id="common_img_old"
                               accept="image/*"
                               required>
                    </div>
                    <div>
                        <label>รูปบิลใหม่ <span class="required">*</span>:</label>
                        <input type="file" 
                               name="common_img_new" 
                               id="common_img_new"
                               accept="image/*"
                               required>
                    </div>
                </div>

                <div id="error-container"></div>

                <!-- ส่วนยืนยันข้อมูล -->
                <div class="confirm-section">
                    <h3>✅ ยืนยันข้อมูลก่อนบันทึก</h3>
                    <label class="confirm-checkbox">
                        <input type="checkbox" id="confirmCheck" onchange="toggleSubmit()">
                        <span>ข้าพเจ้าได้ตรวจสอบข้อมูลทั้งหมดแล้ว และยืนยันว่าถูกต้อง</span>
                    </label>
                </div>

                <div style="text-align:center;">
                    <button type="submit" class="btn-submit" id="btnFinalSubmit" disabled>
                        ✅ บันทึกการเปลี่ยนสินค้าทั้งหมด
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeReturnModal()">❌ ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let selectedItems = [];

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateChangeButton();
}

function updateChangeButton() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('btnChangeSelected').disabled = (count === 0);
}

function openChangeModal() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('กรุณาเลือกรายการที่ต้องการเปลี่ยน');
        return;
    }
    
    // เช็คว่าเป็นบิลเดียวกันหรือไม่
    const billNumbers = new Set();
    checkboxes.forEach(cb => {
        billNumbers.add(cb.dataset.bill);
    });
    
    if (billNumbers.size > 1) {
        alert('กรุณาเลือกสินค้าจากบิลเดียวกันเท่านั้น');
        return;
    }
    
    selectedItems = [];
    checkboxes.forEach(cb => {
        selectedItems.push({
            bill: cb.dataset.bill,
            date: cb.dataset.date,
            store: cb.dataset.store,
            brand: cb.dataset.brand,
            barcode: cb.dataset.barcode,
            desc: cb.dataset.desc,
            qty: cb.dataset.qty,
            price: cb.dataset.price
        });
    });
    
    document.getElementById('modal_item_count').textContent = selectedItems.length;
    
    // สร้างการ์ดสินค้าแต่ละชิ้น
    let html = '<h3 style="color:#667eea; margin-bottom:15px;">📦 สินค้าเดิมที่จะเปลี่ยน</h3>';
    
    selectedItems.forEach((item, idx) => {
        html += `
        <div class="item-card">
            <strong>ชิ้นที่ ${idx + 1}:</strong> ${item.desc}<br>
            <strong>บาร์โค้ดเดิม:</strong> ${item.barcode} | 
            <strong>จำนวน:</strong> ${item.qty}
            <div class="price-display">
                <strong>💰 ราคาเดิม: ${parseFloat(item.price).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</strong>
            </div>
            
            <input type="hidden" name="rows[${item.bill}][${item.barcode}][selected]" value="1">
            <input type="hidden" name="rows[${item.bill}][${item.barcode}][qty_return]" value="${item.qty}">
            
            <div class="product-section" style="margin-top:15px;">
                <h3>🎁 สินค้าใหม่สำหรับชิ้นนี้</h3>
                <div class="product-main">
                    <div class="product-row">
                        <div class="product-input-group">
                            <label>บาร์โค้ดใหม่ <span class="required">*</span>:</label>
                            <input type="text" 
                                   name="rows[${item.bill}][${item.barcode}][ret_barcode]" 
                                   class="barcode-input"
                                   placeholder="13 หลัก" 
                                   maxlength="13"
                                   pattern="[0-9]{13}"
                                   style="width:200px;"
                                   required
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="product-input-group">
                            <label>ราคาใหม่ <span class="required">*</span>:</label>
                            <input type="number" 
                                   step="0.01" 
                                   min="0.01"
                                   name="rows[${item.bill}][${item.barcode}][price_return]" 
                                   class="price-input"
                                   placeholder="0.00" 
                                   style="width:120px;"
                                   required>
                        </div>
                    </div>
                </div>
                
                <div id="extra-wrapper-${idx}"></div>
                
                <button type="button" class="btn-add" onclick="addExtraProduct(${idx}, '${item.bill}', '${item.barcode}')">
                    ➕ เพิ่มสินค้าใหม่ชิ้นถัดไป
                </button>
                <small style="color:#999; margin-left:10px;">(เพิ่มได้สูงสุด 9 ชิ้น)</small>
            </div>
        </div>
        `;
    });
    
    document.getElementById('items-container').innerHTML = html;
    document.getElementById('confirmCheck').checked = false;
    document.getElementById('btnFinalSubmit').disabled = true;
    document.getElementById('error-container').innerHTML = '';
    document.getElementById('returnModal').style.display = 'block';
}

const extraCounts = {};

function addExtraProduct(itemIdx, bill, barcode) {
    const wrapper = document.getElementById('extra-wrapper-' + itemIdx);
    const key = itemIdx + '_' + barcode;
    
    if (!extraCounts[key]) extraCounts[key] = 0;
    
    if (extraCounts[key] >= 9) {
        alert('เพิ่มได้สูงสุด 10 ชิ้น (รวมชิ้นหลัก)');
        return;
    }
    
    extraCounts[key]++;
    const num = extraCounts[key] + 1;
    
    const div = document.createElement('div');
    div.className = 'product-extra';
    
    div.innerHTML =
        '<h4>ชิ้นที่ ' + num + ' (เพิ่มเติม)</h4>' +
        '<div class="product-row">' +
        '  <div class="product-input-group">' +
        '    <label>บาร์โค้ดใหม่ <span class="required">*</span>:</label>' +
        '    <input type="text" ' +
        '      name="extra_barcode[' + bill + '][' + barcode + '][]" ' +
        '      class="barcode-input"' +
        '      placeholder="13 หลัก" ' +
        '      maxlength="13"' +
        '      pattern="[0-9]{13}"' +
        '      style="width:200px;"' +
        '      required' +
        '      oninput="this.value = this.value.replace(/[^0-9]/g, \'\')">' +
        '  </div>' +
        '  <div class="product-input-group">' +
        '    <label>ราคาใหม่ <span class="required">*</span>:</label>' +
        '    <input type="number" step="0.01" min="0.01"' +
        '      name="extra_price[' + bill + '][' + barcode + '][]" ' +
        '      class="price-input"' +
        '      placeholder="0.00" ' +
        '      style="width:120px;"' +
        '      required>' +
        '  </div>' +
        '  <button type="button" class="btn-remove" onclick="removeProduct(this, \'' + key + '\')">🗑️ ลบ</button>' +
        '</div>';
    
    wrapper.appendChild(div);
}

function removeProduct(btn, key) {
    btn.closest('.product-extra').remove();
    if (extraCounts[key]) extraCounts[key]--;
}

function toggleSubmit() {
    const checked = document.getElementById('confirmCheck').checked;
    document.getElementById('btnFinalSubmit').disabled = !checked;
}

function validateForm() {
    const errors = [];
    
    // ตรวจสอบบาร์โค้ด
    const barcodes = document.querySelectorAll('.barcode-input');
    barcodes.forEach((input, idx) => {
        if (input.value.length !== 13) {
            errors.push(`บาร์โค้ดชิ้นที่ ${idx + 1} ต้องมี 13 หลักเท่านั้น (ตอนนี้มี ${input.value.length} หลัก)`);
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
    });
    
    // ตรวจสอบราคา
    const prices = document.querySelectorAll('.price-input');
    prices.forEach((input, idx) => {
        if (!input.value || parseFloat(input.value) <= 0) {
            errors.push(`ราคาชิ้นที่ ${idx + 1} ต้องมากกว่า 0`);
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
    });
    
    // ตรวจสอบรูปภาพ
    const imgOld = document.getElementById('common_img_old');
    const imgNew = document.getElementById('common_img_new');
    
    if (!imgOld.files.length) {
        errors.push('กรุณาแนบรูปบิลเดิม');
    }
    if (!imgNew.files.length) {
        errors.push('กรุณาแนบรูปบิลใหม่');
    }
    
    // ตรวจสอบเลขบิลใหม่
    const newNumber = document.getElementById('common_new_number');
    if (!newNumber.value.trim()) {
        errors.push('กรุณากรอกเลขที่บิลใหม่');
        newNumber.classList.add('error');
    } else {
        newNumber.classList.remove('error');
    }
    
    if (errors.length > 0) {
        let errorHtml = '<div class="error-message"><strong>⚠️ พบข้อผิดพลาด:</strong><ul style="margin:10px 0 0 20px;">';
        errors.forEach(err => {
            errorHtml += `<li>${err}</li>`;
        });
        errorHtml += '</ul></div>';
        
        document.getElementById('error-container').innerHTML = errorHtml;
        document.getElementById('error-container').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    
    return confirm('ยืนยันการบันทึกข้อมูลการเปลี่ยนสินค้า?');
}

function closeReturnModal() {
    document.getElementById('returnModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('returnModal');
    if (event.target == modal) {
        if (confirm('ต้องการปิดหน้าต่างนี้? ข้อมูลที่กรอกจะหายทั้งหมด')) {
            closeReturnModal();
        }
    }
}
</script>

</body>
</html>
