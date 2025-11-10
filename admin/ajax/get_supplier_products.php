<?php
include '../../config.php';
header('Content-Type: application/json');

$supplier_id = intval($_GET['supplier_id'] ?? 0);

if ($supplier_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid supplier ID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        ps.product_id, 
        p.product_name, 
        ps.default_cost_price
    FROM product_suppliers ps
    JOIN product p ON p.product_id = ps.product_id
    WHERE ps.supplier_id = ?
    ORDER BY p.product_name ASC
");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    $supplier_products[] = [
        'product_id' => (int)$r['product_id'],
        'product_name' => $r['product_name'],
        'default_cost_price' => (float)$r['default_cost_price']
    ];
}
$stmt->close();

$excluded_ids = array_column($supplier_products, 'product_id');
$available_products = [];

if (!empty($excluded_ids)) {
    $placeholders = implode(',', array_fill(0, count($excluded_ids), '?'));
    $types = str_repeat('i', count($excluded_ids));
    $query = "SELECT product_id, product_name FROM product WHERE product_id NOT IN ($placeholders) ORDER BY product_name ASC";
    $stmt = $conn->prepare($query);

    $bind_names[] = $types;
    foreach ($excluded_ids as $k => $id) {
        $bind_name = 'bind' . $k;
        $$bind_name = (int)$id;
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $available_products[] = [
            'product_id' => (int)$r['product_id'],
            'product_name' => $r['product_name']
        ];
    }
    $stmt->close();
} else {
    $res = $conn->query("SELECT product_id, product_name FROM product ORDER BY product_name ASC");
    while ($r = $res->fetch_assoc()) {
        $available_products[] = [
            'product_id' => (int)$r['product_id'],
            'product_name' => $r['product_name']
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'supplier_products' => $supplier_products,
    'available_products' => $available_products
]);
