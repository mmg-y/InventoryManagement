<?php
include "../config.php";

// Metrics 
$totalRevenue = $conn->query("
    SELECT SUM(total_amount) AS rev
    FROM sales
")->fetch_assoc()['rev'] ?? 0;

$dailyRevenue = $conn->query("
    SELECT SUM(total_amount) AS rev
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
")->fetch_assoc()['rev'] ?? 0;

$ordersProcessed = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM sales
")->fetch_assoc()['cnt'] ?? 0;

// Low stock items
$lowStock = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM product
    WHERE total_quantity <= threshold
")->fetch_assoc()['cnt'] ?? 0;


// Top products (this month)
$topProducts = $conn->query("
    SELECT p.product_name, p.product_picture, SUM(si.quantity) AS units_sold,
           SUM(si.quantity * si.unit_price) AS revenue
    FROM sales_items AS si
    JOIN product AS p ON si.product_id = p.product_id
    JOIN sales AS s ON si.sale_id = s.sales_id
    WHERE MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date) = YEAR(CURDATE())
    GROUP BY p.product_id
    ORDER BY units_sold DESC
    LIMIT 5
");


// Employees
$employees = $conn->query("
    SELECT id, first_name, last_name, profile_pic, type
    FROM user 
    WHERE type IN ('cashier','warehouse_man')
");

// Product Sales Trends 
$salesTrendsQuery = $conn->query("
    SELECT DAYNAME(s.sale_date) AS day, c.category_name, SUM(si.quantity) AS qty
    FROM sales_items AS si
    JOIN product AS p ON si.product_id = p.product_id
    JOIN sales AS s ON si.sale_id = s.sales_id
    JOIN category AS c ON p.category = c.category_id
    WHERE YEARWEEK(s.sale_date,1) = YEARWEEK(CURDATE(),1)
    GROUP BY day, c.category_name
");

$salesData = [];
while ($row = $salesTrendsQuery->fetch_assoc()) {
    $salesData[$row['category_name']][$row['day']] = (int)$row['qty'];
}

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
foreach ($salesData as $cat => $values) {
    foreach ($days as $d) {
        if (!isset($salesData[$cat][$d])) {
            $salesData[$cat][$d] = 0;
        }
    }
    ksort($salesData[$cat]);
}

// Product Comparison 
$categoriesResult = $conn->query("SELECT category_name FROM category");
$categoryNames = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categoryNames[] = $row['category_name'];
}

$categoryList = "'" . implode("','", $categoryNames) . "'";

$comparisonQuery = $conn->query("
    SELECT DAYNAME(s.sale_date) AS day, c.category_name, SUM(si.quantity) AS qty
    FROM sales_items AS si
    JOIN product AS p ON si.product_id = p.product_id
    JOIN sales AS s ON si.sale_id = s.sales_id
    JOIN category AS c ON p.category = c.category_id
    WHERE c.category_name IN ($categoryList)
      AND YEARWEEK(s.sale_date,1) = YEARWEEK(CURDATE(),1)
    GROUP BY day, c.category_name
");

$comparisonData = [];
while ($row = $comparisonQuery->fetch_assoc()) {
    $comparisonData[$row['category_name']][$row['day']] = (int)$row['qty'];
}

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
foreach ($comparisonData as $cat => $values) {
    foreach ($days as $d) {
        if (!isset($comparisonData[$cat][$d])) {
            $comparisonData[$cat][$d] = 0;
        }
    }
    ksort($comparisonData[$cat]);
}

$comparisonDataJSON = json_encode($comparisonData);
$daysJSON = json_encode($days);


$salesDataJSON = json_encode($salesData);
$comparisonDataJSON = json_encode($comparisonData);
$daysJSON = json_encode($days);
?>

<link rel="stylesheet" href="../css/analytics(staff).css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="dashboard">

    <div class="metrics">
        <?php
        $staffMetrics = [
            ['icon' => 'fa-coins', 'label' => 'Total Revenue', 'value' => '₱' . number_format($totalRevenue, 2), 'color' => '#28A745'],
            ['icon' => 'fa-dollar-sign', 'label' => 'Daily Sales', 'value' => '₱' . number_format($dailyRevenue, 2), 'color' => '#007BFF'],
            ['icon' => 'fa-shopping-cart', 'label' => 'Orders Processed', 'value' => $ordersProcessed, 'color' => '#FFC107'],
            ['icon' => 'fa-boxes', 'label' => 'Low Stock Items', 'value' => $lowStock, 'color' => '#DC3545'],
        ];

        foreach ($staffMetrics as $m):
        ?>
            <div class="card" style="border-top:4px solid <?= $m['color'] ?>;">
                <h3><i class="fas <?= $m['icon'] ?>"></i> <?= $m['label'] ?></h3>
                <p class="value"><?= $m['value'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>


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
                        <img src="../images/<?= $p['product_picture'] ?>" alt="<?= $p['product_name'] ?>">
                        <div class="info">
                            <div class="name"><?= $p['product_name'] ?></div>
                            <div class="details"><?= $p['units_sold'] ?> units | ₱<?= number_format($p['revenue'], 2) ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>

            </div>
        </div>

    </div>

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
                        <img src="../images/<?= $e['profile_pic'] ?: 'default.png' ?>" alt="<?= $e['first_name'] ?>">
                        <div class="info">
                            <div class="name"><?= $e['first_name'] . " " . $e['last_name'] ?></div>
                            <div class="role"><?= ucfirst($e['type']) ?></div>
                        </div>
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