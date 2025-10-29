<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php');

$sm = new SuperMarket($conn);

// Read JSON input
$data = json_decode(file_get_contents("php://input"));

// Validate input
if (
    empty($data->cashier_id) ||
    empty($data->total_amount) ||
    empty($data->total_earning) ||
    empty($data->total_cash_receive) ||
    empty($data->total_change) ||
    empty($data->items)
) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Sanitize
$userId       = htmlspecialchars(strip_tags($data->cashier_id));
$totalAmount  = htmlspecialchars(strip_tags($data->total_amount));
$totalEarning = htmlspecialchars(strip_tags($data->total_earning));
$totalCashReceive = htmlspecialchars(strip_tags($data->total_cash_receive));
$totalChange = htmlspecialchars(strip_tags($data->total_change));
$items        = $data->items;

// Call the proper method
$response = $sm->checkoutCart($userId, $totalAmount, $totalEarning, $items, $totalCashReceive, $totalChange);

// Return JSON response
echo json_encode($response);
