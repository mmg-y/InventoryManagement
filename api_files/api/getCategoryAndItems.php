<?php
// headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once('../includes/config.php');
include_once('../core/initialize.php');
include_once('../core/super_market.php'); 

$sm = new SuperMarket($conn);

$categories = $sm->getCategoriesAndItems();

if (!empty($categories)) {
    echo json_encode(['data' => $categories]);
} else {
    echo json_encode(['data' => []]);
}
