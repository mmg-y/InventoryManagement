<?php
include '../config.php';

// ==========================
// DASHBOARD STATISTICS
// ==========================

//Total Products (count all products)
$total_products = $conn->query("SELECT COUNT(*) AS total FROM product")->fetch_assoc()['total'];

//Inventory Value (sum of product price * quantity)
$inventory_value = $conn->query("SELECT SUM(price * quantity) AS total_value FROM product")->fetch_assoc()['total_value'];
$inventory_value = $inventory_value ?? 0; // Avoid null

//Pending Purchases (from supplier_purchases)
$pending_purchases = $conn->query("SELECT COUNT(*) AS pending FROM stock WHERE status='pending'")->fetch_assoc()['pending'];

//Completed Purchases (from supplier_purchases)
$completed_purchases = $conn->query("SELECT COUNT(*) AS completed FROM stock WHERE status='completed'")->fetch_assoc()['completed'];

// ==========================
// STOCK LEVEL CHART
// ==========================
$stock_labels = [];
$stock_data = [];

$stock_query = $conn->query("SELECT product_name, quantity FROM product ORDER BY product_name ASC");
while ($row = $stock_query->fetch_assoc()) {
    $stock_labels[] = $row['product_name'];
    $stock_data[] = (int)$row['quantity'];
}

// ==========================
// PURCHASE OVERVIEW CHART
// ==========================
$months = [];
$pending_data = [];
$completed_data = [];

for ($m = 1; $m <= 12; $m++) {
    $monthName = date('M', mktime(0, 0, 0, $m, 1));
    $months[] = $monthName;
    $pending_data[$monthName] = 0;
    $completed_data[$monthName] = 0;
}

// Fetch purchase data per month and status
$purchase_query = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b') AS month, status, COUNT(*) AS total
    FROM stock
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY MONTH(created_at), status
    ORDER BY MONTH(created_at)
");

while ($row = $purchase_query->fetch_assoc()) {
    $month = $row['month'];
    $status = strtolower($row['status']);

    if ($status == 'pending') {
        $pending_data[$month] = (int)$row['total'];
    } elseif ($status == 'completed') {
        $completed_data[$month] = (int)$row['total'];
    }
}
?>

<h1 class="page-title">Bodegero Dashboard</h1>

<section class="content">
    <div class="cards">
        <div class="card">
            <h3>Total Products</h3>
            <p><?= number_format($total_products) ?></p>
        </div>

        <div class="card">
            <h3>Inventory Value</h3>
            <p>₱<?= number_format($inventory_value, 2) ?></p>
        </div>

        <div class="card">
            <h3>Pending Purchases</h3>
            <p><?= number_format($pending_purchases) ?></p>
        </div>

        <div class="card">
            <h3>Completed Purchases</h3>
            <p><?= number_format($completed_purchases) ?></p>
        </div>
    </div>

    <div class="charts">
        <div class="chart">
            <h3>Stock Levels</h3>
            <canvas id="stockChart"></canvas>
        </div>
        <div class="chart">
            <h3>Purchases Overview</h3>
            <canvas id="purchaseChart"></canvas>
        </div>
    </div>

    <div class="tables">
        <div class="table">
            <h3>Recent Activity</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // ==========================
                    // DYNAMIC RECENT ACTIVITY
                    // ==========================
                   $activity_query = $conn->query("
                    SELECT created_at, CONCAT('Added new product: ', product_name) AS activity, 'Success' AS status
                    FROM product
                    UNION ALL
                    SELECT created_at, CONCAT('Purchase order #', po_num) AS activity, status
                    FROM stock
                    ORDER BY created_at DESC
                    LIMIT 10
                    ");

                    if ($activity_query->num_rows > 0) {
                        while ($row = $activity_query->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . date('Y-m-d', strtotime($row['created_at'])) . '</td>';
                            echo '<td>' . htmlspecialchars($row['activity']) . '</td>';
                            echo '<td>' . htmlspecialchars(ucfirst($row['status'])) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">No recent activities found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const stockLabels = <?= json_encode($stock_labels) ?>;
    const stockData = <?= json_encode($stock_data) ?>;
    const months = <?= json_encode($months) ?>;
    const pendingData = <?= json_encode(array_values($pending_data)) ?>;
    const completedData = <?= json_encode(array_values($completed_data)) ?>;

    // ======================
    // STOCK LEVEL CHART
    // ======================
    new Chart(document.getElementById('stockChart'), {
        type: 'bar',
        data: {
            labels: stockLabels,
            datasets: [{
                label: 'Stock Quantity',
                data: stockData,
                backgroundColor: '#102C57'
            }]
        },
        options: {
            scales: { y: { beginAtZero: true } }
        }
    });

    // ======================
    // PURCHASE OVERVIEW CHART
    // ======================
    new Chart(document.getElementById('purchaseChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Pending Purchases',
                    data: pendingData,
                    borderColor: '#e6b800',
                    backgroundColor: 'rgba(230, 184, 0, 0.2)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Completed Purchases',
                    data: completedData,
                    borderColor: '#102C57',
                    backgroundColor: 'rgba(16, 44, 87, 0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
