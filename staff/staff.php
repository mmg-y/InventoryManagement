<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "staff") {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/staff.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <aside class="sidebar">
        <h2>Staff</h2>
        <ul class="menu">
            <li class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</li>
            <li><i class="fa-solid fa-cart-plus"></i>POS</li>
            <li><i class="fa-solid fa-truck-ramp-box"></i>Products & Inventory</li>
            <li><i class="fa-solid fa-clipboard"></i>Sales Record</li>
            <li><i class="fa-solid fa-chart-pie"></i>Analytics & Reports</li>
        </ul>
        <div class="settings">
            <li><i class="fa-solid fa-gear"></i> Settings</li>
        </div>
    </aside>

    <main class="main-content">
        <h1 class="page-title">Staff Dashboard</h1>

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
                        <a href="#profile" id="profileLink">Manage Profile</a>
                        <a href="../logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <section class="dashboard">
            <!-- Dashboard Cards -->
            <div class="stats">
                <div class="card"><h3>Today’s Sales</h3><p>₱5,200</p></div>
                <div class="card"><h3>Total Transactions</h3><p>28</p></div>
                <div class="card"><h3>Monthly Sales</h3><p>₱48,900</p></div>
                <div class="card"><h3>Avg Transaction</h3><p>₱185</p></div>
            </div>

            <div class="chart card">
                <h3>Weekly Sales (Personal)</h3>
                <canvas id="salesChart"></canvas>
            </div>

            <div class="card">
                <h3>Recent Transactions</h3>
                <table>
                    <thead><tr><th>Date</th><th>Invoice #</th><th>Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Sept 22, 2025</td><td>#TX1023</td><td>₱250</td></tr>
                        <tr><td>Sept 22, 2025</td><td>#TX1024</td><td>₱480</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3>Top Selling Products</h3>
                <ul>
                    <li>Product A - 15 sold</li>
                    <li>Product B - 12 sold</li>
                    <li>Product C - 10 sold</li>
                </ul>
            </div>

            <div class="card">
                <h3>Notifications</h3>
                <ul>
                    <li>⚠ Low stock: Item #103 (2 left)</li>
                    <li>✅ Sale recorded successfully</li>
                    <li>📢 System maintenance tomorrow 10PM</li>
                </ul>
            </div>
        </section>
    </main>

    <!-- Manage Profile Modal -->
    <div id="profileModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <span class="close-btn" id="closeProfile">&times;</span>
            <h2>Edit Profile</h2>

            <!-- messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
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
        // Chart
        const ctx = document.getElementById('salesChart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
                datasets: [{label:'Sales (₱)', data:[500,800,650,1200,900,1500,1100]}]
            }
        });

        // Profile dropdown
        const profile = document.getElementById("profileMenu");
        profile.addEventListener("click", () => profile.classList.toggle("active"));
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
