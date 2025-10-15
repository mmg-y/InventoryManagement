<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

error_reporting(E_ALL);
ini_set('display_errors', 1); // temporarily enable errors for debugging

// include files
include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php'); 


// Initialize main class
$market = new SuperMarket($conn);

// Fetch all supplier purchases
$purchases = $market->getSupplierPurchases();

// Output response
if (!empty($purchases)) {
    echo json_encode([
        "status" => "success",
        "message" => "Supplier purchase records retrieved successfully.",
        "data" => $purchases
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No supplier purchase records found.",
        "data" => []
    ]);
}
