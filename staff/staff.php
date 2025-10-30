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
    <style>

        .profile-pic-section {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .profile-pic-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #495057;
            font-size: 1.1rem;
        }

        .profile-pic-options {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .pic-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .pic-option:hover {
            transform: translateY(-3px);
        }

        .pic-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #dee2e6;
            transition: border-color 0.2s;
            background-color: white;
        }

        .pic-option:hover .pic-preview {
            border-color: #4dabf7;
        }

        .pic-option.selected .pic-preview {
            border-color: #339af0;
            box-shadow: 0 0 0 3px rgba(51, 154, 240, 0.25);
        }

        .pic-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .upload-area {
            flex-direction: column;
            background-color: #f1f3f5;
            color: #868e96;
            font-size: 0.8rem;
            text-align: center;
            padding: 5px;
        }

        .upload-area i {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .pic-label {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #495057;
        }

        .selected-preview {
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .selected-preview h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1rem;
            color: #495057;
        }

        .selected-img-container {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }

        .selected-img-container img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #339af0;
        }

        .remove-btn {
            background-color: #fa5252;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 0 auto;
        }

        .remove-btn:hover {
            background-color: #e03131;
        }

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

        .input-row {
            display: flex;
            gap: 15px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
        }

        .modal-content label {
            font-weight: 500;
            font-size: 14px;
            color: #333;
        }

        .modal-content input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            outline: none;
            transition: all 0.3s ease;
        }

        .modal-content input:focus {
            border-color: #339af0;
            box-shadow: 0 0 5px rgba(51, 154, 240, 0.3);
        }

        .save-btn {
            align-self: flex-end;
            background: #088395;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .save-btn:hover {
            background: #066a7a;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .profile-pic-options {
                justify-content: center;
            }
            
            .pic-preview {
                width: 60px;
                height: 60px;
            }
            
            .selected-img-container img {
                width: 100px;
                height: 100px;
            }
            
            .input-row {
                flex-direction: column;
            }
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

                <form action="update_profile_staff.php" method="POST" enctype="multipart/form-data">

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
                            <!-- <button type="button" id="removePicBtn" class="remove-btn">
                                <i class="fa-solid fa-trash"></i> Remove Picture
                            </button> -->
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

                    <div class="input-group">
                        <label>Contact</label>
                        <input type="text" name="contact" value="<?= htmlspecialchars($_SESSION['contact'] ?? ''); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($_SESSION['username'] ?? ''); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter new password">
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password">
                    </div>

                    <button type="submit" class="save-btn">Save Changes</button>
                </form>
            </div>
        </div>
    </main>

    <div id="toast-container">
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="toast success show">
                <span class="toast-text">Transaction successful!</span>
                <span class="toast-close">&times;</span>
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] == 1): ?>
            <div class="toast error show">
                <span class="toast-text">Transaction failed. Please try again.</span>
                <span class="toast-close">&times;</span>
            </div>
        <?php elseif (isset($_GET['warning']) && $_GET['warning'] == 1): ?>
            <div class="toast warning show">
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
                    profilePicInput.value = ''; // Clear file input
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

            const toasts = document.querySelectorAll(".toast.show");

            toasts.forEach((toast, index) => {
                const delay = 3000 + index * 300;
                const closeBtn = toast.querySelector(".toast-close");


                setTimeout(() => toast.classList.add("hide"), delay);
                setTimeout(() => toast.remove(), delay + 500);

                closeBtn.addEventListener("click", () => {
                    toast.classList.add("hide");
                    setTimeout(() => toast.remove(), 400);
                });

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