<?php
// headers
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

// instantiate SuperMarket
$sm = new SuperMarket($conn);

// call the method from the class
$products = $sm->getProductsWithStatus();

if ($products !== null) {
    echo json_encode(['data' => $products]);
} else {
    echo json_encode(['data' => []]);
}
