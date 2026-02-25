<?php
require_once('../config.php');

header('Content-Type: application/json');

$monthIndex = isset($_GET['monthIndex']) ? intval($_GET['monthIndex']) : null;

if ($monthIndex === null) {
    http_response_code(400);
    echo json_encode(['error' => 'monthIndex is required']);
    exit;
}

// โหลด theme
$stmt = $pdo->prepare("SELECT theme FROM months WHERE month_index = ?");
$stmt->execute([$monthIndex]);
$month = $stmt->fetch(PDO::FETCH_ASSOC);

// ถ้าไม่มีข้อมูลเลย
if (!$month) {
    echo json_encode(['exists' => false]);
    exit;
}

// โหลด deliverables
$stmt = $pdo->prepare("SELECT * FROM deliverables WHERE month_index = ? ORDER BY deliverable_index ASC");
$stmt->execute([$monthIndex]);
$deliverables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// โหลด tasks
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE month_index = ?");
$stmt->execute([$monthIndex]);
$taskRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// จัดรูปแบบข้อมูล
foreach ($deliverables as &$deliverable) {
    $deliverable['tasks'] = [];
    foreach ($taskRows as $task) {
        if ($task['deliverable_index'] == $deliverable['deliverable_index']) {
            $deliverable['tasks'][] = [
                'name' => $task['name'],
                'completed' => boolval($task['completed'])
            ];
        }
    }
}

echo json_encode([
    'exists' => true,
    'theme' => $month['theme'],
    'deliverables' => $deliverables
]);
