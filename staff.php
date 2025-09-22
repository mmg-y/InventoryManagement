<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Staff Dashboard</title>
    <link rel="stylesheet" href="css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

    <aside class="sidebar">
        <h2>Staff</h2>
        <ul class="menu">
            <li class="active">Dashboard</li>
            <li>POS</li>
            <li>Manage Staff Account</li>
            <li>Products & Inventory (View Only)</li>
            <li>Sales Record</li>
            <li>Analytics & Reports</li>
            <li>Notifications</li>
        </ul>
        <div class="settings">
            <li>System Settings</li>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <h1>Staff Dashboard</h1>
            <div class="search-bar">
                <input type="text" placeholder="Search...">
            </div>

            <div class="topbar-actions">
                <button class="icon-btn">
                    🔔
                    <span class="badge">3</span>
                </button>
                <div class="profile">
                    <img src="https://via.placeholder.com/40" alt="Profile">
                    <span>John Doe</span>
                </div>
            </div>
        </header>

        <section class="dashboard">
            <!-- Quick Stats -->
            <div class="stats">
                <div class="card">
                    <h3>Today’s Sales</h3>
                    <p>₱5,200</p>
                </div>
                <div class="card">
                    <h3>Total Transactions</h3>
                    <p>28</p>
                </div>
                <div class="card">
                    <h3>Monthly Sales</h3>
                    <p>₱48,900</p>
                </div>
                <div class="card">
                    <h3>Avg Transaction</h3>
                    <p>₱185</p>
                </div>
            </div>

            <!-- Sales Chart -->
            <div class="chart card">
                <h3>Weekly Sales (Personal)</h3>
                <canvas id="salesChart"></canvas>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
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
                        <tr>
                            <td>Sept 22, 2025</td>
                            <td>#TX1023</td>
                            <td>₱250</td>
                        </tr>
                        <tr>
                            <td>Sept 22, 2025</td>
                            <td>#TX1024</td>
                            <td>₱480</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Top Products -->
            <div class="card">
                <h3>Top Selling Products</h3>
                <ul>
                    <li>Product A - 15 sold</li>
                    <li>Product B - 12 sold</li>
                    <li>Product C - 10 sold</li>
                </ul>
            </div>

            <!-- Notifications -->
            <div class="card">
                <h3>Notifications</h3>
                <ul>
                    <li>⚠ Low stock: Item #103 (2 left)</li>
                    <li>✅ Sale recorded successfully</li>
                    <li>📢 System maintenance tomorrow 10PM</li>
                </ul>
            </div>
        </section>
    </main>

    <!-- Chart.js for sales chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('salesChart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales (₱)',
                    data: [500, 800, 650, 1200, 900, 1500, 1100]
                }]
            }
        });
    </script>
</body>

</html>