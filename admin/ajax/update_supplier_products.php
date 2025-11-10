<?php
include '../../config.php';
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or empty JSON input']);
    exit;
}

$supplier_id   = intval($data['supplier_id'] ?? 0);
$name          = trim($data['name'] ?? '');
$contact       = trim($data['contact'] ?? '');
$email         = trim($data['email'] ?? '');
$supplier_type = trim($data['supplier_type'] ?? '');
$product_data_raw = $data['product_ids'] ?? [];

$product_data = [];
foreach ($product_data_raw as $p) {
    if (is_array($p) && isset($p['id'])) {
        $id = intval($p['id']);
        $cost = isset($p['cost']) ? floatval($p['cost']) : 0.00;
        if ($id > 0) $product_data[$id] = $cost;
    } elseif (is_numeric($p)) {
        $product_data[intval($p)] = 0.00;
    }
}

if ($supplier_id <= 0 || empty($name) || empty($contact) || empty($email) || empty($supplier_type)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required supplier information']);
    exit;
}

$product_ids = array_keys($product_data);

try {
    $conn->begin_transaction();

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

        $to_add = array_diff($product_ids, $existing);

        if (!empty($to_add)) {
            $ins = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, default_cost_price, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $ins->bind_param("iid", $pid, $supplier_id, $cost);
            foreach ($to_add as $pid) {
                $cost = $product_data[$pid] ?? 0.00;
                $ins->execute();
            }
            $ins->close();
        }

        $upd = $conn->prepare("UPDATE product_suppliers SET default_cost_price=?, updated_at=NOW() WHERE supplier_id=? AND product_id=?");
        $upd->bind_param("dii", $cost, $supplier_id, $pid);
        foreach ($product_data as $pid => $cost) {
            $upd->execute();
        }
        $upd->close();
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
