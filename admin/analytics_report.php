<?php
include '../config.php';

// SALES 
$totalSales = $conn->query("SELECT SUM(total) as revenue FROM carts WHERE status='completed'")
    ->fetch_assoc()['revenue'] ?? 0;

$bestProduct = $conn->query("
    SELECT p.product_name, SUM(ci.qty) as total_qty 
    FROM cart_items ci 
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    WHERE c.status = 'completed'
    GROUP BY p.product_id 
    ORDER BY total_qty DESC 
    LIMIT 1
")->fetch_assoc();

$salesTrend = $conn->query("
    SELECT DATE(created_at) as sale_date, SUM(total) as daily_total
    FROM carts
    WHERE status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
");
$trendData = ['labels' => [], 'values' => []];
while ($row = $salesTrend->fetch_assoc()) {
    $trendData['labels'][] = $row['sale_date'];
    $trendData['values'][] = $row['daily_total'];
}

// Top 5 Products
$topProducts = $conn->query("
    SELECT p.product_name, SUM(ci.qty) as sold
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    WHERE c.status = 'completed'
    GROUP BY p.product_id
    ORDER BY sold DESC
    LIMIT 5
");
$productLabels = [];
$productValues = [];
while ($row = $topProducts->fetch_assoc()) {
    $productLabels[] = $row['product_name'];
    $productValues[] = $row['sold'];
}

// INVENTORY 
$lowStock = $conn->query("SELECT COUNT(*) as cnt FROM product WHERE quantity <= threshold")
    ->fetch_assoc()['cnt'] ?? 0;

$totalInventoryValue = $conn->query("SELECT SUM(quantity * price) as value FROM product")
    ->fetch_assoc()['value'] ?? 0;

// SUPPLIER 
$pendingPurchases = $conn->query("SELECT COUNT(*) as cnt FROM stock WHERE status='pending'")
    ->fetch_assoc()['cnt'] ?? 0;

$topSupplier = $conn->query("
    SELECT sp.name, COUNT(s.stock_id) as orders
    FROM stock s
    JOIN supplier sp ON s.bodegero = sp.supplier_id
    GROUP BY sp.supplier_id
    ORDER BY orders DESC
    LIMIT 1
")->fetch_assoc();

// Purchases by Supplier
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

// --- fallback if no suppliers ---
if (empty($supplierLabels)) {
    $supplierLabels = ['No Data'];
    $supplierValues = [1];
}
?>

<!DOCTYPE html>
<html lang="en">


<link rel="stylesheet" href="../css/analytics.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="main">
    <h1>Analytics Dashboard</h1>

    <div class="cards">
        <div class="card">
            <h3><i class="fas fa-coins"></i> Total Sales</h3>
            <p>₱<?= number_format($totalSales, 2) ?></p>
        </div>
        <div class="card">
            <h3><i class="fas fa-box-open"></i> Best Product</h3>
            <p><?= $bestProduct['product_name'] ?? 'N/A' ?> (<?= $bestProduct['total_qty'] ?? 0 ?> sold)</p>
        </div>
        <div class="card">
            <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h3>
            <p><?= $lowStock ?></p>
        </div>
        <div class="card">
            <h3><i class="fas fa-warehouse"></i> Inventory Value</h3>
            <p>₱<?= number_format($totalInventoryValue, 2) ?></p>
        </div>
        <div class="card">
            <h3><i class="fas fa-hourglass-half"></i> Pending Purchases</h3>
            <p><?= $pendingPurchases ?></p>
        </div>
        <div class="card">
            <h3><i class="fas fa-truck"></i> Top Supplier</h3>
            <p><?= $topSupplier['name'] ?? 'N/A' ?> (<?= $topSupplier['orders'] ?? 0 ?> orders)</p>
        </div>
    </div>


    <div class="charts">
        <div class="chart-card" id="lineChart">
            <canvas id="salesTrendChart"></canvas>
        </div>
        <div class="chart-card" id="barChart">
            <canvas id="topProductsChart"></canvas>
        </div>
        <div class="chart-card" id="pieChart">
            <canvas id="supplierChart"></canvas>
        </div>
    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                x: {
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: 10
                    }
                },
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: true
                }
            }
        }
    });

    // Top 5 Products Bar Chart
    new Chart(document.getElementById('topProductsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($productLabels) ?>,
            datasets: [{
                label: 'Units Sold',
                data: <?= json_encode($productValues) ?>,
                backgroundColor: 'rgba(255,99,132,0.6)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Purchases by Supplier Pie Chart
    new Chart(document.getElementById('supplierChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($supplierLabels) ?>,
            datasets: [{
                label: 'Purchases',
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