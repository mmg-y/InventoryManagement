<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// require login + admin type
if (empty($_SESSION['username']) || ($_SESSION['type'] ?? '') !== "admin") {
    header("Location: index.php");
    exit;
}

include_once '../config.php';

if (!isset($conn) || !$conn) {
    die("Database connection not available.");
}

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


$allowed_pages = [
    'dashboard',
    'add_user',
    'user_roles',
    'supplier',
    'sales_record',
    'analytics_report',
    'sales_prediction',
    'settings',
    'category',
    'retail'
];

$page_to_include = 'dashboard';
if (!empty($_GET['page'])) {
    $requested = basename($_GET['page']);
    if (in_array($requested, $allowed_pages, true)) {
        $page_to_include = $requested;
    } else {
        $page_to_include = 'dashboard';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Admin Page</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" href="../images/logo-teal.png" type="images/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    #profileModal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        justify-content: center;
        align-items: center;
        overflow: auto;
        z-index: 9999;
    }

    #profileModal .modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 30px rgba(0,0,0,0.2);
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
                    </div>
                </div>
                <!-- <button class="collapse-btn" id="collapseBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button> -->
            </div>
        </div>

        <div class="sidebar-content">
            <div class="sidebar-group">
                <p class="sidebar-label">Admin</p>
                <ul class="menu">
                    <li class="<?= ($page_to_include === 'dashboard') ? 'active' : '' ?>">
                        <a href="?page=dashboard" title="Dashboard"><i class="fa-solid fa-chart-line"></i> <span>Dashboard</span></a>
                    </li>

                    <li class="submenu <?= ($page_to_include === 'add_user' || $page_to_include === 'user_roles') ? 'open' : '' ?>">
                        <a class="submenu-toggle" title="User Management">
                            <i class="fa-solid fa-users"></i> <span>User Management</span>
                            <i class="fa-solid fa-chevron-down arrow"></i>
                        </a>
                        <ul class="submenu-items">
                            <li class="<?= ($page_to_include === 'add_user') ? 'active' : '' ?>">
                                <a href="?page=add_user"><i class="fa-solid fa-user-plus"></i> <span>Add User</span></a>
                            </li>
                            <li class="<?= ($page_to_include === 'user_roles') ? 'active' : '' ?>">
                                <a href="?page=user_roles"><i class="fa-solid fa-user-shield"></i> <span>User Roles</span></a>
                            </li>
                        </ul>
                    </li>

                    <li class="submenu <?= ($page_to_include === 'category' || $page_to_include === 'retail_values') ? 'open' : '' ?>">
                        <a class="submenu-toggle" title="Product Settings">
                            <i class="fa-solid fa-boxes-stacked"></i> <span>Product Settings</span>
                            <i class="fa-solid fa-chevron-down arrow"></i>
                        </a>
                        <ul class="submenu-items">
                            <li class="<?= ($page_to_include === 'category') ? 'active' : '' ?>">
                                <a href="?page=category"><i class="fa-solid fa-tags"></i> <span>Manage Categories</span></a>
                            </li>
                            <li class="<?= ($page_to_include === 'retail') ? 'active' : '' ?>">
                                <a href="?page=retail"><i class="fa-solid fa-money-bill-wave"></i> <span>Retail Management </span></a>
                            </li>
                        </ul>
                    </li>

                    <li class="<?= ($page_to_include === 'supplier') ? 'active' : '' ?>">
                        <a href="?page=supplier" title="Add Supplier"><i class="fa-solid fa-user-plus"></i> <span>Supplier</span></a>
                    </li>

                    <li class="<?= ($page_to_include === 'sales_record') ? 'active' : '' ?>">
                        <a href="?page=sales_record" title="Sales Record"><i class="fa-solid fa-receipt"></i> <span>Sales Record</span></a>
                    </li>

                    <li class="<?= ($page_to_include === 'analytics_report') ? 'active' : '' ?>">
                        <a href="?page=analytics_report" title="Analytics Report"><i class="fa-solid fa-chart-pie"></i> <span>Analytics & Reports</span></a>
                    </li>
                </ul>
            </div>
        </div>

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

                <form action="update_profile.php" method="POST" enctype="multipart/form-data">

                    <div class="profile-pic-section">
                        <h3>Profile Picture</h3>
                        <div class="profile-pic-options">

                            <div class="pic-option current-pic">
                                <div class="pic-preview">
                                    <?php
                                    $modal_pic = '../uploads/default.png';
                                    if (!empty($_SESSION['profile_pic'])) $modal_pic = '../' . ltrim($_SESSION['profile_pic'], '/');
                                    ?>
                                    <img src="<?= htmlspecialchars($modal_pic); ?>" alt="Current Profile" id="profilePreview">
                                </div>
                                <span class="pic-label">Current</span>
                            </div>
                            
                            <div class="pic-option upload-pic">
                                <div class="pic-preview upload-area" id="uploadArea">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>Upload New</span>
                                </div>
                                <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" hidden>
                                <span class="pic-label">Upload</span>
                            </div>
                            
                        </div>
                        
                        <div class="selected-preview">
                            <h4>Selected Picture:</h4>
                            <div class="selected-img-container">
                                <img src="<?= htmlspecialchars($modal_pic); ?>" alt="Selected Profile" id="selectedPreview">
                            </div>
                            <button type="button" id="removePicBtn" class="remove-btn">
                                <i class="fa-solid fa-trash"></i> Remove Picture
                            </button>
                        </div>
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
        document.addEventListener("DOMContentLoaded", () => {
            const manageProfile = document.getElementById("manageProfile");
            const profileModal = document.getElementById("profileModal");
            const closeProfile = document.getElementById("closeProfile");

            if (manageProfile && profileModal) {
                manageProfile.addEventListener("click", (e) => {
                    e.preventDefault();
                    profileModal.style.display = "flex";
                    profileModal.setAttribute("aria-hidden", "false");
                });
            }

            if (closeProfile && profileModal) {
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

            const profilePicInput = document.getElementById('profilePicInput');
            const uploadArea = document.getElementById('uploadArea');
            const profilePreview = document.getElementById('profilePreview');
            const selectedPreview = document.getElementById('selectedPreview');
            const removePicBtn = document.getElementById('removePicBtn');
            const defaultPicOptions = document.querySelectorAll('.default-pic .pic-preview img');
            const picOptions = document.querySelectorAll('.pic-option');

            if (uploadArea) {
                uploadArea.addEventListener('click', () => {
                    profilePicInput.click();
                });
            }

            if (profilePicInput) {
                profilePicInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const imageUrl = e.target.result;
                            updateSelectedPicture(imageUrl);
                            selectOption(uploadArea.closest('.pic-option'));
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            defaultPicOptions.forEach(img => {
                img.addEventListener('click', () => {
                    updateSelectedPicture(img.getAttribute('data-src'));
                    selectOption(img.closest('.pic-option'));
                });
            });

            const currentPicOption = document.querySelector('.current-pic');
            if (currentPicOption) {
                currentPicOption.addEventListener('click', () => {
                    updateSelectedPicture(profilePreview.src);
                    selectOption(currentPicOption);
                });
            }

            if (removePicBtn) {
                removePicBtn.addEventListener('click', () => {
                    const defaultPic = '../uploads/default.png';
                    updateSelectedPicture(defaultPic);
                    selectOption(document.querySelector('.default-pic'));
                    profilePicInput.value = ''; 
                });
            }

            function updateSelectedPicture(src) {
                if (selectedPreview) selectedPreview.src = src;
                if (profilePreview) profilePreview.src = src;
            }

            function selectOption(optionElement) {

                picOptions.forEach(opt => opt.classList.remove('selected'));

                if (optionElement) optionElement.classList.add('selected');
            }

            if (currentPicOption) {
                selectOption(currentPicOption);
            }

            <?php if (!empty($_SESSION['open_profile_modal'])): ?>
                const pm = document.getElementById('profileModal');
                if (pm) {
                    pm.style.display = 'flex';
                    pm.setAttribute('aria-hidden', 'false');
                }
            <?php unset($_SESSION['open_profile_modal']);
            endif; ?>

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

            const sidebar = document.querySelector(".sidebar");
            const profileMenu = document.getElementById("profileMenu");
            const toggleBtn = profileMenu?.querySelector(".profile-toggle");
            const dropdown = profileMenu?.querySelector(".profile-dropdown");
            const profileInfo = profileMenu?.querySelector(".profile-info");

            if (profileMenu && dropdown && profileInfo) {
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
            }

            const submenuToggles = document.querySelectorAll(".submenu-toggle");

            submenuToggles.forEach(toggle => {
                toggle.addEventListener("click", (e) => {
                    e.preventDefault();

                    const parentLi = toggle.closest(".submenu");

                    document.querySelectorAll(".submenu.open").forEach(menu => {
                        if (menu !== parentLi) menu.classList.remove("open");
                    });

                    parentLi.classList.toggle("open");
                });
            });
        });
    </script>

</body>

</html>