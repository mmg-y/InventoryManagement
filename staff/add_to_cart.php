<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $cart_id = intval($_POST['cart_id']);

    // Fetch latest active stock, markup percent, and product info
    $stmt = $conn->prepare("
        SELECT 
            p.product_id,
            p.reserved_qty,
            ps.product_stock_id AS batch_id,
            ps.remaining_qty,
            ps.cost_price,
            rv.percent AS markup
        FROM product p
        LEFT JOIN product_stocks ps ON ps.product_id = p.product_id AND ps.status = 'active'
        LEFT JOIN retail_variables rv ON rv.retail_id = p.retail_id
        WHERE p.product_id = ?
        ORDER BY ps.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product || $product['remaining_qty'] <= 0) {
        $_SESSION['error'] = "Product not available or out of stock.";
        header("Location: staff.php?page=dashboard");
        exit;
    }

    $cost_price = floatval($product['cost_price']);
    $markup = floatval($product['markup']);
    $price = $cost_price * (1 + ($markup / 100));
    $earning = $price - $cost_price;
    $batch_id = intval($product['batch_id']);

    // Check if already in cart
    $check = $conn->prepare("SELECT qty FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1");
    $check->bind_param("ii", $cart_id, $product_id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        $conn->query("
            UPDATE cart_items
            SET qty = qty + 1,
                earning = (qty + 1) * $earning,
                updated_at = NOW()
            WHERE cart_id = $cart_id AND product_id = $product_id
        ");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO cart_items 
            (cart_id, product_id, qty, price, batch_id, cost_price, earning, created_at, updated_at)
            VALUES (?, ?, 1, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("iidddd", $cart_id, $product_id, $price, $batch_id, $cost_price, $earning);
        $stmt->execute();
        $stmt->close();
    }

    // Increment reserved_qty
    $conn->query("
        UPDATE product
        SET reserved_qty = COALESCE(reserved_qty, 0) + 1,
            updated_at = NOW()
        WHERE product_id = $product_id
    ");

    $_SESSION['success'] = "Product added to cart!";
}

header("Location: staff.php?page=dashboard");
exit;
