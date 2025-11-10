<?php
include '../../config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing purchase ID']);
    exit;
}

$id = intval($_GET['id']);
$success = $conn->query("DELETE FROM product_stocks WHERE product_stock_id = $id");

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Purchase deleted successfully']);
} else {
    echo json_encode(['error' => 'Delete failed']);
}
