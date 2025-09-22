<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "admin") {
    header("Location: index.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Admin Page</title>
    <link rel="stylesheet" href="../css/admin.css">
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
                <li><i class="fa-solid fa-magnifying-glass-chart"></i> Sales Prediction</li>
            </ul>
        </div>

        <ul class="menu bottom-menu">
            <li><i class="fa-solid fa-gear"></i> Settings</li>
        </ul>
    </aside>

    <main class="main">
        <h1 class="page-title">Admin Dashboard</h1>
        <div class="topbar">
            <div class="search">
                <input type="text" placeholder="Search...">
            </div>
            <div class="topbar-right">
                <button class="icon-btn">
                    <i class="fa-solid fa-bell" style="color: #102c57;"></i>
                    <span class="badge">3</span>
                </button>
                <div class="icon">⚙</div>
                <div class="profile" id="profileMenu">
                    <img src="https://via.placeholder.com/40" alt="Profile" class="profile-img">
                    <span>
                        <h1>Welcome, <?= $_SESSION['username']; ?>!</h1>
                    </span>
                    <i class="fa-solid fa-chevron-down chevron"></i>

                    <div class="dropdown" id="dropdownMenu">
                        <a href="#">Profile</a>
                        <a href="../logout.php">Logout</a>
                    </div>
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