<?php
include '../config.php';

// Total Products
$total_products = $conn->query("SELECT COUNT(*) AS total FROM product")->fetch_assoc()['total'] ?? 0;

// Inventory Value (SUM of cost_price * remaining_qty)
$inventory_value = $conn->query("
    SELECT SUM(ps.cost_price * ps.remaining_qty) AS total_value
    FROM product_stocks ps
")->fetch_assoc()['total_value'] ?? 0;

// Pending Purchases (where status != 'pulled' or remaining_qty < quantity)
$pending_purchases = $conn->query("
    SELECT COUNT(*) AS pending
    FROM product_stocks
    WHERE status IS NULL OR status NOT IN ('pulled', 'out')
")->fetch_assoc()['pending'] ?? 0;

// Completed Purchases (where status = 'pulled')
$completed_purchases = $conn->query("
    SELECT COUNT(*) AS completed
    FROM product_stocks
    WHERE status = 'pulled'
")->fetch_assoc()['completed'] ?? 0;


// stock level
$stock_labels = [];
$stock_data = [];

$stock_query = $conn->query("
    SELECT p.product_name, p.total_quantity
    FROM product p
    ORDER BY p.product_name ASC
");
while ($row = $stock_query->fetch_assoc()) {
    $stock_labels[] = $row['product_name'];
    $stock_data[] = (int)$row['total_quantity'];
}


// purchase overview
$months = [];
$pending_data = [];
$completed_data = [];

for ($m = 1; $m <= 12; $m++) {
    $monthName = date('M', mktime(0, 0, 0, $m, 1));
    $months[] = $monthName;
    $pending_data[$monthName] = 0;
    $completed_data[$monthName] = 0;
}

// Count purchases (from product_stocks table)
$purchase_query = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b') AS month, status, COUNT(*) AS total
    FROM product_stocks
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY MONTH(created_at), status
    ORDER BY MONTH(created_at)
");

while ($row = $purchase_query->fetch_assoc()) {
    $month = $row['month'];
    $status = strtolower($row['status'] ?? '');
    if ($status === 'pulled') {
        $completed_data[$month] = (int)$row['total'];
    } else {
        $pending_data[$month] = (int)$row['total'];
    }
}
?>

<link rel="stylesheet" href="../css/budegero.css">
<link rel="icon" href="images/logo-teal.png" type="images/png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<h1 class="page-title">Bodegero Dashboard</h1>

<section class="content">
    <div class="cards">
        <div class="card">
            <h3><i class="fa-solid fa-boxes-stacked"></i> Total Products</h3>
            <p><?= number_format($total_products) ?></p>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-peso-sign"></i> Inventory Value</h3>
            <p>₱<?= number_format($inventory_value, 2) ?></p>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Pending Purchases</h3>
            <p><?= number_format($pending_purchases) ?></p>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-check-circle"></i> Completed Purchases</h3>
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
                    $activity_query = $conn->query("
                        SELECT created_at, CONCAT('Added new product: ', product_name) AS activity, 'Success' AS status
                        FROM product
                        UNION ALL
                        SELECT created_at, CONCAT('Stock batch: ', batch_number) AS activity, status
                        FROM product_stocks
                        ORDER BY created_at DESC
                        LIMIT 10
                    ");

                    if ($activity_query && $activity_query->num_rows > 0) {
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const stockLabels = <?= json_encode($stock_labels) ?>;
    const stockData = <?= json_encode($stock_data) ?>;
    const months = <?= json_encode($months) ?>;
    const pendingData = <?= json_encode(array_values($pending_data)) ?>;
    const completedData = <?= json_encode(array_values($completed_data)) ?>;

    new Chart(document.getElementById('stockChart'), {
        type: 'bar',
        data: {
            labels: stockLabels,
            datasets: [{
                label: 'Stock Quantity',
                data: stockData,
                backgroundColor: '#088395'
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('purchaseChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
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
                    borderColor: '#088395',
                    backgroundColor: 'rgba(16, 44, 87, 0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>