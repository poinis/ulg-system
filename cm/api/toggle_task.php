<?php
require_once('../config.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$monthIndex = $data['monthIndex'];
$deliverableIndex = $data['deliverableIndex'];
$type = $data['type'];
$value = $data['value'];

if ($type === 'task') {
    $taskIndex = $data['taskIndex'];
    $stmt = $pdo->prepare("UPDATE tasks SET completed = ? 
        WHERE month_index = ? AND deliverable_index = ? AND task_index = ?");
    $stmt->execute([$value ? 1 : 0, $monthIndex, $deliverableIndex, $taskIndex]);
} else {
    $stmt = $pdo->prepare("UPDATE deliverables SET {$type} = ? 
        WHERE month_index = ? AND deliverable_index = ?");
    $stmt->execute([$value ? 1 : 0, $monthIndex, $deliverableIndex]);
}

echo json_encode(['status' => 'success']);
