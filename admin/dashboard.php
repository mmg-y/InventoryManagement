<?php
include '../config.php';

$userCount = $conn->query("SELECT COUNT(*) AS total FROM user")->fetch_assoc()['total'];
$productCount = $conn->query("SELECT COUNT(*) AS total FROM product")->fetch_assoc()['total'];
$purchaseCount = $conn->query("SELECT COUNT(*) AS total FROM product_stocks")->fetch_assoc()['total'];
$salesRevenue = $conn->query("SELECT IFNULL(SUM(total_amount),0) AS revenue FROM sales")->fetch_assoc()['revenue'];

// sales growth
$last7daysSales = $conn->query("
    SELECT SUM(total_amount) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['total'] ?? 0;

$prev7daysSales = $conn->query("
    SELECT SUM(total_amount) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      AND sale_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['total'] ?? 0;

if ($prev7daysSales > 0) {
    $growth = (($last7daysSales - $prev7daysSales) / $prev7daysSales) * 100;
    $salesPrediction = ($growth >= 0 ? '+' : '') . round($growth, 2) . "% (₱" . number_format($last7daysSales, 2) . " vs ₱" . number_format($prev7daysSales, 2) . ")";
} else {
    $salesPrediction = "₱" . number_format($last7daysSales, 2) . " this week — no data last week";
}

$comparisonLabels = ['Previous 7 Days', 'Last 7 Days'];
$comparisonValues = [
    (float)$prev7daysSales,
    (float)$last7daysSales
];

// $salesPrediction = ($salesPrediction >= 0 ? '+' : '') . round($salesPrediction, 2) . '%';

$recentPurchases = $conn->query("
    SELECT ps.created_at AS date, sp.name AS supplier, ps.quantity AS items
    FROM product_stocks ps
    LEFT JOIN supplier sp ON ps.supplier_id = sp.supplier_id
    ORDER BY ps.created_at DESC
    LIMIT 5
");

$recentSales = $conn->query("
    SELECT s.sale_date AS date, CONCAT(u.first_name, ' ', u.last_name) AS cashier, s.total_amount AS total_amount
    FROM sales s
    LEFT JOIN user u ON s.cashier_id = u.id
    ORDER BY s.sale_date DESC
    LIMIT 5
");

// sales trend
$salesTrend = $conn->query("
    SELECT DATE(sale_date) AS sale_date, SUM(total_amount) AS daily_total
    FROM sales
    GROUP BY DATE(sale_date)
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
} else {
    $inventoryLabels = ['No Products'];
    $inventoryValues = [0];
}

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

$salesDataQuery = "
    SELECT p.product_name, DATE(s.sale_date) as sale_date, SUM(si.quantity) as total_qty
    FROM sales_items si
    JOIN product p ON si.product_id = p.product_id
    JOIN sales s ON si.sale_id = s.sales_id
    GROUP BY p.product_id, DATE(s.sale_date)
    ORDER BY p.product_name, sale_date ASC
";
$result = $conn->query($salesDataQuery);

$salesData = [];
while ($result && $row = $result->fetch_assoc()) {
    $salesData[$row['product_name']]['labels'][] = $row['sale_date'];
    $salesData[$row['product_name']]['values'][] = (int)$row['total_qty'];
}

// Moving average forecast
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

        $values[] = $avg; // rolling window
    }
}

// Fallback if no data
if (empty($salesData)) {
    $salesData['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
    $predictions['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
}
?>




<link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<h1 class="page-title">Admin Dashboard</h1>

<div class="cards">
    <?php
    $metrics = [
        ['icon' => 'fa-users', 'label' => 'Total Users', 'value' => number_format($userCount), 'color' => '#102C57'],
        ['icon' => 'fa-boxes', 'label' => 'Total Products', 'value' => number_format($productCount), 'color' => '#007BFF'],
        ['icon' => 'fa-shopping-cart', 'label' => 'Purchases', 'value' => number_format($purchaseCount), 'color' => '#FFC107'],
        ['icon' => 'fa-coins', 'label' => 'Sales Revenue', 'value' => '₱' . number_format($salesRevenue, 2), 'color' => '#28A745'],
        ['icon' => 'fa-chart-line', 'label' => 'Sales Growth', 'value' => $salesPrediction, 'color' => '#DC3545']
    ];

    foreach ($metrics as $m): ?>
        <div class="card" style="border-top:4px solid <?= $m['color'] ?>;">
            <h3><i class="fas <?= $m['icon'] ?>"></i> <?= $m['label'] ?></h3>
            <p class="value"><?= $m['value'] ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="charts">
    <div class="chart-row">
        <div class="chart-card full-width">
            <h3>Sales Trend</h3>
            <canvas id="salesTrendChart"></canvas>
        </div>

        <div class="chart-card full-width">
            <h3>Supplier Orders</h3>
            <canvas id="supplierChart"></canvas>
        </div>
    </div>
    <div class="chart-row">
        <div class="chart-card half-width">
            <h3>Weekly Sales Comparison</h3>
            <canvas id="weeklyComparisonChart"></canvas>
        </div>
        <div class="chart-card half-width">
            <h3>Low Stock Products</h3>
            <canvas id="inventoryChart"></canvas>
        </div>
    </div>
</div>

<div class="tables">
    <div class="table">
        <h3>Recent Purchases</h3>
        <div class="table-scroll">
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
    </div>

    <div class="table">
        <h3>Recent Sales</h3>
        <div class="table-scroll">
            <table>
                <tr>
                    <th>Date</th>
                    <th>Cashier</th>
                    <th>Total</th>
                </tr>
                <?php while ($row = $recentSales->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['cashier'] ?? 'N/A') ?></td>
                        <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</div>

<script>
    const trendData = <?= json_encode($trendData) ?>;
    const comparisonData = <?= json_encode($comparisonValues) ?>;
    const comparisonLabels = <?= json_encode($comparisonLabels) ?>;
    const inventoryData = <?= json_encode($inventoryValues) ?>;
    const inventoryLabels = <?= json_encode($inventoryLabels) ?>;
    const supplierData = <?= json_encode($supplierValues) ?>;
    const supplierLabels = <?= json_encode($supplierLabels) ?>;

    // Sales Trend Chart
    new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: {
            labels: trendData.labels,
            datasets: [{
                label: 'Daily Sales',
                data: trendData.values,
                borderColor: '#102C57',
                backgroundColor: 'rgba(16,44,87,0.2)',
                fill: true,
                tension: 0.3
            }]
        }
    });

    // Weekly Comparison
    new Chart(document.getElementById('weeklyComparisonChart'), {
        type: 'bar',
        data: {
            labels: comparisonLabels,
            datasets: [{
                label: 'Total Sales (₱)',
                data: comparisonData,
                backgroundColor: ['#DC3545', '#28A745']
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

    // Inventory Chart
    new Chart(document.getElementById('inventoryChart'), {
        type: 'bar',
        data: {
            labels: inventoryLabels,
            datasets: [{
                label: 'Stock Quantity',
                data: inventoryData,
                backgroundColor: inventoryData.map(v => v < 5 ? '#DC3545' : '#FFC107')
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

    // Supplier Chart
    new Chart(document.getElementById('supplierChart'), {
        type: 'pie',
        data: {
            labels: supplierLabels,
            datasets: [{
                label: 'Supplier Orders',
                data: supplierData,
                backgroundColor: ['#102C57', '#007BFF', '#28A745', '#FFC107', '#DC3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>