<?php
include '../../config.php';

header('Content-Type: application/json');

function generateBatchNumber() {
    return 'BATCH-' . date('Ymd-His-') . rand(100, 999);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$supplier_id = $_POST['supplier_id'] ?? null;
$product_id = $_POST['product_id'] ?? null;
$qty = $_POST['qty'] ?? 0;
$cost_price = $_POST['cost_price'] ?? 0;
$remarks = $_POST['remarks'] ?? null;

if (empty($supplier_id) || empty($product_id) || $qty <= 0 || $cost_price <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$batch_number = generateBatchNumber();

$stmt = $conn->prepare("
    INSERT INTO product_stocks 
    (product_id, supplier_id, batch_number, quantity, remaining_qty, cost_price, remarks, status, created_at, updated_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'ordered', NOW(), NOW())
");
$stmt->bind_param("iisidds", $product_id, $supplier_id, $batch_number, $qty, $qty, $cost_price, $remarks);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Purchase order created successfully']);
} else {
    echo json_encode(['error' => 'Database insert failed']);
}

$stmt->close();
