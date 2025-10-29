<?php
include '../config.php';

// Handle adding a new supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $supplier_type = trim($_POST['supplier_type']);
    $product_ids = $_POST['product_ids'] ?? [];

    // Insert supplier
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

// Handle updating a supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_supplier'])) {
    $supplier_id = intval($_POST['supplier_id']);
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $supplier_type = trim($_POST['supplier_type']);
    $product_ids = $_POST['product_ids'] ?? [];

    // Update supplier info
    $stmt = $conn->prepare("UPDATE supplier SET name=?, contact=?, email=?, supplier_type=?, updated_at=NOW() WHERE supplier_id=?");
    $stmt->bind_param("ssssi", $name, $contact, $email, $supplier_type, $supplier_id);
    $stmt->execute();
    $stmt->close();

    // Delete old product links
    $stmt = $conn->prepare("DELETE FROM product_suppliers WHERE supplier_id=?");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $stmt->close();

    // Insert new product links
    if (!empty($product_ids)) {
        $stmt = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        foreach ($product_ids as $pid) {
            $stmt->bind_param("ii", $pid, $supplier_id);
            $stmt->execute();
        }
        $stmt->close();
    }

    header("Location: admin.php?page=supplier&success=Supplier updated successfully");
    exit;
}

// Fetch products for selection
$products = $conn->query("SELECT product_id, product_name FROM product ORDER BY product_name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch suppliers with their products
$suppliers = $conn->query("
    SELECT s.*, GROUP_CONCAT(ps.product_id) AS product_ids
    FROM supplier s
    LEFT JOIN product_suppliers ps ON s.supplier_id = ps.supplier_id
    GROUP BY s.supplier_id
    ORDER BY s.created_at DESC
");
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
            <input type="text" name="supplier_type" placeholder="Enter supplier type" required>

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
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Items</th>
                    <th>Default Prices</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = $suppliers->fetch_assoc()):
                    // Fetch supplier products
                    $supplier_products = [];
                    $prod_res = $conn->query(
                        "
        SELECT ps.product_id, p.product_name, ps.default_cost_price
        FROM product_suppliers ps
        JOIN product p ON p.product_id = ps.product_id
        WHERE ps.supplier_id = " . $s['supplier_id']
                    );

                    while ($row = $prod_res->fetch_assoc()) {
                        $supplier_products[] = [
                            'id' => $row['product_id'],
                            'name' => $row['product_name'],
                            'default_cost_price' => $row['default_cost_price']
                        ];
                    }

                    $products_json = json_encode(array_column($supplier_products, 'id')); // For modal
                ?>
                    <tr>
                        <td><?= $s['supplier_id'] ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['contact']) ?></td>
                        <td><?= htmlspecialchars($s['email']) ?></td>
                        <td><?= htmlspecialchars($s['supplier_type']) ?></td>
                        <td>
                            <?php foreach ($supplier_products as $prod): ?>
                                <div><?= htmlspecialchars($prod['name']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach ($supplier_products as $prod): ?>
                                <div>₱<?= number_format($prod['default_cost_price'], 2) ?></div>
                            <?php endforeach; ?>
                        </td>

                        <td><?= $s['created_at'] ?></td>
                        <td><?= $s['updated_at'] ?></td>
                        <td>
                            <button class="edit-btn"
                                data-id="<?= $s['supplier_id'] ?>"
                                data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                                data-contact="<?= htmlspecialchars($s['contact'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>"
                                data-type="<?= htmlspecialchars($s['supplier_type'], ENT_QUOTES) ?>"
                                data-products='<?= $products_json ?>'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editSupplierModal" class="modal-supply">
    <div class="modal-content-supply">
        <span class="close-btn" id="closeEditModal">&times;</span>
        <h2>Edit Supplier</h2>
        <form id="editSupplierForm" method="POST">
            <input type="hidden" name="supplier_id" id="editSupplierId">

            <label>Supplier Name</label>
            <input type="text" name="name" id="editName" required>

            <label>Contact</label>
            <input type="text" name="contact" id="editContact" required>

            <label>Email</label>
            <input type="email" name="email" id="editEmail" required>

            <label>Supplier Type</label>
            <input type="text" name="supplier_type" id="editType" required>

            <label>Select Products</label>
            <select class="product-select" name="product_ids[]" id="editProducts" multiple="multiple" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="btn-container">
                <button type="submit" name="update_supplier" class="save-btn">Update</button>
            </div>
        </form>
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
        const $editModal = $('#editSupplierModal');

        $editModal.hide();

        $('.edit-btn').on('click', function() {
            const $btn = $(this);

            $('#editSupplierId').val($btn.data('id'));
            $('#editName').val($btn.data('name'));
            $('#editContact').val($btn.data('contact'));
            $('#editEmail').val($btn.data('email'));
            $('#editType').val($btn.data('type'));

            let products = $btn.data('products');

            if (typeof products === 'string') {
                products = JSON.parse(products);
            }

            $('#editProducts').val(products).trigger('change');

            $editModal.fadeIn();
        });

        $('#closeEditModal').on('click', function() {
            $editModal.fadeOut();
        });

        $editModal.on('click', function(e) {
            if (e.target === this) {
                $editModal.fadeOut();
            }
        });
    });
</script>