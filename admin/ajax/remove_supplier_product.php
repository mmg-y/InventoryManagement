<?php
include '../../config.php';
header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$supplier_id = intval($_POST['supplier_id'] ?? 0);
$product_id  = intval($_POST['product_id'] ?? 0);

if ($supplier_id <= 0 || $product_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid supplier_id or product_id']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM product_suppliers WHERE supplier_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $supplier_id, $product_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'Product removed from supplier']);
    exit;

} catch (mysqli_sql_exception $e) {
    error_log("remove_supplier_product.php error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
