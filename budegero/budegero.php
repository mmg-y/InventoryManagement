<?php
session_start();
include "../config.php";

if (!isset($_SESSION['username']) || $_SESSION['type'] !== "warehouse_man") {
    header("Location: ../index.php");
    exit;
}

$profile_src = '../uploads/default.png';
if (!empty($_SESSION['profile_pic'])) {
    $profile_src = '../' . ltrim($_SESSION['profile_pic'], '/');
}

$notifications = [];

$lowStockSql = "SELECT product_name, total_quantity AS quantity, threshold FROM product WHERE total_quantity <= threshold";
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

$unreadCount = count($notifications);

$page_to_include = $_GET['page'] ?? 'dashboard';
?>
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Bodegero Dashboard</title>
    <link rel="icon" href="images/logo-teal.png" type="images/png">
    <link rel="stylesheet" href="../css/budegero.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Remove blue underline from sidebar links */
        .sidebar a {
            text-decoration: none;
            color: inherit;
        }

        .sidebar a:visited,
        .sidebar a:active {
            color: inherit;
        }
    </style>
</head>

<body>
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
                <button class="collapse-btn" id="collapseBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
            </div>
        </div>

        <div class="sidebar-content">
            <div class="sidebar-group">
                <p class="sidebar-label">Warehouse Man</p>
                <ul class="menu">
                    <li class="<?= (!isset($_GET['page']) || $_GET['page'] === 'dashboard') ? 'active' : '' ?>">
                        <a href="?page=dashboard" title="Dashboard"><i class="fa-solid fa-truck-ramp-box"></i> <span>Dashboard</span></a>
                    </li>
                    <li class="<?= ($_GET['page'] ?? '') === 'product_inventory' ? 'active' : '' ?>">
                        <a href="?page=product_inventory" title="Product Inventory"><i class="fa-solid fa-boxes-stacked"></i><span>Products & Inventory</span></a>
                    </li>
                    <li class="<?= ($_GET['page'] ?? '') === 'supplier_purchases' ? 'active' : '' ?>">
                        <a href="?page=supplier_purchases" title="Supplier Purchases"><i class="fa-solid fa-truck"></i><span>Supplier Purchases</span></a>
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
                            <a href="<?= htmlspecialchars($note['link']) ?>" class="notification-item <?= empty($note['read']) ? 'unread' : '' ?>">
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

        <!-- Profile Modal -->
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

                <form action="update_profile_budegero.php" method="POST" enctype="multipart/form-data">
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

    </main>


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
        document.addEventListener("DOMContentLoaded", () => {
            // === SIDEBAR SUBMENU TOGGLE ===
            const submenuToggles = document.querySelectorAll(".submenu-toggle");

            submenuToggles.forEach(toggle => {
                toggle.addEventListener("click", (e) => {
                    e.preventDefault();

                    const parentLi = toggle.closest(".submenu");

                    // Close other open submenus (optional)
                    document.querySelectorAll(".submenu.open").forEach(menu => {
                        if (menu !== parentLi) menu.classList.remove("open");
                    });

                    // Toggle current submenu
                    parentLi.classList.toggle("open");
                });
            });
        });
    </script>
</body>

</html>