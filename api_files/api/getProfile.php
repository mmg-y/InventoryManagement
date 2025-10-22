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

    $profile = new SuperMarket($conn);
    $data = json_decode(file_get_contents("php://input"));
    if(empty($data->user_id)){
        echo json_encode([
                "status" => "Error",
                "message" => "User ID is required",
                "response" => []
            ]
        );
        exit;
    }

    $userId = htmlspecialchars(strip_tags(($data->user_id)));
    $userProfile = $profile->getProfile($userId);
    if(count($userProfile) > 0){
        echo json_encode([
            "status" => "success",
            "message" => "User profile retrieved successfully.",
            "data" => $userProfile
        ]);
    }
    else{
        echo json_encode([
            "status" => "error",
            "message" => "No user profile found",
            "data" => []
        ]);
    }