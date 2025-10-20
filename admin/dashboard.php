<?php
include '../config.php';

// User, Product, Purchase, Sales totals 
$userCount = $conn->query("SELECT COUNT(*) AS total FROM user")->fetch_assoc()['total'];
$productCount = $conn->query("SELECT COUNT(*) AS total FROM product")->fetch_assoc()['total'];
$purchaseCount = $conn->query("SELECT COUNT(*) AS total FROM product_stocks")->fetch_assoc()['total'];
$salesRevenue = $conn->query("SELECT IFNULL(SUM(total),0) AS revenue FROM carts WHERE status='completed'")->fetch_assoc()['revenue'];

// Sales prediction & last 7 days revenue
$last7daysSales = $conn->query("
    SELECT SUM(total) AS total
    FROM carts
    WHERE status='completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['total'] ?? 0;

// Previous 7 days revenue (days 8-14 ago)
$prev7daysSales = $conn->query("
    SELECT SUM(total) AS total
    FROM carts
    WHERE status='completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['total'] ?? 0;

// Calculate % growth
if ($prev7daysSales > 0) {
    $salesPrediction = (($last7daysSales - $prev7daysSales) / $prev7daysSales) * 100;
} else {
    $salesPrediction = 0; // fallback
}
$salesPrediction = ($salesPrediction >= 0 ? '+' : '') . round($salesPrediction, 2) . '%';

$recentPurchases = $conn->query("
    SELECT ps.created_at AS date, sp.name AS supplier, ps.quantity AS items
    FROM product_stocks ps
    LEFT JOIN supplier sp ON ps.supplier_id = sp.supplier_id
    ORDER BY ps.created_at DESC
    LIMIT 5
");

// Recent Sales 
$recentSales = $conn->query("
    SELECT c.created_at AS date, c.seller AS customer, c.total AS total_amount
    FROM carts c
    WHERE c.status='completed'
    ORDER BY c.created_at DESC
    LIMIT 5
");

// Sales Trend 
$salesTrend = $conn->query("
    SELECT DATE(created_at) AS sale_date, SUM(total) AS daily_total
    FROM carts
    WHERE status='completed'
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
");

$trendData = ['labels' => [], 'values' => []];
if ($salesTrend && $salesTrend->num_rows > 0) {
    while ($row = $salesTrend->fetch_assoc()) {
        $trendData['labels'][] = $row['sale_date'];
        $trendData['values'][] = $row['daily_total'];
    }
}
if (empty($trendData['labels'])) {
    $trendData['labels'] = ['No Data'];
    $trendData['values'] = [0];
}

// Low Stock Products 
$lowStockProducts = $conn->query("
    SELECT product_name, total_quantity 
    FROM product 
    WHERE total_quantity <= threshold
    LIMIT 5
");

$inventoryLabels = [];
$inventoryValues = [];
if ($lowStockProducts && $lowStockProducts->num_rows > 0) {
    while ($row = $lowStockProducts->fetch_assoc()) {
        $inventoryLabels[] = $row['product_name'];
        $inventoryValues[] = $row['total_quantity'];
    }
}
if (empty($inventoryLabels)) {
    $inventoryLabels = ['No Products'];
    $inventoryValues = [0];
}

// Supplier Stats 
$supplierStats = $conn->query("
    SELECT sp.name, COUNT(ps.product_stock_id) AS total_orders
    FROM product_stocks ps
    JOIN supplier sp ON ps.supplier_id = sp.supplier_id
    GROUP BY sp.supplier_id
");

$supplierLabels = [];
$supplierValues = [];
if ($supplierStats && $supplierStats->num_rows > 0) {
    while ($row = $supplierStats->fetch_assoc()) {
        $supplierLabels[] = $row['name'];
        $supplierValues[] = $row['total_orders'];
    }
} else {
    $supplierLabels = ['No Suppliers'];
    $supplierValues = [0];
}

// Next 7-day forecast per product (moving average) 
$salesDataQuery = "
    SELECT p.product_name, DATE(c.created_at) as sale_date, SUM(ci.qty) as total_qty
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    WHERE c.status = 'completed'
    GROUP BY p.product_id, DATE(c.created_at)
    ORDER BY p.product_name, sale_date ASC
";
$result = $conn->query($salesDataQuery);

$salesData = [];
while ($result && $row = $result->fetch_assoc()) {
    $salesData[$row['product_name']]['labels'][] = $row['sale_date'];
    $salesData[$row['product_name']]['values'][] = (int)$row['total_qty'];
}

// Moving average forecast for next 7 days
$window = 7;
$predictions = [];
foreach ($salesData as $product => $data) {
    $values = $data['values'];
    $lastDate = end($data['labels']) ?: date('Y-m-d');
    $date = new DateTime($lastDate);

    for ($i = 1; $i <= 7; $i++) {
        $slice = array_slice($values, -$window);
        $avg = !empty($slice) ? array_sum($slice) / count($slice) : 0;

        $date->modify('+1 day');
        $predictions[$product]['labels'][] = $date->format('Y-m-d');
        $predictions[$product]['values'][] = round($avg, 2);

        $values[] = $avg; // append for rolling window
    }
}

// Fallback if no sales
if (empty($salesData)) {
    $salesData['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
    $predictions['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
}
?>



<link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- <h1 class="page-title">Admin Dashboard</h1> -->

<div class="cards">
    <div class="card">
        <h3><i class="fas fa-users"></i> Total Users</h3>
        <p><?= number_format($userCount) ?></p>
    </div>
    <div class="card">
        <h3><i class="fas fa-boxes"></i> Total Products</h3>
        <p><?= number_format($productCount) ?></p>
    </div>
    <div class="card">
        <h3><i class="fas fa-shopping-cart"></i> Purchases</h3>
        <p><?= number_format($purchaseCount) ?></p>
    </div>
    <div class="card">
        <h3><i class="fas fa-coins"></i> Sales Revenue</h3>
        <p>₱<?= number_format($salesRevenue, 2) ?></p>
    </div>
    <div class="card">
        <h3><i class="fas fa-chart-line"></i> Sales Prediction</h3>
        <p>Revenue growth (last 7 days vs previous 7 days): <strong><?= $salesPrediction ?></strong></p>
    </div>
</div>


<div class="charts">
    <!-- Top row: Sales Trend Line -->
    <div class="chart-row">
        <div class="chart-card full-width">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    <!-- Bottom row: Inventory Bar + Supplier Pie -->
    <div class="chart-row">
        <div class="chart-card half-width">
            <canvas id="inventoryChart"></canvas>
        </div>
        <div class="chart-card half-width">
            <canvas id="supplierChart"></canvas>
        </div>
    </div>

</div>

<div class="tables">
    <div class="table">
        <h3>Recent Purchases</h3>
        <table>
            <tr>
                <th>Date</th>
                <th>Supplier</th>
                <th>Items</th>
            </tr>
            <?php while ($row = $recentPurchases->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['supplier'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['items']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="table">
        <h3>Recent Sales</h3>
        <table>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Total</th>
            </tr>
            <?php while ($row = $recentSales->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['customer']) ?></td>
                    <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script>
    new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendData['labels']) ?>,
            datasets: [{
                label: 'Daily Sales',
                data: <?= json_encode($trendData['values']) ?>,
                borderColor: 'rgba(16,44,87,0.9)',
                backgroundColor: 'rgba(16,44,87,0.2)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('inventoryChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($inventoryLabels) ?>,
            datasets: [{
                label: 'Stock Quantity',
                data: <?= json_encode($inventoryValues) ?>,
                backgroundColor: 'rgba(255,99,132,0.6)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('supplierChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($supplierLabels) ?>,
            datasets: [{
                label: 'Supplier Orders',
                data: <?= json_encode($supplierValues) ?>,
                backgroundColor: ['#102C57', '#007BFF', '#28A745', '#FFC107', '#DC3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>