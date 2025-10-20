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

    if (empty($data->cart_items_id) && empty($data->action)) {
        echo json_encode(['success' => false, 'message' => 'cart items id or actionrequired']);
        exit;
    }

    $cartItemsId = htmlspecialchars(strip_tags($data->cart_items_id));
    $action = htmlspecialchars(strip_tags($data->action));
    $cartUpdate = $market->updateCartItem($cartItemsId, $action);

    // Prepare JSON response
    if (count($cartUpdate) > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "cart Item updated successfully.",
            "data" => $cartUpdate
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No items found.",
            "data" => []
        ]);
    }
