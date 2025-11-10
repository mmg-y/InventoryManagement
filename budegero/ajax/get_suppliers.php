<?php
include '../../config.php';

if (!isset($_GET['product_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing product_id"]);
    exit;
}

$product_id = intval($_GET['product_id']);

// Prepare and execute the query
$stmt = $conn->prepare("
    SELECT 
        ps.product_supplier_id,
        ps.default_cost_price,
        s.supplier_id,
        s.name AS supplier_name,
        s.contact,
        s.email,
        s.supplier_type
    FROM product_suppliers ps
    JOIN supplier s ON ps.supplier_id = s.supplier_id
    WHERE ps.product_id = ?
    ORDER BY s.name
");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();

$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

header('Content-Type: application/json');
echo json_encode($suppliers);
exit;
