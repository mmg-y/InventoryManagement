<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "bodegero") {
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
</head>

<body>

    <aside class="sidebar">
        <h2>Bodegero</h2>
        <ul class="menu">
            <li class="active"><i class="fa-solid fa-truck-ramp-box"></i>Dashboard</li>
            <li><i class="fa-solid fa-boxes-stacked"></i>Product & Inventory</li>
            <li><i class="fa-solid fa-shop"></i>Supplier Purchases</li>
        </ul>
        <div class="settings">
            <li><i class="fa-solid fa-gear"></i> Settings</li>
        </div>
    </aside>

    <main class="main">
        <h1 class="page-title">Bodegero Dashboard</h1>
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
                    <span><h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h1></span>
                    <i class="fa-solid fa-chevron-down chevron"></i>

                    <div class="dropdown" id="dropdownMenu">
                        <a href="#profile" id="profileLink">Manage Account</a>
                        <a href="../logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <section class="content">
            <div class="cards">
                <div class="card"><h3>Total Products</h3><p>120</p></div>
                <div class="card"><h3>Inventory Value</h3><p>₱250,000</p></div>
                <div class="card"><h3>Pending Purchases</h3><p>8</p></div>
                <div class="card"><h3>Completed Purchases</h3><p>150</p></div>
            </div>

            <div class="charts">
                <div class="chart"><h3>Stock Levels</h3><canvas id="stockChart"></canvas></div>
                <div class="chart"><h3>Purchases Overview</h3><canvas id="purchaseChart"></canvas></div>
            </div>

            <div class="tables">
                <div class="table">
                    <h3>Recent Activity</h3>
                    <table>
                        <thead><tr><th>Date</th><th>Activity</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr><td>2025-09-20</td><td>Purchased from Supplier A</td><td>Completed</td></tr>
                            <tr><td>2025-09-19</td><td>Added new product</td><td>Success</td></tr>
                            <tr><td>2025-09-18</td><td>Inventory updated</td><td>Success</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Change Profile Modal -->
    <div id="profileModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <span class="close-btn" id="closeProfile" aria-label="Close">&times;</span>
            <h2>Edit Profile</h2>

            <!-- show messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
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
        // Charts
        new Chart(document.getElementById('stockChart'), {
            type: 'bar',
            data: { labels: ['Item A','Item B','Item C','Item D','Item E'], datasets: [{label:'Stock Level',data:[30,50,20,15,40]}]}
        });
        new Chart(document.getElementById('purchaseChart'), {
            type: 'line',
            data: { labels: ['Jan','Feb','Mar','Apr','May'], datasets: [{label:'Purchases',data:[5,8,6,10,7]}]}
        });

        // Profile dropdown toggle
        const profile = document.getElementById("profileMenu");
        profile.addEventListener("click", () => { profile.classList.toggle("active"); });
        document.addEventListener("click", (e) => { if (!profile.contains(e.target)) profile.classList.remove("active"); });

        // Modal
        const profileLink = document.querySelector('#dropdownMenu a[href="#profile"]');
        const profileModal = document.getElementById('profileModal');
        const closeProfile = document.getElementById('closeProfile');
        const profilePicInput = document.getElementById('profilePicInput');
        const profilePreview = document.getElementById('profilePreview');

        if (profileLink) {
            profileLink.addEventListener('click', (e) => {
                e.preventDefault();
                profileModal.style.display = 'flex';
                profileModal.setAttribute('aria-hidden','false');
            });
        }
        closeProfile.addEventListener('click', () => {
            profileModal.style.display = 'none';
            profileModal.setAttribute('aria-hidden','true');
        });
        window.addEventListener('click', (e) => {
            if (e.target === profileModal) {
                profileModal.style.display = 'none';
                profileModal.setAttribute('aria-hidden','true');
            }
        });
        if (profilePicInput && profilePreview) {
            profilePicInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) profilePreview.src = URL.createObjectURL(file);
            });
        }

        // auto-open if server requested
        <?php if (!empty($_SESSION['open_profile_modal'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                profileModal.style.display = 'flex';
                profileModal.setAttribute('aria-hidden','false');
            });
        <?php unset($_SESSION['open_profile_modal']); endif; ?>
    </script>

</body>
</html>
