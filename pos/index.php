<?php
// pos/index.php - POS System (Fast Version)
session_start();
error_reporting(0);

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

$userId = $_SESSION['id'];
$username = $_SESSION['username'];

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$userBranch = $user['branch_name'] ?: 'HQ';
$userName = $user['name'] ?: $username;
$storeName = $user['department'] ?: 'PRONTO & CO';

// Check if pos_products exists
$tableExists = $conn->query("SHOW TABLES LIKE 'pos_products'")->num_rows > 0;

$products = [];
$brands = [];

if ($tableExists) {
    // ดึงจาก pos_products - เร็วมาก!
    $result = $conn->query("
        SELECT id, sku, product_name, brand, category, size, price 
        FROM pos_products 
        WHERE is_active = 1 
        ORDER BY brand, product_name
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $displayName = $row['product_name'];
            if (!empty($row['size'])) {
                $displayName .= ' [' . $row['size'] . ']';
            }
            $row['display_name'] = $displayName;
            $products[] = $row;
            
            if (!empty($row['brand']) && !in_array($row['brand'], $brands)) {
                $brands[] = $row['brand'];
            }
        }
    }
}
sort($brands);
$productCount = count($products);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS | <?= htmlspecialchars($userBranch) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,sans-serif;background:#1a1a2e;min-height:100vh;color:#fff}
        .pos-container{display:grid;grid-template-columns:1fr 420px;height:100vh}
        .products-panel{background:#16213e;display:flex;flex-direction:column;overflow:hidden}
        .panel-header{background:#0f3460;padding:15px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #1a1a2e}
        .panel-header h1{font-size:1.2em;display:flex;align-items:center;gap:10px}
        .branch-badge{background:linear-gradient(135deg,#e94560,#ff6b6b);padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600}
        .search-bar{padding:15px 20px;background:#1a1a2e}
        .search-input{width:100%;padding:14px 20px 14px 45px;background:#252542;border:2px solid #3a3a5a;border-radius:12px;color:#fff;font-size:16px}
        .search-input:focus{outline:none;border-color:#e94560}
        .search-wrapper{position:relative}
        .search-wrapper i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#666}
        .categories{display:flex;gap:8px;padding:0 20px 15px;overflow-x:auto}
        .category-btn{padding:8px 16px;background:#252542;border:none;border-radius:20px;color:#aaa;font-size:12px;cursor:pointer;white-space:nowrap}
        .category-btn:hover,.category-btn.active{background:#e94560;color:#fff}
        .products-grid{flex:1;overflow-y:auto;padding:15px 20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;align-content:start}
        .product-card{background:#252542;border-radius:15px;padding:15px;cursor:pointer;transition:all .2s;border:2px solid transparent;text-align:center}
        .product-card:hover{transform:translateY(-2px);border-color:#e94560;box-shadow:0 8px 20px rgba(233,69,96,.2)}
        .product-card .brand{font-size:10px;color:#e94560;font-weight:600;margin-bottom:5px;text-transform:uppercase}
        .product-card .name{font-size:12px;font-weight:600;margin-bottom:4px;height:36px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-height:1.5}
        .product-card .sku{font-size:9px;color:#666;margin-bottom:6px}
        .product-card .price{font-size:16px;font-weight:700;color:#2ed573}
        .cart-panel{background:#0f3460;display:flex;flex-direction:column;border-left:2px solid #1a1a2e}
        .cart-header{padding:18px 20px;background:#0a2647;border-bottom:2px solid #1a1a2e}
        .cart-header h2{font-size:1.1em;display:flex;align-items:center;gap:10px}
        .cart-count{background:#e94560;padding:2px 10px;border-radius:15px;font-size:12px}
        .cart-items{flex:1;overflow-y:auto;padding:15px}
        .cart-item{background:#16213e;border-radius:12px;padding:12px;margin-bottom:10px;display:flex;align-items:center;gap:10px}
        .cart-item-info{flex:1;min-width:0}
        .cart-item-name{font-size:13px;font-weight:600;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .cart-item-price{font-size:11px;color:#2ed573}
        .cart-item-qty{display:flex;align-items:center;gap:6px}
        .qty-btn{width:28px;height:28px;border:none;border-radius:6px;background:#252542;color:#fff;font-size:14px;cursor:pointer}
        .qty-btn:hover{background:#e94560}
        .qty-btn.delete{background:#ff6b6b}
        .qty-value{font-size:15px;font-weight:600;min-width:28px;text-align:center}
        .cart-item-total{font-size:14px;font-weight:700;color:#fbbf24;min-width:80px;text-align:right}
        .cart-empty{text-align:center;padding:40px 20px;color:#666}
        .cart-empty i{font-size:48px;margin-bottom:15px}
        .cart-summary{background:#0a2647;padding:20px;border-top:2px solid #1a1a2e}
        .summary-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:14px;color:#aaa}
        .summary-row.total{font-size:22px;font-weight:700;color:#fff;padding-top:10px;border-top:1px solid #3a3a5a;margin-top:10px}
        .summary-row.total .amount{color:#2ed573}
        .summary-row input{background:#252542;border:1px solid #3a3a5a;border-radius:6px;color:#fff;padding:8px 12px;width:120px;text-align:right;font-size:14px}
        .summary-row input:focus{outline:none;border-color:#e94560}
        .summary-row.discount .amount{color:#ff6b6b}
        .note-section{margin-top:10px;padding-top:10px;border-top:1px solid #3a3a5a}
        .note-section label{display:block;font-size:12px;color:#888;margin-bottom:5px}
        .note-section textarea{width:100%;background:#252542;border:1px solid #3a3a5a;border-radius:8px;color:#fff;padding:10px;font-size:13px;resize:none;height:50px}
        .note-section textarea:focus{outline:none;border-color:#e94560}
        .action-buttons{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px}
        .btn{padding:14px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
        .btn-clear{background:#3a3a5a;color:#fff}
        .btn-clear:hover{background:#ff6b6b}
        .btn-checkout{background:linear-gradient(135deg,#2ed573,#1abc9c);color:#fff;grid-column:span 2;font-size:17px;padding:16px}
        .btn-checkout:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(46,213,115,.3)}
        .btn-checkout:disabled{background:#3a3a5a;cursor:not-allowed;transform:none;box-shadow:none}
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;z-index:1000}
        .modal-overlay.show{display:flex}
        .modal{background:#1a1a2e;border-radius:20px;padding:25px;width:90%;max-width:380px;text-align:center}
        .modal h3{font-size:1.2em;margin-bottom:5px}
        .modal .brand-tag{font-size:11px;color:#e94560;margin-bottom:10px}
        .modal .price{font-size:28px;color:#2ed573;font-weight:700;margin-bottom:20px}
        .modal-qty{display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:20px}
        .modal-qty button{width:50px;height:50px;border:none;border-radius:15px;background:#252542;color:#fff;font-size:24px;cursor:pointer}
        .modal-qty button:hover{background:#e94560}
        .modal-qty input{width:70px;text-align:center;font-size:26px;font-weight:700;background:transparent;border:none;color:#fff}
        .modal-actions{display:flex;gap:10px}
        .modal-actions button{flex:1;padding:14px;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer}
        .btn-cancel{background:#3a3a5a;color:#fff}
        .btn-add{background:#e94560;color:#fff}
        .receipt-modal{max-width:350px}
        .receipt{background:#fff;color:#000;padding:15px;border-radius:10px;font-family:'Courier New',monospace;text-align:left;margin-bottom:15px;font-size:11px}
        .receipt-header{text-align:center;border-bottom:1px dashed #000;padding-bottom:10px;margin-bottom:10px}
        .receipt-header h4{font-size:16px;margin-bottom:3px}
        .receipt-header p{font-size:10px;margin:2px 0}
        .receipt-item{display:flex;justify-content:space-between;margin-bottom:3px;font-size:10px}
        .receipt-summary{border-top:1px dashed #000;padding-top:8px;margin-top:8px}
        .receipt-summary-row{display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px}
        .receipt-summary-row.discount{color:#c00}
        .receipt-total{border-top:1px dashed #000;padding-top:8px;margin-top:8px;font-weight:bold;display:flex;justify-content:space-between;font-size:14px}
        .receipt-note{margin-top:8px;padding:8px;background:#f5f5f5;font-size:10px;border-radius:4px}
        .receipt-footer{text-align:center;font-size:9px;margin-top:10px;padding-top:8px;border-top:1px dashed #000}
        .nav-links{display:flex;gap:8px}
        .nav-links a{background:#252542;color:#fff;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:12px}
        .nav-links a:hover{background:#e94560}
        .product-count{font-size:11px;color:#888;margin-left:10px}
        .no-products{grid-column:span 4;text-align:center;padding:60px 20px;color:#666}
        .no-products i{font-size:60px;margin-bottom:20px;color:#3a3a5a}
        .no-products a{color:#e94560}
        @media(max-width:900px){.pos-container{grid-template-columns:1fr}.cart-panel{border-left:none;border-top:2px solid #1a1a2e;max-height:55vh}}
        @media(max-width:600px){.products-grid{grid-template-columns:repeat(2,1fr)}.panel-header h1{font-size:1em}}
    </style>
</head>
<body>
    <div class="pos-container">
        <div class="products-panel">
            <div class="panel-header">
                <h1>🛒 POS <span class="product-count">(<?= number_format($productCount) ?> รายการ)</span></h1>
                <div class="nav-links">
                    <span class="branch-badge">📍 <?= htmlspecialchars($userBranch) ?></span>
                    <a href="products.php"><i class="fas fa-box"></i></a>
                    <a href="history.php"><i class="fas fa-history"></i></a>
                    <a href="../dashboard.php"><i class="fas fa-home"></i></a>
                </div>
            </div>
            <div class="search-bar">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="ค้นหาสินค้า, barcode, แบรนด์..." autofocus>
                </div>
            </div>
            <div class="categories">
                <button class="category-btn active" onclick="filterCategory('all')">ทั้งหมด</button>
                <?php foreach ($brands as $brand): ?>
                <button class="category-btn" onclick="filterCategory('<?= htmlspecialchars($brand) ?>')"><?= htmlspecialchars($brand) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                <div class="no-products">
                    <i class="fas fa-box-open"></i>
                    <h3>ยังไม่มีสินค้าในระบบ</h3>
                    <p style="margin-top:10px">กรุณา <a href="import_products.php">Import สินค้า</a> ก่อนใช้งาน</p>
                </div>
                <?php else: ?>
                <?php foreach ($products as $p): ?>
                <div class="product-card" 
                     data-sku="<?= htmlspecialchars($p['sku']) ?>" 
                     data-name="<?= htmlspecialchars($p['display_name']) ?>" 
                     data-price="<?= floatval($p['price']) ?>" 
                     data-brand="<?= htmlspecialchars($p['brand']) ?>"
                     onclick="openAddModal(this)">
                    <div class="brand"><?= htmlspecialchars($p['brand']) ?></div>
                    <div class="name"><?= htmlspecialchars($p['display_name']) ?></div>
                    <div class="sku"><?= htmlspecialchars($p['sku']) ?></div>
                    <div class="price">฿<?= number_format(floatval($p['price']),0) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="cart-panel">
            <div class="cart-header"><h2>🧾 รายการ <span class="cart-count" id="cartCount">0</span></h2></div>
            <div class="cart-items" id="cartItems"><div class="cart-empty"><i class="fas fa-shopping-cart"></i><p>ยังไม่มีสินค้า</p></div></div>
            <div class="cart-summary">
                <div class="summary-row"><span>จำนวน</span><span id="totalItems">0 ชิ้น</span></div>
                <div class="summary-row"><span>ยอดรวม</span><span id="subtotal">฿0</span></div>
                <div class="summary-row discount">
                    <span>ส่วนลด</span>
                    <input type="number" id="discountInput" value="0" min="0" step="1" onchange="updateTotals()" onkeyup="updateTotals()">
                </div>
                <div class="summary-row total"><span>สุทธิ</span><span class="amount" id="grandTotal">฿0</span></div>
                <div class="note-section">
                    <label><i class="fas fa-sticky-note"></i> หมายเหตุ</label>
                    <textarea id="paymentNote" placeholder="เช่น: โอน SCB, เงินสด, บัตรเครดิต..."></textarea>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-clear" onclick="clearCart()"><i class="fas fa-trash"></i> ล้าง</button>
                    <button class="btn" style="background:#3b82f6;color:#fff" onclick="holdOrder()"><i class="fas fa-pause"></i> พัก</button>
                    <button class="btn btn-checkout" id="checkoutBtn" onclick="checkout()" disabled><i class="fas fa-check-circle"></i> ชำระเงิน (F2)</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <div class="brand-tag" id="modalBrand"></div>
            <h3 id="modalProductName">สินค้า</h3>
            <div class="price" id="modalProductPrice">฿0</div>
            <div class="modal-qty">
                <button onclick="changeModalQty(-1)">−</button>
                <input type="number" id="modalQty" value="1" min="1" max="99">
                <button onclick="changeModalQty(1)">+</button>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeAddModal()">ยกเลิก</button>
                <button class="btn-add" onclick="addToCart()"><i class="fas fa-plus"></i> เพิ่ม</button>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="receiptModal">
        <div class="modal receipt-modal">
            <div class="receipt" id="receiptContent"></div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeReceiptModal()">ปิด</button>
                <button class="btn-add" onclick="printThermal()"><i class="fas fa-print"></i> พิมพ์ 80mm</button>
            </div>
        </div>
    </div>

    <script>
        let cart=[],selectedProduct=null,lastSaleData=null;
        const branchName='<?= htmlspecialchars($userBranch) ?>';
        const userName='<?= htmlspecialchars($userName) ?>';
        const storeName='<?= htmlspecialchars($storeName) ?>';
        
        document.getElementById('searchInput').addEventListener('input',function(e){
            const q=e.target.value.toLowerCase();
            document.querySelectorAll('.product-card').forEach(c=>{
                const n=(c.dataset.name||'').toLowerCase();
                const s=(c.dataset.sku||'').toLowerCase();
                const b=(c.dataset.brand||'').toLowerCase();
                c.style.display=(n.includes(q)||s.includes(q)||b.includes(q))?'':'none';
            });
        });
        
        function filterCategory(brand){
            document.querySelectorAll('.category-btn').forEach(b=>b.classList.remove('active'));
            event.target.classList.add('active');
            document.querySelectorAll('.product-card').forEach(c=>{
                c.style.display=(brand==='all'||c.dataset.brand===brand)?'':'none';
            });
        }
        
        function openAddModal(c){
            selectedProduct={sku:c.dataset.sku,name:c.dataset.name,price:parseFloat(c.dataset.price)||0,brand:c.dataset.brand};
            document.getElementById('modalBrand').textContent=selectedProduct.brand;
            document.getElementById('modalProductName').textContent=selectedProduct.name;
            document.getElementById('modalProductPrice').textContent='฿'+selectedProduct.price.toLocaleString();
            document.getElementById('modalQty').value=1;
            document.getElementById('addModal').classList.add('show');
        }
        
        function closeAddModal(){document.getElementById('addModal').classList.remove('show');selectedProduct=null;}
        function changeModalQty(d){const i=document.getElementById('modalQty');let v=parseInt(i.value)+d;if(v<1)v=1;if(v>99)v=99;i.value=v;}
        
        function addToCart(){
            if(!selectedProduct)return;
            const qty=parseInt(document.getElementById('modalQty').value);
            const ex=cart.find(i=>i.sku===selectedProduct.sku);
            if(ex)ex.qty+=qty;else cart.push({...selectedProduct,qty});
            updateCartUI();closeAddModal();
        }
        
        function updateCartUI(){
            const ci=document.getElementById('cartItems'),cc=document.getElementById('cartCount'),ti=document.getElementById('totalItems'),cb=document.getElementById('checkoutBtn');
            if(cart.length===0){
                ci.innerHTML='<div class="cart-empty"><i class="fas fa-shopping-cart"></i><p>ยังไม่มีสินค้า</p></div>';
                cc.textContent='0';ti.textContent='0 ชิ้น';
                document.getElementById('subtotal').textContent='฿0';
                document.getElementById('grandTotal').textContent='฿0';
                cb.disabled=true;return;
            }
            let h='',ic=0;
            cart.forEach((i,idx)=>{const it=i.price*i.qty;ic+=i.qty;h+=`<div class="cart-item"><div class="cart-item-info"><div class="cart-item-name">${i.name}</div><div class="cart-item-price">฿${i.price.toLocaleString()} x${i.qty}</div></div><div class="cart-item-qty"><button class="qty-btn" onclick="updateQty(${idx},-1)">−</button><span class="qty-value">${i.qty}</span><button class="qty-btn" onclick="updateQty(${idx},1)">+</button><button class="qty-btn delete" onclick="removeItem(${idx})"><i class="fas fa-trash"></i></button></div><div class="cart-item-total">฿${it.toLocaleString()}</div></div>`;});
            ci.innerHTML=h;cc.textContent=cart.length;ti.textContent=ic+' ชิ้น';cb.disabled=false;
            updateTotals();
        }
        
        function updateTotals(){
            const subtotal=cart.reduce((s,i)=>s+(i.price*i.qty),0);
            const discount=parseFloat(document.getElementById('discountInput').value)||0;
            const grandTotal=Math.max(0,subtotal-discount);
            document.getElementById('subtotal').textContent='฿'+subtotal.toLocaleString();
            document.getElementById('grandTotal').textContent='฿'+grandTotal.toLocaleString();
        }
        
        function updateQty(i,d){cart[i].qty+=d;if(cart[i].qty<=0)cart.splice(i,1);updateCartUI();}
        function removeItem(i){cart.splice(i,1);updateCartUI();}
        function clearCart(){if(cart.length===0)return;if(confirm('ล้างตะกร้า?')){cart=[];document.getElementById('discountInput').value=0;document.getElementById('paymentNote').value='';updateCartUI();}}
        function holdOrder(){if(cart.length===0)return;localStorage.setItem('posHeld',JSON.stringify({cart,discount:document.getElementById('discountInput').value,note:document.getElementById('paymentNote').value}));alert('พักรายการแล้ว');cart=[];document.getElementById('discountInput').value=0;document.getElementById('paymentNote').value='';updateCartUI();}
        
        window.onload=function(){
            const h=localStorage.getItem('posHeld');
            if(h&&confirm('โหลดรายการที่พัก?')){
                const data=JSON.parse(h);
                cart=data.cart||[];
                document.getElementById('discountInput').value=data.discount||0;
                document.getElementById('paymentNote').value=data.note||'';
                updateCartUI();
            }
            localStorage.removeItem('posHeld');
        };
        
        async function checkout(){
            if(cart.length===0)return;
            const subtotal=cart.reduce((s,i)=>s+(i.price*i.qty),0);
            const discount=parseFloat(document.getElementById('discountInput').value)||0;
            const total=Math.max(0,subtotal-discount);
            const note=document.getElementById('paymentNote').value.trim();
            
            try{
                const r=await fetch('save_sale.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items:cart,subtotal,discount,total,branch:branchName,store:storeName,note})});
                const text=await r.text();
                let res;
                try{res=JSON.parse(text);}catch(e){throw new Error('Server error: '+text.substring(0,100));}
                if(res.success){
                    lastSaleData={saleId:res.sale_id,subtotal,discount,total,items:[...cart],note,date:new Date()};
                    showReceipt();
                    cart=[];
                    document.getElementById('discountInput').value=0;
                    document.getElementById('paymentNote').value='';
                    updateCartUI();
                }else{alert('Error: '+res.message);}
            }catch(e){alert('Error: '+e.message);}
        }
        
        function showReceipt(){
            const d=lastSaleData.date,ds=d.toLocaleDateString('th-TH'),ts=d.toLocaleTimeString('th-TH');
            let ih='';lastSaleData.items.forEach(i=>{
                const sn=i.name.length>28?i.name.substring(0,28)+'..':i.name;
                ih+=`<div class="receipt-item"><span>${sn} x${i.qty}</span><span>฿${(i.price*i.qty).toLocaleString()}</span></div>`;
            });
            let sumHtml=`<div class="receipt-summary"><div class="receipt-summary-row"><span>ยอดรวม</span><span>฿${lastSaleData.subtotal.toLocaleString()}</span></div>`;
            if(lastSaleData.discount>0)sumHtml+=`<div class="receipt-summary-row discount"><span>ส่วนลด</span><span>-฿${lastSaleData.discount.toLocaleString()}</span></div>`;
            sumHtml+=`</div>`;
            let noteHtml=lastSaleData.note?`<div class="receipt-note">📝 ${lastSaleData.note}</div>`:'';
            document.getElementById('receiptContent').innerHTML=`<div class="receipt-header"><h4>${storeName}</h4><p>สาขา: ${branchName}</p><p>${ds} ${ts}</p><p>#${lastSaleData.saleId}</p></div><div class="receipt-items">${ih}</div>${sumHtml}<div class="receipt-total"><span>สุทธิ</span><span>฿${lastSaleData.total.toLocaleString()}</span></div>${noteHtml}<div class="receipt-footer"><p>พนักงาน: ${userName}</p><p>ขอบคุณที่ใช้บริการ</p></div>`;
            document.getElementById('receiptModal').classList.add('show');
        }
        
        function closeReceiptModal(){document.getElementById('receiptModal').classList.remove('show');}
        
        function printThermal(){
            if(!lastSaleData)return;
            const d=lastSaleData.date,ds=d.toLocaleDateString('th-TH'),ts=d.toLocaleTimeString('th-TH');
            let ih='',ic=0;
            lastSaleData.items.forEach(i=>{
                const it=i.price*i.qty;ic+=i.qty;
                const sn=i.name.length>20?i.name.substring(0,20)+'..':i.name;
                ih+=`<tr><td>${sn}</td><td style="text-align:center">${i.qty}</td><td style="text-align:right">${it.toLocaleString()}</td></tr>`;
            });
            let discLine=lastSaleData.discount>0?`<div class="tr disc"><span>ส่วนลด:</span><span>-฿${lastSaleData.discount.toLocaleString()}</span></div>`:'';
            let noteLine=lastSaleData.note?`<div class="note">📝 ${lastSaleData.note}</div>`:'';
            const pc=`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt</title><style>@page{size:80mm auto;margin:0}*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Courier New',monospace;font-size:12px;width:80mm;padding:3mm;background:#fff;color:#000}.hdr{text-align:center;border-bottom:1px dashed #000;padding-bottom:8px;margin-bottom:8px}.hdr h1{font-size:18px;margin-bottom:3px}.hdr p{font-size:11px;margin:2px 0}table{width:100%;border-collapse:collapse;margin-bottom:8px}th{border-bottom:1px dashed #000;padding:4px 0;font-size:10px;text-align:left}td{padding:3px 0;font-size:11px}.tot{border-top:1px dashed #000;padding-top:8px;margin-top:8px}.tr{display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px}.tr.disc{color:#c00}.gt{font-size:18px;font-weight:bold;border-top:2px solid #000;border-bottom:2px solid #000;padding:5px 0;margin-top:5px}.note{margin-top:8px;padding:5px;background:#f5f5f5;font-size:10px}.ftr{text-align:center;margin-top:10px;padding-top:8px;border-top:1px dashed #000;font-size:10px}.bc{font-size:11px;margin-top:8px;letter-spacing:2px}</style></head><body><div class="hdr"><h1>${storeName}</h1><p>สาขา: ${branchName}</p><p>========================</p><p>เลขที่: ${lastSaleData.saleId}</p><p>${ds} ${ts}</p></div><table><thead><tr><th style="width:55%">รายการ</th><th style="width:15%;text-align:center">จน.</th><th style="width:30%;text-align:right">ราคา</th></tr></thead><tbody>${ih}</tbody></table><div class="tot"><div class="tr"><span>จำนวน:</span><span>${ic} ชิ้น</span></div><div class="tr"><span>ยอดรวม:</span><span>฿${lastSaleData.subtotal.toLocaleString()}</span></div>${discLine}<div class="tr gt"><span>สุทธิ:</span><span>฿${lastSaleData.total.toLocaleString()}</span></div></div>${noteLine}<div class="ftr"><p>พนักงาน: ${userName}</p><p>========================</p><p>ขอบคุณที่ใช้บริการ</p><p class="bc">*${lastSaleData.saleId}*</p></div><script>window.onload=function(){window.print();setTimeout(function(){window.close();},500);}<\/script></body></html>`;
            const pw=window.open('','_blank','width=302,height=600');pw.document.write(pc);pw.document.close();
        }
        
        document.querySelectorAll('.modal-overlay').forEach(m=>{m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('show');});});
        document.addEventListener('keydown',function(e){
            if(e.key==='Escape')document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('show'));
            if(e.key==='Enter'&&document.getElementById('addModal').classList.contains('show'))addToCart();
            if(e.key==='F2'&&!document.getElementById('checkoutBtn').disabled){e.preventDefault();checkout();}
            if(e.key==='F3'&&lastSaleData){e.preventDefault();printThermal();}
        });
    </script>
</body>
</html>
