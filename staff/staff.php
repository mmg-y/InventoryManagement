<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "cashier") {
    header("Location: index.php");
    exit;
}

$profile_src = '../uploads/default.png';
if (!empty($_SESSION['profile_pic'])) {
    $profile_src = '../' . ltrim($_SESSION['profile_pic'], '/');
}

include '../config.php';

// Notifications
$notifications = [];

$lowStockItems = $conn->query("SELECT product_name, quantity, threshold FROM product WHERE quantity <= threshold");
while ($row = $lowStockItems->fetch_assoc()) {
    $notifications[] = [
        'message' => "Low stock: {$row['product_name']} ({$row['quantity']} left)",
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
    <title>IMS - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div>
            <div class="logo">
                <img src="../images/logo-bl.png" alt="Logo">
                <span>IMS</span>
            </div>
            <ul class="menu">
                <li class="<?= (!isset($_GET['page']) || $_GET['page'] === 'dashboard') ? 'active' : '' ?>">
                    <a href="?page=dashboard"><i class="fa-solid fa-cart-plus"></i> Dashboard</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'analytics_report' ? 'active' : '' ?>">
                    <a href="?page=analytics_report"><i class="fa-solid fa-chart-pie"></i> Analytics & Reports</a>
                </li>
                <!-- <li class="<?= ($_GET['page'] ?? '') === 'pos' ? 'active' : '' ?>">
                    <a href="?page=pos"><i class="fa-solid fa-cart-plus"></i> POS</a>
                </li> -->
                <li class="<?= ($_GET['page'] ?? '') === 'product_inventory' ? 'active' : '' ?>">
                    <a href="?page=product_inventory"><i class="fa-solid fa-boxes-stacked"></i> Products & Inventory</a>
                </li>
                <li class="<?= ($_GET['page'] ?? '') === 'sales_record' ? 'active' : '' ?>">
                    <a href="?page=sales_record"><i class="fa-solid fa-clipboard"></i> Sales Record</a>
                </li>
            </ul>
        </div>

        <ul class="menu bottom-menu">
            <li class="<?= ($_GET['page'] ?? '') === 'settings' ? 'active' : '' ?>">
                <a href="?page=settings"><i class="fa-solid fa-gear"></i> Settings</a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main">

        <div class="topbar">
            <div class="search">
                <form method="get" action="">
                    <input type="hidden" name="page" value="dashboard">
                    <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </form>
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
            $page = $_GET['page'] ?? 'dashboard';
            switch ($page) {
                // case 'pos':
                //     include 'pos.php';
                //     break;
                case 'product_inventory':
                    include 'product_inventory.php';
                    break;
                case 'sales_record':
                    include 'sales_record.php';
                    break;
                case 'analytics_report':
                    include 'analytics_report.php';
                    break;
                case 'settings':
                    include 'settings.php';
                    break;
                case 'dashboard':
                default:
                    include 'dashboard.php';
                    break;
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
            <span class="close-btn" id="closeProfile">&times;</span>
            <h2>Edit Profile</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?= $_SESSION['success'];
                                            unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= $_SESSION['error'];
                                            unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form action="update_profile_staff.php" method="POST" enctype="multipart/form-data">
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

        if (profilePicInput && profilePreview) {
            profilePicInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    profilePreview.src = URL.createObjectURL(file);
                }
            });
        }

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

        profileMenu.addEventListener("click", (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle("show");
            notificationDropdown.style.display = 'none';
        });

        notificationBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            notificationDropdown.style.display = notificationDropdown.style.display === 'block' ? 'none' : 'block';
            dropdownMenu.classList.remove("show");
        });

        document.addEventListener("click", () => {
            dropdownMenu.classList.remove("show");
            notificationDropdown.style.display = 'none';
        });
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.remove('unread');
                item.classList.add('read');
            });
        });
    </script>


</body>

</html>