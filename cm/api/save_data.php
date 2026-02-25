<?php
require_once('../config.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$monthIndex = $data['monthIndex'] ?? null;
$theme = $data['theme'] ?? '';
$deliverables = $data['deliverables'] ?? [];

if ($monthIndex === null) {
    http_response_code(400);
    echo json_encode(['error' => 'monthIndex required']);
    exit;
}

// อัปเดต theme
$stmt = $pdo->prepare("UPDATE months SET theme = ? WHERE month_index = ?");
$stmt->execute([$theme, $monthIndex]);

// อัปเดต deliverables
foreach ($deliverables as $dIndex => $d) {
    $stmt = $pdo->prepare("UPDATE deliverables SET 
        custom_name = ?, notes = ?, price_received = ?, ordered = ?
        WHERE month_index = ? AND deliverable_index = ?");
    $stmt->execute([
        $d['customName'] ?? '',
        $d['notes'] ?? '',
        $d['priceReceived'] ? 1 : 0,
        $d['ordered'] ? 1 : 0,
        $monthIndex,
        $dIndex
    ]);

    // อัปเดต tasks
    foreach ($d['tasks'] as $tIndex => $task) {
        $stmt = $pdo->prepare("UPDATE tasks SET completed = ? 
            WHERE month_index = ? AND deliverable_index = ? AND task_index = ?");
        $stmt->execute([
            $task['completed'] ? 1 : 0,
            $monthIndex,
            $dIndex,
            $tIndex
        ]);
    }
}

echo json_encode(['status' => 'updated']);
