<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');
error_reporting(0);
ini_set('display_errors', 0);

include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php');

$sm = new SuperMarket($conn);

// Read JSON input
$data = json_decode(file_get_contents("php://input"));

if (empty($data->product_id)) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$productId = htmlspecialchars(strip_tags($data->product_id));
$suppliers = $sm->getSuppliersByProduct($productId);

echo json_encode([
    'success' => true,
    'suppliers' => $suppliers
]);
