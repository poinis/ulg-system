<?php
/**
 * View Imported Data
 * หน้าดูข้อมูลที่นำเข้าแล้ว
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
            max-width: 1600px;
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
        
        .back-link {
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Social Media Posts</h1>
            <a href="index.php" class="back-link">📤 อัพโหลดไฟล์ใหม่</a>
            <a href="report.php" class="back-link" style="margin-left: 10px;">📊 รายงาน</a>
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
                    <div class="icon">📭</div>
                    <h2>ไม่พบข้อมูล</h2>
                    <p>ยังไม่มีข้อมูลในระบบ กรุณาอัพโหลดไฟล์ CSV หรือ Excel</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Account</th>
                                <th>Content</th>
                                <th>Type</th>
                                <th>Published</th>
                                <th class="number">Views</th>
                                <th class="number">Likes</th>
                                <th class="number">Comments</th>
                                <th class="number">Shares</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post): ?>
                                <tr>
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
                                            <?php if ($post['account_username']): ?>
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
                                        <?php if ($post['post_type']): ?>
                                            <span class="post-type"><?php echo htmlspecialchars($post['post_type']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <?php echo $post['publish_time'] ? date('d/m/Y H:i', strtotime($post['publish_time'])) : '-'; ?>
                                    </td>
                                    <td class="number"><?php echo number_format($post['views']); ?></td>
                                    <td class="number"><?php echo number_format($post['likes']); ?></td>
                                    <td class="number"><?php echo number_format($post['comments']); ?></td>
                                    <td class="number"><?php echo number_format($post['shares']); ?></td>
                                    <td class="permalink">
                                        <?php if ($post['permalink']): ?>
                                            <a href="<?php echo htmlspecialchars($post['permalink']); ?>" target="_blank">
                                                🔗 View
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
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
</body>
</html>
