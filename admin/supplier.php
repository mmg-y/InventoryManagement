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
    $product_ids = array_map('intval', $_POST['product_ids'] ?? []);

    // Update supplier info
    $stmt = $conn->prepare("UPDATE supplier SET name=?, contact=?, email=?, supplier_type=?, updated_at=NOW() WHERE supplier_id=?");
    $stmt->bind_param("ssssi", $name, $contact, $email, $supplier_type, $supplier_id);
    $stmt->execute();
    $stmt->close();

    $existing = [];
    $res = $conn->prepare("SELECT product_id FROM product_suppliers WHERE supplier_id=?");
    $res->bind_param("i", $supplier_id);
    $res->execute();
    $res->bind_result($pid);
    while ($res->fetch()) $existing[] = $pid;
    $res->close();

    $to_add = array_diff($product_ids, $existing);
    $to_remove = array_diff($existing, $product_ids);

    if (!empty($to_add)) {
        $stmt = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        foreach ($to_add as $pid) {
            $stmt->bind_param("ii", $pid, $supplier_id);
            $stmt->execute();
        }
        $stmt->close();
    }

    if (!empty($to_remove)) {
        $placeholders = implode(',', array_fill(0, count($to_remove), '?'));
        $types = str_repeat('i', count($to_remove) + 1);
        $query = "DELETE FROM product_suppliers WHERE supplier_id=? AND product_id IN ($placeholders)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, $supplier_id, ...$to_remove);
        $stmt->execute();
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

<style>
.readonly-box div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #eef2ff;
    padding: 5px 10px;
    margin: 4px 0;
    border-radius: 5px;
}
.readonly-box button {
    background: #ef4444;
    border: none;
    color: white;
    border-radius: 4px;
    cursor: pointer;
    padding: 2px 6px;
}
.readonly-box button:hover {
    background: #dc2626;
}



.item-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-width: 250px;
}

.item-pill {
    background: #e0f2fe;
    color: #0f172a;
    font-size: 13px;
    padding: 4px 8px;
    border-radius: 12px;
    white-space: nowrap;
    border: 1px solid #bae6fd;
    transition: all 0.2s ease;
}

.item-pill:hover {
    background: #0284c7;
    color: white;
    border-color: #0284c7;
}

</style>


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
                    <th>Supplier</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Items Supplied</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = $suppliers->fetch_assoc()):
                    // Fetch supplier products with default_cost
                    $supplier_products = [];
                    $prod_res = $conn->query("
                        SELECT p.product_name, ps.default_cost_price
                        FROM product_suppliers ps
                        JOIN product p ON p.product_id = ps.product_id
                        WHERE ps.supplier_id = {$s['supplier_id']}
                        ORDER BY p.product_name ASC
                    ");
                    while ($row = $prod_res->fetch_assoc()) {
                        $supplier_products[] = [
                            'product_name' => $row['product_name'],
                            'default_cost' => $row['default_cost_price']
                        ];
                    }

                    $products_json = json_encode($supplier_products);
                ?>
                    <tr>
                        <td><?= $s['supplier_id'] ?></td>
                        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['contact']) ?></td>
                        <td><?= htmlspecialchars($s['email']) ?></td>
                        <td><?= htmlspecialchars($s['supplier_type']) ?></td>
                        <td>
                            <?php if (!empty($supplier_products)): ?>
                                <div class="item-list">
                                    <?php foreach ($supplier_products as $prod): ?>
                                        <span class="item-pill">
                                            <?= htmlspecialchars($prod['product_name']) ?>
                                            (₱<?= number_format($prod['default_cost'], 2) ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <em>No items supplied yet.</em>
                            <?php endif; ?>
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

            <label>Currently Supplying</label>
            <div id="currentProductsBox" class="readonly-box">
                <em>Loading products...</em>
            </div>

            <label>Add New Products</label>
            <select id="addProductsDropdown" class="product-select" multiple="multiple"></select>

            <label>Products to be Added</label>
            <div id="productsToAddBox" class="readonly-box">
                <em>No new products selected.</em>
            </div>

            <input type="hidden" name="product_ids[]" id="hiddenProductIds">

            <div class="btn-container">
                <button type="submit" name="update_supplier" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    const $editModal = $('#editSupplierModal');
    const $currentBox = $('#currentProductsBox');
    const $addDropdown = $('#addProductsDropdown');
    const $toAddBox = $('#productsToAddBox');
    let currentProducts = []; // products already supplied
    let productsToAdd = [];   // products chosen to add
    let allProducts = [];     // fetched from ajax/get_all_products.php
    let currentCosts = {}; // store updated cost per product_id

    $('.product-select').select2({
        placeholder: "Search and select products",
        allowClear: true,
        width: '100%'
    });

    $editModal.hide();

    $('.edit-btn').on('click', function() {
        const supplierId = $(this).data('id');
        $('#editSupplierId').val(supplierId);
        $('#editName').val($(this).data('name'));
        $('#editContact').val($(this).data('contact'));
        $('#editEmail').val($(this).data('email'));
        $('#editType').val($(this).data('type'));

        productsToAdd = [];
        $toAddBox.html('<em>No new products selected.</em>');
        $currentBox.html('<em>Loading products...</em>');

        loadSupplierProducts(supplierId);

        $editModal.fadeIn();
    });

    function loadAllProducts() {
        return $.getJSON('ajax/get_all_products.php', function(data) {
            $addDropdown.empty().off().select2('destroy');

            allProducts = data.products || [];

            if (allProducts.length === 0) {
                console.warn("⚠️ No products found in database.");
            }

            data.available_products.forEach(p => {
                $addDropdown.append(new Option(p.product_name, p.product_id, false, false));
            });
            allProducts = allProducts.filter(p => !currentProducts.includes(parseInt(p.product_id)));

            setTimeout(() => {
                $addDropdown.select2({
                    dropdownParent: $('#editSupplierModal'),
                    placeholder: "Select products to add",
                    allowClear: true,
                    width: '100%'
                });
            }, 100);
        }).fail(function(xhr) {
            console.error("❌ AJAX error loading products:", xhr.status, xhr.statusText);
        });
    }

    function loadSupplierProducts(supplierId) {
        $.getJSON('ajax/get_supplier_products.php', { supplier_id: supplierId }, function(data) {
            $currentBox.empty();
            $addDropdown.empty().off().select2('destroy');
            currentProducts = [];
            productsToAdd = [];

            if (data.supplier_products.length === 0) {
                $currentBox.html('<em>No products currently supplied.</em>');
            } else {
                data.supplier_products.forEach(p => {
                    const pid = parseInt(p.product_id);
                    const cost = parseFloat(p.default_cost_price || 0).toFixed(2);
                    currentProducts.push(pid);

                    $currentBox.append(`
                        <div data-id="${pid}">
                            <span>${p.product_name}</span>
                            <input type="number" class="existing-cost-input" value="${cost}" step="0.01" min="0" style="width:90px; margin-left:8px;">
                            <button type="button" class="removeProduct">Remove</button>
                        </div>
                    `);
                });
                currentCosts = {};
                data.supplier_products.forEach(p => {
                    currentCosts[p.product_id] = parseFloat(p.default_cost_price || 0);
                });
            }

            $.getJSON('ajax/get_all_products.php', function(allData) {
                allProducts = allData.products || [];

                const availableProducts = allProducts.filter(p => !currentProducts.includes(parseInt(p.product_id)));

                if (availableProducts.length === 0) {
                    $addDropdown.append('<option disabled>No available products</option>');
                } else {
                    availableProducts.forEach(p => {
                        $addDropdown.append(new Option(p.product_name, p.product_id, false, false));
                    });
                }

                setTimeout(() => {
                    $addDropdown.select2({
                        dropdownParent: $('#editSupplierModal'),
                        placeholder: "Select products to add",
                        allowClear: true,
                        width: '100%'
                    });
                }, 100);
            });

            updateHiddenProductIds();
        });
    }


    $(document).on('click', '.removeProduct', function() {
        const $row = $(this).closest('div');
        const pid = parseInt($row.data('id'));
        const supplierId = parseInt($('#editSupplierId').val());

        if (!confirm("Remove this product from supplier?")) return;

        $.ajax({
            url: 'ajax/remove_supplier_product.php',
            method: 'POST',
            dataType: 'json',
            data: {
                supplier_id: supplierId,
                product_id: pid
            },
            success: function(resp) {
                if (resp.status === 'success') {
                    currentProducts = currentProducts.filter(id => id !== pid);
                    $row.remove();

                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });

                    if (currentProducts.length === 0) {
                        $currentBox.html('<em>No products currently supplied.</em>');
                    }

                    updateHiddenProductIds();
                    console.log('✅ Product removed:', pid);
                } else {
                    alert('❌ ' + (resp.message || 'Failed to remove product.'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error, xhr.responseText);
                alert('Failed to delete product. Check console for details.');
            }
        });
    });

    $(document).on('change', '#addProductsDropdown', function() {
        const selected = $(this).val() || [];
        console.log("Selected product IDs:", selected);

        selected.forEach(pid => {
            pid = parseInt(pid);
            const productName = allProducts.find(p => parseInt(p.product_id) === pid)?.product_name || 'Unknown Product';

            if (!currentProducts.includes(pid) && !productsToAdd.some(p => p.id === pid)) {
                if ($toAddBox.find('em').length) $toAddBox.empty();

                const newItem = {
                    id: pid,
                    cost: 0.00
                };
                productsToAdd.push(newItem);

                $toAddBox.append(`
                    <div data-id="${pid}">
                        <span>${productName}</span>
                        <input type="number" class="cost-input" placeholder="Cost (₱)" step="0.01" min="0" style="width:90px;">
                        <button type="button" class="removeToAdd">Remove</button>
                    </div>
                `);
            }
        });

        $(this).val(null).trigger('change'); // clear select2 selection
        updateHiddenProductIds();
    });

    $(document).on('input', '.cost-input', function() {
        const pid = parseInt($(this).closest('div').data('id'));
        const cost = parseFloat($(this).val()) || 0;
        const product = productsToAdd.find(p => p.id === pid);
        if (product) product.cost = cost;
    });

    $(document).on('input', '.existing-cost-input', function() {
        const pid = parseInt($(this).closest('div').data('id'));
        const cost = parseFloat($(this).val()) || 0;
        currentCosts[pid] = cost;
    });

    $(document).on('click', '.removeToAdd', function() {
        const pid = $(this).closest('div').data('id');
        productsToAdd = productsToAdd.filter(id => id !== pid);
        $(this).closest('div').remove();

        if (productsToAdd.length === 0) {
            $toAddBox.html('<em>No new products selected.</em>');
        }

        updateHiddenProductIds();
    });

    function updateHiddenProductIds() {
        const allSelected = [...currentProducts, ...productsToAdd];
        $('#hiddenProductIds').val(allSelected.join(','));
    }

    $('#editSupplierForm').on('submit', function(e) {
        e.preventDefault();
        const supplierId = $('#editSupplierId').val();
        const allSelected = [
            ...currentProducts.map(id => ({ id, cost: currentCosts[id] ?? 0 })),
            ...productsToAdd.map(p => ({ id: p.id, cost: p.cost }))
        ];

        console.log("Submitting update for supplier:", supplierId, "product_ids:", allSelected);

        $.ajax({
            url: 'ajax/update_supplier_products.php',
            method: 'POST',
            data: {
                supplier_id: supplierId,
                name: $('#editName').val(),
                contact: $('#editContact').val(),
                email: $('#editEmail').val(),
                supplier_type: $('#editType').val(),
                product_ids: allSelected
            },
            traditional: false,
            contentType: "application/json",
            data: JSON.stringify({
                supplier_id: supplierId,
                name: $('#editName').val(),
                contact: $('#editContact').val(),
                email: $('#editEmail').val(),
                supplier_type: $('#editType').val(),
                product_ids: allSelected
            }),
            dataType: 'json',
            success: function(resp) {
                console.log('update response', resp);
                if (resp.status === 'success') {
                    alert(resp.message);
                    location.reload();
                } else {
                    alert('Error: ' + (resp.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error', status, error, xhr.responseText);
                alert('Failed to update supplier info. Check console for details.');
            }
        });
    });


    $('#closeEditModal').on('click', () => $editModal.fadeOut());
    $editModal.on('click', function(e) {
        if (e.target === this) $editModal.fadeOut();
    });
});
</script>