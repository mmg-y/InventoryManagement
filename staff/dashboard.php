<?php
// session_start();
include "../config.php";

if (!isset($_SESSION['id']) || $_SESSION['type'] !== "cashier") {
    header("Location: ../index.php");
    exit;
}

$cashier_id = $_SESSION['id'];

$selected_category = $_GET['category'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = [];
if ($selected_category !== 'all') {
    $where[] = "p.category = " . intval($selected_category);
}
if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where[] = "p.product_name LIKE '%$safe_search%'";
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$products = $conn->query("
    SELECT 
        p.product_id,
        p.product_name,
        p.product_picture,
        p.total_quantity,
        p.reserved_qty,
        p.category,
        c.category_name,
        rv.percent AS retail_percent,
        COALESCE(ps.remaining_qty, 0) AS remaining_qty,
        COALESCE(ps.cost_price, 0) AS cost_price,
        ps.product_stock_id AS batch_id,
        ps.status AS batch_status
    FROM product p
    JOIN category c ON p.category = c.category_id
    LEFT JOIN retail_variables rv ON p.retail_id = rv.retail_id
    LEFT JOIN (
        SELECT * FROM product_stocks WHERE status = 'active' ORDER BY created_at DESC
    ) ps ON p.product_id = ps.product_id
    $where_sql
    GROUP BY p.product_id
    ORDER BY p.product_name
");

$categories = $conn->query("SELECT * FROM category ORDER BY category_name");

// Get or create cart
$cart = $conn->query("SELECT * FROM carts WHERE seller = '$cashier_id' AND status = 'pending' LIMIT 1")->fetch_assoc();
if (!$cart) {
    $conn->query("
        INSERT INTO carts (seller, status, total, total_earning, created_at, updated_at) 
        VALUES ('$cashier_id', 'pending', 0, 0, NOW(), NOW())
    ");
    $cart_id = $conn->insert_id;
} else {
    $cart_id = $cart['cart_id'];
}

// Cart items query including product_picture
$cart_items = $conn->query("
    SELECT 
        ci.cart_items_id,
        ci.qty,
        ci.price,
        p.product_name,
        p.product_picture
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    WHERE ci.cart_id = $cart_id
");

// Billing queries remain the same
$where_billing_sql = "WHERE c.seller = '$cashier_id' AND c.status = 'completed'";
if (!empty($_GET['from'])) {
    $from = $conn->real_escape_string($_GET['from']);
    $where_billing_sql .= " AND DATE(c.created_at) >= '$from'";
}
if (!empty($_GET['to'])) {
    $to = $conn->real_escape_string($_GET['to']);
    $where_billing_sql .= " AND DATE(c.created_at) <= '$to'";
}
if (!empty($_GET['cart_search'])) {
    $cart_search = intval($_GET['cart_search']);
    $where_billing_sql .= " AND c.cart_id = $cart_search";
}

$valid_sort_columns = ["c.cart_id", "total_items", "c.total", "c.created_at"];
$sort_col = $_GET['sort'] ?? "c.created_at";
$sort_dir = strtoupper($_GET['dir'] ?? "DESC");
if (!in_array($sort_col, $valid_sort_columns)) $sort_col = "c.created_at";
$sort_dir = $sort_dir === "ASC" ? "ASC" : "DESC";

$per_page = isset($_GET['records']) ? max(5, intval($_GET['records'])) : 5;
$page_num = max(1, intval($_GET['bpage'] ?? 1));
$offset = ($page_num - 1) * $per_page;

$count_sql = "
    SELECT COUNT(DISTINCT c.cart_id) AS total
    FROM carts c
    JOIN cart_items ci ON ci.cart_id = c.cart_id
    $where_billing_sql
";
$total_billing_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_billing_pages = ceil($total_billing_rows / $per_page);

$billing = $conn->query("
    SELECT 
        s.sales_id,
        COUNT(*) AS total_items,
        s.total_amount AS total,
        s.sale_date AS created_at
    FROM sales s
    JOIN sales_items si ON si.sale_id = s.sales_id
    WHERE s.cashier_id = '$cashier_id'
    GROUP BY s.sales_id
    ORDER BY s.sale_date DESC
    LIMIT $per_page OFFSET $offset
");

$retail_percent = $conn->query("
    SELECT rv.percent
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN retail_variables rv ON p.retail_id = rv.retail_id
    WHERE ci.cart_id = $cart_id
    LIMIT 1
")->fetch_assoc()['percent'] ?? 0;


$columns = [
    'c.cart_id'    => 'Cart #',
    'total_items'  => 'Items',
    'c.total'      => 'Total',
    'c.created_at' => 'Date'
];
?>


<link rel="stylesheet" href="../css/dashboard(staff).css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<?php if (isset($_GET['receipt_id'])): ?>
    <script>
        window.addEventListener("DOMContentLoaded", () => {
            Toastify({
                text: "Purchase complete!",
                duration: 5000,
                gravity: "top",
                position: "right",
                close: true,
                stopOnFocus: true,
                backgroundColor: "#22c55e",
                onClick: function() {
                    window.open("../fpdf/receipt/receipt.php?sale_id=<?= intval($_GET['receipt_id']) ?>", "_blank");
                }
            }).showToast();
        });
    </script>
<?php endif; ?>



<div class="dashboard">
    <div class="dashboard-left">

        <!-- PRODUCTS -->
        <div class="products">
            <h2>Products</h2>
            <div class="category-filter">
                <label for="categorySelect">Category:</label>
                <select id="categorySelect">
                    <option value="all" <?= $selected_category === 'all' ? 'selected' : '' ?>>All</option>
                    <?php
                    $categories->data_seek(0);
                    while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div id="product-grid" class="product-grid">
                <?php while ($p = $products->fetch_assoc()):
                    $price = $p['cost_price'] * (1 + ($p['retail_percent'] / 100));
                    $available_stock = $p['remaining_qty'] - $p['reserved_qty'];
                    $picture = $p['product_picture'] ? htmlspecialchars(basename($p['product_picture'])) : '';
                ?>
                    <div class="product-card" data-category="<?= $p['category'] ?>">
                        <?php if ($picture && file_exists("../uploads/$picture")): ?>
                            <img src="../images/<?= $picture ?>" alt="<?= htmlspecialchars($p['product_name']) ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($p['product_name']) ?></h4>
                        <p>₱<?= number_format($price, 2) ?></p>

                        <?php if ($available_stock > 0): ?>
                            <form method="post" action="add_to_cart.php">
                                <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
                                <button type="submit">Add</button>
                            </form>
                        <?php else: ?>
                            <button disabled>Out of Stock</button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- BILLING HISTORY -->
        <div class="billing-history">
            <h2>Billing History</h2>
            <form method="get" class="billing-filter" id="billing-filter">
                <label>From:</label>
                <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
                <label>To:</label>
                <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
                <label>Cart #:</label>
                <input type="text" name="cart_search" value="<?= htmlspecialchars($_GET['cart_search'] ?? '') ?>">

                <div class="filter-actions">
                    <div class="filter-group">
                        <label>Show:</label>
                        <select name="records" onchange="this.form.submit()">
                            <?php foreach ([5, 10, 20, 30, 40, 50] as $opt):
                                $sel = ($_GET['records'] ?? 5) == $opt ? "selected" : "";
                                echo "<option value='$opt' $sel>$opt</option>";
                            endforeach; ?>
                        </select>

                        <span>records</span>
                    </div>
                    <button type="submit">
                        <i class="fa-solid fa-filter"></i>
                    </button>

                </div>
            </form>


            <div id="billing-results">
                <table class="billing-table">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col => $label):
                                $indicator = ($sort_col === $col) ? ($sort_dir === 'ASC' ? '▲' : '▼') : '';
                                $new_dir = ($sort_col === $col && $sort_dir === 'ASC') ? 'DESC' : 'ASC';
                            ?>
                                <th>
                                    <a href="#" class="sort" data-col="<?= htmlspecialchars($col) ?>" data-dir="<?= htmlspecialchars($new_dir) ?>">
                                        <?= htmlspecialchars($label) ?> <?= $indicator ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = $billing->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($b['sales_id']) ?></td>
                                <td><?= htmlspecialchars($b['total_items']) ?></td>
                                <td>₱<?= number_format($b['total'], 2) ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($b['created_at'])) ?></td>
                                <td>
                                    <a href="../fpdf/receipt/receipt.php?sale_id=<?= $b['sales_id'] ?>" target="_blank"
                                        class="view-receipt">
                                        <i class="fa-solid fa-receipt"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>


        </div>
    </div>

    <!-- CART -->
    <div class="cart">
        <h2>Cart</h2>
        <div class="cart-items">
            <?php
            $subtotal = 0;
            while ($ci = $cart_items->fetch_assoc()):
                $item_price = $ci['price'];
                $line_total = $item_price * $ci['qty'];
                $subtotal += $line_total;
                $ci_picture = $ci['product_picture'] ? htmlspecialchars(basename($ci['product_picture'])) : '';
            ?>
                <div class="cart-item">
                    <?php if ($ci_picture && file_exists("../uploads/$ci_picture")): ?>
                        <img src="../uploads/<?= $ci_picture ?>" alt="<?= htmlspecialchars($ci['product_name']) ?>">
                    <?php else: ?>
                        <div class="no-image">No Image</div>
                    <?php endif; ?>
                    <div class="cart-item-info">
                        <?= htmlspecialchars($ci['product_name']) ?><br>
                        ₱<?= number_format($item_price, 2) ?>
                    </div>
                    <div class="cart-item-qty">
                        <form method="post" action="update_cart.php" style="display:inline;">
                            <input type="hidden" name="cart_items_id" value="<?= $ci['cart_items_id'] ?>">
                            <button name="action" value="decrease">-</button>
                        </form>
                        <span><?= $ci['qty'] ?></span>
                        <form method="post" action="update_cart.php" style="display:inline;">
                            <input type="hidden" name="cart_items_id" value="<?= $ci['cart_items_id'] ?>">
                            <button name="action" value="increase">+</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="totals">
            <div><span>Subtotal</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
            <div><span>Retail Percent</span><span><?= $retail_percent ?>%</span></div>
            <div><strong>Grand Total</strong><strong>₱<?= number_format($subtotal, 2) ?></strong></div>
        </div>


        <form id="checkout-form">
            <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
            <input type="hidden" name="total" id="total" value="<?= $subtotal ?>">

            <div class="cash-section">
                <div class="cash-group">
                    <label class="cash-label" for="cash">Cash Received:</label>
                    <div class="cash-input-wrapper">
                        <!-- NOTE: name="cash" is required so POST includes it -->
                        <input
                            type="number"
                            name="cash"
                            id="cash"
                            class="cash-input"
                            placeholder="Enter amount received"
                            min="0"
                            step="0.01"
                            required>
                    </div>
                </div>

                <div class="change-display">
                    <span>Change:</span>
                    <span id="change" class="change-amount">₱0.00</span>
                </div>
            </div>

            <button type="submit" class="checkout-btn">Complete Purchase</button>
        </form>


    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const select = document.getElementById("categorySelect");
        select.addEventListener("change", () => {
            const url = new URL(window.location.href);
            url.searchParams.set("category", select.value);
            window.location.href = url.toString();
        });
    });
</script>

<script>
    $(document).ready(function() {
        $('.add-to-cart-form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            $.post('add_to_cart.php', form.serialize(), function() {
                refreshCartAndProducts();
            });
        });

        $('.update-cart-form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            $.post('update_cart.php', form.serialize(), function() {
                refreshCartAndProducts();
            });
        });

        function refreshCartAndProducts() {
            $.get('refresh_cart.php', function(data) {
                $('#product-grid').html(data.product_grid);
                $('.cart-items').html(data.cart_items);
                $('.totals').html(data.totals);
            }, 'json');
        }

    });
</script>

<script>
    $(document).ready(function() {
        $("#checkout-form").on("submit", function(e) {
            e.preventDefault();

            $.ajax({
                url: "checkout.php",
                type: "POST",
                data: $(this).serialize(),
                dataType: "json",
                success: function(response) {
                    if (response.status === "success") {
                        Toastify({
                            text: "Purchase completed successfully!",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#22c55e",
                            close: true,
                            stopOnFocus: true
                        }).showToast();

                        // Open receipt
                        window.open("../fpdf/receipt/receipt.php?sale_id=" + response.sale_id, "_blank");

                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toastify({
                            text: "⚠️ " + response.message,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#facc15",
                            close: true,
                            stopOnFocus: true
                        }).showToast();
                    }
                },
                error: function() {
                    Toastify({
                        text: " Something went wrong. Please try again.",
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#ef4444",
                        close: true,
                        stopOnFocus: true
                    }).showToast();
                }
            });
        });
    });
</script>

<script>
    $(document).ready(function() {
        // live update change and keep formatted display
        const cashInput = document.getElementById("cash");
        const totalVal = parseFloat(document.getElementById("total").value) || 0;
        const changeSpan = document.getElementById("change");

        cashInput.addEventListener("input", () => {
            const cash = parseFloat(cashInput.value) || 0;
            const change = cash - totalVal;
            changeSpan.textContent = (change >= 0) ? `₱${change.toFixed(2)}` : "₱0.00";
        });

        $("#checkout-form").on("submit", function(e) {
            e.preventDefault();

            // serialize form (includes name="cash")
            const data = $(this).serialize();

            $.ajax({
                url: "checkout.php",
                type: "POST",
                data: data,
                dataType: "json", // expect JSON
                success: function(response) {
                    if (response.status === "success") {
                        Toastify({
                            text: "✅ Purchase completed successfully!",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#22c55e",
                            close: true,
                            stopOnFocus: true
                        }).showToast();

                        // Open receipt
                        window.open("../fpdf/receipt/receipt.php?sale_id=" + response.sale_id, "_blank");

                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toastify({
                            text: "⚠️ " + (response.message || "Checkout failed."),
                            duration: 4000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#facc15",
                            close: true
                        }).showToast();
                    }
                },
                error: function(xhr, status, err) {
                    // show toast and print full server response to console for debugging
                    let body = xhr.responseText || "";
                    Toastify({
                        text: "❌ Server error. Check console (F12) for details.",
                        duration: 6000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#ef4444",
                        close: true
                    }).showToast();

                    console.error("AJAX ERROR:", status, err);
                    console.log("Response body:", body);
                    // try to parse JSON error if server returned JSON
                    try {
                        const json = JSON.parse(body);
                        console.info("Server JSON response:", json);
                    } catch (e) {
                        // not JSON — it's probably a PHP error/stack trace
                    }
                }
            });
        });
    });
</script>