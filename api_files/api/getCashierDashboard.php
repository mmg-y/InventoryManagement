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

    $todaysSale = new SuperMarket($conn);

    $data = json_decode(file_get_contents("php://input"));

    if(empty($data->seller_id)){
        echo json_encode([
                "status" => "Error",
                "message" => "Seller ID is required",
                "response" => []
            ]
        );
        exit;
    }

    $sellerId = htmlspecialchars(strip_tags(($data->seller_id)));
    $salesToday = $todaysSale->cashierDashboard($sellerId);

    if(count($salesToday) > 0){
        echo json_encode([
            "status" => "success",
            "message" => "sales data retrieved successfully.",
            "data" => $salesToday
        ]);
    }
    else{
        echo json_encode([
            "status" => "error",
            "message" => "No sales found today",
            "data" => []
        ]);
    }

