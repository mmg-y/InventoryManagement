<?php
    // headers
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');
    error_reporting(0);        
    ini_set('display_errors', 0);

    // include files
    include_once('../includes/config.php');
    include_once('../core/initialize.php');
    include_once('../core/super_market.php'); 

    // instantiate SuperMarket
    $sm = new SuperMarket($conn);

    // read JSON input
    $data = json_decode(file_get_contents("php://input"));

    // validate input
    if (empty($data->username) || empty($data->password)) {
        echo json_encode(['message' => 'Username and password required']);
        exit;
    }

    // assign properties
    $sm->username = $data->username;
    $sm->password = $data->password;

    // try login
    $user = $sm->login();

    if ($user) {
        // return user details as JSON
        echo json_encode(['data' => [$user]]);
    } else {
        echo json_encode([
            "data" => []
        ]);
    }
