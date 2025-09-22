<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Admin Page</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

    <aside class="sidebar">
        <div>
            <h2>Admin</h2>
            <ul class="menu">
                <li class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</li>
                <li><i class="fa-solid fa-user-shield"></i> User Roles</li>
                <li><i class="fa-solid fa-boxes-stacked"></i> Products & Inventory</li>
                <li><i class="fa-solid fa-truck"></i> Supplier Purchases</li>
                <li><i class="fa-solid fa-receipt"></i> Sales Record</li>
                <li><i class="fa-solid fa-chart-pie"></i> Analytics & Reports</li>
                <li><i class="fa-solid fa-chart-trend"></i> Sales Prediction</li>
            </ul>
        </div>

        <!-- Settings at bottom -->
        <ul class="menu bottom-menu">
            <li><i class="fa-solid fa-gear"></i> Settings</li>
        </ul>
    </aside>

    <main class="main">

        <div class="topbar">
            <div class="search">
                <input type="text" placeholder="Search...">
            </div>
            <div class="topbar-right">
                <div class="icon">🔔</div>
                <div class="icon">⚙</div>
                <div class="profile">
                    <div class="profile-img"></div>
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Cards -->
        <div class="cards">
            <div class="card">
                <h3>Total Users</h3>
                <p>250</p>
            </div>
            <div class="card">
                <h3>Total Products</h3>
                <p>1,200</p>
            </div>
            <div class="card">
                <h3>Purchases</h3>
                <p>320</p>
            </div>
            <div class="card">
                <h3>Sales Revenue</h3>
                <p>$25,430</p>
            </div>
            <div class="card">
                <h3>Sales Prediction</h3>
                <p>+12%</p>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts">
            <div class="chart">[ Sales Trend Chart Placeholder ]</div>
            <div class="chart">[ Inventory Analytics Placeholder ]</div>
        </div>

        <!-- Tables -->
        <div class="tables">
            <div class="table">
                <h3>Recent Purchases</h3>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Items</th>
                    </tr>
                    <tr>
                        <td>2026-09-20</td>
                        <td>ABC Supplies</td>
                        <td>150</td>
                    </tr>
                    <tr>
                        <td>2026-09-19</td>
                        <td>XYZ Traders</td>
                        <td>80</td>
                    </tr>
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
                    <tr>
                        <td>2026-09-21</td>
                        <td>John Doe</td>
                        <td>$320</td>
                    </tr>
                    <tr>
                        <td>2026-09-20</td>
                        <td>Jane Smith</td>
                        <td>$450</td>
                    </tr>
                </table>
            </div>
        </div>
    </main>

</body>

</html>