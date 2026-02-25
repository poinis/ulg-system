<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ดาวน์โหลดข้อมูล Variant Inventory</title>
    <style>
        body {
            font-family: 'Sarabun', Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #2196F3;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .download-btn {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .download-btn:hover {
            background: linear-gradient(135deg, #1976D2, #1565C0);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        .download-btn:active {
            transform: translateY(0);
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 5px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        .info-box h4 {
            margin-top: 0;
            color: #1565C0;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 8px 0;
            color: #555;
        }
        .status {
            display: none;
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .status.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        .status.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        .icon {
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📥 ดาวน์โหลดข้อมูล Variant Inventory</h2>
        
        <button class="download-btn" onclick="downloadExcel()">
            <span class="icon">📊</span>
            ดาวน์โหลด Excel (CSV)
        </button>
        
        <div id="statusMessage" class="status"></div>
        
        <div class="info-box">
            <h4>ℹ️ รายละเอียด:</h4>
            <ul>
                <li>ดาวน์โหลดข้อมูลทั้งหมดจากตาราง variant_inventory</li>
                <li>ไฟล์จะอยู่ในรูปแบบ <strong>XLS</strong> (Excel)</li>
                <li>รองรับภาษาไทยและตัวเลขแสดงเป็น Number Format</li>
                <li>ชื่อไฟล์จะมี Timestamp เช่น variant_inventory_2024-11-27_14-30-45.xls</li>
            </ul>
            
            <h4>📋 คอลัมน์ที่ Export:</h4>
            <ul>
                <li>ID, Handle, Variant Inventory Item ID</li>
                <li>Variant ID, Option1 Value, Variant Position</li>
                <li>Variant SKU, Variant Barcode</li>
                <li>Variant Inventory Qty, Variant Inventory Adjust</li>
            </ul>
        </div>
    </div>

    <script>
        function downloadExcel() {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.textContent = '⏳ กำลังเตรียมข้อมูล...';
            statusDiv.className = 'status';
            statusDiv.style.display = 'block';
            
            // เรียก API เพื่อดาวน์โหลด
            fetch('export_excel.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('เกิดข้อผิดพลาดในการดาวน์โหลด');
                    }
                    return response.blob();
                })
                .then(blob => {
                    // สร้าง URL สำหรับดาวน์โหลด
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'variant_inventory_' + new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.xls';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    statusDiv.textContent = '✅ ดาวน์โหลดสำเร็จ!';
                    statusDiv.className = 'status success';
                    
                    setTimeout(() => {
                        statusDiv.style.display = 'none';
                    }, 3000);
                })
                .catch(error => {
                    statusDiv.textContent = '❌ เกิดข้อผิดพลาด: ' + error.message;
                    statusDiv.className = 'status error';
                });
        }
    </script>
</body>
</html>