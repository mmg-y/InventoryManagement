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

    // Read JSON input
    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->product_code)) {
        echo json_encode(['success' => false, 'message' => 'Product code is required']);
        exit;
    }

    $productCode = htmlspecialchars(strip_tags($data->product_code));
    $sellerId = htmlspecialchars(strip_tags($data->seller_id));
    $cartProduct = $market->getProductByQr($productCode, $sellerId);

    // Prepare JSON response
    if (count($cartProduct) > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "cart Items retrieved successfully.",
            "data" => $cartProduct
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No items found.",
            "data" => []
        ]);
    }
