<?php
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: POST, PUT');
    header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

    error_reporting(E_ALL);
    ini_set('display_errors', 1); // temporarily enable errors for debugging

    // include files
    include_once('../includes/config.php');
    include_once('../core/initialize.php');
    include_once('../core/super_market.php'); 

    $updateUser = new SuperMarket($conn);
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->product_name) || empty($data->product_code)) {
        echo json_encode([
            'success' => false,
            'message' => 'Product name and product code are required'
            ]);
        exit;
    }

    $productName = htmlspecialchars(strip_tags($data->product_name));
    $productCode = htmlspecialchars(strip_tags($data->product_code));

    $searchResults = $updateUser->searchProduct($productName, $productCode);
    if (count($searchResults) > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Products found",
            "data" => $searchResults
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No products found',
            'data' => []
        ]);
    }