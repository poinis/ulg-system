<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Documentation | Stock Sync Master</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .gradient-text {
            background: linear-gradient(to right, #4f46e5, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <nav class="bg-white/80 backdrop-blur-md sticky top-0 z-50 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <i class="fas fa-book-reader text-indigo-600 text-2xl mr-3"></i>
                    <span class="font-bold text-xl text-slate-800">System Documentation</span>
                </div>
                <div class="text-sm text-slate-500">
                    วิเคราะห์โค้ดโดย AI ผู้ช่วยของคุณ
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-4 text-slate-900">
                สรุปการทำงานระบบ <span class="gradient-text">Stock Sync Master</span>
            </h1>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto">
                เจาะลึกรายละเอียดการทำงานของไฟล์ index.php, pronto.php และ soup.php 
                ในเวอร์ชั่น 3.1.2 (Turbo Standard Edition)
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            <div class="bg-white rounded-2xl p-6 shadow-lg border-l-4 border-slate-800 hover:shadow-xl transition duration-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-slate-800">1. index.php</h2>
                    <span class="bg-slate-100 text-slate-600 text-xs px-2 py-1 rounded-full uppercase font-bold">Portal</span>
                </div>
                <p class="text-slate-500 text-sm mb-4">หน้า Landing Page สำหรับเลือกเข้าระบบ</p>
                <ul class="text-sm text-slate-600 space-y-2">
                    <li><i class="fas fa-check text-green-500 mr-2"></i>Dashboard รวม</li>
                    <li><i class="fas fa-link text-indigo-500 mr-2"></i>ลิงก์ไป Pronto System</li>
                    <li><i class="fas fa-link text-orange-500 mr-2"></i>ลิงก์ไป SOUP System</li>
                    <li><i class="fab fa-css3 text-blue-500 mr-2"></i>ใช้ Tailwind CSS</li>
                </ul>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-lg border-l-4 border-indigo-600 hover:shadow-xl transition duration-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-indigo-600">2. pronto.php</h2>
                    <span class="bg-indigo-100 text-indigo-600 text-xs px-2 py-1 rounded-full uppercase font-bold">Logic Core</span>
                </div>
                <p class="text-slate-500 text-sm mb-4">ระบบซิงค์สำหรับร้าน PRONTO</p>
                <ul class="text-sm text-slate-600 space-y-2">
                    <li><i class="fas fa-database text-indigo-500 mr-2"></i>DB: inventory_sync.db</li>
                    <li><i class="fas fa-store text-indigo-500 mr-2"></i>Shop: newpronto</li>
                    <li><i class="fas fa-warehouse text-indigo-500 mr-2"></i>คลัง: 10000, 12010, 77000</li>
                    <li><i class="fas fa-shield-alt text-red-500 mr-2"></i>กรอง Freitag ออก</li>
                </ul>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-lg border-l-4 border-orange-500 hover:shadow-xl transition duration-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-orange-600">3. soup.php</h2>
                    <span class="bg-orange-100 text-orange-600 text-xs px-2 py-1 rounded-full uppercase font-bold">Logic Core</span>
                </div>
                <p class="text-slate-500 text-sm mb-4">ระบบซิงค์สำหรับร้าน SOUP</p>
                <ul class="text-sm text-slate-600 space-y-2">
                    <li><i class="fas fa-database text-orange-500 mr-2"></i>DB: inventory_sync_soup.db</li>
                    <li><i class="fas fa-store text-orange-500 mr-2"></i>Shop: soupth</li>
                    <li><i class="fas fa-warehouse text-orange-500 mr-2"></i>คลัง: 10000, 17020, 77000</li>
                    <li><i class="fas fa-copy text-slate-400 mr-2"></i>Logic เหมือน Pronto</li>
                </ul>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-xl p-8 mb-12">
            <h3 class="text-2xl font-bold text-slate-800 mb-8 border-b pb-4">
                <i class="fas fa-cogs mr-2 text-slate-400"></i>
                เจาะลึกกระบวนการทำงาน (Process Flow)
            </h3>
            
            <div class="relative border-l-2 border-slate-200 ml-4 space-y-12">
                
                <div class="relative pl-8">
                    <span class="absolute -left-[9px] top-0 w-4 h-4 bg-indigo-600 rounded-full ring-4 ring-white"></span>
                    <h4 class="text-lg font-bold text-indigo-600 mb-2">Step 1: Fetch Shopify (ดึงข้อมูลสินค้า)</h4>
                    <div class="bg-slate-50 rounded-xl p-4 text-slate-600 text-sm space-y-2">
                        <p><strong>Action:</strong> <code>?ajax=step1_init</code> และ <code>step1_continue</code></p>
                        <p>ระบบจะดึงสินค้าทั้งหมดจาก Shopify ผ่าน API <code>products.json</code></p>
                        <div class="bg-white border border-slate-200 rounded p-3 mt-2">
                            <span class="text-xs font-bold text-red-500 uppercase">Feature สำคัญ: Freitag Protection</span><br>
                            ระบบจะตรวจสอบ Title, Vendor, และ Tags หากพบคำว่า <strong>"freitag"</strong> จะข้ามสินค้านั้นทันที ไม่นำเข้า Database เพื่อป้องกันการ Sync ผิดพลาด
                        </div>
                        <p class="mt-2 text-xs text-slate-400">Database: สร้างตาราง SQLite ใหม่ทุกครั้งที่เริ่ม Step 1</p>
                    </div>
                </div>

                <div class="relative pl-8">
                    <span class="absolute -left-[9px] top-0 w-4 h-4 bg-blue-500 rounded-full ring-4 ring-white"></span>
                    <h4 class="text-lg font-bold text-blue-600 mb-2">Step 2: Check Cegid (เช็คสต็อกจริง)</h4>
                    <div class="bg-slate-50 rounded-xl p-4 text-slate-600 text-sm space-y-2">
                        <p><strong>Action:</strong> <code>?ajax=step2_batch</code></p>
                        <p>ดึงสินค้าจาก SQLite ที่สถานะ <code>pending</code> ทีละ <strong>10 รายการ</strong> (Batch Size)</p>
                        <div class="bg-blue-50 border border-blue-100 p-3 rounded mt-2">
                            <h5 class="font-bold text-blue-700 text-xs">SOAP API Logic:</h5>
                            <p class="text-xs mt-1">
                                ใช้ Service: <code>ItemInventoryWcfService</code><br>
                                Method: <code>GetListItemInventoryDetailByStore</code><br>
                                Config: <code>AllAvailableWarehouse = false</code> (เน้น Physical Stock เท่านั้น)
                            </p>
                        </div>
                        <p class="mt-2">นำผลรวมสต็อก (Total Available) จากทุกคลังที่กำหนด มาอัปเดตลง SQLite และเปลี่ยนสถานะเป็น <code>ok</code></p>
                    </div>
                </div>

                <div class="relative pl-8">
                    <span class="absolute -left-[9px] top-0 w-4 h-4 bg-green-500 rounded-full ring-4 ring-white"></span>
                    <h4 class="text-lg font-bold text-green-600 mb-2">Step 3: Sync Back (อัปเดตกลับ Shopify)</h4>
                    <div class="bg-slate-50 rounded-xl p-4 text-slate-600 text-sm space-y-2">
                        <p><strong>Action:</strong> <code>?ajax=step3_batch</code></p>
                        <p>เลือกสินค้าที่ <code>status='ok'</code> และ <strong>ยอดไม่ตรงกัน</strong> (Diff != 0)</p>
                        <p>ส่งคำสั่ง POST ไปยัง <code>inventory_levels/set.json</code> เพื่อปรับยอดใน Shopify</p>
                        <div class="bg-yellow-50 border border-yellow-100 p-2 rounded mt-2 text-xs text-yellow-700">
                            <i class="fas fa-clock mr-1"></i> มีการใส่ <code>usleep(250000)</code> (0.25 วินาที) เพื่อป้องกันการโดนบล็อก (Rate Limit) จาก Shopify
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-3xl shadow-lg p-6">
                <h3 class="font-bold text-slate-800 mb-4 flex items-center">
                    <i class="fas fa-database text-slate-400 mr-2"></i> Database Schema (SQLite)
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="bg-slate-100 text-slate-800 font-bold">
                            <tr><th class="p-2">Field</th><th class="p-2">Type</th><th class="p-2">Description</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr><td class="p-2 font-mono text-xs">id</td><td class="p-2">PK</td><td class="p-2">Auto Increment</td></tr>
                            <tr><td class="p-2 font-mono text-xs">variant_id</td><td class="p-2">TEXT</td><td class="p-2">รหัสสินค้าใน Shopify</td></tr>
                            <tr><td class="p-2 font-mono text-xs">barcode</td><td class="p-2">TEXT</td><td class="p-2">บาร์โค้ด (Key หลักในการเชื่อม)</td></tr>
                            <tr><td class="p-2 font-mono text-xs">inventory_item_id</td><td class="p-2">TEXT</td><td class="p-2">รหัสสต็อก Shopify</td></tr>
                            <tr><td class="p-2 font-mono text-xs">shopify_qty</td><td class="p-2">INT</td><td class="p-2">จำนวนตั้งต้น</td></tr>
                            <tr><td class="p-2 font-mono text-xs">cegid_qty</td><td class="p-2">INT</td><td class="p-2">จำนวนที่ได้จาก Cegid</td></tr>
                            <tr><td class="p-2 font-mono text-xs">status</td><td class="p-2">TEXT</td><td class="p-2">'pending' -> 'ok'</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-lg p-6">
                <h3 class="font-bold text-slate-800 mb-4 flex items-center">
                    <i class="fas fa-boxes text-slate-400 mr-2"></i> Warehouse Configuration
                </h3>
                
                <div class="space-y-6">
                    <div>
                        <h4 class="text-xs font-black text-indigo-600 uppercase mb-2">PRONTO (pronto.php)</h4>
                        <div class="flex gap-2">
                            <span class="bg-slate-100 px-3 py-1 rounded text-xs">10000 (ULG DC)</span>
                            <span class="bg-slate-100 px-3 py-1 rounded text-xs">12010 (Rama 9)</span>
                            <span class="bg-slate-100 px-3 py-1 rounded text-xs">77000 (Online)</span>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-xs font-black text-orange-600 uppercase mb-2">SOUP (soup.php)</h4>
                        <div class="flex gap-2">
                            <span class="bg-slate-100 px-3 py-1 rounded text-xs">10000 (ULG DC)</span>
                            <span class="bg-slate-100 px-3 py-1 rounded text-xs">17020 (Emsphere)</span>
                            <span class="bg-slate-100 px-3 py-1 rounded text-xs">77000 (Online)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-16 text-slate-400 text-sm">
            &copy; <?= date("Y") ?> Documentation generated by AI Assistant
        </div>

    </div>
</body>
</html>