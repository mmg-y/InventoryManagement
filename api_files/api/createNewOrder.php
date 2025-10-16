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

// Validate input
if (
    empty($data->product_id) ||
    empty($data->supplier_id) ||
    empty($data->quantity) ||
    empty($data->cost_price)
) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Assign and sanitize
$productId  = htmlspecialchars(strip_tags($data->product_id));
$supplierId = htmlspecialchars(strip_tags($data->supplier_id));
$quantity   = htmlspecialchars(strip_tags($data->quantity));
$costPrice  = htmlspecialchars(strip_tags($data->cost_price));
$remarks    = !empty($data->remarks) ? htmlspecialchars(strip_tags($data->remarks)) : '';

// Call the method
$response = $sm->createNewOrder($productId, $supplierId, $quantity, $costPrice, $remarks);

// Return JSON
echo json_encode(['response' => $response]);
