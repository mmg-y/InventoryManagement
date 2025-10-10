<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $cart_id = intval($_POST['cart_id']);

    // Check if already in cart
    $check = $conn->query("SELECT * FROM cart_items WHERE cart_id=$cart_id AND product_id=$product_id LIMIT 1");

    if ($check->num_rows > 0) {
        $conn->query("UPDATE cart_items SET qty = qty + 1 WHERE cart_id=$cart_id AND product_id=$product_id");
    } else {
        $conn->query("INSERT INTO cart_items (cart_id, product_id, qty) VALUES ($cart_id, $product_id, 1)");
    }
}

// ✅ stay inside the staff layout
header("Location: staff.php?page=dashboard");
exit;
