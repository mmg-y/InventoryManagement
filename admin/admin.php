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
                <li class="<?= (!isset($_GET['page']) || $_GET['page'] === 'dashboard') ? 'active' : '' ?>">
                    <a href="?page=dashboard"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'user_roles' ? 'active' : '' ?>">
                    <a href="?page=user_roles"><i class="fa-solid fa-user-shield"></i> User Roles</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'product_inventory' ? 'active' : '' ?>">
                    <a href="?page=product_inventory"><i class="fa-solid fa-boxes-stacked"></i> Products & Inventory</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'supplier_purchases' ? 'active' : '' ?>">
                    <a href="?page=supplier_purchases"><i class="fa-solid fa-truck"></i> Supplier Purchases</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'sales_record' ? 'active' : '' ?>">
                    <a href="?page=sales_record"><i class="fa-solid fa-receipt"></i> Sales Record</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'analytics_report' ? 'active' : '' ?>">
                    <a href="?page=analytics_report"><i class="fa-solid fa-chart-pie"></i> Analytics & Reports</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'sales_prediction' ? 'active' : '' ?>">
                    <a href="?page=sales_prediction"><i class="fa-solid fa-magnifying-glass-chart"></i> Sales Prediction</a>
                </li>
            </ul>
        </div>

        <ul class="menu bottom-menu">
            <li class="<?= ($_GET['page'] ?? '') === 'settings' ? 'active' : '' ?>">
                <a href="?page=settings"><i class="fa-solid fa-gear"></i> Settings</a>
            </li>
        </ul>
    </aside>

    <main class="main">

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

        <div class="page-content">
            <?php
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                switch ($page) {
                    // case 'user_roles':
                    //     include 'user_roles.php';
                    //     break;
                    case 'product_inventory':
                        include 'product_inventory.php';
                        break;
                    case 'supplier_purchases':
                        include 'supplier_purchases.php';
                        break;
                    case 'sales_record':
                        include 'sales_record.php';
                        break;
                    case 'analytics_report':
                        include 'analytics_report.php';
                        break;
                    case 'sales_prediction':
                        include 'sales_prediction.php';
                        break;
                    case 'settings':
                        include 'settings.php';
                        break;
                    case 'dashboard':
                    default:
                        include 'dashboard.php';
                        break;
                }
            } else {
                include 'dashboard.php';
            }
            ?>
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