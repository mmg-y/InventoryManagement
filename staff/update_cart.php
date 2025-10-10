<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_items_id = intval($_POST['cart_items_id']);
    $action = $_POST['action'];

    $item = $conn->query("SELECT qty FROM cart_items WHERE cart_items_id=$cart_items_id")->fetch_assoc();

    if ($item) {
        if ($action === "increase") {
            $conn->query("UPDATE cart_items SET qty = qty + 1 WHERE cart_items_id=$cart_items_id");
        } elseif ($action === "decrease") {
            if ($item['qty'] > 1) {
                $conn->query("UPDATE cart_items SET qty = qty - 1 WHERE cart_items_id=$cart_items_id");
            } else {
                $conn->query("DELETE FROM cart_items WHERE cart_items_id=$cart_items_id");
            }
        }
    }
}

header("Location: staff.php?page=dashboard");
exit;
