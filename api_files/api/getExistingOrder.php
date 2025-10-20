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

    if (empty($data->user_id)) {
        echo json_encode(['success' => false, 'message' => 'Cashier ID is required']);
        exit;
    }

    $userId = htmlspecialchars(strip_tags($data->user_id));
    $cartItems = $market->getExistingOrder($userId);

    // Prepare JSON response
    if (count($cartItems) > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "cart Items retrieved successfully.",
            "data" => $cartItems
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No items found.",
            "data" => []
        ]);
    }
