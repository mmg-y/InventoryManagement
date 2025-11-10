<?php
include '../../config.php';
header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$supplier_id = intval($_POST['supplier_id'] ?? 0);
$product_ids_raw = $_POST['product_ids'] ?? [];

if ($supplier_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid supplier id']);
    exit;
}

$product_ids = array_values(array_unique(array_map('intval', (array)$product_ids_raw)));
$product_ids = array_filter($product_ids, function($v) { return $v > 0; });

try {

    $conn->begin_transaction();

    $existing = [];
    $stmt = $conn->prepare("SELECT product_id FROM product_suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $stmt->bind_result($pid);
    while ($stmt->fetch()) {
        $existing[] = (int)$pid;
    }
    $stmt->close();

    $to_add = array_values(array_diff($product_ids, $existing));

    if (!empty($to_add)) {
        $insert_stmt = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        foreach ($to_add as $add_pid) {
            $add_pid = (int)$add_pid;
            $insert_stmt->bind_param("ii", $add_pid, $supplier_id);
            $insert_stmt->execute();
        }
        $insert_stmt->close();
    }

    $conn->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Supplier products updated',
        'added'   => $to_add
    ]);
    exit;
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("update_supplier_products.php error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
