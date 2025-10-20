<?php
include '../config.php';

function tableExists($conn, $table)
{
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    return $check && $check->num_rows > 0;
}

// total sales
$totalSales = 0;
if (tableExists($conn, 'sales')) {
    $row = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales")->fetch_assoc();
    $totalSales = $row['total'] ?? 0;
}

// best product
$bestProduct = ['product_name' => 'N/A', 'total_qty' => 0];
if (tableExists($conn, 'sales_item')) {
    $sql = "
        SELECT p.product_name, SUM(si.quantity) AS total_qty
        FROM sales_item si
        JOIN product p ON si.product_id = p.product_id
        GROUP BY p.product_id
        ORDER BY total_qty DESC
        LIMIT 1
    ";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $bestProduct = $res->fetch_assoc();
    }
}

// sales trends or daily totals
$trendData = ['labels' => [], 'values' => []];
if (tableExists($conn, 'sales')) {
    $res = $conn->query("
        SELECT DATE(sale_date) AS sale_date, SUM(total_amount) AS total
        FROM sales
        GROUP BY DATE(sale_date)
        ORDER BY sale_date ASC
    ");
    while ($row = $res->fetch_assoc()) {
        $trendData['labels'][] = $row['sale_date'];
        $trendData['values'][] = (float)$row['total'];
    }
}

// top products
$productLabels = [];
$productValues = [];
if (tableExists($conn, 'sales_item')) {
    $res = $conn->query("
        SELECT p.product_name, SUM(si.quantity) AS sold
        FROM sales_item si
        JOIN product p ON si.product_id = p.product_id
        GROUP BY p.product_id
        ORDER BY sold DESC
        LIMIT 5
    ");
    while ($row = $res->fetch_assoc()) {
        $productLabels[] = $row['product_name'];
        $productValues[] = (int)$row['sold'];
    }
}

// low stock items
$lowStock = 0;
if (tableExists($conn, 'product_stocks')) {
    $res = $conn->query("
        SELECT COUNT(*) AS cnt FROM (
            SELECT p.product_id, p.threshold,
                   COALESCE(SUM(ps.remaining_qty),0) AS total_remaining
            FROM product p
            LEFT JOIN product_stocks ps ON p.product_id = ps.product_id
            GROUP BY p.product_id
            HAVING total_remaining <= COALESCE(p.threshold,0)
        ) t
    ");
    $lowStock = $res->fetch_assoc()['cnt'] ?? 0;
}

// total inv. value
$totalInventoryValue = 0;
if (tableExists($conn, 'product_stocks')) {
    $res = $conn->query("
        SELECT COALESCE(SUM(remaining_qty * cost_price),0) AS val
        FROM product_stocks
    ");
    $totalInventoryValue = $res->fetch_assoc()['val'] ?? 0;
}

// pending purchases
$pendingPurchases = 0;
if (tableExists($conn, 'product_stocks')) {
    $res = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM product_stocks
        WHERE status = 'pending' OR (status IS NULL AND (cancel_details IS NULL OR cancel_details = ''))
    ");
    $pendingPurchases = $res->fetch_assoc()['cnt'] ?? 0;
}

// top supplier
$topSupplier = ['name' => 'N/A', 'orders' => 0];
if (tableExists($conn, 'supplier') && tableExists($conn, 'product_stocks')) {
    $res = $conn->query("
        SELECT s.name, COUNT(ps.product_stock_id) AS orders
        FROM supplier s
        LEFT JOIN product_stocks ps ON ps.supplier_id = s.supplier_id
        GROUP BY s.supplier_id
        ORDER BY orders DESC
        LIMIT 1
    ");
    if ($res && $res->num_rows > 0) {
        $topSupplier = $res->fetch_assoc();
    }
}

// ----------------------
// 9. SUPPLIER PIE DATA
// ----------------------
$supplierLabels = [];
$supplierValues = [];
if (tableExists($conn, 'supplier') && tableExists($conn, 'product_stocks')) {
    $res = $conn->query("
        SELECT s.name, COUNT(ps.product_stock_id) AS total_orders
        FROM supplier s
        LEFT JOIN product_stocks ps ON ps.supplier_id = s.supplier_id
        GROUP BY s.supplier_id
        ORDER BY total_orders DESC
    ");
    while ($row = $res->fetch_assoc()) {
        $supplierLabels[] = $row['name'];
        $supplierValues[] = (int)$row['total_orders'];
    }
}

if (empty($supplierLabels)) {
    $supplierLabels = ['No Data'];
    $supplierValues = [1];
}
?>


<!DOCTYPE html>
<html lang="en">
<link rel="stylesheet" href="../css/analytics.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="page-content analytics-report">
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