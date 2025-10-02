<?php
include '../config.php';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "";
if ($search) {
    $safeSearch = $conn->real_escape_string($search);
    $where = "AND (c.seller LIKE '%$safeSearch%' OR p.product_name LIKE '%$safeSearch%')";
}

$valid_columns = ['cart_id', 'seller', 'product_name', 'qty', 'price', 'total', 'status', 'created_at'];
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], $valid_columns)) ? $_GET['sort'] : 'created_at';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

$count_sql = "
SELECT COUNT(*) as count
FROM carts c
JOIN cart_items ci ON c.cart_id = ci.cart_id
JOIN product p ON ci.product_id = p.product_id
WHERE c.status = 'completed' $where
";
$total = $conn->query($count_sql)->fetch_assoc()['count'];
$pages = ceil($total / $limit);

$sql = "
SELECT c.cart_id, c.seller, c.total, c.status, c.created_at,
       ci.product_id, ci.qty, ci.price, p.product_name
FROM carts c
JOIN cart_items ci ON c.cart_id = ci.cart_id
JOIN product p ON ci.product_id = p.product_id
WHERE c.status = 'completed' $where
ORDER BY $sort $order
LIMIT $start, $limit
";
$result = $conn->query($sql);
?>

<link rel="stylesheet" href="../css/sales_record.css">

<div class="main">
    <h1>Sales Record</h1>

    <div class="actions-bar">
        <form class="search-form" method="GET">
            <input type="text" name="search" placeholder="Search by seller or product..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fa fa-search"></i></button>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <?php
                    $columns = [
                        'cart_id' => 'Sale ID',
                        'seller' => 'Seller',
                        'product_name' => 'Product',
                        'qty' => 'Quantity',
                        'price' => 'Price',
                        'total' => 'Line Total',
                        'status' => 'Status',
                        'created_at' => 'Sale Date'
                    ];
                    foreach ($columns as $col => $label) {
                        $indicator = ($sort === $col) ? ($order === 'ASC' ? '▲' : '▼') : '';
                        echo "<th><a href='?sort=$col&order=$toggle_order&search=" . urlencode($search) . "'>$label <span class='sort-indicator'>$indicator</span></a></th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentCart = 0;
                $subtotal = 0;
                $grandTotal = 0;

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        if ($currentCart != $row['cart_id']) {
                            if ($currentCart != 0) {
                                echo "<tr>
                                    <td colspan='5' style='text-align:right;'><strong>Subtotal:</strong></td>
                                    <td><strong>$subtotal</strong></td>
                                    <td colspan='2'></td>
                                  </tr>";
                            }
                            $currentCart = $row['cart_id'];
                            $subtotal = 0;
                        }

                        $lineTotal = $row['qty'] * $row['price'];
                        $subtotal += $lineTotal;
                        $grandTotal += $lineTotal;

                        echo "<tr>
                            <td>{$row['cart_id']}</td>
                            <td>{$row['seller']}</td>
                            <td>{$row['product_name']}</td>
                            <td>{$row['qty']}</td>
                            <td>{$row['price']}</td>
                            <td>$lineTotal</td>
                            <td>{$row['status']}</td>
                            <td>{$row['created_at']}</td>
                          </tr>";
                    }

                    echo "<tr>
                        <td colspan='5' style='text-align:right;'><strong>Subtotal:</strong></td>
                        <td><strong>$subtotal</strong></td>
                        <td colspan='2'></td>
                      </tr>";
                } else {
                    echo "<tr><td colspan='8'>No sales records found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Grand Total -->
    <div style="margin-top:15px; text-align:right; font-weight:bold;">
        Grand Total: <?= $grandTotal ?>
    </div>

    <!-- Pagination -->
    <div class="pagination" style="margin-top:15px;">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>"
                class="<?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>