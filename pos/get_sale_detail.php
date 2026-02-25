<?php
// pos/get_sale_detail.php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once "../config.php";

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$saleId) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pos_sales WHERE id = ?");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pos_sale_items WHERE sale_id = ?");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'sale' => $sale, 'items' => $items]);
