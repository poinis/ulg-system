<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัพโหลดยอดขายรายวัน</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        
        .upload-form { margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="file"] { padding: 10px; border: 2px dashed #ccc; border-radius: 5px; width: 100%; cursor: pointer; }
        input[type="file"]:hover { border-color: #4CAF50; }
        
        .btn { padding: 12px 30px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #45a049; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .btn-cancel { background: #dc3545; margin-left: 10px; }
        .btn-cancel:hover { background: #c82333; }
        
        .file-info { background: #e7f3ff; padding: 10px 15px; border-radius: 5px; margin: 10px 0; display: none; }
        
        .info-box { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2196F3; }
        .info-box ul { margin-left: 20px; margin-top: 10px; }
        .info-box li { margin-bottom: 5px; }
        
        .progress-container { display: none; margin: 20px 0; padding: 20px; background: white; border: 1px solid #ddd; border-radius: 5px; }
        .progress-bar { width: 100%; height: 30px; background: #f0f0f0; border-radius: 15px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { padding: 15px; border-radius: 5px; text-align: center; }
        .stat-box h3 { margin: 0 0 5px 0; font-size: 24px; }
        .stat-box p { margin: 0; color: #666; font-size: 12px; }
        
        .phase { padding: 10px; margin: 10px 0; border-left: 4px solid #ddd; background: #f9f9f9; }
        .phase.active { border-left-color: #2196F3; background: #e3f2fd; }
        .phase.done { border-left-color: #4CAF50; background: #e8f5e9; }
        
        .log { background: #1e1e1e; color: #0f0; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 11px; margin-top: 10px; }
        .log .error { color: #f66; }
        .log .success { color: #6f6; }
        .log .info { color: #6cf; }
        .log .warn { color: #ff0; }
        
        .result { padding: 20px; border-radius: 5px; margin-top: 20px; }
        .result.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .result.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        
        .back-link { display: inline-block; margin-top: 20px; color: #2196F3; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        .mapping-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
        .mapping-table th, .mapping-table td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        .mapping-table th { background: #f5f5f5; }
        .mapping-table .found { color: #155724; background: #d4edda; }
        .mapping-table .notfound { color: #721c24; background: #f8d7da; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 อัพโหลดยอดขายรายวัน</h1>
        
        <div class="info-box">
            <strong>📋 ระบบจะ Auto-detect คอลัมน์จาก Header:</strong>
            <ul>
                <li>Date: GL_DATEPIECE, Date, sale_date</li>
                <li>Store: GL_ETABLISSEMENT, Store, store_code</li>
                <li>Internal Ref: GP_REFINTERNE, Internal reference</li>
                <li>Brand, Group, Class, Qty, Price, etc.</li>
            </ul>
            รองรับไฟล์ขนาดใหญ่ 200MB+ (UTF-8, UTF-16 LE)
        </div>
        
        <div class="upload-form">
            <div class="form-group">
                <label>เลือกไฟล์ CSV:</label>
                <input type="file" id="csvFile" accept=".csv,.txt" required>
                <small style="color: #666;">รองรับ UTF-8, UTF-16 LE (Tab หรือ Comma)</small>
            </div>
            <div class="file-info" id="fileInfo"></div>
            <button onclick="startUpload()" id="uploadBtn" class="btn">🚀 อัพโหลด</button>
            <button onclick="cancelUpload()" id="cancelBtn" class="btn btn-cancel" style="display:none;">ยกเลิก</button>
        </div>

        <div class="progress-container" id="progressContainer">
            <h3 id="mainStatus">กำลังประมวลผล...</h3>
            
            <div class="phase" id="phase1">
                <strong>Phase 1:</strong> อ่านและแปลงไฟล์ CSV <span id="phase1Status"></span>
            </div>
            <div class="phase" id="phase2">
                <strong>Phase 2:</strong> บันทึกลงฐานข้อมูล <span id="phase2Status"></span>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill">0%</div>
            </div>
            <p id="progressText">เตรียมข้อมูล...</p>
            
            <div class="stats">
                <div class="stat-box" style="background:#e7f3ff;">
                    <h3 id="liveRead">0</h3>
                    <p>📖 อ่านแล้ว</p>
                </div>
                <div class="stat-box" style="background:#d4edda;">
                    <h3 id="liveInserted">0</h3>
                    <p>✅ บันทึกสำเร็จ</p>
                </div>
                <div class="stat-box" style="background:#f8d7da;">
                    <h3 id="liveErrors">0</h3>
                    <p>❌ ข้อผิดพลาด</p>
                </div>
                <div class="stat-box" style="background:#fff3cd;">
                    <h3 id="liveBatch">0/0</h3>
                    <p>📦 Batch</p>
                </div>
            </div>
            
            <div id="mappingContainer"></div>
            
            <details open>
                <summary style="cursor:pointer; font-weight:bold; margin-bottom:10px;">📋 Log</summary>
                <div class="log" id="logContainer"></div>
            </details>
        </div>

        <div id="resultContainer"></div>
        
        <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
    </div>

    <script>
        // ==========================================
        // Configuration
        // ==========================================
        const CONFIG = {
            BATCH_SIZE: 500,
            CONCURRENT_REQUESTS: 2,
            RETRY_ATTEMPTS: 3,
            LINES_PER_CHUNK: 5000
        };

        // ==========================================
        // Column Name Patterns (case-insensitive)
        // ==========================================
        const COLUMN_PATTERNS = {
            DATE: ['gl_datepiece', 'date', 'sale_date', 'transaction_date', 'วันที่'],
            STORE: ['gl_etablissement', 'store', 'store_code', 'branch', 'สาขา'],
            INTERNAL_REF: ['gp_refinterne', 'internal reference', 'internal_ref', 'doc_no', 'เลขที่เอกสาร'],
            SALES_DIVISION: ['gl_souche', 'sales division', 'sales_division', 'division'],
            LINE_BARCODE: ['gl_refartbarre', 'barcode', 'line_barcode', 'ean', 'บาร์โค้ด'],
            ITEM_DESCRIPTION: ['gl_libelle', 'description', 'item_description', 'product_name', 'รายละเอียด'],
            CUSTOMER: ['gl_tiers', 'gl_codearticle', 'customer', 'customer_code', 'ลูกค้า'],
            FIRST_NAME: ['t_prenom', 'first_name', 'firstname', 'ชื่อ'],
            LAST_NAME: ['t_libelle', 'last_name', 'lastname', 'นามสกุล'],
            BRAND: ['c22', 'ga_libreseau', 'brand', 'แบรนด์'],
            GROUP_NAME: ['c24', 'ga_libfamille', 'group', 'group_name', 'กลุ่ม'],
            CLASS_NAME: ['c25', 'ga_libsfamille', 'class', 'class_name', 'ประเภท'],
            SIZE: ['libdim2', 'ga_libtaille', 'size', 'ขนาด'],
            QTY: ['gl_qte', 'qty', 'quantity', 'จำนวน'],
            BASE_PRICE: ['gl_puttc', 'base_price', 'price', 'unit_price', 'ราคา'],
            TAX_INCL_TOTAL: ['gl_mtttc', 'total', 'tax_incl_total', 'amount', 'ยอดรวม']
        };

        // ==========================================
        // Global Variables
        // ==========================================
        let totalRead = 0;
        let totalInserted = 0;
        let totalErrors = 0;
        let totalSkipped = 0;
        let startTime = 0;
        let isCancelled = false;
        let allRows = [];
        let detectedDelimiter = ',';
        let columnMapping = {};

        // ==========================================
        // File Info Display
        // ==========================================
        document.getElementById('csvFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                const info = document.getElementById('fileInfo');
                info.style.display = 'block';
                info.innerHTML = `<strong>📁 ${file.name}</strong> | ขนาด: ${sizeMB} MB`;
            }
        });

        // ==========================================
        // Main Upload Function
        // ==========================================
        async function startUpload() {
            const fileInput = document.getElementById('csvFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('กรุณาเลือกไฟล์ CSV');
                return;
            }

            // Reset
            totalRead = 0;
            totalInserted = 0;
            totalErrors = 0;
            totalSkipped = 0;
            isCancelled = false;
            allRows = [];
            columnMapping = {};
            
            document.getElementById('uploadBtn').disabled = true;
            document.getElementById('cancelBtn').style.display = 'inline-block';
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('logContainer').innerHTML = '';
            document.getElementById('resultContainer').innerHTML = '';
            document.getElementById('mappingContainer').innerHTML = '';
            
            resetPhases();
            startTime = Date.now();
            
            try {
                // Phase 1: Read file
                setPhaseActive('phase1');
                addLog('Phase 1: เริ่มอ่านไฟล์ CSV...', 'info');
                updateProgress(5, 'กำลังอ่านไฟล์...');
                
                await readAndParseFile(file);
                
                if (isCancelled) throw new Error('ยกเลิกโดยผู้ใช้');
                
                setPhaseComplete('phase1', `✓ ${totalRead.toLocaleString()} แถว`);
                addLog(`อ่านไฟล์สำเร็จ: ${totalRead.toLocaleString()} แถว`, 'success');
                
                if (totalRead === 0) {
                    throw new Error('ไม่พบข้อมูลที่ parse ได้ กรุณาตรวจสอบ Column Mapping');
                }
                
                // Phase 2: Insert to database
                setPhaseActive('phase2');
                addLog('Phase 2: เริ่มบันทึกลงฐานข้อมูล...', 'info');
                
                await insertToDatabase();
                
                if (isCancelled) throw new Error('ยกเลิกโดยผู้ใช้');
                
                setPhaseComplete('phase2', `✓ ${totalInserted.toLocaleString()} รายการ`);
                finishProcess();
                
            } catch (error) {
                if (error.message === 'ยกเลิกโดยผู้ใช้') {
                    addLog('❌ ยกเลิกการประมวลผล', 'error');
                    document.getElementById('mainStatus').textContent = 'ยกเลิกแล้ว';
                } else {
                    addLog(`เกิดข้อผิดพลาด: ${error.message}`, 'error');
                    document.getElementById('mainStatus').textContent = '❌ เกิดข้อผิดพลาด';
                }
                document.getElementById('uploadBtn').disabled = false;
                document.getElementById('cancelBtn').style.display = 'none';
            }
        }

        function cancelUpload() {
            if (confirm('ต้องการยกเลิกหรือไม่?')) {
                isCancelled = true;
            }
        }

        // ==========================================
        // Read File
        // ==========================================
        async function readAndParseFile(file) {
            addLog(`ขนาดไฟล์: ${(file.size / (1024 * 1024)).toFixed(2)} MB`, 'info');
            
            const encoding = await detectEncoding(file);
            addLog(`Encoding: ${encoding}`, 'info');
            
            updateProgress(10, `กำลังอ่านไฟล์...`);
            const text = await readFileWithEncoding(file, encoding);
            
            addLog(`อ่านข้อความได้: ${text.length.toLocaleString()} ตัวอักษร`, 'info');
            
            detectedDelimiter = detectDelimiter(text);
            addLog(`Delimiter: "${detectedDelimiter === '\t' ? 'TAB' : 'COMMA'}"`, 'info');
            
            updateProgress(30, 'กำลังประมวลผลข้อมูล...');
            await parseCSVText(text);
        }

        async function detectEncoding(file) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const arr = new Uint8Array(e.target.result);
                    
                    if (arr[0] === 0xFF && arr[1] === 0xFE) {
                        resolve('UTF-16LE');
                    } else if (arr[0] === 0xFE && arr[1] === 0xFF) {
                        resolve('UTF-16BE');
                    } else if (arr[0] === 0xEF && arr[1] === 0xBB && arr[2] === 0xBF) {
                        resolve('UTF-8');
                    } else {
                        let nullCount = 0;
                        for (let i = 1; i < Math.min(200, arr.length); i += 2) {
                            if (arr[i] === 0x00) nullCount++;
                        }
                        resolve(nullCount > 80 ? 'UTF-16LE' : 'UTF-8');
                    }
                };
                reader.readAsArrayBuffer(file.slice(0, 200));
            });
        }

        function detectDelimiter(text) {
            const firstLine = text.split(/\r?\n/)[0] || '';
            const tabCount = (firstLine.match(/\t/g) || []).length;
            const commaCount = (firstLine.match(/,/g) || []).length;
            return tabCount > commaCount ? '\t' : ',';
        }

        async function readFileWithEncoding(file, encoding) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let text = e.target.result;
                    if (text.charCodeAt(0) === 0xFEFF || text.charCodeAt(0) === 0xFFFE) {
                        text = text.substring(1);
                    }
                    resolve(text);
                };
                reader.onerror = () => reject(new Error('ไม่สามารถอ่านไฟล์ได้'));
                reader.readAsText(file, encoding);
            });
        }

        // ==========================================
        // Auto-detect Column Mapping
        // ==========================================
        function detectColumnMapping(headers) {
            const mapping = {};
            const headersLower = headers.map(h => h.toLowerCase().trim());
            
            for (const [field, patterns] of Object.entries(COLUMN_PATTERNS)) {
                for (const pattern of patterns) {
                    const idx = headersLower.findIndex(h => h.includes(pattern));
                    if (idx !== -1) {
                        mapping[field] = idx;
                        break;
                    }
                }
            }
            
            return mapping;
        }

        function showMappingTable(headers, mapping) {
            let html = '<h4 style="margin:15px 0 10px;">📊 Column Mapping:</h4>';
            html += '<table class="mapping-table"><tr><th>Field</th><th>Column Index</th><th>Header Name</th><th>Status</th></tr>';
            
            const requiredFields = ['DATE', 'STORE'];
            
            for (const [field, patterns] of Object.entries(COLUMN_PATTERNS)) {
                const idx = mapping[field];
                const isRequired = requiredFields.includes(field);
                const found = idx !== undefined;
                const statusClass = found ? 'found' : (isRequired ? 'notfound' : '');
                const status = found ? '✓' : (isRequired ? '✗ Required!' : '-');
                
                html += `<tr class="${statusClass}">
                    <td>${field}${isRequired ? ' *' : ''}</td>
                    <td>${found ? idx : '-'}</td>
                    <td>${found ? headers[idx] : '-'}</td>
                    <td>${status}</td>
                </tr>`;
            }
            
            html += '</table>';
            document.getElementById('mappingContainer').innerHTML = html;
        }

        // ==========================================
        // Parse CSV Text
        // ==========================================
        async function parseCSVText(text) {
            const lines = text.split(/\r?\n/);
            addLog(`พบ ${lines.length.toLocaleString()} บรรทัด`, 'info');
            
            let headerParsed = false;
            let headers = [];
            let lineCount = 0;
            
            for (let i = 0; i < lines.length; i++) {
                if (isCancelled) break;
                
                const line = lines[i].trim();
                if (!line) continue;
                
                const cols = parseLine(line, detectedDelimiter);
                
                // Parse header and detect mapping
                if (!headerParsed) {
                    headerParsed = true;
                    headers = cols;
                    columnMapping = detectColumnMapping(headers);
                    
                    addLog(`Header: ${cols.length} คอลัมน์`, 'info');
                    showMappingTable(headers, columnMapping);
                    
                    // Check required columns
                    if (columnMapping.DATE === undefined) {
                        throw new Error('ไม่พบคอลัมน์ Date (GL_DATEPIECE)');
                    }
                    if (columnMapping.STORE === undefined) {
                        throw new Error('ไม่พบคอลัมน์ Store (GL_ETABLISSEMENT)');
                    }
                    
                    addLog(`DATE column: ${columnMapping.DATE} (${headers[columnMapping.DATE]})`, 'success');
                    addLog(`STORE column: ${columnMapping.STORE} (${headers[columnMapping.STORE]})`, 'success');
                    continue;
                }
                
                // Parse row using detected mapping
                const rowData = parseRow(cols, i + 1);
                if (rowData) {
                    allRows.push(rowData);
                    totalRead++;
                } else {
                    totalSkipped++;
                }
                
                lineCount++;
                
                if (lineCount % CONFIG.LINES_PER_CHUNK === 0) {
                    const progress = 30 + Math.round((i / lines.length) * 15);
                    updateProgress(progress, `ประมวลผล ${lineCount.toLocaleString()} บรรทัด`);
                    document.getElementById('liveRead').textContent = totalRead.toLocaleString();
                    await sleep(1);
                }
            }
            
            document.getElementById('liveRead').textContent = totalRead.toLocaleString();
            addLog(`Parse: ${totalRead} แถว, ข้าม: ${totalSkipped} แถว`, 'success');
        }

        function parseLine(line, delimiter) {
            if (delimiter === '\t') {
                return line.split('\t').map(s => s.trim());
            }
            
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                if (char === '"') {
                    if (inQuotes && line[i + 1] === '"') {
                        current += '"';
                        i++;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            result.push(current.trim());
            return result;
        }

        // ==========================================
        // Parse Row using Dynamic Mapping
        // ==========================================
        function parseRow(cols, lineNum) {
            try {
                // Get date
                const dateIdx = columnMapping.DATE;
                let dateStr = cols[dateIdx] || '';
                dateStr = dateStr.trim().replace(/[^\d\/\-\.]/g, '');
                
                if (!dateStr) {
                    if (lineNum <= 5) addLog(`Line ${lineNum}: วันที่ว่าง`, 'error');
                    return null;
                }
                
                // Parse date
                let date = '';
                let match = dateStr.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (match) {
                    date = `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
                } else {
                    match = dateStr.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
                    if (match) {
                        date = `${match[1]}-${match[2].padStart(2, '0')}-${match[3].padStart(2, '0')}`;
                    } else {
                        match = dateStr.match(/^(\d{4})(\d{2})(\d{2})$/);
                        if (match) {
                            date = `${match[1]}-${match[2]}-${match[3]}`;
                        } else {
                            if (lineNum <= 5) addLog(`Line ${lineNum}: วันที่ผิดรูปแบบ "${dateStr}"`, 'error');
                            return null;
                        }
                    }
                }
                
                // Get store
                const storeIdx = columnMapping.STORE;
                const store = cols[storeIdx]?.trim() || '';
                if (!store) {
                    if (lineNum <= 5) addLog(`Line ${lineNum}: Store ว่าง`, 'error');
                    return null;
                }
                
                // Helper function
                const getCol = (field) => {
                    const idx = columnMapping[field];
                    return idx !== undefined ? (cols[idx]?.trim() || '') : '';
                };
                
                const getNumCol = (field) => {
                    const val = getCol(field).replace(/,/g, '');
                    return parseFloat(val) || 0;
                };
                
                const customer = getCol('CUSTOMER');
                const member = customer ? (/^\d+$/.test(customer) ? 'ULG Member' : customer) : '';
                
                return {
                    sale_date: date,
                    store_code: store,
                    internal_ref: getCol('INTERNAL_REF'),
                    sales_division: getCol('SALES_DIVISION'),
                    brand: getCol('BRAND'),
                    group_name: getCol('GROUP_NAME'),
                    class_name: getCol('CLASS_NAME'),
                    line_barcode: getCol('LINE_BARCODE'),
                    item_description: getCol('ITEM_DESCRIPTION'),
                    customer: customer,
                    member: member,
                    first_name: getCol('FIRST_NAME'),
                    last_name: getCol('LAST_NAME'),
                    size: getCol('SIZE'),
                    qty: parseInt(getCol('QTY').replace(/,/g, '')) || 0,
                    base_price: getNumCol('BASE_PRICE'),
                    tax_incl_total: getNumCol('TAX_INCL_TOTAL')
                };
            } catch (e) {
                if (lineNum <= 5) addLog(`Line ${lineNum}: ${e.message}`, 'error');
                return null;
            }
        }

        // ==========================================
        // Insert to Database
        // ==========================================
        async function insertToDatabase() {
            const batchSize = CONFIG.BATCH_SIZE;
            const concurrency = CONFIG.CONCURRENT_REQUESTS;
            
            const batches = [];
            for (let i = 0; i < allRows.length; i += batchSize) {
                batches.push(allRows.slice(i, i + batchSize));
            }
            
            const totalBatches = batches.length;
            let completedBatches = 0;
            
            addLog(`แบ่งเป็น ${totalBatches} batches`, 'info');
            document.getElementById('liveBatch').textContent = `0/${totalBatches}`;
            
            for (let i = 0; i < batches.length; i += concurrency) {
                if (isCancelled) break;
                
                const chunk = batches.slice(i, i + concurrency);
                const promises = chunk.map((batch, idx) => 
                    processBatchWithRetry(batch, i + idx + 1)
                );
                
                const results = await Promise.all(promises);
                
                results.forEach(result => {
                    if (result.success) {
                        totalInserted += result.inserted;
                        totalErrors += result.errors;
                    } else {
                        totalErrors += result.count || 0;
                    }
                });
                
                completedBatches += chunk.length;
                
                const progress = 45 + Math.round((completedBatches / totalBatches) * 50);
                updateProgress(progress, `บันทึก ${completedBatches}/${totalBatches}`);
                document.getElementById('liveInserted').textContent = totalInserted.toLocaleString();
                document.getElementById('liveErrors').textContent = totalErrors.toLocaleString();
                document.getElementById('liveBatch').textContent = `${completedBatches}/${totalBatches}`;
                
                if (completedBatches % 20 === 0 || completedBatches === totalBatches) {
                    addLog(`Batch ${completedBatches}/${totalBatches}: ${totalInserted.toLocaleString()}`, 'success');
                }
            }
        }

        async function processBatchWithRetry(batch, batchNum) {
            for (let attempt = 1; attempt <= CONFIG.RETRY_ATTEMPTS; attempt++) {
                try {
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 60000);
                    
                    const response = await fetch('process_sales_batch.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ batch: batch }),
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeout);
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return await response.json();
                    
                } catch (error) {
                    if (attempt === CONFIG.RETRY_ATTEMPTS) {
                        addLog(`Batch ${batchNum} ล้มเหลว`, 'error');
                        return { success: false, inserted: 0, errors: batch.length, count: batch.length };
                    }
                    await sleep(1000 * attempt);
                }
            }
        }

        // ==========================================
        // Utility Functions
        // ==========================================
        function addLog(message, type = '') {
            const log = document.getElementById('logContainer');
            const time = new Date().toLocaleTimeString('th-TH');
            const div = document.createElement('div');
            div.className = type;
            div.textContent = `[${time}] ${message}`;
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
        }

        function updateProgress(percent, text) {
            document.getElementById('progressFill').style.width = percent + '%';
            document.getElementById('progressFill').textContent = percent + '%';
            document.getElementById('progressText').textContent = text;
        }

        function resetPhases() {
            ['phase1', 'phase2'].forEach(p => {
                document.getElementById(p).className = 'phase';
                document.getElementById(p + 'Status').textContent = '';
            });
        }

        function setPhaseActive(id) { document.getElementById(id).className = 'phase active'; }
        function setPhaseComplete(id, status) {
            document.getElementById(id).className = 'phase done';
            document.getElementById(id + 'Status').textContent = status;
        }

        function finishProcess() {
            updateProgress(100, 'เสร็จสิ้น!');
            document.getElementById('mainStatus').textContent = '✅ เสร็จสิ้น!';
            document.getElementById('cancelBtn').style.display = 'none';
            
            const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
            
            document.getElementById('resultContainer').innerHTML = `
                <div class="result success">
                    <h3>✅ อัพโหลดยอดขายสำเร็จ!</h3>
                    <p><strong>📖 อ่านทั้งหมด:</strong> ${totalRead.toLocaleString()} แถว</p>
                    <p><strong>✅ บันทึกสำเร็จ:</strong> ${totalInserted.toLocaleString()} รายการ</p>
                    <p><strong>❌ ข้อผิดพลาด:</strong> ${totalErrors.toLocaleString()} รายการ</p>
                    <p><strong>⏱️ เวลาที่ใช้:</strong> ${elapsed} วินาที</p>
                </div>
            `;
            
            document.getElementById('uploadBtn').disabled = false;
            document.getElementById('uploadBtn').textContent = '🚀 อัพโหลดไฟล์ใหม่';
            allRows = [];
        }

        function sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }
    </script>
</body>
</html>