<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    // Collect supplier info
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $supplier_type = trim($_POST['supplier_type']);
    $product_ids = $_POST['product_ids'] ?? [];

    // Insert new supplier
    $stmt = $conn->prepare("INSERT INTO supplier (name, contact, email, supplier_type, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssss", $name, $contact, $email, $supplier_type);
    $stmt->execute();
    $supplier_id = $stmt->insert_id;
    $stmt->close();

    // Link products
    if (!empty($product_ids)) {
        $stmt = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        foreach ($product_ids as $pid) {
            $stmt->bind_param("ii", $pid, $supplier_id);
            $stmt->execute();
        }
        $stmt->close();
    }

    header("Location: admin.php?page=supplier&success=New supplier added successfully");
    exit;
}

// Fetch products
$products = $conn->query("SELECT product_id, product_name FROM product ORDER BY product_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="../css/supplier.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="supplier-container">
    <h1 class="page-title">Suppliers</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert success"><?= htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Add New Supplier</h2>
        <form method="POST">

            <label>Supplier Name</label>
            <input type="text" name="name" placeholder="Enter supplier name" required>

            <label>Contact</label>
            <input type="text" name="contact" placeholder="Enter contact number" required>

            <label>Email</label>
            <input type="email" name="email" placeholder="Enter supplier email" required>

            <label>Supplier Type</label>
            <input type="text" name="supplier_type" placeholder="Enter supplier type (e.g., Beverage Supplier)" required>

            <label>Select Products</label>
            <select class="product-select" name="product_ids[]" multiple="multiple" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="btn-container">
                <button type="submit" name="add_supplier" class="save-btn">Save</button>
            </div>

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
                <?php
                $result = $conn->query("SELECT * FROM supplier ORDER BY created_at DESC");
                while ($s = $result->fetch_assoc()):
                ?>
                    <tr>
                        <td><?= $s['supplier_id'] ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['contact']) ?></td>
                        <td><?= htmlspecialchars($s['email']) ?></td>
                        <td><?= htmlspecialchars($s['supplier_type']) ?></td>
                        <td><?= $s['created_at'] ?></td>
                        <td><?= $s['updated_at'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('.product-select').select2({
            placeholder: "Search and select products",
            allowClear: true,
            width: '100%'
        });
    });
</script>