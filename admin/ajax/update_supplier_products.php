<?php
include '../../config.php';
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$supplier_id   = intval($_POST['supplier_id'] ?? 0);
$name          = trim($_POST['name'] ?? '');
$contact       = trim($_POST['contact'] ?? '');
$email         = trim($_POST['email'] ?? '');
$supplier_type = trim($_POST['supplier_type'] ?? '');
$product_ids_raw = $_POST['product_ids'] ?? [];

if ($supplier_id <= 0 || empty($name) || empty($contact) || empty($email) || empty($supplier_type)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required supplier information']);
    exit;
}

$product_ids = array_values(array_unique(array_map('intval', (array)$product_ids_raw)));
$product_ids = array_filter($product_ids, fn($v) => $v > 0);

try {
    $conn->begin_transaction();

    // === 1. Update supplier info ===
    $stmt = $conn->prepare("UPDATE supplier SET name=?, contact=?, email=?, supplier_type=?, updated_at=NOW() WHERE supplier_id=?");
    $stmt->bind_param("ssssi", $name, $contact, $email, $supplier_type, $supplier_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($product_ids)) {
        $existing = [];
        $res = $conn->prepare("SELECT product_id FROM product_suppliers WHERE supplier_id=?");
        $res->bind_param("i", $supplier_id);
        $res->execute();
        $res->bind_result($pid);
        while ($res->fetch()) $existing[] = $pid;
        $res->close();

        $to_add = array_values(array_diff($product_ids, $existing));

        if (!empty($to_add)) {
            $ins = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            foreach ($to_add as $pid) {
                $ins->bind_param("ii", $pid, $supplier_id);
                $ins->execute();
            }
            $ins->close();
        }
    }

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Supplier information updated successfully']);
    exit;
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("update_supplier_info.php error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
