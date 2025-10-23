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
    if (empty($_POST['user_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User ID is required'
        ]);
        exit;
    }

    $userId = intval($_POST['user_id']);
    $targetDir = "../../images/"; 
    $uploadSuccess = false; 

    if (!empty($_FILES["profile_pic"]["name"])) {
        $fileName = time() . "_" . basename($_FILES["profile_pic"]["name"]);
        $targetFilePath = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetFilePath)) {
            $profileUpdate = $updateUser->uploadProfile($userId, $fileName);
            echo json_encode($profileUpdate);
            $uploadSuccess = true;
        } else {
            echo json_encode(["status" => "error", "message" => "File upload failed."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No image selected."]);
    }
