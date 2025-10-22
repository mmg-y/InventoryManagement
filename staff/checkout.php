<?php
session_start();
include "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['id']) || $_SESSION['type'] !== "cashier") {
        header("Location: ../index.php");
        exit;
    }

    $cashier_id = intval($_SESSION['id']);
    $cart_id = intval($_POST['cart_id']);
    $total = floatval($_POST['total']);

    $cart_items = $conn->query("
        SELECT ci.cart_items_id, ci.product_id, ci.qty,
               ps.product_stock_id AS batch_id, ps.cost_price,
               rv.percent AS retail_percent
        FROM cart_items ci
        JOIN product p ON ci.product_id = p.product_id
        LEFT JOIN product_stocks ps ON ci.batch_id = ps.product_stock_id
        LEFT JOIN retail_variables rv ON p.retail_id = rv.retail_id
        WHERE ci.cart_id = $cart_id
    ");

    if ($cart_items->num_rows === 0) {
        $_SESSION['error'] = "Cart is empty.";
        header("Location: staff.php?page=dashboard");
        exit;
    }

    $total_earning = 0;
    while ($item = $cart_items->fetch_assoc()) {
        $price = $item['cost_price'] * (1 + ($item['retail_percent'] / 100));
        $total_earning += ($price - $item['cost_price']) * $item['qty'];
    }

    $conn->query("
        INSERT INTO sales (total_amount, total_earning, sale_date, cashier_id, remarks)
        VALUES ($total, $total_earning, NOW(), $cashier_id, 'Sale completed')
    ");
    $sale_id = $conn->insert_id;

    $cart_items->data_seek(0);
    while ($item = $cart_items->fetch_assoc()) {
        $price = $item['cost_price'] * (1 + ($item['retail_percent'] / 100));
        $earning = ($price - $item['cost_price']) * $item['qty'];
        $batch_id = $item['batch_id'] ?? 'NULL';

        $conn->query("
            INSERT INTO sales_item (sale_id, product_id, batch_id, quantity, unit_price, cost_price, earning, remarks)
            VALUES ($sale_id, {$item['product_id']}, $batch_id, {$item['qty']}, $price, {$item['cost_price']}, $earning, 'Sold item')
        ");

        if (!empty($item['batch_id'])) {
            $conn->query("
                UPDATE product_stocks
                SET remaining_qty = GREATEST(remaining_qty - {$item['qty']}, 0),
                    updated_at = NOW()
                WHERE product_stock_id = {$item['batch_id']}
            ");
        }

        $conn->query("
            UPDATE product
            SET total_quantity = GREATEST(total_quantity - {$item['qty']},0),
                reserved_qty = GREATEST(COALESCE(reserved_qty,0) - {$item['qty']},0),
                updated_at = NOW()
            WHERE product_id = {$item['product_id']}
        ");
    }

    $conn->query("
        UPDATE carts
        SET status='completed', total=$total, total_earning=$total_earning, updated_at=NOW()
        WHERE cart_id=$cart_id
    ");

    $conn->query("DELETE FROM cart_items WHERE cart_id=$cart_id");

    $_SESSION['success'] = "Checkout completed!";
}

header("Location: staff.php?page=dashboard");
exit;
