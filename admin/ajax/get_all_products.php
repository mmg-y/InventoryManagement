<?php
include '../../config.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT product_id, product_name FROM product ORDER BY product_name ASC");

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    'status' => 'success',
    'products' => $products
]);
