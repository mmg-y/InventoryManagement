<?php
include '../config.php';

// handler of ajax
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $start = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
    $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

    $valid_columns = ['cart_id', 'seller', 'status', 'created_at'];
    if (!in_array($sort, $valid_columns)) $sort = 'created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    $toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

    $where = "WHERE 1=1";
    if ($search) {
        $safeSearch = $conn->real_escape_string($search);
        $where .= " AND c.seller LIKE '%$safeSearch%'";
    }
    if ($statusFilter) {
        $safeStatus = $conn->real_escape_string($statusFilter);
        $where .= " AND c.status='$safeStatus'";
    }

    // Get total carts for pagination
    $count_sql = "SELECT COUNT(*) as count FROM carts c $where";
    $total = $conn->query($count_sql)->fetch_assoc()['count'];
    $pages = ceil($total / $limit);

    // Fetch paginated carts
    $sql_carts = "SELECT c.cart_id, c.seller, c.status, c.created_at
                  FROM carts c
                  $where
                  ORDER BY $sort $order
                  LIMIT $start, $limit";
    $result_carts = $conn->query($sql_carts);

    $grandTotal = 0;
    $carts = [];
    $cart_ids = [];

    while ($cart = $result_carts->fetch_assoc()) {
        $carts[$cart['cart_id']] = $cart;
        $cart_ids[] = $cart['cart_id'];
    }

    // Fetch all items for these carts in one query
    $items_by_cart = [];
    if (!empty($cart_ids)) {
        $ids_str = implode(',', $cart_ids);
        $sql_items = "SELECT ci.cart_id, ci.qty, ci.price, p.product_name
                      FROM cart_items ci
                      JOIN product p ON ci.product_id = p.product_id
                      WHERE ci.cart_id IN ($ids_str)
                      ORDER BY ci.cart_id";
        $items_result = $conn->query($sql_items);

        while ($item = $items_result->fetch_assoc()) {
            $items_by_cart[$item['cart_id']][] = $item;
        }
    }
?>

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
                        echo "<th><a href='#' class='sort-column' data-column='$col' data-order='$toggle_order'><span class='sort-indicator'>$indicator</span>$label</a></th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($carts)) {
                    foreach ($carts as $cart_id => $cart) {
                        $subtotal = 0;
                        if (isset($items_by_cart[$cart_id])) {
                            foreach ($items_by_cart[$cart_id] as $item) {
                                $lineTotal = $item['qty'] * $item['price'];
                                $subtotal += $lineTotal;
                                $grandTotal += $lineTotal;

                                $statusClass = '';
                                if ($cart['status'] == 'completed') $statusClass = 'status-completed';
                                if ($cart['status'] == 'pending') $statusClass = 'status-pending';
                                if ($cart['status'] == 'cancelled') $statusClass = 'status-cancelled';

                                echo "<tr>
                                    <td>{$cart['cart_id']}</td>
                                    <td>{$cart['seller']}</td>
                                    <td>{$item['product_name']}</td>
                                    <td>{$item['qty']}</td>
                                    <td>{$item['price']}</td>
                                    <td>$lineTotal</td>
                                    <td class='$statusClass'>{$cart['status']}</td>
                                    <td>{$cart['created_at']}</td>
                                  </tr>";
                            }

                            // Print subtotal once per cart
                            // echo "<tr class='subtotal-row'>
                            //     <td colspan='5' class='text-right'><strong>Subtotal:</strong></td>
                            //     <td><strong>$subtotal</strong></td>
                            //     <td colspan='2'></td>
                            //   </tr>";
                        }
                    }
                } else {
                    echo "<tr><td colspan='8'>No sales records found.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <div class="grand-total">Grand Total: <?= $grandTotal ?></div>

        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="#" class="page-link" data-page="<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>

<?php
    exit;
}
?>


<link rel="stylesheet" href="../css/sales_record.css">

<div class="page-content sales-record">
    <h1>Sales Record</h1>
    <div class="actions-bar">
        <form method="GET" class="search-form">
            <input type="hidden" name="page" value="sales_record">
            <input type="text" name="search" id="searchInput" placeholder="Search by seller or product..." value="<?= htmlspecialchars($search ?? '') ?>">
            <button type="submit"><i class="fa fa-search"></i></button>
            <div class="filter-wrapper">
                <select name="status" id="statusFilter" class="category-filter" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="completed" <?= (isset($statusFilter) && $statusFilter == 'completed') ? 'selected' : '' ?>>Completed</option>
                    <option value="pending" <?= (isset($statusFilter) && $statusFilter == 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="cancelled" <?= (isset($statusFilter) && $statusFilter == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <i class="fa fa-filter filter-icon"></i>
            </div>
        </form>
    </div>

    <div id="salesTable">
    </div>
</div>

<script>
    let currentSort = 'created_at';
    let currentOrder = 'DESC';

    function loadSales(page = 1) {
        const search = document.getElementById('searchInput').value;
        const status = document.getElementById('statusFilter').value;
        const params = new URLSearchParams({
            ajax: 1,
            page,
            search,
            status,
            sort: currentSort,
            order: currentOrder
        });
        fetch('<?= basename(__FILE__) ?>?' + params.toString())
            .then(res => res.text())
            .then(html => document.getElementById('salesTable').innerHTML = html);
    }

    // Initial load
    loadSales();

    // Events
    document.getElementById('searchInput').addEventListener('input', () => loadSales(1));
    document.getElementById('statusFilter').addEventListener('change', () => loadSales(1));

    // Delegate clicks for pagination & sorting
    document.getElementById('salesTable').addEventListener('click', function(e) {
        if (e.target.classList.contains('page-link')) {
            e.preventDefault();
            const page = e.target.dataset.page;
            loadSales(page);
        } else if (e.target.closest('.sort-column')) {
            e.preventDefault();
            const col = e.target.closest('.sort-column').dataset.column;
            const order = e.target.closest('.sort-column').dataset.order;
            currentSort = col;
            currentOrder = order;
            loadSales(1);
        }
    });
</script>