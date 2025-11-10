<?php
include '../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$id = $_POST['product_stock_id'] ?? null;
$product_id = $_POST['product_id'] ?? null;
$qty = $_POST['qty'] ?? 0;
$cost_price = $_POST['cost_price'] ?? 0;
$status = $_POST['status'] ?? null;
$remarks = $_POST['remarks'] ?? '';

if (!$id || !$product_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE product_stocks 
    SET product_id=?, quantity=?, remaining_qty=?, cost_price=?, status=?, remarks=?, updated_at=NOW()
    WHERE product_stock_id=?
");
$stmt->bind_param("sdddssi", $product_id, $qty, $qty, $cost_price, $status, $remarks, $id);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Purchase updated successfully']);
} else {
    echo json_encode(['error' => 'Failed to update record']);
}

$stmt->close();
