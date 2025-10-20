<?php
include '../config.php';

// get product sales histort
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

// forecast for the next 7 days (Moving Average) 
$window = 7;
$predictions = [];

foreach ($salesData as $product => $data) {

    $historical_values = $data['values'];
    $lastDate = end($data['labels']) ?: date('Y-m-d');
    $date = new DateTime($lastDate);

    $start_index = count($historical_values);

    for ($i = 1; $i <= 7; $i++) {

        $start = max(0, $start_index - $window);

        $slice = array_slice($historical_values, $start, $window);

        $avg = !empty($slice) ? array_sum($slice) / count($slice) : 0;

        $date->modify('+1 day');
        $predictions[$product]['labels'][] = $date->format('Y-m-d');
        $predictions[$product]['values'][] = round($avg, 2);
    }
}

//  dummy product if no sales 
if (empty($salesData)) {
    $salesData['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
    $predictions['No Data'] = ['labels' => [date('Y-m-d')], 'values' => [0]];
}

$allDates = [];
// unique dates from historical sales and predictions
foreach ($salesData as $data) {
    $allDates = array_merge($allDates, $data['labels']);
}
foreach ($predictions as $data) {
    $allDates = array_merge($allDates, $data['labels']);
}
$combinedLabels = array_values(array_unique($allDates));
sort($combinedLabels);

$totalActualSalesData = [];
$totalPredictedSalesData = [];
$firstPredictionDate = date('Y-m-d', strtotime('+1 day', strtotime(end($salesData[array_key_first($salesData)]['labels'])))); // Use the last date of the first product as a reference

foreach ($combinedLabels as $date) {
    $actualSum = 0;
    $predictionSum = 0;

    // Sum actual sales for this date across all products
    foreach ($salesData as $product => $data) {
        $key = array_search($date, $data['labels']);
        if ($key !== false) {
            $actualSum += $data['values'][$key];
        }
    }
    $totalActualSalesData[] = $actualSum;

    // Sum predicted sales for this date across all products
    if ($date >= $firstPredictionDate) {
        foreach ($predictions as $product => $data) {
            $key = array_search($date, $data['labels']);
            if ($key !== false) {
                $predictionSum += $data['values'][$key];
            }
        }
        $totalPredictedSalesData[] = $predictionSum;
    } else {
        $totalPredictedSalesData[] = null;
    }
}

// Calculation of overall 7-day sales growth for dashboard 
$totalPredicted = 0;
$totalLastSevenDays = 0;

foreach ($salesData as $product => $data) {

    $totalPredicted += array_sum($predictions[$product]['values']);

    $lastSeven = array_slice($data['values'], -7);
    $totalLastSevenDays += array_sum($lastSeven);
}

$salesPredictionPercent = $totalLastSevenDays > 0
    ? round(($totalPredicted - $totalLastSevenDays) / $totalLastSevenDays * 100, 2)
    : 0;
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
        <canvas id="aggregatedSalesChart" style="width: 100%; height: 400px;"></canvas>
        <script>
            const labels = <?= json_encode($combinedLabels) ?>;
            const actualData = <?= json_encode($totalActualSalesData) ?>;
            const predictedData = <?= json_encode($totalPredictedSalesData) ?>;

            new Chart(document.getElementById("aggregatedSalesChart"), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Total Actual Sales',
                            data: actualData,
                            borderColor: 'blue',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Total Predicted Sales',
                            data: predictedData,
                            borderColor: 'orange',
                            borderDash: [5, 5],
                            backgroundColor: 'transparent',
                            fill: false,
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
                        title: {
                            display: true,
                            text: 'Total Sales: Actual vs. Predicted'
                        },
                        legend: {
                            display: true
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        },
                        beforeDraw: (chart) => {
                            const ctx = chart.ctx;
                            ctx.save();
                            ctx.globalCompositeOperation = 'destination-over';
                            ctx.fillStyle = '#fff';
                            ctx.fillRect(0, 0, chart.width, chart.height);
                            ctx.restore();
                        }
                    },

                }

            });
        </script>
    </div>

    <div class="overall-growth">
        <h2>Overall Sales Growth Prediction: <?= $salesPredictionPercent ?>%</h2>
    </div>
</div>