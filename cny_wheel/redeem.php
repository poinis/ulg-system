<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$shop_name = getShopName($username);

// ดึง role จาก DB
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$role = $stmt->fetchColumn() ?: '';

// สรุปการใช้สิทธิ์ของร้านนี้
$summary_sql = "SELECT 
    COUNT(*) as total_spins,
    SUM(is_redeemed = 1) as redeemed,
    SUM(is_redeemed = 0) as not_redeemed
    FROM cny_spin_log WHERE shop_name = ?";
$stmt = $pdo->prepare($summary_sql);
$stmt->execute([$shop_name]);
$summary = $stmt->fetch();

// รายการล่าสุด 20 รายการ
$recent_sql = "SELECT l.*, u.name as user_name 
               FROM cny_spin_log l 
               LEFT JOIN users u ON l.user_id = u.id 
               WHERE l.shop_name = ? 
               ORDER BY l.spun_at DESC LIMIT 20";
$stmt = $pdo->prepare($recent_sql);
$stmt->execute([$shop_name]);
$recent = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>🎫 ตรวจสอบ/ใช้สิทธิ์</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700;900&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background: #F5F5F5;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* Top bar */
        .topbar {
            background: linear-gradient(135deg, #8B0000, #DC143C);
            padding: 16px 20px;
            color: #FFD700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar h1 { font-size: 18px; }
        .topbar a { color: #FFE4B5; text-decoration: none; font-size: 13px; }

        .container { max-width: 500px; margin: 0 auto; padding: 16px; }

        /* Search box */
        .search-box {
            background: #FFF;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 16px;
        }
        .search-box label {
            font-weight: 700;
            color: #8B0000;
            font-size: 15px;
            display: block;
            margin-bottom: 10px;
        }
        .search-row {
            display: flex;
            gap: 10px;
        }
        .search-row input {
            flex: 1;
            padding: 14px 16px;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 16px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-row input:focus { border-color: #DC143C; }
        .search-btn {
            padding: 14px 20px;
            background: #DC143C;
            color: #FFF;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
        }
        .search-btn:active { background: #B71C1C; }

        /* Result card */
        .result-card {
            display: none;
            background: #FFF;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 16px;
            animation: slideUp 0.3s ease;
        }
        .result-card.show { display: block; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .prize-banner {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 16px;
        }
        .prize-banner.not-redeemed {
            background: linear-gradient(135deg, #FFF8E1, #FFECB3);
            border: 2px solid #FFD700;
        }
        .prize-banner.redeemed {
            background: #F5F5F5;
            border: 2px solid #E0E0E0;
        }
        .prize-banner .icon { font-size: 48px; margin-bottom: 8px; }
        .prize-banner .prize-name {
            font-size: 28px;
            font-weight: 900;
            color: #DC143C;
        }
        .prize-banner.redeemed .prize-name { color: #999; }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #F0F0F0;
            font-size: 14px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #888; }
        .detail-value { font-weight: 600; color: #333; }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }
        .status-badge.unused { background: #E8F5E9; color: #2E7D32; }
        .status-badge.used { background: #FFEBEE; color: #C62828; }

        .redeem-btn {
            display: block;
            width: 100%;
            padding: 16px;
            margin-top: 16px;
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            color: #FFF;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 900;
            cursor: pointer;
            font-family: inherit;
        }
        .redeem-btn:active { transform: scale(0.98); }
        .redeem-btn:disabled {
            background: #E0E0E0;
            color: #999;
            cursor: not-allowed;
        }

        .already-used {
            text-align: center;
            padding: 16px;
            background: #FFEBEE;
            border-radius: 12px;
            margin-top: 16px;
            color: #C62828;
            font-weight: 700;
        }

        /* Summary cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        .s-card {
            background: #FFF;
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .s-card .s-num { font-size: 28px; font-weight: 900; }
        .s-card .s-label { font-size: 11px; color: #888; margin-top: 4px; }
        .s-card.green .s-num { color: #2E7D32; }
        .s-card.red .s-num { color: #DC143C; }
        .s-card.blue .s-num { color: #1565C0; }

        /* Recent list */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin: 20px 0 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .recent-list { display: flex; flex-direction: column; gap: 8px; }
        .recent-item {
            background: #FFF;
            border-radius: 10px;
            padding: 14px 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recent-left .r-bill { font-weight: 700; font-size: 14px; color: #333; }
        .recent-left .r-date { font-size: 12px; color: #999; margin-top: 2px; }
        .recent-right { text-align: right; }
        .recent-right .r-prize { font-weight: 700; font-size: 14px; color: #DC143C; }
        .recent-right .r-status { font-size: 11px; margin-top: 2px; }
        .r-status.used { color: #999; }
        .r-status.unused { color: #2E7D32; font-weight: 600; }

        /* Confirm modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .confirm-modal {
            background: #FFF;
            border-radius: 16px;
            padding: 30px 24px;
            width: 90%;
            max-width: 360px;
            text-align: center;
            animation: modalPop 0.3s ease;
        }
        @keyframes modalPop {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .confirm-modal .cm-icon { font-size: 48px; margin-bottom: 12px; }
        .confirm-modal h3 { color: #333; font-size: 18px; margin-bottom: 8px; }
        .confirm-modal p { color: #666; font-size: 14px; margin-bottom: 6px; }
        .confirm-modal .cm-prize { font-size: 22px; font-weight: 900; color: #DC143C; margin: 12px 0; }
        .confirm-modal .cm-bill { color: #888; font-size: 13px; }
        .cm-buttons { display: flex; gap: 10px; margin-top: 20px; }
        .cm-btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }
        .cm-btn.cancel { background: #E0E0E0; color: #666; }
        .cm-btn.confirm { background: #2E7D32; color: #FFF; }
        .cm-btn.confirm:active { background: #1B5E20; }

        /* Success toast */
        .toast {
            display: none;
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: #2E7D32;
            color: #FFF;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            z-index: 200;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            animation: toastIn 0.3s ease;
        }
        .toast.show { display: block; }
        @keyframes toastIn {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }

        .no-data { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>🎫 ตรวจสอบ / ใช้สิทธิ์</h1>
        <a href="wheel.php">← กลับกงล้อ</a>
    </div>

    <div class="container">
        <!-- Search -->
        <div class="search-box">
            <label>🔍 ค้นหาเลขที่บิล</label>
            <div class="search-row">
                <input type="text" id="searchBill" placeholder="เลขที่บิล" autocomplete="off"
                       onkeydown="if(event.key==='Enter')searchBill()">
                <button class="search-btn" onclick="searchBill()">ค้นหา</button>
            </div>
        </div>

        <!-- Search Result -->
        <div class="result-card" id="resultCard">
            <div class="prize-banner" id="prizeBanner">
                <div class="icon" id="prizeIcon"></div>
                <div class="prize-name" id="prizeName"></div>
            </div>
            <div id="detailRows"></div>
            <div id="actionArea"></div>
        </div>

        <!-- Summary -->
        <div class="summary-cards">
            <div class="s-card blue">
                <div class="s-num"><?= $summary['total_spins'] ?? 0 ?></div>
                <div class="s-label">หมุนทั้งหมด</div>
            </div>
            <div class="s-card green">
                <div class="s-num"><?= $summary['redeemed'] ?? 0 ?></div>
                <div class="s-label">ใช้สิทธิ์แล้ว</div>
            </div>
            <div class="s-card red">
                <div class="s-num"><?= $summary['not_redeemed'] ?? 0 ?></div>
                <div class="s-label">ยังไม่ใช้</div>
            </div>
        </div>

        <!-- Recent list -->
        <div class="section-title">
            📋 รายการล่าสุด (<?= htmlspecialchars($shop_name) ?>)
        </div>
        <div class="recent-list">
            <?php if (empty($recent)): ?>
                <div class="no-data">ยังไม่มีข้อมูล</div>
            <?php else: ?>
                <?php foreach ($recent as $r): ?>
                <div class="recent-item" onclick="document.getElementById('searchBill').value='<?= htmlspecialchars($r['bill_number']) ?>';searchBill();">
                    <div class="recent-left">
                        <div class="r-bill"><?= htmlspecialchars($r['bill_number']) ?></div>
                        <div class="r-date"><?= date('d/m H:i', strtotime($r['spun_at'])) ?></div>
                    </div>
                    <div class="recent-right">
                        <div class="r-prize">
                            <?php
                            if (in_array($r['prize_name'], ['50%','30%','20%','15%'])) {
                                echo 'ส่วนลด '.$r['prize_name'];
                            } else {
                                echo htmlspecialchars($r['prize_name']);
                            }
                            ?>
                        </div>
                        <div class="r-status <?= $r['is_redeemed'] ? 'used' : 'unused' ?>">
                            <?= $r['is_redeemed'] 
                                ? '✓ ใช้แล้ว' . ($r['redeemed_shop'] ? ' @ '.htmlspecialchars($r['redeemed_shop']) : '')
                                : '○ ยังไม่ใช้' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="confirm-modal">
            <div class="cm-icon">⚠️</div>
            <h3>ยืนยันใช้สิทธิ์?</h3>
            <p>รางวัล:</p>
            <div class="cm-prize" id="cmPrize"></div>
            <div class="cm-bill" id="cmBill"></div>
            <div class="cm-buttons">
                <button class="cm-btn cancel" onclick="closeConfirm()">ยกเลิก</button>
                <button class="cm-btn confirm" id="confirmBtn" onclick="confirmRedeem()">✓ ยืนยันใช้สิทธิ์</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast">✅ ใช้สิทธิ์เรียบร้อยแล้ว!</div>

    <script>
    let currentSpinId = null;

    function searchBill() {
        const bill = document.getElementById('searchBill').value.trim();
        if (!bill) { alert('กรุณากรอกเลขที่บิล'); return; }

        fetch('redeem_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'search', bill_number: bill })
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                document.getElementById('resultCard').classList.remove('show');
                return;
            }
            showResult(data.data);
        })
        .catch(() => alert('เกิดข้อผิดพลาด'));
    }

    function showResult(d) {
        const card = document.getElementById('resultCard');
        const banner = document.getElementById('prizeBanner');
        const isRedeemed = d.is_redeemed === 1;

        // Icon
        let icon = '🎫';
        if (d.prize_name === 'เสื้อ') icon = '👕';
        else if (d.prize_name === 'หมวก') icon = '🧢';
        else if (d.prize_name === '50%') icon = '🔥';
        else if (d.prize_name === '30%') icon = '⭐';
        else if (d.prize_name === '20%') icon = '✨';
        else if (d.prize_name === '15%') icon = '🎫';

        document.getElementById('prizeIcon').textContent = icon;
        document.getElementById('prizeName').textContent = d.prize_display;

        banner.className = 'prize-banner ' + (isRedeemed ? 'redeemed' : 'not-redeemed');

        // Details
        document.getElementById('detailRows').innerHTML = `
            <div class="detail-row">
                <span class="detail-label">เลขที่บิล</span>
                <span class="detail-value">${escHtml(d.bill_number)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">สาขาที่หมุน</span>
                <span class="detail-value">${escHtml(d.shop_name)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">เวลาหมุน</span>
                <span class="detail-value">${d.spun_at}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">สถานะ</span>
                <span class="detail-value">
                    ${isRedeemed 
                        ? '<span class="status-badge used">❌ ใช้สิทธิ์แล้ว</span>' 
                        : '<span class="status-badge unused">✅ ยังไม่ใช้สิทธิ์</span>'}
                </span>
            </div>
            ${isRedeemed && d.redeemed_at ? `
            <div class="detail-row">
                <span class="detail-label">ใช้สิทธิ์เมื่อ</span>
                <span class="detail-value">${d.redeemed_at}</span>
            </div>` : ''}
            ${isRedeemed && d.redeemed_shop ? `
            <div class="detail-row">
                <span class="detail-label">สาขาที่ใช้สิทธิ์</span>
                <span class="detail-value" style="color:#2E7D32;font-weight:700">${escHtml(d.redeemed_shop)}</span>
            </div>` : ''}
        `;

        // Action
        if (isRedeemed) {
            document.getElementById('actionArea').innerHTML = `
                <div class="already-used">⛔ สิทธิ์นี้ถูกใช้ไปแล้ว</div>`;
        } else {
            currentSpinId = d.id;
            document.getElementById('actionArea').innerHTML = `
                <button class="redeem-btn" onclick="showConfirm('${escHtml(d.prize_display)}','${escHtml(d.bill_number)}')">
                    🎫 กดใช้สิทธิ์
                </button>`;
        }

        card.classList.add('show');
    }

    function showConfirm(prize, bill) {
        document.getElementById('cmPrize').textContent = prize;
        document.getElementById('cmBill').textContent = 'บิล: ' + bill;
        document.getElementById('confirmModal').classList.add('show');
    }

    function closeConfirm() {
        document.getElementById('confirmModal').classList.remove('show');
    }

    function confirmRedeem() {
        if (!currentSpinId) return;
        document.getElementById('confirmBtn').disabled = true;
        document.getElementById('confirmBtn').textContent = 'กำลังดำเนินการ...';

        fetch('redeem_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'redeem', spin_id: currentSpinId })
        })
        .then(r => r.json())
        .then(data => {
            closeConfirm();
            if (data.error) {
                alert(data.error);
            } else {
                // Show toast
                const toast = document.getElementById('toast');
                toast.classList.add('show');
                setTimeout(() => { toast.classList.remove('show'); }, 2500);
                // Reload to update summary
                setTimeout(() => location.reload(), 1500);
            }
        })
        .catch(() => {
            closeConfirm();
            alert('เกิดข้อผิดพลาด');
        });
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    </script>
</body>
</html>
