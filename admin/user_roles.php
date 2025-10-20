<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../config.php';

// Fetch all users
$sql = "SELECT id, first_name, last_name, contact, email, type, created_at 
        FROM user 
        ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result === false) {
    $dbError = $conn->error;
    $result = null;
}
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

    <?php if (isset($dbError)): ?>
        <div class="alert error">Database error: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>
    <div class="main-content">
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
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php
                        $i = 1;
                        $roleLabels = [
                            'admin' => 'Administrator',
                            'cashier' => 'Cashier',
                            'warehouse_man' => 'Warehouse'
                        ];
                        ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            $role = $row['type'] ?? 'N/A';
                            $roleLabel = $roleLabels[$role] ?? ucfirst(str_replace('_', ' ', $role));
                            ?>
                            <tr>
                                <td><?= $i++; ?></td>
                                <td><?= htmlspecialchars($row['first_name']); ?></td>
                                <td><?= htmlspecialchars($row['last_name']); ?></td>
                                <td><?= htmlspecialchars($row['contact']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= htmlspecialchars($roleLabel); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>