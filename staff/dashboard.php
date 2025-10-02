<?php
include '../config.php';

$staff = $_SESSION['username'];

$todaySales = $conn->query("
    SELECT IFNULL(SUM(total),0) AS total 
    FROM carts 
    WHERE status='completed' 
      AND seller='$staff' 
      AND DATE(created_at) = CURDATE()
")->fetch_assoc()['total'];

$totalTransactions = $conn->query("
    SELECT COUNT(*) AS cnt 
    FROM carts 
    WHERE status='completed' 
      AND seller='$staff'
")->fetch_assoc()['cnt'];

$monthlySales = $conn->query("
    SELECT IFNULL(SUM(total),0) AS total 
    FROM carts 
    WHERE status='completed' 
      AND seller='$staff' 
      AND MONTH(created_at) = MONTH(CURDATE())
")->fetch_assoc()['total'];

$avgTransaction = $totalTransactions ? round($monthlySales / $totalTransactions, 2) : 0;

$recentSales = $conn->query("
    SELECT created_at AS date, cart_id AS invoice_no, total 
    FROM carts 
    WHERE status='completed' AND seller='$staff' 
    ORDER BY created_at DESC 
    LIMIT 5
");

$topProducts = $conn->query("
    SELECT p.product_name, SUM(ci.qty) AS sold
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    WHERE c.seller = '$staff' AND c.status='completed'
    GROUP BY ci.product_id
    ORDER BY sold DESC
    LIMIT 5
");

$weeklySalesQuery = $conn->query("
    SELECT DATE(created_at) AS sale_date, SUM(total) AS daily_total
    FROM carts
    WHERE status='completed' AND seller='$staff' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
");

$weeklyLabels = [];
$weeklyData = [];
while ($row = $weeklySalesQuery->fetch_assoc()) {
    $weeklyLabels[] = $row['sale_date'];
    $weeklyData[] = $row['daily_total'];
}

$allDays = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $allDays[$day] = 0;
}
foreach ($weeklyLabels as $index => $day) {
    $allDays[$day] = $weeklyData[$index];
}
$weeklyLabels = array_keys($allDays);
$weeklyData = array_values($allDays);

$lowStockItems = $conn->query("SELECT product_name, quantity FROM product WHERE quantity <= threshold LIMIT 5");
?>

<link rel="stylesheet" href="../css/dashboard(staff).css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


<h1 class="page-title">Staff Dashboard</h1>

<section class="dashboard">
    <div class="stats">
        <div class="card">
            <h3>Today’s Sales</h3>
            <p>₱<?= number_format($todaySales, 2) ?></p>
        </div>
        <div class="card">
            <h3>Total Transactions</h3>
            <p><?= number_format($totalTransactions) ?></p>
        </div>
        <div class="card">
            <h3>Monthly Sales</h3>
            <p>₱<?= number_format($monthlySales, 2) ?></p>
        </div>
        <div class="card">
            <h3>Avg Transaction</h3>
            <p>₱<?= number_format($avgTransaction, 2) ?></p>
        </div>
    </div>

    <div class="chart card">
        <h3>Weekly Sales</h3>
        <canvas id="salesChart"></canvas>
    </div>

    <div class="table">
        <h3>Recent Transactions</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice #</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $recentSales->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td>#<?= htmlspecialchars($row['invoice_no']) ?></td>
                        <td>₱<?= number_format($row['total'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Top Selling Products</h3>
        <ul>
            <?php while ($row = $topProducts->fetch_assoc()): ?>
                <li><?= htmlspecialchars($row['product_name']) ?> - <?= $row['sold'] ?> sold</li>
            <?php endwhile; ?>
        </ul>
    </div>

</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($weeklyLabels) ?>,
            datasets: [{
                label: 'Daily Sales (₱)',
                data: <?= json_encode($weeklyData) ?>,
                backgroundColor: 'rgba(16,44,87,0.7)'
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
</script>