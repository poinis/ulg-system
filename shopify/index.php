<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Sync Master | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hover-card:hover {
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-4xl w-full">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-black text-slate-800 mb-2">
                <i class="fas fa-sync-alt text-indigo-600 mr-3"></i>Stock Sync Master
            </h1>
            <p class="text-slate-500 italic">ระบบบริหารจัดการสต็อกสินค้า Shopify ⇌ Cegid</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <a href="pronto.php" class="hover-card group block">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-b-8 border-indigo-600 p-8 text-center transition-all">
                    <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        <i class="fas fa-store text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">PRONTO</h2>
                    <p class="text-slate-500 text-sm mb-6">จัดการสต็อกสินค้าสำหรับร้าน Pronto</p>
                    <span class="inline-flex items-center text-indigo-600 font-bold uppercase tracking-wider text-xs">
                        เข้าใช้งานระบบ <i class="fas fa-arrow-right ml-2"></i>
                    </span>
                </div>
            </a>

            <a href="soup.php" class="hover-card group block">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-b-8 border-orange-500 p-8 text-center transition-all">
                    <div class="w-20 h-20 bg-orange-100 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                        <i class="fas fa-bowl-food text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">SOUP</h2>
                    <p class="text-slate-500 text-sm mb-6">จัดการสต็อกสินค้าสำหรับร้าน SOUP</p>
                    <span class="inline-flex items-center text-orange-600 font-bold uppercase tracking-wider text-xs">
                        เข้าใช้งานระบบ <i class="fas fa-arrow-right ml-2"></i>
                    </span>
                </div>
            </a>

        </div>

        <div class="text-center mt-12 text-slate-400 text-xs uppercase tracking-widest">
            &copy; 2026 Stock Sync Master System | Ver 2.7.0
        </div>
    </div>

</body>
</html>