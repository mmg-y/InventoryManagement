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
    if (empty($data->user_id)) {
        echo json_encode([
            'success' => false,
             'message' => 'User ID is required'
            ]);
        exit;
    }
    elseif (empty($data->email) && empty($data->username) && empty($data->telephone) && empty($data->first_name) && empty($data->last_name)) {
        echo json_encode([
            'success' => false, 
            'message' => 'At least one field to update is required'
        ]);
        exit;
    }
    $userId = htmlspecialchars(strip_tags($data->user_id));
    $email = isset($data->email) ? htmlspecialchars(strip_tags($data->email)) : null;
    $username = isset($data->username) ? htmlspecialchars(strip_tags($data->username)) : null;
    $telephone = isset($data->telephone) ? htmlspecialchars(strip_tags($data->telephone)) : null;
    $firstName = isset($data->first_name) ? htmlspecialchars(strip_tags($data->first_name)) : null;
    $lastName = isset($data->last_name) ? htmlspecialchars(strip_tags($data->last_name)) : null;

    $profileUpdate = $updateUser->updateProfile($userId, $email, $username, $telephone, $firstName, $lastName);
    
    echo json_encode($profileUpdate);
