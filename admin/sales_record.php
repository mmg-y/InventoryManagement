<?php
include '../config.php';

// ===================== AJAX HANDLER =====================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $start = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
    $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

    $valid_columns = ['cart_id', 'seller', 'product_name', 'qty', 'price', 'total', 'status', 'created_at'];
    if (!in_array($sort, $valid_columns)) $sort = 'created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    $toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

    $where = "WHERE 1=1";
    if ($search) {
        $safeSearch = $conn->real_escape_string($search);
        $where .= " AND (c.seller LIKE '%$safeSearch%' OR p.product_name LIKE '%$safeSearch%')";
    }
    if ($statusFilter) {
        $safeStatus = $conn->real_escape_string($statusFilter);
        $where .= " AND c.status='$safeStatus'";
    }

    // Total records
    $count_sql = "SELECT COUNT(*) as count 
                  FROM carts c 
                  JOIN cart_items ci ON c.cart_id = ci.cart_id 
                  JOIN product p ON ci.product_id = p.product_id 
                  $where";
    $total = $conn->query($count_sql)->fetch_assoc()['count'];
    $pages = ceil($total / $limit);

    // Fetch paginated results
    $sql = "SELECT c.cart_id, c.seller, c.total, c.status, c.created_at,
                   ci.product_id, ci.qty, ci.price, p.product_name
            FROM carts c
            JOIN cart_items ci ON c.cart_id = ci.cart_id
            JOIN product p ON ci.product_id = p.product_id
            $where
            ORDER BY $sort $order
            LIMIT $start, $limit";
    $result = $conn->query($sql);

    $grandTotal = 0;
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
                $currentCart = 0;
                $subtotal = 0;
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        if ($currentCart != $row['cart_id']) {
                            if ($currentCart != 0) {
                                echo "<tr class='subtotal-row'><td colspan='5' class='text-right'><strong>Subtotal:</strong></td><td><strong>$subtotal</strong></td><td colspan='2'></td></tr>";
                            }
                            $currentCart = $row['cart_id'];
                            $subtotal = 0;
                        }
                        $lineTotal = $row['qty'] * $row['price'];
                        $subtotal += $lineTotal;
                        $grandTotal += $lineTotal;

                        $statusClass = '';
                        if ($row['status'] == 'completed') $statusClass = 'status-completed';
                        if ($row['status'] == 'pending') $statusClass = 'status-pending';
                        if ($row['status'] == 'cancelled') $statusClass = 'status-cancelled';

                        echo "<tr>
                            <td>{$row['cart_id']}</td>
                            <td>{$row['seller']}</td>
                            <td>{$row['product_name']}</td>
                            <td>{$row['qty']}</td>
                            <td>{$row['price']}</td>
                            <td>$lineTotal</td>
                            <td class='$statusClass'>{$row['status']}</td>
                            <td>{$row['created_at']}</td>
                          </tr>";
                    }
                    echo "<tr class='subtotal-row'><td colspan='5' class='text-right'><strong>Subtotal:</strong></td><td><strong>$subtotal</strong></td><td colspan='2'></td></tr>";
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

<div class="main">
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
        <!-- AJAX-loaded table will appear here -->
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