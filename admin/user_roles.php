<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../config.php';

// Handle archive request BEFORE any HTML output
if (isset($_POST['archive_user'])) {
    $user_id = (int)$_POST['user_id'];

    $stmt = $conn->prepare("UPDATE user SET archived=1 WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $success = $stmt->affected_rows > 0;
    $stmt->close();

    // Redirect to admin.php?page=user_roles instead of $_SERVER['PHP_SELF']
    header("Location: admin.php?page=user_roles&archived=" . ($success ? "1" : "0"));
    exit;
}


// Fetch users
$sql = "SELECT id, first_name, last_name, contact, email, type, archived FROM user";
$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Roles</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <div class="page-title">User Roles</div>

    <!-- Alert -->
    <?php if (isset($_GET['archived'])): ?>
        <div class="alert <?= $_GET['archived'] == 1 ? 'success' : 'error' ?>">
            <?= $_GET['archived'] == 1 ? 'User archived successfully.' : 'Failed to archive user.' ?>
        </div>
    <?php endif; ?>

    <div class="table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php $i = 1;
                    while ($row = $result->fetch_assoc()): ?>
                        <tr class="<?= $row['archived'] ? 'archived' : '' ?>">
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['first_name']); ?></td>
                            <td><?= htmlspecialchars($row['last_name']); ?></td>
                            <td><?= htmlspecialchars($row['contact']); ?></td>
                            <td><?= htmlspecialchars($row['email']); ?></td>
                            <td><?= htmlspecialchars($row['type']); ?></td>
                            <td class="action-cell">
                                <?php if (!$row['archived'] && in_array($row['type'], ['staff', 'bodegero'])): ?>
                                    <button type="button"
                                        class="archive-icon-btn open-archive-modal"
                                        data-id="<?= $row['id'] ?>"
                                        title="Archive Account">
                                        <i class="fa fa-archive"></i>
                                    </button>
                                <?php elseif ($row['archived']): ?>
                                    <i class="fa fa-archive archived-label" title="Archived"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No users found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Archive Modal -->
    <div id="archiveModal" class="modal">
        <div class="modal-content">
            <h2>Archive Account</h2>
            <p>Are you sure you want to archive this account?</p>
            <form method="POST" id="archiveForm">
                <input type="hidden" name="user_id" id="archive_user_id">
                <button type="submit" name="archive_user" class="yes-btn">Yes, Archive</button>
                <button type="button" class="cancel-btn" id="cancelArchive" style="background:#ccc;color:#333;">Cancel</button>
            </form>
        </div>
    </div>

</body>

</html>

<script>
    const archiveModal = document.getElementById('archiveModal');
    const archiveUserId = document.getElementById('archive_user_id');
    const cancelArchive = document.getElementById('cancelArchive');

    document.querySelectorAll('.open-archive-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            archiveUserId.value = btn.dataset.id;
            archiveModal.style.display = 'flex';
        });
    });

    cancelArchive.onclick = () => {
        archiveModal.style.display = 'none';
    };

    window.onclick = (e) => {
        if (e.target == archiveModal) archiveModal.style.display = 'none';
    };
</script>