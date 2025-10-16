<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');
error_reporting(0);
ini_set('display_errors', 0);

include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php');

$sm = new SuperMarket($conn);

// Get all products
$products = $sm->getAllProducts();

echo json_encode([
    'success' => true,
    'products' => $products
]);
