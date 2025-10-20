<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "warehouse_man") {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Bodegero Dashboard</title>
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
        <div class="logo">
            <img src="../images/logo-b.png" alt="Logo">
            <span>IMS</span>
        </div>
        <ul class="menu">
            <li class="<?= (!isset($_GET['page']) || $_GET['page'] === 'dashboard') ? 'active' : '' ?>">
                <a href="?page=dashboard"><i class="fa-solid fa-truck-ramp-box"></i> Dashboard</a>
            </li>
            <li class="<?= ($_GET['page'] ?? '') === 'product_inventory' ? 'active' : '' ?>">
                <a href="?page=product_inventory"><i class="fa-solid fa-boxes-stacked"></i> Products & Inventory</a>
            </li>
            <li class="<?= ($_GET['page'] ?? '') === 'supplier_purchases' ? 'active' : '' ?>">
                <a href="?page=supplier_purchases"><i class="fa-solid fa-truck"></i> Supplier Purchases</a>
            </li>
        </ul>
    </aside>

    <main class="main">
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
                    <?php
                    $pic = '../uploads/default.png';
                    if (!empty($_SESSION['profile_pic'])) $pic = '../' . ltrim($_SESSION['profile_pic'], '/');
                    ?>
                    <img src="<?= htmlspecialchars($pic); ?>" alt="Profile" class="profile-img" id="profileImage">
                    <span>
                        <h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h1>
                    </span>
                    <i class="fa-solid fa-chevron-down chevron"></i>

                    <div class="dropdown" id="dropdownMenu">
                        <a href="#profile" id="profileLink">Manage Account</a>
                        <a href="../logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT AREA -->
        <div class="page-content">
            <?php
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                switch ($page) {
                    case 'product_inventory':
                        include 'product_inventory.php';
                        break;
                    case 'supplier_purchases':
                        include 'supplier_purchases.php';
                        break;
                    case 'dashboard':
                    default:
                        include 'dashboard.php'; // bodegero-specific dashboard
                        break;
                }
            } else {
                include 'dashboard.php';
            }
            ?>
        </div>
    </main>

    <!-- PROFILE MODAL -->
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

            <form action="update_profile_budegero.php" method="POST" enctype="multipart/form-data" id="profileForm">
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
        const profile = document.getElementById("profileMenu");
        profile.addEventListener("click", () => profile.classList.toggle("active"));
        document.addEventListener("click", (e) => {
            if (!profile.contains(e.target)) profile.classList.remove("active");
        });

        const profileLink = document.getElementById('profileLink');
        const profileModal = document.getElementById('profileModal');
        const closeProfile = document.getElementById('closeProfile');
        const profilePicInput = document.getElementById('profilePicInput');
        const profilePreview = document.getElementById('profilePreview');

        profileLink.addEventListener('click', (e) => {
            e.preventDefault();
            profileModal.style.display = 'flex';
            profileModal.setAttribute('aria-hidden', 'false');
        });

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
                if (file) profilePreview.src = URL.createObjectURL(file);
            });
        }
    </script>
</body>

</html>