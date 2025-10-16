<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');
error_reporting(0);
ini_set('display_errors', 0);

include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php');

$sm = new SuperMarket($conn);

// Read JSON input
$data = json_decode(file_get_contents("php://input"));

if (empty($data->orderId)) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$orderId = htmlspecialchars(strip_tags($data->orderId));
$response = $sm->markOrderAsArrived($orderId);

echo json_encode($response);
?><?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');
error_reporting(0);
ini_set('display_errors', 0);

include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php');

$sm = new SuperMarket($conn);

// Read JSON input
$data = json_decode(file_get_contents("php://input"));

if (empty($data->orderId)) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$orderId = htmlspecialchars(strip_tags($data->orderId));
$response = $sm->markOrderAsArrived($orderId);

echo json_encode(["response" => $response]);