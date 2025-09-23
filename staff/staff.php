<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "staff") {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/staff.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

    <aside class="sidebar">
        <h2>Staff</h2>
        <ul class="menu">
            <li class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</li>
            <li><i class="fa-solid fa-cart-plus"></i>POS</li>
            <li><i class="fa-solid fa-truck-ramp-box"></i>Products & Inventory</li>
            <li><i class="fa-solid fa-clipboard"></i>Sales Record</li>
            <li><i class="fa-solid fa-chart-pie"></i>Analytics & Reports</li>
        </ul>
        <div class="settings">
            <li><i class="fa-solid fa-gear"></i> Settings</li>
        </div>
    </aside>

    <main class="main-content">
        <h1 class="page-title">Staff Dashboard</h1>

        <header class="topbar">
            <div class="search-bar">
                <input type="text" placeholder="Search...">
            </div>

            <div class="topbar-actions">
                <button class="icon-btn">
                    <i class="fa-solid fa-bell" style="color: #102c57;"></i>
                    <span class="badge">3</span>
                </button>
                <div class="profile" id="profileMenu">
                    <img src="https://via.placeholder.com/40" alt="Profile" class="profile-img">
                    <span>
                        <h1>Welcome, <?= $_SESSION['username']; ?>!</h1>
                    </span>
                    <i class="fa-solid fa-chevron-down chevron"></i>

                    <div class="dropdown" id="dropdownMenu">
                        <a href="#">Manage Profile</a>
                        <a href="../logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <section class="dashboard">
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

            <div class="chart card">
                <h3>Weekly Sales (Personal)</h3>
                <canvas id="salesChart"></canvas>
            </div>

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


            <div class="card">
                <h3>Top Selling Products</h3>
                <ul>
                    <li>Product A - 15 sold</li>
                    <li>Product B - 12 sold</li>
                    <li>Product C - 10 sold</li>
                </ul>
            </div>

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

    <script>
        const profile = document.getElementById("profileMenu");

        profile.addEventListener("click", () => {
            profile.classList.toggle("active");
        });

        document.addEventListener("click", (e) => {
            if (!profile.contains(e.target)) {
                profile.classList.remove("active");
            }
        });
    </script>

</body>

</html>