<?php
session_start();
include "../config.php";

$cashier_id = $_SESSION['id'];

// Get current pending cart
$cart = $conn->query("SELECT * FROM carts WHERE seller='$cashier_id' AND status='pending' LIMIT 1")->fetch_assoc();
$cart_id = $cart['cart_id'] ?? 0;

// Re-fetch products with product_picture
$products = $conn->query("
    SELECT 
        p.product_id,
        p.product_name,
        p.product_picture,
        p.total_quantity,
        p.reserved_qty,
        rv.percent AS retail_percent,
        COALESCE(ps.cost_price,0) AS cost_price,
        CASE WHEN (p.total_quantity - COALESCE(p.reserved_qty,0)) <= 0 THEN 'Out of stock' ELSE 'Sufficient' END AS stock_status
    FROM product p
    LEFT JOIN retail_variables rv ON p.retail_id = rv.retail_id
    LEFT JOIN (SELECT product_id, cost_price FROM product_stocks WHERE status='active' ORDER BY created_at DESC) ps ON p.product_id = ps.product_id
    ORDER BY p.product_name
");

// Build product grid HTML
$product_grid = '';
while ($p = $products->fetch_assoc()) {
    $available = $p['total_quantity'] - ($p['reserved_qty'] ?? 0);
    $price = $p['cost_price'] * (1 + ($p['retail_percent'] / 100));

    $picture = htmlspecialchars(basename($p['product_picture']));
    $product_grid .= '<div class="product-card" data-product-id="' . $p['product_id'] . '" data-available="' . $available . '">';
    if ($picture && file_exists("../uploads/$picture")) {
        $product_grid .= '<img src="../uploads/' . $picture . '" alt="' . htmlspecialchars($p['product_name']) . '">';
    } else {
        $product_grid .= '<div class="no-image">No Image</div>';
    }
    $product_grid .= '<h4>' . htmlspecialchars($p['product_name']) . '</h4>';
    $product_grid .= '<p>₱' . number_format($price, 2) . '</p>';
    $product_grid .= '<p>Status: ' . $p['stock_status'] . '</p>';
    $product_grid .= '<p>Available: ' . $available . '</p>';
    $product_grid .= '<form method="post" class="add-to-cart-form">';
    $product_grid .= '<input type="hidden" name="product_id" value="' . $p['product_id'] . '">';
    $product_grid .= '<input type="hidden" name="cart_id" value="' . $cart_id . '">';
    $product_grid .= '<button type="submit" ' . ($available <= 0 ? 'disabled' : '') . '>Add</button>';
    $product_grid .= '</form></div>';
}

// Re-fetch cart items with product_picture and retail percent
$cart_items_query = $conn->query("
    SELECT ci.cart_items_id, ci.qty, ci.price, ci.cost_price, p.product_name, p.product_picture, rv.percent AS retail_percent
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    LEFT JOIN retail_variables rv ON p.retail_id = rv.retail_id
    WHERE ci.cart_id = $cart_id
");

$cart_items_html = '';
$subtotal = 0;
$retail_percent_display = 0;
while ($ci = $cart_items_query->fetch_assoc()) {
    $line_total = $ci['price'] * $ci['qty'];
    $subtotal += $line_total;
    $earning_per_unit = $ci['price'] - $ci['cost_price'];
    $line_earning = $earning_per_unit * $ci['qty'];

    // capture retail percent from first item (assumes all products use same retail type)
    if (!$retail_percent_display) $retail_percent_display = $ci['retail_percent'];

    $ci_picture = htmlspecialchars(basename($ci['product_picture']));
    $cart_items_html .= '<div class="cart-item">';
    if ($ci_picture && file_exists("../uploads/$ci_picture")) {
        $cart_items_html .= '<img src="../uploads/' . $ci_picture . '" alt="' . htmlspecialchars($ci['product_name']) . '">';
    } else {
        $cart_items_html .= '<div class="no-image">No Image</div>';
    }
    $cart_items_html .= '<div class="cart-item-info">' . htmlspecialchars($ci['product_name']) . '<br>₱' . number_format($ci['price'], 2) . ' | Profit/unit: ₱' . number_format($earning_per_unit, 2) . '</div>';
    $cart_items_html .= '<div class="cart-item-qty">';
    $cart_items_html .= '<form method="post" class="update-cart-form" style="display:inline;">';
    $cart_items_html .= '<input type="hidden" name="cart_items_id" value="' . $ci['cart_items_id'] . '">';
    $cart_items_html .= '<button name="action" value="decrease">-</button></form>';
    $cart_items_html .= '<span>' . $ci['qty'] . '</span>';
    $cart_items_html .= '<form method="post" class="update-cart-form" style="display:inline;">';
    $cart_items_html .= '<input type="hidden" name="cart_items_id" value="' . $ci['cart_items_id'] . '">';
    $cart_items_html .= '<button name="action" value="increase">+</button></form>';
    $cart_items_html .= '</div>';
    $cart_items_html .= '<div>Line Total: ₱' . number_format($line_total, 2) . '</div>';
    $cart_items_html .= '<div>Profit: ₱' . number_format($line_earning, 2) . '</div></div>';
}

// Totals with retail percent
$totals_html = '<div class="totals">';
$totals_html .= '<div><span>Subtotal</span><span>₱' . number_format($subtotal, 2) . '</span></div>';
$totals_html .= '<div><span>Retail Percent</span><span>' . $retail_percent_display . '%</span></div>';
$totals_html .= '<div><strong>Grand Total</strong><strong>₱' . number_format($subtotal, 2) . '</strong></div>';
$totals_html .= '</div>';

echo json_encode([
    'product_grid' => $product_grid,
    'cart_items' => $cart_items_html,
    'totals' => $totals_html
]);
