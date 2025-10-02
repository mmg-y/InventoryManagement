<?php
include '../config.php';

$userCount = $conn->query("SELECT COUNT(*) AS total FROM user")->fetch_assoc()['total'];
$productCount = $conn->query("SELECT COUNT(*) AS total FROM product")->fetch_assoc()['total'];
$purchaseCount = $conn->query("SELECT COUNT(*) AS total FROM stock")->fetch_assoc()['total'];
$salesRevenue = $conn->query("SELECT IFNULL(SUM(total),0) AS revenue FROM carts WHERE status='completed'")->fetch_assoc()['revenue'];
$salesPrediction = "+12%";

$recentPurchases = $conn->query("
    SELECT s.created_at AS date, sp.supplier_type AS supplier, s.qty AS items
    FROM stock s
    LEFT JOIN supplier sp ON s.bodegero = sp.supplier_id
    ORDER BY s.created_at DESC
    LIMIT 5
");

$recentSales = $conn->query("
    SELECT c.created_at AS date, c.seller AS customer, c.total AS total_amount
    FROM carts c
    WHERE c.status='completed'
    ORDER BY c.created_at DESC
    LIMIT 5
");

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

$lowStockProducts = $conn->query("
    SELECT product_name, quantity 
    FROM product 
    WHERE quantity <= threshold
    LIMIT 5
");
$inventoryLabels = [];
$inventoryValues = [];
if ($lowStockProducts && $lowStockProducts->num_rows > 0) {
    while ($row = $lowStockProducts->fetch_assoc()) {
        $inventoryLabels[] = $row['product_name'];
        $inventoryValues[] = $row['quantity'];
    }
}
if (empty($inventoryLabels)) {
    $inventoryLabels = ['No Products'];
    $inventoryValues = [0];
}

$supplierStats = $conn->query("
    SELECT sp.name, COUNT(s.stock_id) as total_orders
    FROM stock s
    JOIN supplier sp ON s.bodegero = sp.supplier_id
    GROUP BY sp.supplier_id
");
$supplierLabels = [];
$supplierValues = [];
while ($row = $supplierStats->fetch_assoc()) {
    $supplierLabels[] = $row['name'];
    $supplierValues[] = $row['total_orders'];
}
if (empty($supplierLabels)) {
    $supplierLabels = ['No Suppliers'];
    $supplierValues = [0];
}
?>

<link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<h1 class="page-title">Admin Dashboard</h1>

<div class="cards">
    <div class="card">
        <h3>Total Users</h3>
        <p><?= number_format($userCount) ?></p>
    </div>
    <div class="card">
        <h3>Total Products</h3>
        <p><?= number_format($productCount) ?></p>
    </div>
    <div class="card">
        <h3>Purchases</h3>
        <p><?= number_format($purchaseCount) ?></p>
    </div>
    <div class="card">
        <h3>Sales Revenue</h3>
        <p>₱<?= number_format($salesRevenue, 2) ?></p>
    </div>
    <div class="card">
        <h3>Sales Prediction</h3>
        <p><?= $salesPrediction ?></p>
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