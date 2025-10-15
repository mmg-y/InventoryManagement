<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php');

$market = new SuperMarket($conn);

// Read JSON body
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($data['orderId']) || !isset($data['cancelDetails'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required parameters: batchId, or cancelDetails."
    ]);
    exit;
}

$batchId = intval($data['orderId']);
$cancelDetails = trim($data['cancelDetails']);

// Call the update function
$success = $market->cancelOrder($batchId, $cancelDetails);

if ($success) {
    echo json_encode([
        "status" => "success",
        "message" => "Order cancelled successfully.",
        "id" => $batchId,
        "cancelDetails" => $cancelDetails
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to cancel order. Check if batchId exists."
    ]);
}
