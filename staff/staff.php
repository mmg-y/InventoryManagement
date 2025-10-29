<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "cashier") {
    header("Location: index.php");
    exit;
}

include '../config.php';

$page_to_include = $_GET['page'] ?? 'dashboard';

$default_pic = '../uploads/default.png';
$profile_src = $default_pic;

if (!empty($_SESSION['profile_pic'])) {
    $custom_path = '../' . ltrim($_SESSION['profile_pic'], '/');
    if (file_exists($custom_path)) {
        $profile_src = $custom_path;
    }
}

$notifications = [];

// Low stock items 
$lowStockSql = "
    SELECT p.product_name, COALESCE(SUM(ps.remaining_qty),0) AS quantity, p.threshold
    FROM product p
    LEFT JOIN product_stocks ps ON p.product_id = ps.product_id
    GROUP BY p.product_id
    HAVING quantity <= COALESCE(p.threshold, 0)
";
$lowStockItems = $conn->query($lowStockSql);
if ($lowStockItems && $lowStockItems->num_rows > 0) {
    while ($row = $lowStockItems->fetch_assoc()) {
        $notifications[] = [
            'message' => "Low stock: {$row['product_name']} ({$row['quantity']} left)",
            'read' => false,
            'link' => '#'
        ];
    }
}

// Pending purchases
$pendingQuery = $conn->query("SELECT COUNT(*) AS cnt FROM product_stocks WHERE status='pending'");
$pendingPurchases = 0;
if ($pendingQuery && ($row = $pendingQuery->fetch_assoc())) {
    $pendingPurchases = (int)$row['cnt'];
}

if ($pendingPurchases > 0) {
    $notifications[] = [
        'message' => "You have {$pendingPurchases} pending purchase(s).",
        'read' => false,
        'link' => '#'
    ];
}

// Count unread notifications
$unreadCount = 0;
foreach ($notifications as $note) {
    if ($note['read'] === false) $unreadCount++;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/staff.css">
    <link rel="icon" href="../images/logo-teal.png" type="images/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="top">
                <div class="logo">
                    <div class="team-logo">
                        <img src="../images/logo-white.png" alt="Logo">
                    </div>
                    <div class="team-info">
                        <span class="team-name">MartIQ</span>
                        <!-- <span class="team-plan">Smart sales, Smart Store</span> -->
                    </div>
                </div>
                <!-- <button class="collapse-btn" id="collapseBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button> -->
            </div>
        </div>

        <div class="sidebar-content">
            <div class="sidebar-group">
                <p class="sidebar-label">Cashier</p>
                <ul class="menu">
                    <li class="<?= ($page_to_include === 'dashboard') ? 'active' : '' ?>">
                        <a href="?page=dashboard" title="Dashboard"><i class="fa-solid fa-cart-plus"></i> <span>Dashboard</span></a>
                    </li>
                    <li class="<?= ($page_to_include === 'analytics_report') ? 'active' : '' ?>">
                        <a href="?page=analytics_report"><i class="fa-solid fa-chart-pie"></i><span>Analytics Report</span></a>
                    </li>
                    <li class="<?= ($page_to_include === 'product_inventory') ? 'active' : '' ?>">
                        <a href="?page=product_inventory"><i class="fa-solid fa-boxes-stacked"></i> <span>Products & Inventory</span></a>
                    </li>
                    <li class="<?= ($page_to_include === 'sales_record') ? 'active' : '' ?>">
                        <a href="?page=sales_record"><i class="fa-solid fa-clipboard"></i> <span>Sales Record</span></a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer" id="profileMenu">
            <div class="profile-info">
                <img src="<?= htmlspecialchars($profile_src); ?>" alt="User" class="profile-img" />
                <div class="details">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'admin'); ?></span>
                    <span class="user-email"><?= htmlspecialchars($_SESSION['email'] ?? 'admin@gmail.com'); ?></span>
                </div>
            </div>

            <button class="profile-toggle" aria-label="Profile Menu">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>

            <div class="profile-dropdown">
                <div class="profile-header">
                    <img src="<?= htmlspecialchars($profile_src); ?>" alt="User" class="profile-img" />
                    <div class="info">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'admin'); ?></span>
                        <span class="user-email"><?= htmlspecialchars($_SESSION['email'] ?? 'admin@gmail.com'); ?></span>
                    </div>
                </div>
                <ul class="menu">
                    <li><a href="#profile" id="manageProfile"><i class="fa-solid fa-user"></i> Manage Profile</a></li>
                    <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
        <div class="topbar">
            <div class="notification-wrapper" id="notifWrapper">
                <button class="icon-btn" id="notifBtn">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge" id="notifCount"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="notification-dropdown" id="notifDropdown">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $note): ?>
                            <a href="<?= htmlspecialchars($note['link']) ?>" class="notification-item <?= $note['read'] === false ? 'unread' : '' ?>">
                                <?= htmlspecialchars($note['message']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notification-item">No notifications.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="page-content">
            <?php
            $include_file = $page_to_include . '.php';
            $include_path = __DIR__ . '/' . $include_file;

            if (file_exists($include_path)) {
                include $include_path;
            } else {
                include __DIR__ . '/dashboard.php';
            }
            ?>
        </div>



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

                    <div class="input-row">
                        <div class="input-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

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


    <div id="toast-container">
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="toast success show">
                <!-- <span class="toast-icon">✅</span> -->
                <span class="toast-text">Transaction successful!</span>
                <span class="toast-close">&times;</span>
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] == 1): ?>
            <div class="toast error show">
                <!-- <span class="toast-icon">❌</span> -->
                <span class="toast-text">Transaction failed. Please try again.</span>
                <span class="toast-close">&times;</span>
            </div>
        <?php elseif (isset($_GET['warning']) && $_GET['warning'] == 1): ?>
            <div class="toast warning show">
                <!-- <span class="toast-icon">⚠️</span> -->
                <span class="toast-text">Warning: Please check your input or stock levels.</span>
                <span class="toast-close">&times;</span>
            </div>
        <?php endif; ?>
    </div>




    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const manageProfile = document.getElementById("manageProfile");
            const profileModal = document.getElementById("profileModal");
            const closeProfile = document.getElementById("closeProfile");

            if (manageProfile) {
                manageProfile.addEventListener("click", (e) => {
                    e.preventDefault();
                    profileModal.style.display = "flex";
                    profileModal.setAttribute("aria-hidden", "false");
                });
            }

            if (closeProfile) {
                closeProfile.addEventListener("click", () => {
                    profileModal.style.display = "none";
                    profileModal.setAttribute("aria-hidden", "true");
                });
            }

            window.addEventListener("click", (e) => {
                if (e.target === profileModal) {
                    profileModal.style.display = "none";
                    profileModal.setAttribute("aria-hidden", "true");
                }
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const profileLink = document.querySelector('#dropdownMenu a[href="#profile"]');
            const profileModal = document.getElementById('profileModal');
            const closeProfile = document.getElementById('closeProfile');
            const profilePicInput = document.getElementById('profilePicInput');
            const profilePreview = document.getElementById('profilePreview');

            if (profileLink && profileModal) {
                profileLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    profileModal.style.display = 'flex';
                    profileModal.setAttribute('aria-hidden', 'false');
                });
            }

            if (closeProfile && profileModal) {
                closeProfile.addEventListener('click', () => {
                    profileModal.style.display = 'none';
                    profileModal.setAttribute('aria-hidden', 'true');
                });
            }

            window.addEventListener('click', (e) => {
                if (e.target === profileModal) {
                    profileModal.style.display = 'none';
                    profileModal.setAttribute('aria-hidden', 'true');
                }
            });

            if (profilePicInput && profilePreview) {
                profilePicInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) profilePreview.src = URL.createObjectURL(file);
                });
            }

            <?php if (!empty($_SESSION['open_profile_modal'])): ?>
                const pm = document.getElementById('profileModal');
                if (pm) {
                    pm.style.display = 'flex';
                    pm.setAttribute('aria-hidden', 'false');
                }
            <?php unset($_SESSION['open_profile_modal']);
            endif; ?>
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // === NOTIFICATION DROPDOWN ===
            const notifBtn = document.getElementById("notifBtn");
            const notifWrapper = document.getElementById("notifWrapper");

            if (notifBtn && notifWrapper) {
                notifBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    notifWrapper.classList.toggle("active");
                });

                document.addEventListener("click", (e) => {
                    if (!notifWrapper.contains(e.target)) {
                        notifWrapper.classList.remove("active");
                    }
                });
            }
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const collapseBtn = document.getElementById("collapseBtn");
            const sidebar = document.querySelector(".sidebar");
            const main = document.querySelector(".main");
            const topbar = document.querySelector(".topbar");

            if (collapseBtn && sidebar) {
                collapseBtn.addEventListener("click", () => {
                    sidebar.classList.toggle("collapsed");
                });
            }
        });
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const sidebar = document.querySelector(".sidebar");
            const profileMenu = document.getElementById("profileMenu");
            const toggleBtn = profileMenu?.querySelector(".profile-toggle");
            const dropdown = profileMenu?.querySelector(".profile-dropdown");
            const profileInfo = profileMenu?.querySelector(".profile-info");

            if (!profileMenu || !dropdown || !profileInfo) return;

            const toggleDropdown = (e) => {
                e.stopPropagation();
                profileMenu.classList.toggle("open");
                const isOpen = profileMenu.classList.contains("open");
                dropdown.style.display = isOpen ? "block" : "none";

                if (sidebar.classList.contains("collapsed")) {
                    dropdown.style.position = "fixed";
                    dropdown.style.left = "90px";
                    dropdown.style.bottom = "80px";
                } else {
                    dropdown.style.position = "fixed";
                    dropdown.style.left = "260px";
                    dropdown.style.bottom = "80px";
                }

                dropdown.style.right = "auto";
                dropdown.style.zIndex = "5000";
            };

            profileInfo.addEventListener("click", toggleDropdown);

            if (toggleBtn) {
                toggleBtn.addEventListener("click", toggleDropdown);
            }

            document.addEventListener("click", (e) => {
                if (!profileMenu.contains(e.target)) {
                    profileMenu.classList.remove("open");
                    dropdown.style.display = "none";
                }
            });
        });
    </script>



    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toasts = document.querySelectorAll(".toast.show");

            toasts.forEach((toast, index) => {
                const delay = 3000 + index * 300;
                const closeBtn = toast.querySelector(".toast-close");

                // Auto hide after delay
                setTimeout(() => toast.classList.add("hide"), delay);
                setTimeout(() => toast.remove(), delay + 500);

                // Click to dismiss instantly
                closeBtn.addEventListener("click", () => {
                    toast.classList.add("hide");
                    setTimeout(() => toast.remove(), 400);
                });

                // Click anywhere on toast to dismiss
                toast.addEventListener("click", (e) => {
                    if (!e.target.classList.contains("toast-close")) {
                        toast.classList.add("hide");
                        setTimeout(() => toast.remove(), 400);
                    }
                });
            });
        });
    </script>

</body>

</html>