<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    $supplier_type = $_POST['supplier_type'];
    $product_ids = $_POST['product_ids'] ?? [];

    $stmt = $conn->prepare("UPDATE supplier SET supplier_type = ?, updated_at = NOW() WHERE supplier_id = ?");
    $stmt->bind_param("si", $supplier_type, $supplier_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
    foreach ($product_ids as $pid) {
        $stmt->bind_param("ii", $pid, $supplier_id);
        $stmt->execute();
    }
    $stmt->close();

    header("Location: admin.php?page=supplier&success=Supplier linked successfully");
    exit;
}

$suppliers = $conn->query("SELECT supplier_id, name FROM supplier ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("SELECT product_id, product_name FROM product ORDER BY product_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="../css/supplier.css">

<div class="supplier-container">
    <h1 class="page-title">Suppliers</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert success"><?= htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Link Supplier with Product</h2>
        <form method="POST">

            <label>Select Supplier</label>
            <select name="supplier_id" id="supplierSelect" required>
                <option value="" disabled selected>Choose supplier</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Select Products</label>
            <div class="searchable-select">
                <input type="text" id="productSearch" placeholder="Search product..." />
                <select name="product_ids[]" id="productSelect" multiple required size="8">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Typable Supplier Type -->
            <label>Supplier Type</label>
            <input type="text" name="supplier_type" placeholder="Enter supplier type (e.g., Beverage Supplier)" required>

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

<script>
    // Show product dropdown only when supplier is selected
    document.getElementById('supplierSelect').addEventListener('change', function() {
        document.getElementById('productSelectContainer').style.display = this.value ? 'block' : 'none';
    });
</script>

<script>
    const searchInput = document.getElementById('productSearch');
    const productSelect = document.getElementById('productSelect');

    searchInput.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        Array.from(productSelect.options).forEach(opt => {
            const text = opt.text.toLowerCase();
            opt.style.display = text.includes(filter) ? 'block' : 'none';
        });
    });
</script>