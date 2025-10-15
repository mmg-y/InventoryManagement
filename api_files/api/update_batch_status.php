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
if (!isset($data['batchId']) || !isset($data['status'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required parameters: batchId or status."
    ]);
    exit;
}

$batchId = intval($data['batchId']);
$status = trim($data['status']);

// Call the update function
$success = $market->updateBatchStatus($batchId, $status);

if ($success) {
    echo json_encode([
        "status" => "success",
        "message" => "Batch status updated successfully."
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update batch status. Check if batchId exists."
    ]);
}

