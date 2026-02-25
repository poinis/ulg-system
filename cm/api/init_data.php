<?php
require_once('../config.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$monthIndex = $data['monthIndex'] ?? null;
$deliverables = $data['deliverables'] ?? [];

if ($monthIndex === null || empty($deliverables)) {
    http_response_code(400);
    echo json_encode(['error' => 'monthIndex and deliverables required']);
    exit;
}

// ป้องกันไม่ให้ insert ซ้ำ
$stmt = $pdo->prepare("SELECT COUNT(*) FROM months WHERE month_index = ?");
$stmt->execute([$monthIndex]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['message' => 'Month already exists']);
    exit;
}

// สร้างเดือน
$stmt = $pdo->prepare("INSERT INTO months (month_index, theme) VALUES (?, '')");
$stmt->execute([$monthIndex]);

// สร้าง deliverables และ tasks
foreach ($deliverables as $dIndex => $d) {
    $stmt = $pdo->prepare("INSERT INTO deliverables 
        (month_index, deliverable_index, name, icon) VALUES (?, ?, ?, ?)");
    $stmt->execute([$monthIndex, $dIndex, $d['name'], $d['icon']]);

    foreach ($d['tasks'] as $tIndex => $taskName) {
        $stmt = $pdo->prepare("INSERT INTO tasks 
            (month_index, deliverable_index, task_index, name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$monthIndex, $dIndex, $tIndex, $taskName]);
    }
}

echo json_encode(['message' => 'Initialized']);
