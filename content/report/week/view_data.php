<?php
/**
 * View Imported Data
 * หน้าดูข้อมูลที่นำเข้าแล้ว (พร้อมฟีเจอร์ลบ)
 */

require_once 'SocialMediaImporter.php';

$filter = $_GET['social'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

try {
    $importer = new SocialMediaImporter();
    $stats = $importer->getCountBySocial();
    $statsSummary = $importer->getStatsSummary();
    
    if ($filter !== 'all' && in_array($filter, ['Facebook', 'Instagram', 'TikTok'])) {
        $posts = $importer->getPostsBySocial($filter, $perPage, $offset);
    } else {
        $posts = $importer->getAllPosts($perPage, $offset);
    }
    
    $totalCount = $importer->getTotalCount();
} catch (Exception $e) {
    $error = $e->getMessage();
    $posts = [];
    $stats = [];
    $totalCount = 0;
}

// คำนวณสถิติ
$socialCounts = ['Facebook' => 0, 'Instagram' => 0, 'TikTok' => 0];
foreach ($stats as $stat) {
    $socialCounts[$stat['social']] = $stat['count'];
}
$totalPosts = array_sum($socialCounts);

// Pagination
$totalPages = ceil($totalCount / $perPage);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Data - Social Media Importer</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1800px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 30px 40px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .back-link, .delete-selected-btn {
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .delete-selected-btn {
            background: rgba(255,59,48,0.8);
            display: none;
        }
        
        .delete-selected-btn:hover {
            background: rgba(255,59,48,1);
            transform: translateY(-2px);
        }
        
        .delete-selected-btn.show {
            display: inline-block;
        }
        
        .filter-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 25px;
            background: white;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-tab:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .filter-tab.active {
            color: white;
        }
        
        .filter-tab.active.all { background: linear-gradient(135deg, #667eea, #764ba2); }
        .filter-tab.active.facebook { background: linear-gradient(135deg, #1877F2, #166FE5); }
        .filter-tab.active.instagram { background: linear-gradient(135deg, #E4405F, #C13584); }
        .filter-tab.active.tiktok { background: linear-gradient(135deg, #25F4EE, #FE2C55); color: #000; }
        
        .filter-tab .count {
            background: rgba(0,0,0,0.1);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
        }
        
        .filter-tab.active .count {
            background: rgba(255,255,255,0.2);
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            white-space: nowrap;
        }
        
        tr:hover {
            background: #f8f9ff;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .badge.facebook { background: #1877F2; }
        .badge.instagram { background: linear-gradient(135deg, #E4405F, #C13584); }
        .badge.tiktok { background: #000; }
        
        .post-content {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .post-content:hover {
            white-space: normal;
            word-break: break-word;
        }
        
        .permalink a {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #667eea;
            text-decoration: none;
            padding: 5px 10px;
            background: #f0f3ff;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .permalink a:hover {
            background: #e0e6ff;
        }
        
        .number {
            text-align: right;
            font-family: 'Consolas', monospace;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h2 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .post-type {
            display: inline-block;
            padding: 3px 10px;
            background: #e9ecef;
            border-radius: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 25px;
            background: #f8f9fa;
        }
        
        .pagination a, .pagination span {
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .pagination a {
            background: white;
            color: #333;
            border: 1px solid #ddd;
            transition: all 0.2s;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .account-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .account-name {
            font-weight: 600;
            color: #333;
        }
        
        .account-username {
            font-size: 12px;
            color: #888;
        }
        
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .delete-btn {
            padding: 6px 12px;
            background: #ff3b30;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .delete-btn:hover {
            background: #ff1f0f;
            transform: scale(1.05);
        }
        
        .created-date {
            font-size: 12px;
            color: #666;
        }
        
        .created-date .date {
            display: block;
            font-weight: 600;
        }
        
        .created-date .time {
            color: #999;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            animation: fadeIn 0.2s;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            animation: slideUp 0.3s;
        }
        
        .modal-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .modal-message {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .modal-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .modal-btn-cancel {
            background: #e9ecef;
            color: #333;
        }
        
        .modal-btn-cancel:hover {
            background: #dee2e6;
        }
        
        .modal-btn-confirm {
            background: #ff3b30;
            color: white;
        }
        
        .modal-btn-confirm:hover {
            background: #ff1f0f;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            animation: slideInRight 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .notification.success {
            background: #34c759;
        }
        
        .notification.error {
            background: #ff3b30;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            th, td {
                padding: 10px 12px;
                font-size: 12px;
            }
            
            .filter-tabs {
                justify-content: center;
            }
            
            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Social Media Posts</h1>
            <div class="header-actions">
                <button class="delete-selected-btn" id="deleteSelectedBtn">
                    🗑️ ลบที่เลือก (<span id="selectedCount">0</span>)
                </button>
                <a href="upload.php" class="back-link">📤 อัพโหลดไฟล์ใหม่</a>
                <a href="index.php" class="back-link">📊 รายงาน</a>
            </div>
        </div>
        
        <div class="filter-tabs">
            <a href="?social=all" class="filter-tab all <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <span>📊</span>
                <span>ทั้งหมด</span>
                <span class="count"><?php echo number_format($totalPosts); ?></span>
            </a>
            <a href="?social=Facebook" class="filter-tab facebook <?php echo $filter === 'Facebook' ? 'active' : ''; ?>">
                <span>📘</span>
                <span>Facebook</span>
                <span class="count"><?php echo number_format($socialCounts['Facebook']); ?></span>
            </a>
            <a href="?social=Instagram" class="filter-tab instagram <?php echo $filter === 'Instagram' ? 'active' : ''; ?>">
                <span>📸</span>
                <span>Instagram</span>
                <span class="count"><?php echo number_format($socialCounts['Instagram']); ?></span>
            </a>
            <a href="?social=TikTok" class="filter-tab tiktok <?php echo $filter === 'TikTok' ? 'active' : ''; ?>">
                <span>🎵</span>
                <span>TikTok</span>
                <span class="count"><?php echo number_format($socialCounts['TikTok']); ?></span>
            </a>
        </div>
        
        <div class="card">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <div class="icon">🔭</div>
                    <h2>ไม่พบข้อมูล</h2>
                    <p>ยังไม่มีข้อมูลในระบบ กรุณาอัพโหลดไฟล์ CSV หรือ Excel</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Platform</th>
                                <th>Account</th>
                                <th>Content</th>
                                <th>Type</th>
                                <th>Published</th>
                                <th>อัพโหลดเมื่อ</th>
                                <th class="number">Views</th>
                                <th class="number">Likes</th>
                                <th class="number">Comments</th>
                                <th class="number">Shares</th>
                                <th>Link</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="row-checkbox" data-id="<?php echo $post['id']; ?>">
                                    </td>
                                    <td>
                                        <span class="badge <?php echo strtolower($post['social']); ?>">
                                            <?php 
                                            echo $post['social'] === 'Facebook' ? '📘' : 
                                                ($post['social'] === 'Instagram' ? '📸' : '🎵'); 
                                            ?>
                                            <?php echo $post['social']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="account-info">
                                            <span class="account-name"><?php echo htmlspecialchars($post['account_name'] ?: '-'); ?></span>
                                            <?php if (!empty($post['account_username'])): ?>
                                                <span class="account-username">@<?php echo htmlspecialchars($post['account_username']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="post-content" title="<?php echo htmlspecialchars($post['title'] ?: $post['description']); ?>">
                                        <?php 
                                        $content = $post['title'] ?: $post['description'];
                                        echo htmlspecialchars(mb_substr($content, 0, 60)) . (mb_strlen($content) > 60 ? '...' : ''); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($post['post_type'])): ?>
                                            <span class="post-type"><?php echo htmlspecialchars($post['post_type']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <?php echo !empty($post['publish_time']) ? date('d/m/Y H:i', strtotime($post['publish_time'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <div class="created-date">
                                            <?php if (!empty($post['created_at'])): ?>
                                                <span class="date"><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                                                <span class="time"><?php echo date('H:i น.', strtotime($post['created_at'])); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="number"><?php echo number_format($post['views']); ?></td>
                                    <td class="number"><?php echo number_format($post['likes']); ?></td>
                                    <td class="number"><?php echo number_format($post['comments']); ?></td>
                                    <td class="number"><?php echo number_format($post['shares']); ?></td>
                                    <td class="permalink">
                                        <?php if (!empty($post['permalink'])): ?>
                                            <a href="<?php echo htmlspecialchars($post['permalink']); ?>" target="_blank">
                                                🔗 View
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="delete-btn" onclick="deletePost(<?php echo $post['id']; ?>)">
                                            🗑️ ลบ
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?social=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">← ก่อนหน้า</a>
                    <?php else: ?>
                        <span class="disabled">← ก่อนหน้า</span>
                    <?php endif; ?>
                    
                    <span class="current">หน้า <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?social=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">ถัดไป →</a>
                    <?php else: ?>
                        <span class="disabled">ถัดไป →</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-icon">⚠️</div>
            <h2 class="modal-title">ยืนยันการลบ</h2>
            <p class="modal-message" id="deleteMessage">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?</p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">ยกเลิก</button>
                <button class="modal-btn modal-btn-confirm" id="confirmDeleteBtn">ลบ</button>
            </div>
        </div>
    </div>

    <script>
        let selectedIds = new Set();
        let deleteCallback = null;
        
        // Select All Checkbox
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                if (this.checked) {
                    selectedIds.add(cb.dataset.id);
                } else {
                    selectedIds.delete(cb.dataset.id);
                }
            });
            updateSelectedCount();
        });
        
        // Individual Checkboxes
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedIds.add(this.dataset.id);
                } else {
                    selectedIds.delete(this.dataset.id);
                }
                updateSelectedCount();
                
                // Update select all checkbox
                const allCheckboxes = document.querySelectorAll('.row-checkbox');
                const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
                document.getElementById('selectAll').checked = checkedCount === allCheckboxes.length;
            });
        });
        
        // Update Selected Count
        function updateSelectedCount() {
            const count = selectedIds.size;
            document.getElementById('selectedCount').textContent = count;
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            if (count > 0) {
                deleteBtn.classList.add('show');
            } else {
                deleteBtn.classList.remove('show');
            }
        }
        
        // Delete Selected
        document.getElementById('deleteSelectedBtn')?.addEventListener('click', function() {
            if (selectedIds.size === 0) return;
            
            document.getElementById('deleteMessage').textContent = 
                `คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูล ${selectedIds.size} รายการ?`;
            
            deleteCallback = async function() {
                try {
                    const response = await fetch('delete_post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: Array.from(selectedIds)
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification('success', result.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('error', result.message);
                    }
                } catch (error) {
                    showNotification('error', 'เกิดข้อผิดพลาดในการลบข้อมูล');
                }
            };
            
            openModal();
        });
        
        // Delete Single Post
        function deletePost(id) {
            document.getElementById('deleteMessage').textContent = 
                'คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?';
            
            deleteCallback = async function() {
                try {
                    const response = await fetch('delete_post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification('success', result.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('error', result.message);
                    }
                } catch (error) {
                    showNotification('error', 'เกิดข้อผิดพลาดในการลบข้อมูล');
                }
            };
            
            openModal();
        }
        
        // Modal Functions
        function openModal() {
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('show');
            deleteCallback = null;
        }
        
        document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
            if (deleteCallback) {
                deleteCallback();
                closeModal();
            }
        });
        
        // Close modal on backdrop click
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Show Notification
        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>