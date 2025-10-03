<?php
include '../config.php';

// ======== GET PRODUCT SALES HISTORY ========
$sql = "
    SELECT p.product_name, DATE(c.created_at) as sale_date, SUM(ci.qty) as total_qty
    FROM cart_items ci
    JOIN product p ON ci.product_id = p.product_id
    JOIN carts c ON ci.cart_id = c.cart_id
    WHERE c.status = 'completed'
    GROUP BY p.product_id, DATE(c.created_at)
    ORDER BY p.product_name, sale_date ASC
";
$result = $conn->query($sql);

$salesData = [];
while ($row = $result->fetch_assoc()) {
    $salesData[$row['product_name']]['labels'][] = $row['sale_date'];
    $salesData[$row['product_name']]['values'][] = (int)$row['total_qty'];
}

// ======== FORECAST NEXT 7 DAYS (Moving Average) ========
$window = 7;
$predictions = [];

foreach ($salesData as $product => $data) {
    $values = $data['values'];
    $lastDate = end($data['labels']) ?: date('Y-m-d'); // fallback if no data
    $date = new DateTime($lastDate);

    for ($i = 1; $i <= 7; $i++) {
        $slice = array_slice($values, -$window);
        $avg = !empty($slice) ? array_sum($slice) / count($slice) : 0;

        $date->modify('+1 day');
        $predictions[$product]['labels'][] = $date->format('Y-m-d');
        $predictions[$product]['values'][] = round($avg, 2);

        $values[] = $avg;
    }
}

// ======== Ensure at least one dummy product if no sales ========
if (empty($salesData)) {
    $salesData['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
    $predictions['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
}
?>

<link rel="stylesheet" href="../css/sales_prediction.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main">
    <h1>Sales Prediction</h1>

    <div class="cards">
        <?php foreach ($predictions as $product => $data): ?>
            <div class="card">
                <h3><?= htmlspecialchars($product) ?></h3>
                <p>Next 7 days forecast: <strong><?= array_sum($data['values']) ?></strong> units</p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="charts">
        <?php foreach ($salesData as $product => $data):
            $labels = $data['labels'] ?? [$predictions[$product]['labels'][0]];
            $values = $data['values'] ?? [0];
            $predLabels = $predictions[$product]['labels'] ?? [$labels[0]];
            $predValues = $predictions[$product]['values'] ?? [0];
        ?>
            <canvas id="chart_<?= md5($product) ?>"></canvas>
            <script>
                new Chart(document.getElementById("chart_<?= md5($product) ?>"), {
                    type: 'line',
                    data: {
                        labels: [...<?= json_encode($labels) ?>, ...<?= json_encode($predLabels) ?>],
                        datasets: [{
                                label: 'Actual Sales (<?= addslashes($product) ?>)',
                                data: <?= json_encode($values) ?>,
                                borderColor: 'blue',
                                backgroundColor: 'rgba(255,255,255,1)',
                                fill: true,
                                tension: 0.3
                            },
                            {
                                label: 'Predicted Sales',
                                data: [...Array(<?= count($values) ?>).fill(null), ...<?= json_encode($predValues) ?>],
                                borderColor: 'orange',
                                borderDash: [5, 5],
                                backgroundColor: 'rgba(255,255,255,1)',
                                fill: true,
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
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
            </script>
        <?php endforeach; ?>
    </div>
</div>