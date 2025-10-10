<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_id = intval($_POST['cart_id']);
    $total = floatval($_POST['total']);

    $conn->query("UPDATE carts SET status='completed', total=$total, updated_at=NOW() WHERE cart_id=$cart_id");
}

header("Location: staff.php?page=dashboard");
exit;
