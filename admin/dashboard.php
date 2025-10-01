<?php
include '../config.php';

$userCount = $conn->query("SELECT COUNT(*) AS total FROM user")->fetch_assoc()['total'];

$productCount = $conn->query("SELECT COUNT(*) AS total FROM product")->fetch_assoc()['total'];

$purchaseCount = $conn->query("SELECT COUNT(*) AS total FROM stock")->fetch_assoc()['total'];

$salesRevenue = $conn->query("SELECT IFNULL(SUM(total),0) AS revenue FROM carts WHERE status='completed'")
    ->fetch_assoc()['revenue'];

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
?>

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
        <p>$<?= number_format($salesRevenue, 2) ?></p>
    </div>
    <div class="card">
        <h3>Sales Prediction</h3>
        <p><?= $salesPrediction ?></p>
    </div>
</div>

<div class="charts">
    <div class="chart">[ Sales Trend Chart Placeholder ]</div>
    <div class="chart">[ Inventory Analytics Placeholder ]</div>
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
                    <td>$<?= number_format($row['total_amount'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>