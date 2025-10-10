<?php
// session_start();
include "../config.php";

if (!isset($_SESSION['id']) || $_SESSION['type'] !== "staff") {
    header("Location: ../index.php");
    exit;
}

$staff_id = $_SESSION['id'];

// Get selected category from query string
$selected_category = $_GET['category'] ?? 'all';
$search = trim($_GET['q'] ?? '');

// Build WHERE conditions
$where = [];
if ($selected_category !== 'all') {
    $where[] = "p.category = " . intval($selected_category);
}
if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where[] = "p.product_name LIKE '%$safe_search%'";
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch Products
$products = $conn->query("
    SELECT p.*, c.category_name 
    FROM product p
    JOIN category c ON p.category = c.category_id
    $where_sql
    ORDER BY p.product_name
");

// Get Categories
$categories = $conn->query("SELECT * FROM category ORDER BY category_name");

// Check or Create Active Cart
$cart = $conn->query("SELECT * FROM carts WHERE seller = $staff_id AND status = 'pending' LIMIT 1")->fetch_assoc();
if (!$cart) {
    $conn->query("INSERT INTO carts (seller, status, total, created_at) VALUES ($staff_id, 'pending', 0, NOW())");
    $cart_id = $conn->insert_id;
} else {
    $cart_id = $cart['cart_id'];
}

// Get Cart Items
$cart_items = $conn->query("
    SELECT ci.*, p.product_name, p.product_picture, p.price AS product_price
    FROM cart_items ci 
    JOIN product p ON ci.product_id = p.product_id
    WHERE ci.cart_id = $cart_id
");

// 5. Billing History
$where_billing_sql = "WHERE c.seller = $staff_id AND c.status = 'completed'";

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

// --- Sorting ---
$valid_sort_columns = ["c.cart_id", "total_items", "c.total", "c.created_at"];
$sort_col = $_GET['sort'] ?? "c.created_at";
$sort_dir = $_GET['dir'] ?? "DESC";
if (!in_array($sort_col, $valid_sort_columns)) $sort_col = "c.created_at";
$sort_dir = strtoupper($sort_dir) === "ASC" ? "ASC" : "DESC";

// --- Records per page ---
$per_page = isset($_GET['records']) ? max(5, intval($_GET['records'])) : 10; // default 10, min 5
$page_num = max(1, intval($_GET['bpage'] ?? 1));
$offset = ($page_num - 1) * $per_page;

// Count total rows
$count_sql = "SELECT COUNT(DISTINCT c.cart_id) as total
              FROM carts c
              JOIN cart_items ci ON ci.cart_id = c.cart_id
              $where_billing_sql";
$total_billing_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_billing_pages = ceil($total_billing_rows / $per_page);

// Fetch billing rows
$billing = $conn->query("
    SELECT c.cart_id, c.total, c.created_at, COUNT(ci.cart_items_id) as total_items
    FROM carts c
    JOIN cart_items ci ON ci.cart_id = c.cart_id
    $where_billing_sql
    GROUP BY c.cart_id
    ORDER BY $sort_col $sort_dir
    LIMIT $per_page OFFSET $offset
");

$columns = [
    'c.cart_id'    => 'Cart #',
    'total_items'  => 'Items',
    'c.total'      => 'Total',
    'c.created_at' => 'Date'
];

// Current sorting from GET
$sort = $_GET['sort'] ?? 'c.created_at';
$order = $_GET['dir'] ?? 'DESC';
?>


<link rel="stylesheet" href="../css/dashboard(staff).css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="dashboard">

    <!-- LEFT SIDE (scrolls) -->
    <div class="dashboard-left">
        <!-- Products -->
        <div class="products">
            <h2>Products</h2>
            <!-- Category Dropdown -->
            <div class="category-filter">
                <label for="categorySelect">Category:</label>
                <select id="categorySelect">
                    <option value="all" <?= $selected_category === 'all' ? 'selected' : '' ?>>All</option>
                    <?php
                    $categories->data_seek(0);
                    while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['category_id'] ?>"
                            <?= $selected_category == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Product Grid -->
            <div class="product-grid">
                <?php while ($row = $products->fetch_assoc()):
                    $picture = basename($row['product_picture']); ?>
                    <div class="product-card" data-category="<?= $row['category'] ?>">
                        <img src="../uploads/<?= htmlspecialchars($picture) ?>" alt="">
                        <h4><?= htmlspecialchars($row['product_name']) ?></h4>
                        <p>₱<?= number_format($row['price'], 2) ?></p>
                        <form method="post" action="add_to_cart.php">
                            <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                            <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
                            <button type="submit">Add</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Billing -->
        <div class="billing-history">
            <h2>Billing History</h2>
            <form method="get" class="billing-filter">
                <label for="from">From:</label>
                <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">

                <label for="to">To:</label>
                <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">

                <label for="cart_search">Cart #:</label>
                <input type="text" name="cart_search" value="<?= htmlspecialchars($_GET['cart_search'] ?? '') ?>">


                <div class="filter-group">
                    <label for="records">Show:</label>
                    <select name="records" id="records" onchange="this.form.submit()">
                        <?php
                        $records_options = [10, 20, 30, 40, 50];
                        $selected_records = $_GET['records'] ?? 10;
                        foreach ($records_options as $opt) {
                            $selected = ($opt == $selected_records) ? "selected" : "";
                            echo "<option value='$opt' $selected>$opt</option>";
                        }
                        ?>
                    </select>
                    <span>records</span>
                </div>


                <button type="submit">Filter</button>
            </form>

            <!-- Results -->
            <div id="billing-results">
                <table class="billing-table">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col => $label):
                                $indicator = ($sort === $col) ? ($order === 'ASC' ? '▲' : '▼') : '';
                                $newOrder = ($sort === $col && $order === 'ASC') ? 'DESC' : 'ASC';
                            ?>
                                <th>
                                    <a href="#"
                                        class="sort"
                                        data-col="<?= $col ?>"
                                        data-dir="<?= $newOrder ?>">
                                        <?= $label ?> <?= $indicator ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = $billing->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $b['cart_id'] ?></td>
                                <td><?= $b['total_items'] ?></td>
                                <td>₱<?= number_format($b['total'], 2) ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($b['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page_num > 1): ?>
                        <a href="#" class="page-link" data-page="<?= $page_num - 1 ?>">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_billing_pages; $i++): ?>
                        <a href="#" class="page-link <?= $i == $page_num ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page_num < $total_billing_pages): ?>
                        <a href="#" class="page-link" data-page="<?= $page_num + 1 ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="cart">
        <h2>Cart</h2>
        <div class="cart-items">
            <?php
            $subtotal = 0;
            while ($ci = $cart_items->fetch_assoc()):
                $line_total = $ci['qty'] * $ci['product_price'];
                $subtotal += $line_total;
                $ci_picture = basename($ci['product_picture']);
            ?>
                <div class="cart-item">
                    <img src="../uploads/<?= htmlspecialchars($ci_picture) ?>" alt="">
                    <div class="cart-item-info">
                        <?= htmlspecialchars($ci['product_name']) ?><br>
                        ₱<?= number_format($ci['product_price'], 2) ?>
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
        <?php
        $vat = $subtotal * 0.12;
        $grand_total = $subtotal + $vat;
        ?>
        <div class="totals">
            <div><span>Subtotal</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
            <div><span>VAT (12%)</span><span>₱<?= number_format($vat, 2) ?></span></div>
            <div><strong>Grand Total</strong><strong>₱<?= number_format($grand_total, 2) ?></strong></div>
        </div>
        <form method="post" action="checkout.php">
            <input type="hidden" name="cart_id" value="<?= $cart_id ?>">
            <input type="hidden" name="total" value="<?= $grand_total ?>">
            <button class="checkout-btn">Complete Purchase</button>
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
            // keep search term if present
            const searchBox = document.querySelector('.search input[name="q"]');
            if (searchBox && searchBox.value) {
                url.searchParams.set("q", searchBox.value);
            }
            window.location.href = url.toString();
        });
    });
</script>

<script>
    function loadBilling(params = {}) {
        $.get(window.location.pathname, params, function(data) {
            $("#billing-results").html(
                $(data).find("#billing-results").html()
            );
        });
    }

    // Initial load
    loadBilling();

    // Handle filter form
    $("#billing-filter").on("submit", function(e) {
        e.preventDefault();
        loadBilling($(this).serialize());
    });

    // Handle pagination (delegated)
    $(document).on("click", ".page-link", function(e) {
        e.preventDefault();
        let params = $("#billing-filter").serialize() + "&bpage=" + $(this).data("page");
        loadBilling(params);
    });

    // Handle sorting (now uses PHP-provided data-dir)
    $(document).on("click", ".sort", function(e) {
        e.preventDefault();
        let col = $(this).data("col");
        let dir = $(this).data("dir"); // already toggled by PHP

        let params = $("#billing-filter").serialize() + "&sort=" + col + "&dir=" + dir;
        loadBilling(params);
    });
</script>