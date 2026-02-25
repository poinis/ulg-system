<?php
require_once 'config.php';

$pdo = getConnection();
$stmt = $pdo->query("SELECT * FROM stores_sms WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
$stores = $stmt->fetchAll();

// Group stores by brand
$grouped = [];
foreach ($stores as $store) {
    $name = $store['name'];
    
    // ดึงชื่อแบรนด์จากชื่อร้าน
    if (preg_match('/^(PRONTO|FREITAG|Topologie|Soup|The Popup)/i', $name, $matches)) {
        $brand = $matches[1];
        // แปลงให้เป็นรูปแบบมาตรฐาน
        $brand = ucfirst(strtolower($brand));
        if (strtoupper($brand) === 'PRONTO') $brand = 'PRONTO';
        if (strtoupper($brand) === 'FREITAG') $brand = 'FREITAG';
        if (strtolower($brand) === 'the popup') $brand = 'The Popup';
    } else {
        // ถ้าไม่ตรงกับแบรนด์ที่กำหนด ให้ดึงคำแรกก่อน - หรือก่อน &
        preg_match('/^([^-&]+)/', $name, $matches);
        $brand = trim($matches[1] ?? 'Other');
    }
    
    if (!isset($grouped[$brand])) {
        $grouped[$brand] = [];
    }
    $grouped[$brand][] = $store;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Locations | ค้นหาสาขาใกล้คุณ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #111111;
            --primary-dark: #000000;
            --secondary: #666666;
            --success: #333333;
            --warning: #888888;
            --danger: #ef4444;
            --dark: #111111;
            --light: #ffffff;
            --gray-100: #f7f7f7;
            --gray-200: #eeeeee;
            --gray-300: #dddddd;
            --gray-400: #999999;
            --gray-500: #666666;
            --gray-600: #444444;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08), 0 1px 2px -1px rgb(0 0 0 / 0.08);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.08);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.08);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --radius: 8px;
            --radius-lg: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Noto Sans Thai', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        /* --- ส่วนที่เพิ่มใหม่: Announcement Banner --- */
        .promo-banner {
            background-color: #fffbeb;
            color: #92400e;
            font-size: 0.8rem;
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid #fef3c7;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .promo-banner i {
            font-size: 0.9rem;
        }
        /* -------------------------------------- */

        .hero {
            background: #ffffff;
            padding: 50px 20px 70px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid var(--gray-200);
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        .hero p {
            font-size: clamp(0.9rem, 2vw, 1rem);
            color: var(--gray-500);
            margin-bottom: 28px;
        }

        .search-box {
            max-width: 480px;
            margin: 0 auto;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            font-size: 15px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            background: white;
            box-shadow: var(--shadow-sm);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-box input:focus {
            border-color: var(--dark);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }

        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 16px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 60px;
        }

        .brand-section {
            margin-bottom: 48px;
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }

        .brand-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            letter-spacing: -0.01em;
        }

        .brand-count {
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .stores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .store-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--gray-200);
        }

        .store-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .store-image {
            position: relative;
            height: 180px;
            overflow: hidden;
            background: var(--gray-100);
        }

        .store-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
            filter: grayscale(20%);
        }

        .store-card:hover .store-image img {
            transform: scale(1.05);
            filter: grayscale(0%);
        }

        .store-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            background: var(--gray-100);
        }

        .store-image-placeholder i {
            font-size: 2.5rem;
            margin-bottom: 8px;
        }

        .store-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.95);
            color: var(--dark);
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: var(--shadow-sm);
        }

        .store-badge i {
            font-size: 0.5rem;
            color: #22c55e;
        }

        .store-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .store-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 14px;
            line-height: 1.4;
        }

        .store-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .info-item i {
            width: 16px;
            color: var(--gray-400);
            margin-top: 2px;
        }

        .info-item a {
            color: var(--dark);
            text-decoration: none;
            transition: color 0.2s;
        }

        .info-item a:hover {
            text-decoration: underline;
        }

        .store-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            flex: 1;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--dark);
            color: white;
        }

        .btn-primary:hover {
            background: #333;
        }

        .btn-outline {
            background: transparent;
            color: var(--dark);
            border: 1px solid var(--gray-300);
        }

        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 18px;
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }
        
        .filter-tab.active {
            background: var(--dark);
            color: white;
            border-color: var(--dark);
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        .no-results h3 {
            font-size: 1.25rem;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .no-results p {
            color: var(--gray-500);
        }

        footer {
            background: white;
            padding: 30px 20px;
            text-align: center;
            color: var(--gray-500);
            border-top: 1px solid var(--gray-200);
            font-size: 0.875rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hero {
                padding: 40px 16px 50px;
            }

            .container {
                padding: 30px 16px 40px;
            }

            .stores-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .store-image {
                height: 160px;
            }

            .store-content {
                padding: 16px;
            }

            .store-actions {
                flex-direction: column;
            }

            .filter-tabs {
                gap: 6px;
            }

            .filter-tab {
                padding: 8px 14px;
                font-size: 0.8125rem;
            }

            .brand-header {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 400px) {
            .hero h1 {
                font-size: 1.5rem;
            }

            .search-box input {
                padding: 14px 16px 14px 44px;
                font-size: 14px;
            }

            .store-name {
                font-size: 0.9375rem;
            }
            
            .promo-banner {
                font-size: 0.75rem;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .store-card {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        .store-card:nth-child(1) { animation-delay: 0.1s; }
        .store-card:nth-child(2) { animation-delay: 0.15s; }
        .store-card:nth-child(3) { animation-delay: 0.2s; }
        .store-card:nth-child(4) { animation-delay: 0.25s; }
        .store-card:nth-child(5) { animation-delay: 0.3s; }
        .store-card:nth-child(6) { animation-delay: 0.35s; }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="promo-banner">
        <i class="fas fa-info-circle"></i> *TC ไม่สามารถใช้ร่วมกับสินค้าลดราคาได้
    </div>

    <section class="hero">
        <div class="hero-content">
            <h1>ค้นหาสาขาใกล้คุณ</h1>
            <p>เยี่ยมชมสาขาของเราได้ทั่วประเทศ พร้อมให้บริการคุณ</p>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="ค้นหาสาขา, ที่อยู่, หรือเบอร์โทร...">
            </div>
        </div>
    </section>

    <div class="container">
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">ทั้งหมด</button>
            <?php foreach ($grouped as $brand => $brandStores): ?>
            <button class="filter-tab" data-filter="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $brand))) ?>">
                <?= htmlspecialchars($brand) ?> (<?= count($brandStores) ?>)
            </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($grouped as $brand => $brandStores): ?>
        <section class="brand-section" data-brand="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $brand))) ?>">
            <div class="brand-header">
                <h2 class="brand-name"><?= htmlspecialchars($brand) ?></h2>
                <span class="brand-count"><?= count($brandStores) ?> สาขา</span>
            </div>

            <div class="stores-grid">
                <?php foreach ($brandStores as $store): ?>
                <article class="store-card" data-name="<?= htmlspecialchars(strtolower($store['name'])) ?>" data-phone="<?= htmlspecialchars($store['phone'] ?? '') ?>">
                    <div class="store-image">
                        <?php if ($store['image']): ?>
                            <img src="<?= htmlspecialchars($store['image']) ?>" alt="<?= htmlspecialchars($store['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="store-image-placeholder">
                                <i class="fas fa-image"></i>
                                <span>ไม่มีรูปภาพ</span>
                            </div>
                        <?php endif; ?>
                        <div class="store-badge">
                            <i class="fas fa-circle"></i> เปิดให้บริการ
                        </div>
                    </div>

                    <div class="store-content">
                        <h3 class="store-name"><?= htmlspecialchars($store['name']) ?></h3>
                        
                        <div class="store-info">
                            <?php if ($store['opening_hours']): ?>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span><?= htmlspecialchars($store['opening_hours']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($store['phone']): ?>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?= preg_replace('/[^0-9]/', '', $store['phone']) ?>"><?= htmlspecialchars($store['phone']) ?></a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="store-actions">
                            <?php if ($store['phone']): ?>
                            <a href="tel:<?= preg_replace('/[^0-9]/', '', $store['phone']) ?>" class="btn btn-outline">
                                <i class="fas fa-phone"></i> โทร
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($store['map_link']): ?>
                            <a href="<?= htmlspecialchars($store['map_link']) ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-map-marker-alt"></i> นำทาง
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <div class="no-results hidden" id="noResults">
            <i class="fas fa-search"></i>
            <h3>ไม่พบสาขาที่ค้นหา</h3>
            <p>ลองค้นหาด้วยคำอื่น หรือดูสาขาทั้งหมด</p>
        </div>
    </div>

    <footer>
        <p>© <?= date('Y') ?> Store Locator</p>
    </footer>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const storeCards = document.querySelectorAll('.store-card');
        const brandSections = document.querySelectorAll('.brand-section');
        const noResults = document.getElementById('noResults');
        const filterTabs = document.querySelectorAll('.filter-tab');

        let currentFilter = 'all';

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            filterStores(searchTerm, currentFilter);
        });

        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                filterStores(searchInput.value.toLowerCase().trim(), currentFilter);
            });
        });

        function filterStores(searchTerm, brand) {
            let visibleCount = 0;

            brandSections.forEach(section => {
                const sectionBrand = section.dataset.brand;
                const cards = section.querySelectorAll('.store-card');
                let sectionVisible = 0;

                if (brand !== 'all' && sectionBrand !== brand) {
                    section.classList.add('hidden');
                    return;
                }

                section.classList.remove('hidden');

                cards.forEach(card => {
                    const name = card.dataset.name;
                    const phone = card.dataset.phone;
                    const matchesSearch = !searchTerm || 
                        name.includes(searchTerm) || 
                        phone.includes(searchTerm);

                    if (matchesSearch) {
                        card.classList.remove('hidden');
                        sectionVisible++;
                        visibleCount++;
                    } else {
                        card.classList.add('hidden');
                    }
                });

                if (sectionVisible === 0) {
                    section.classList.add('hidden');
                }
            });

            noResults.classList.toggle('hidden', visibleCount > 0);
        }
    </script>
</body>
</html>