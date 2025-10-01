<h1 class="page-title">Bodegero Dashboard</h1>

<section class="content">
    <div class="cards">
        <div class="card">
            <h3>Total Products</h3>
            <p>120</p>
        </div>
        <div class="card">
            <h3>Inventory Value</h3>
            <p>₱250,000</p>
        </div>
        <div class="card">
            <h3>Pending Purchases</h3>
            <p>8</p>
        </div>
        <div class="card">
            <h3>Completed Purchases</h3>
            <p>150</p>
        </div>
    </div>

    <div class="charts">
        <div class="chart">
            <h3>Stock Levels</h3>
            <canvas id="stockChart"></canvas>
        </div>
        <div class="chart">
            <h3>Purchases Overview</h3>
            <canvas id="purchaseChart"></canvas>
        </div>
    </div>

    <div class="tables">
        <div class="table">
            <h3>Recent Activity</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2025-09-20</td>
                        <td>Purchased from Supplier A</td>
                        <td>Completed</td>
                    </tr>
                    <tr>
                        <td>2025-09-19</td>
                        <td>Added new product</td>
                        <td>Success</td>
                    </tr>
                    <tr>
                        <td>2025-09-18</td>
                        <td>Inventory updated</td>
                        <td>Success</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
    new Chart(document.getElementById('stockChart'), {
        type: 'bar',
        data: {
            labels: ['Item A', 'Item B', 'Item C', 'Item D', 'Item E'],
            datasets: [{
                label: 'Stock Level',
                data: [30, 50, 20, 15, 40],
                backgroundColor: '#102C57'
            }]
        }
    });
    new Chart(document.getElementById('purchaseChart'), {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
            datasets: [{
                label: 'Purchases',
                data: [5, 8, 6, 10, 7],
                borderColor: '#102C57',
                fill: false
            }]
        }
    });
</script>