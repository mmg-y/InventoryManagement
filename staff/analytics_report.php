<?php
include "../config.php";

// Metrics 
$totalRevenue = $conn->query("SELECT SUM(total) AS rev FROM carts WHERE status='completed'")
    ->fetch_assoc()['rev'] ?? 0;

$dailySales = $conn->query("SELECT SUM(total) AS rev FROM carts WHERE status='completed' 
                             AND DATE(created_at)=CURDATE()")
    ->fetch_assoc()['rev'] ?? 0;

$ordersProcessed = $conn->query("SELECT COUNT(*) AS cnt FROM carts WHERE status='completed'")
    ->fetch_assoc()['cnt'] ?? 0;

$lowStock = $conn->query("SELECT COUNT(*) AS cnt FROM product WHERE quantity <= threshold")
    ->fetch_assoc()['cnt'] ?? 0;

// Top Products 
$topProducts = $conn->query("
    SELECT p.product_name, p.product_picture, SUM(ci.qty) as units_sold, 
           SUM(ci.qty * ci.price) as revenue, s.status_label
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    LEFT JOIN status s ON p.notice_status = s.status_id
    WHERE c.status='completed' AND MONTH(c.created_at)=MONTH(CURDATE())
    GROUP BY p.product_id
    ORDER BY units_sold DESC
    LIMIT 5
");

// Employees 
$employees = $conn->query("SELECT id, first_name, last_name, profile_pic, type, archived FROM user WHERE type='staff'");

// Product Sales Trends (Bar) 
$salesTrendsQuery = $conn->query("
    SELECT DAYNAME(c.created_at) as day, cat.category_name, SUM(ci.qty) as qty
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    JOIN category cat ON p.category = cat.category_id
    WHERE c.status='completed' AND YEARWEEK(c.created_at,1)=YEARWEEK(CURDATE(),1)
    GROUP BY day, cat.category_name
");

$salesData = [];
while ($row = $salesTrendsQuery->fetch_assoc()) {
    $salesData[$row['category_name']][$row['day']] = (int)$row['qty'];
}

// Ensure all days exist
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
foreach ($salesData as $cat => $values) {
    foreach ($days as $d) {
        if (!isset($salesData[$cat][$d])) {
            $salesData[$cat][$d] = 0;
        }
    }
    ksort($salesData[$cat]);
}

// Product Comparison (Vegetables vs Meat & Seafood) 
$comparisonQuery = $conn->query("
    SELECT DAYNAME(c.created_at) as day, cat.category_name, SUM(ci.qty) as qty
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    JOIN category cat ON p.category = cat.category_id
    WHERE c.status='completed'
      AND cat.category_name IN ('Vegetables','Meat & Seafood')
      AND YEARWEEK(c.created_at,1)=YEARWEEK(CURDATE(),1)
    GROUP BY day, cat.category_name
");

$comparisonData = [];
while ($row = $comparisonQuery->fetch_assoc()) {
    $comparisonData[$row['category_name']][$row['day']] = (int)$row['qty'];
}

foreach (['Vegetables', 'Meat & Seafood'] as $cat) {
    foreach ($days as $d) {
        if (!isset($comparisonData[$cat][$d])) {
            $comparisonData[$cat][$d] = 0;
        }
    }
    ksort($comparisonData[$cat]);
}

// Convert PHP arrays → JS JSON
$salesDataJSON = json_encode($salesData);
$comparisonDataJSON = json_encode($comparisonData);
$daysJSON = json_encode($days);
?>

<link rel="stylesheet" href="../css/analytics(staff).css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="dashboard">

    <?php if ($lowStock > 0): ?>
        <div class="notification">
            ⚠️ Low Stock Alert: <?= $lowStock ?> items need restocking.
        </div>
    <?php endif; ?>

    <!-- === METRICS === -->
    <div class="metrics">
        <div class="card">
            <div class="icon-label">
                <i class="fas fa-coins"></i>
                <div class="label">Total Revenue</div>
            </div>
            <div class="value">₱<?= number_format($totalRevenue, 2) ?></div>
        </div>
        <div class="card">
            <div class="icon-label">
                <i class="fas fa-dollar-sign"></i>
                <div class="label">Daily Sales</div>
            </div>
            <div class="value">₱<?= number_format($dailySales, 2) ?></div>
        </div>
        <div class="card">
            <div class="icon-label">
                <i class="fas fa-shopping-cart"></i>
                <div class="label">Orders Processed</div>
            </div>
            <div class="value"><?= $ordersProcessed ?></div>
        </div>
        <div class="card">
            <div class="icon-label">
                <i class="fas fa-boxes"></i>
                <div class="label">Low Stock Items</div>
            </div>
            <div class="value"><?= $lowStock ?></div>
        </div>
    </div>


    <!-- === FIRST ROW === -->
    <div class="row">
        <div class="col">
            <div class="card_sales">
                <h3>Product Sales Trends</h3>
                <canvas id="salesTrends"></canvas>
            </div>
        </div>
        <div class="col">
            <div class="card_products">
                <h3>
                    <i class="fas fa-chart-bar"></i> Top Products (This Month)
                </h3>
                <?php while ($p = $topProducts->fetch_assoc()): ?>
                    <div class="product">
                        <img src="../<?= $p['product_picture'] ?>" alt="<?= $p['product_name'] ?>">

                        <div class="info">
                            <div class="name"><?= $p['product_name'] ?></div>
                            <div class="details"><?= $p['units_sold'] ?> units | ₱<?= number_format($p['revenue'], 2) ?></div>
                        </div>
                        <span class="status"><?= $p['status_label'] ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

    </div>

    <!-- === SECOND ROW === -->
    <div class="row">
        <div class="col">
            <div class="card_comparison">
                <h3>Product Comparison</h3>
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
        <div class="col">
            <div class="card_employees">
                <h3>Employees</h3>
                <?php while ($e = $employees->fetch_assoc()): ?>
                    <div class="employee">
                        <img src="../uploads/<?= $e['profile_pic'] ?: 'default.png' ?>" alt="">
                        <div class="info">
                            <div class="name"><?= $e['first_name'] . " " . $e['last_name'] ?></div>
                            <div class="role"><?= ucfirst($e['type']) ?></div>
                        </div>
                        <?php if ($e['archived'] == 1): ?>
                            <span class="badge archived">Archived</span>
                        <?php else: ?>
                            <span class="badge active">Active</span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const days = <?= $daysJSON ?>;

    // Sales Trends (Bar Chart) 
    const salesData = <?= $salesDataJSON ?>;
    const salesDatasets = Object.keys(salesData).map(cat => ({
        label: cat,
        data: days.map(d => salesData[cat][d]),
        backgroundColor: '#' + Math.floor(Math.random() * 16777215).toString(16)
    }));

    new Chart(document.getElementById('salesTrends'), {
        type: 'bar',
        data: {
            labels: days,
            datasets: salesDatasets
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });

    // Product Comparison (Line Chart) 
    const compData = <?= $comparisonDataJSON ?>;
    const comparisonDatasets = Object.keys(compData).map(cat => ({
        label: cat,
        data: days.map(d => compData[cat][d]),
        borderColor: '#' + Math.floor(Math.random() * 16777215).toString(16),
        fill: false
    }));

    new Chart(document.getElementById('comparisonChart'), {
        type: 'line',
        data: {
            labels: days,
            datasets: comparisonDatasets
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
</script>