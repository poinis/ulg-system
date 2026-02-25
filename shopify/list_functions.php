<?php
/**
 * 🛠️ CEGID API EXPLORER
 * สคริปต์สำหรับดูรายชื่อฟังก์ชันและโครงสร้างข้อมูลทั้งหมดที่มีในระบบ
 */

header('Content-Type: text/html; charset=utf-8');
require_once 'system_info.php'; // เรียกใช้ Config จากไฟล์ system_info.php (ถ้ามี)

// ถ้ายังไม่มีไฟล์ system_info.php ให้ใช้ค่า Default นี้
if (!isset($CEGID_CONFIG)) {
    $CEGID_CONFIG = [
        'wsdl_url' => 'http://90643827-test-retail-ondemand.cegid.cloud/Y2/ItemInventoryWcfService.svc?wsdl',
        'username' => '90643827_002_TEST\\frt',
        'password' => 'adgjm',
    ];
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Cegid API Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="bg-indigo-800 text-white p-6 rounded-t-lg shadow-lg flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold"><i class="fas fa-code"></i> Cegid API Explorer</h1>
                <p class="opacity-80 text-sm mt-1">สำรวจคำสั่งและโครงสร้างข้อมูลทั้งหมดบน Server</p>
            </div>
            <div class="text-right text-xs opacity-70">
                WSDL: <?= basename($CEGID_CONFIG['wsdl_url']) ?>
            </div>
        </div>

        <div class="bg-white p-6 shadow-lg rounded-b-lg">
            <?php
            try {
                $client = new SoapClient($CEGID_CONFIG['wsdl_url'], [
                    'login' => $CEGID_CONFIG['username'],
                    'password' => $CEGID_CONFIG['password'],
                    'trace' => 1,
                    'cache_wsdl' => WSDL_CACHE_NONE
                ]);

                $functions = $client->__getFunctions();
                $types = $client->__getTypes();

                echo "<div class='grid grid-cols-1 lg:grid-cols-2 gap-8'>";

                // 1. แสดงรายชื่อฟังก์ชัน (Functions)
                echo "<div>";
                echo "<h2 class='text-xl font-bold text-indigo-700 mb-4 border-b pb-2'><i class='fas fa-cogs'></i> Available Functions (".count($functions).")</h2>";
                echo "<div class='space-y-3'>";
                foreach ($functions as $func) {
                    // จัดรูปแบบให้อ่านง่าย
                    $func = str_replace(' ', '<span class="mx-1"> </span>', $func);
                    $func = preg_replace('/^(\w+)/', '<span class="text-gray-500 text-xs">$1</span><br><span class="font-bold text-indigo-600 text-lg">', $func);
                    $func = str_replace('(', '</span><span class="text-gray-400">(</span><span class="text-sm text-gray-700">', $func);
                    $func = str_replace(')', '</span><span class="text-gray-400">)</span>', $func);
                    
                    echo "<div class='bg-gray-50 p-3 rounded border hover:border-indigo-300 transition'>";
                    echo $func;
                    echo "</div>";
                }
                echo "</div>";
                echo "</div>";

                // 2. แสดงโครงสร้างข้อมูล (Types)
                echo "<div>";
                echo "<h2 class='text-xl font-bold text-green-700 mb-4 border-b pb-2'><i class='fas fa-project-diagram'></i> Data Structures (Types)</h2>";
                echo "<div class='h-[600px] overflow-y-auto bg-gray-900 text-green-400 p-4 rounded text-xs font-mono leading-relaxed'>";
                foreach ($types as $type) {
                    // จัดรูปแบบ Struct
                    $type = str_replace('struct', '<span class="text-pink-400">struct</span>', $type);
                    $type = str_replace('{', '<span class="text-white">{</span>', $type);
                    $type = str_replace('}', '<span class="text-white">}</span>', $type);
                    $type = preg_replace('/ (\w+);/', ' <span class="text-yellow-300">$1</span>;', $type);
                    echo $type . "<br><br>";
                }
                echo "</div>";
                echo "</div>";

                echo "</div>";

            } catch (Exception $e) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>";
                echo "<strong class='font-bold'>Connection Error!</strong>";
                echo "<span class='block sm:inline'> " . $e->getMessage() . "</span>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
</body>
</html>