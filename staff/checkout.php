<?php
session_start();
include "../config.php";
header('Content-Type: application/json; charset=utf-8');

// For development: show PHP errors in response (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['type'] !== "cashier") {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Make mysqli throw exceptions on error
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $cashier_id = intval($_SESSION['id']);
    $cart_id = intval($_POST['cart_id'] ?? 0);
    $total = floatval($_POST['total'] ?? 0);
    $cash = floatval($_POST['cash'] ?? 0);

    if (!$cart_id || $total <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }

    if ($cash < $total) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Insufficient cash received.']);
        exit;
    }

    $change = round($cash - $total, 2);

    $conn->begin_transaction();

    // Insert sale (prepared)
    $stmt = $conn->prepare("
        INSERT INTO sales (total_amount, total_earning, sale_date, cashier_id, remarks)
        VALUES (?, ?, NOW(), ?, ?)
    ");

    // We'll compute total_earning below
    $total_earning = 0;

    // Fetch cart items
    $itemsRes = $conn->query("
        SELECT ci.cart_items_id, ci.product_id, ci.qty, ci.price, ci.cost_price, ci.batch_id,
               rv.percent AS retail_percent
        FROM cart_items ci
        JOIN product p ON ci.product_id = p.product_id
        LEFT JOIN retail_variables rv ON p.retail_id = rv.retail_id
        WHERE ci.cart_id = $cart_id
    ");

    if (!$itemsRes || $itemsRes->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Cart is empty.']);
        exit;
    }

    // compute total earning using unit data (price - cost) * qty
    $items = [];
    while ($r = $itemsRes->fetch_assoc()) {
        $items[] = $r;
        $unit_price = floatval($r['price']);
        $cost_price = floatval($r['cost_price']);
        $qty = intval($r['qty']);
        $total_earning += ($unit_price - $cost_price) * $qty;
    }

    // Insert sale
    $remarks = 'Sale completed';
    $stmt = $conn->prepare("INSERT INTO sales (total_amount, total_earning, sale_date, cashier_id, remarks) VALUES (?, ?, NOW(), ?, ?)");
    $stmt->bind_param("ddis", $total, $total_earning, $cashier_id, $remarks);
    $stmt->execute();
    $sale_id = $stmt->insert_id;

    // Insert sale items and update stock
    $si_stmt = $conn->prepare("INSERT INTO sales_items (sale_id, product_id, batch_id, quantity, unit_price, cost_price, earning, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $upd_stock = $conn->prepare("UPDATE product_stocks SET remaining_qty = GREATEST(remaining_qty - ?, 0), updated_at = NOW() WHERE product_stock_id = ?");
    $upd_product = $conn->prepare("UPDATE product SET total_quantity = GREATEST(total_quantity - ?,0), reserved_qty = GREATEST(COALESCE(reserved_qty,0) - ?,0), updated_at=NOW() WHERE product_id = ?");

    foreach ($items as $it) {
        $unit_price = floatval($it['price']);
        $cost_price = floatval($it['cost_price']);
        $qty = intval($it['qty']);
        $earning = ($unit_price - $cost_price) * $qty;
        $batch_id = $it['batch_id'] !== null ? intval($it['batch_id']) : null;
        $rem = 'Sold item';

        $si_stmt->bind_param("iiiiddds", $sale_id, $it['product_id'], $batch_id, $qty, $unit_price, $cost_price, $earning, $rem);
        $si_stmt->execute();

        if ($batch_id) {
            $upd_stock->bind_param("ii", $qty, $batch_id);
            $upd_stock->execute();
        }

        $upd_product->bind_param("iii", $qty, $qty, $it['product_id']);
        $upd_product->execute();
    }

    // finalize cart
    $stmt = $conn->prepare("UPDATE carts SET status='completed', total=?, total_earning=?, updated_at=NOW() WHERE cart_id=?");
    $stmt->bind_param("ddi", $total, $total_earning, $cart_id);
    $stmt->execute();

    // clear cart items
    $del = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
    $del->bind_param("i", $cart_id);
    $del->execute();

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Checkout completed successfully.', 'sale_id' => $sale_id, 'change' => number_format($change, 2)]);
    exit;
} catch (Exception $ex) {
    if ($conn->errno) {
        $conn->rollback();
    }
    http_response_code(500);
    // Return the exception message to the client for dev; remove or log in production
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $ex->getMessage()]);
    exit;
}
