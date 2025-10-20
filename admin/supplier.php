<?php
include '../config.php';

//handdling of form sumbission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $supplier_type = $_POST['supplier_type'];

    $stmt = $conn->prepare("INSERT INTO supplier (name, contact, email, supplier_type, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssss", $name, $contact, $email, $supplier_type);
    $stmt->execute();
    $stmt->close();

    header("Location: admin.php?page=supplier&success=Supplier added successfully");
    exit;
}

$result = $conn->query("SELECT * FROM supplier ORDER BY created_at DESC");
$suppliers = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="../css/supplier.css">

<div class="supplier-container">
    <h1 class="page-title">Suppliers</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert success"><?= htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Add New Supplier</h2>
        <form method="POST">
            <label>Supplier Name</label>
            <input type="text" name="name" required>

            <label>Contact</label>
            <input type="text" name="contact" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Supplier Type</label>
            <select name="supplier_type" required>
                <option value="Raw Materials">Raw Materials</option>
                <option value="Packaging">Packaging</option>
                <option value="Logistics">Logistics</option>
                <option value="Other">Other</option>
            </select>

            <button type="submit" name="add_supplier" class="save-btn">Add Supplier</button>
        </form>
    </div>

    <div class="table">
        <h2>Supplier List</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Supplier Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Created</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= $s['supplier_id'] ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['contact']) ?></td>
                        <td><?= htmlspecialchars($s['email']) ?></td>
                        <td><?= htmlspecialchars($s['supplier_type']) ?></td>
                        <td><?= $s['created_at'] ?></td>
                        <td><?= $s['updated_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>