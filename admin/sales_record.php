<?php
include '../config.php';

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $limit = 10;
    $page = max(1, intval($_GET['page'] ?? 1));
    $start = ($page - 1) * $limit;

    $search = trim($_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $sort = $_GET['sort'] ?? 'sale_date';
    $order = strtoupper($_GET['order'] ?? 'DESC');

    $valid_columns = ['sales_id', 'cashier_id', 'total_amount', 'sale_date', 'status'];
    if (!in_array($sort, $valid_columns)) $sort = 'sale_date';
    $order = $order === 'ASC' ? 'ASC' : 'DESC';
    $toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

    // Build WHERE clause
    $where = ["c.status != 'cancelled'"]; // exclude cancelled by default
    if ($statusFilter) $where[] = "c.status = '" . $conn->real_escape_string($statusFilter) . "'";
    if ($search) $where[] = "(u.first_name LIKE '%" . $conn->real_escape_string($search) . "%' 
                               OR u.last_name LIKE '%" . $conn->real_escape_string($search) . "%'
                               OR p.product_name LIKE '%" . $conn->real_escape_string($search) . "%')";
    $where_sql = "WHERE " . implode(" AND ", $where);

    // Count total rows for pagination
    $count_sql = "SELECT COUNT(DISTINCT s.sales_id) AS total
              FROM sales s
              LEFT JOIN user u ON s.cashier_id = u.id
              LEFT JOIN sales_items si ON si.sale_id = s.sales_id
              LEFT JOIN product p ON si.product_id = p.product_id
              LEFT JOIN carts c ON c.cart_id = s.sales_id
              $where_sql";
    $total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
    $pages = ceil($total_rows / $limit);

    // Fetch paginated sales
    $sql = "SELECT s.sales_id, s.cashier_id, s.sale_date, s.total_amount, s.cash_received, s.change_amount, s.remarks,
               u.first_name, u.last_name, c.status
        FROM sales s
        LEFT JOIN user u ON s.cashier_id = u.id
        LEFT JOIN sales_items si ON si.sale_id = s.sales_id
        LEFT JOIN product p ON si.product_id = p.product_id
        LEFT JOIN carts c ON c.cart_id = s.sales_id
        $where_sql
        GROUP BY s.sales_id
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
                        'sales_id' => 'Sale ID',
                        'cashier_id' => 'Cashier ID',
                        'total_amount' => 'Total',
                        'sale_date' => 'Sale Date',
                        'cash_received' => 'Cash Received',
                        'change_amount' => 'Change',
                        'status' => 'Status',       // added status column
                        'remarks' => 'Remarks'
                    ];
                    foreach ($columns as $col => $label) {
                        $indicator = ($sort === $col) ? ($order === 'ASC' ? '▲' : '▼') : '';
                        echo "<th><a href='#' class='sort-column' data-column='$col' data-order='$toggle_order'>$label $indicator</a></th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $grandTotal += $row['total_amount'];
                        echo "<tr>
                    <td>{$row['sales_id']}</td>
                    <td>{$row['cashier_id']} </td>
                    <td>₱" . number_format($row['total_amount'], 2) . "</td>
                    <td>" . date('M d, Y h:i A', strtotime($row['sale_date'])) . "</td>
                    <td>₱" . number_format($row['cash_received'], 2) . "</td>
                    <td>₱" . number_format($row['change_amount'], 2) . "</td>
                    <td class='status-" . strtolower($row['status']) . "'>" . ucfirst($row['status']) . "</td>
                    <td>" . htmlspecialchars($row['remarks']) . "</td>
                </tr>";
                    }
                } else {
                    echo "<tr><td colspan='8'>No sales records found.</td></tr>";
                }
                ?>
            </tbody>
        </table>


        <div class="grand-total">Grand Total: ₱<?= number_format($grandTotal, 2) ?></div>

        <div class="pagination">
            <?php
            $range = 2;
            $prev = max(1, $page - 1);
            $next = min($pages, $page + 1);

            if ($page > 1) {
                echo "<a href='#' class='page-link' data-page='1'>&laquo;&laquo;</a>";
                echo "<a href='#' class='page-link' data-page='$prev'>&laquo;</a>";
            }

            for ($i = max(1, $page - $range); $i <= min($pages, $page + $range); $i++) {
                $active = $i == $page ? 'active' : '';
                echo "<a href='#' class='page-link $active' data-page='$i'>$i</a>";
            }

            if ($page < $pages) {
                echo "<a href='#' class='page-link' data-page='$next'>&raquo;</a>";
                echo "<a href='#' class='page-link' data-page='$pages'>&raquo;&raquo;</a>";
            }
            ?>
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