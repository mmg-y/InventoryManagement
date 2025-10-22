<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_items_id = intval($_POST['cart_items_id']);
    $action = $_POST['action'] ?? '';

    // Get cart item and product info
    $stmt = $conn->prepare("
        SELECT ci.qty, ci.price, ci.cost_price, ci.product_id
        FROM cart_items ci
        WHERE ci.cart_items_id = ?
    ");
    $stmt->bind_param("i", $cart_items_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        header("Location: staff.php?page=dashboard");
        exit;
    }

    $price = $item['price'];
    $cost = $item['cost_price'];
    $earning_per_unit = $price - $cost;
    $product_id = $item['product_id'];

    if ($action === "increase") {
        $conn->query("
            UPDATE cart_items
            SET qty = qty + 1,
                earning = (qty + 1) * $earning_per_unit,
                updated_at = NOW()
            WHERE cart_items_id = $cart_items_id
        ");
        $conn->query("
            UPDATE product
            SET reserved_qty = COALESCE(reserved_qty,0) + 1,
                updated_at = NOW()
            WHERE product_id = $product_id
        ");
    } elseif ($action === "decrease") {
        if ($item['qty'] > 1) {
            $conn->query("
                UPDATE cart_items
                SET qty = qty - 1,
                    earning = (qty - 1) * $earning_per_unit,
                    updated_at = NOW()
                WHERE cart_items_id = $cart_items_id
            ");
            $conn->query("
                UPDATE product
                SET reserved_qty = GREATEST(COALESCE(reserved_qty,0) - 1,0),
                    updated_at = NOW()
                WHERE product_id = $product_id
            ");
        } else {
            $conn->query("DELETE FROM cart_items WHERE cart_items_id = $cart_items_id");
            $conn->query("
                UPDATE product
                SET reserved_qty = GREATEST(COALESCE(reserved_qty,0) - 1,0),
                    updated_at = NOW()
                WHERE product_id = $product_id
            ");
        }
    }
}

header("Location: staff.php?page=dashboard");
exit;
