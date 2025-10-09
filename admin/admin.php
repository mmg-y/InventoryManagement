<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "admin") {
    header("Location: index.php");
    exit;
}

$profile_src = '../uploads/default.png';
if (!empty($_SESSION['profile_pic'])) {
    $profile_src = '../' . ltrim($_SESSION['profile_pic'], '/');
}

include '../config.php';

$notifications = [];

$lowStockItems = $conn->query("SELECT product_name, quantity, threshold FROM product WHERE quantity <= threshold");
while ($row = $lowStockItems->fetch_assoc()) {
    $notifications[] = [
        'message' => "Low stock: {$row['product_name']} ({$row['quantity']} left)",
        'read' => false,
        'link' => '#'
    ];
}

$pendingPurchases = $conn->query("SELECT COUNT(*) as cnt FROM stock WHERE status='pending'")->fetch_assoc()['cnt'];
if ($pendingPurchases > 0) {
    $notifications[] = [
        'message' => "You have {$pendingPurchases} pending purchase(s).",
        'read' => false,
        'link' => '#'
    ];
}

$unreadCount = 0;
foreach ($notifications as $note) {
    if (!$note['read']) $unreadCount++;
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
            <div class="logo">
                <img src="../images/logo-b.png" alt="Logo">
                <span>IMS</span>
            </div>
            <ul class="menu">
                <li class="<?= (!isset($_GET['page']) || $_GET['page'] === 'dashboard') ? 'active' : '' ?>">
                    <a href="?page=dashboard"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                </li>

                <!-- User Dropdown -->
                <li class="submenu">
                    <a><i class="fa-solid fa-users"></i> User Management <i class="fa-solid fa-chevron-down arrow"></i></a>
                    <ul class="submenu-items">
                        <li class="<?= ($_GET['page'] ?? '') === 'add_user' ? 'active' : '' ?>">
                            <a href="?page=add_user"><i class="fa-solid fa-user-plus"></i> Add User</a>
                        </li>
                        <li class="<?= ($_GET['page'] ?? '') === 'user_roles' ? 'active' : '' ?>">
                            <a href="?page=user_roles"><i class="fa-solid fa-user-shield"></i> User Roles</a>
                        </li>
                    </ul>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'supplier' ? 'active' : '' ?>">
                    <a href="?page=supplier"><i class="fa-solid fa-user-plus"></i> Add Supplier</a>
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

                <div class="notification-wrapper">
                    <button class="icon-btn" id="notificationBtn">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <?php if (empty($notifications)): ?>
                            <div class="notification-item">No notifications</div>
                        <?php else: ?>
                            <?php foreach ($notifications as $note): ?>
                                <a href="<?= htmlspecialchars($note['link']) ?>" class="notification-item <?= $note['read'] ? 'read' : 'unread' ?>">
                                    <?= htmlspecialchars($note['message']) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile" id="profileMenu">
                    <img src="<?= htmlspecialchars($profile_src); ?>" alt="Profile" class="profile-img">
                    <span>
                        <h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h1>
                    </span>
                    <i class="fa-solid fa-chevron-down chevron"></i>

                    <div class="dropdown" id="dropdownMenu">
                        <a href="#profile">Manage Profile</a>
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
                    case 'add_user':
                        include 'add_user.php';
                        break;
                    case 'user_roles':
                        include 'user_roles.php';
                        break;
                    case 'supplier':
                        include 'supplier.php';
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

    <div id="profileModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <span class="close-btn" id="closeProfile" aria-label="Close">&times;</span>
            <h2>Edit Profile</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?= $_SESSION['success'];
                                            unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= $_SESSION['error'];
                                            unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form action="update_profile.php" method="POST" enctype="multipart/form-data" id="profileForm">
                <div class="profile-pic-wrapper">
                    <?php
                    $modal_pic = '../uploads/default.png';
                    if (!empty($_SESSION['profile_pic'])) $modal_pic = '../' . ltrim($_SESSION['profile_pic'], '/');
                    ?>
                    <img src="<?= htmlspecialchars($modal_pic); ?>" alt="Profile" id="profilePreview">
                    <input type="file" name="profile_pic" id="profilePicInput" accept="image/*">
                </div>

                <label>First Name</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" required>

                <label>Last Name</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" required>

                <label>Contact</label>
                <input type="text" name="contact" value="<?= htmlspecialchars($_SESSION['contact'] ?? ''); ?>" required>

                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>

                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($_SESSION['username'] ?? ''); ?>" required>

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter new password">

                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password">

                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>


    <script>
        const profileLink = document.querySelector('#dropdownMenu a[href="#profile"]');
        const profileModal = document.getElementById('profileModal');
        const closeProfile = document.getElementById('closeProfile');
        const profilePicInput = document.getElementById('profilePicInput');
        const profilePreview = document.getElementById('profilePreview');

        if (profileLink) {
            profileLink.addEventListener('click', (e) => {
                e.preventDefault();
                profileModal.style.display = 'flex';
                profileModal.setAttribute('aria-hidden', 'false');
            });
        }

        closeProfile.addEventListener('click', () => {
            profileModal.style.display = 'none';
            profileModal.setAttribute('aria-hidden', 'true');
        });

        window.addEventListener('click', (e) => {
            if (e.target === profileModal) {
                profileModal.style.display = 'none';
                profileModal.setAttribute('aria-hidden', 'true');
            }
        });

        // Live preview profile picture
        if (profilePicInput && profilePreview) {
            profilePicInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    profilePreview.src = URL.createObjectURL(file);
                }
            });
        }

        // auto-open modal if server asked (after submit)
        <?php if (!empty($_SESSION['open_profile_modal'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const pm = document.getElementById('profileModal');
                if (pm) {
                    pm.style.display = 'flex';
                    pm.setAttribute('aria-hidden', 'false');
                }
            });
        <?php unset($_SESSION['open_profile_modal']);
        endif; ?>
    </script>

    <script>
        const profileMenu = document.getElementById("profileMenu");
        const dropdownMenu = document.getElementById("dropdownMenu");
        const notificationBtn = document.getElementById("notificationBtn");
        const notificationDropdown = document.getElementById("notificationDropdown");

        //  PROFILE DROPDOWN TOGGLE
        profileMenu.addEventListener("click", (e) => {
            e.stopPropagation(); // Prevent closing when clicking inside
            dropdownMenu.classList.toggle("show");
            notificationDropdown.style.display = 'none'; // Close notifications if open
        });

        // NOTIFICATIONS DROPDOWN TOGGLE 
        notificationBtn.addEventListener("click", (e) => {
            e.stopPropagation(); // Prevent closing when clicking inside
            notificationDropdown.style.display = notificationDropdown.style.display === 'block' ? 'none' : 'block';
            dropdownMenu.classList.remove("show"); // Close profile dropdown if open
        });

        //  CLOSE DROPDOWNS WHEN CLICKING OUTSIDE 
        document.addEventListener("click", () => {
            dropdownMenu.classList.remove("show");
            notificationDropdown.style.display = 'none';
        });

        //  OPTIONAL: MARK NOTIFICATION AS READ WHEN CLICKED 
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.remove('unread');
                item.classList.add('read');
                // You can also make an AJAX call here to update 'read' in DB
            });
        });
    </script>

    <script>
        // Toggle submenu on click
        document.querySelectorAll(".submenu > a").forEach(menu => {
            menu.addEventListener("click", function(e) {
                e.preventDefault(); // stop page jump if <a href="#">
                this.parentElement.classList.toggle("open");
            });
        });

        // Auto-open submenu if one of its children has class "active"
        document.querySelectorAll(".submenu").forEach(sub => {
            if (sub.querySelector(".submenu-items li.active")) {
                sub.classList.add("open");
            }
        });
    </script>



</body>

</html>