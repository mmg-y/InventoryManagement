<?php
include '../config.php';

// ✅ Function to auto-generate batch numbers
function generateBatchNumber()
{
    return 'BATCH-' . date('Ymd-His-') . rand(100, 999);
}

/* ------------------------------
   ADD NEW PURCHASE
--------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_purchase'])) {
    $supplier_id = $_POST['supplier_id'];
    $product_id = $_POST['product_id'];
    $qty = $_POST['qty'];
    $cost_price = $_POST['cost_price'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? null;

    if (!empty($supplier_id) && !empty($product_id) && $qty > 0 && $cost_price > 0) {
        $batch_number = generateBatchNumber();

        $stmt = $conn->prepare("INSERT INTO product_stocks 
            (product_id, batch_number, quantity, remaining_qty, cost_price, remarks, created_at, supplier_id, updated_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?)");
        $stmt->bind_param("ssddssis", $product_id, $batch_number, $qty, $qty, $cost_price, $remarks, $supplier_id, $status);
        $stmt->execute();
        $stmt->close();

        echo "<script>window.location.href='budegero.php?page=supplier_purchases&success=Purchase added successfully';</script>";
        exit;
    } else {
        echo "<script>window.location.href='budegero.php?page=supplier_purchases&success=Invalid Input';</script>";
        exit;
    }
}

/* ------------------------------
   UPDATE PURCHASE
--------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_purchase'])) {
    $id = $_POST['product_stock_id'];
    $product_id = $_POST['product_id'];
    $qty = $_POST['qty'];
    $cost_price = $_POST['cost_price'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];

    $stmt = $conn->prepare("UPDATE product_stocks 
                            SET product_id=?, quantity=?, remaining_qty=?, cost_price=?, status=?, remarks=?, updated_at=NOW() 
                            WHERE product_stock_id=?");
    $stmt->bind_param("sdddssi", $product_id, $qty, $qty, $cost_price, $status, $remarks, $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location.href='budegero.php?page=supplier_purchases&success=Purchase updated successfully';</script>";
    exit;
}

/* ------------------------------
   DELETE PURCHASE
--------------------------------*/
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM product_stocks WHERE product_stock_id='$id'");
    echo "<script>window.location.href='budegero.php?page=supplier_purchases&success=Purchase deleted successfully';</script>";
    exit;
}

/* ------------------------------
   PAGINATION, SEARCH & SORT
--------------------------------*/
$limit = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = $search ? "WHERE p.product_name LIKE '%$search%' OR s.name LIKE '%$search%' OR ps.batch_number LIKE '%$search%'" : '';

$valid_columns = ['product_stock_id', 'batch_number', 'name', 'product_name', 'quantity', 'status', 'created_at'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_columns) ? $_GET['sort'] : 'product_stock_id';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';
$toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

/* ------------------------------
   QUERY DATA
--------------------------------*/
$countSql = "SELECT COUNT(*) AS count 
             FROM product_stocks ps 
             LEFT JOIN supplier s ON ps.supplier_id = s.supplier_id
             LEFT JOIN product p ON ps.product_id = p.product_id 
             $where";
$total = $conn->query($countSql)->fetch_assoc()['count'];
$pages = ceil($total / $limit);

$result = $conn->query("
    SELECT ps.*, s.name AS supplier_name, p.product_name
    FROM product_stocks ps
    LEFT JOIN supplier s ON ps.supplier_id = s.supplier_id
    LEFT JOIN product p ON ps.product_id = p.product_id
    $where
    ORDER BY $sort $order
    LIMIT $start, $limit
");
?>

<link rel="stylesheet" href="../css/supplier_purch.css">

<div class="purchases-container">
    <h1 class="page-title">Supplier Purchases</h1>

    <?php if (isset($_GET['success'])): ?>
        <?php if (stripos($_GET['success'], 'deleted') !== false): ?>
            <div class="alert danger"><?= htmlspecialchars($_GET['success']); ?></div>
        <?php else: ?>
            <div class="alert success"><?= htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert error"><?= htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <div class="actions-bar">
        <form method="GET" class="search-form">
            <input type="hidden" name="page" value="supplier_purchases">
            <input type="text" name="search" placeholder="Search Batch, Supplier, or Product..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fa fa-search"></i></button>
        </form>
        <button class="add-btn"><i class="fa fa-plus"></i> Add Purchase</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <?php
                    $columns = [
                        'product_stock_id' => 'ID',
                        'batch_number' => 'Batch Number',
                        'supplier_name' => 'Supplier',
                        'product_name' => 'Product',
                        'quantity' => 'Quantity',
                        'cost_price' => 'Cost Price',
                        'status' => 'Status',
                    ];
                    foreach ($columns as $col => $label) {
                        $indicator = ($sort === $col) ? ($order === 'ASC' ? '▲' : '▼') : '';
                        echo "<th><a href='budegero.php?page=supplier_purchases&sort=$col&order=$toggle_order&search=" . urlencode($search) . "'>$label <span class='sort-indicator'>$indicator</span></a></th>";
                    }
                    ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['product_stock_id'] ?></td>
                            <td><?= htmlspecialchars($row['batch_number']) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td>₱<?= number_format($row['cost_price'], 2) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td class="actions">
                                <button class="edit-btn"
                                    data-id="<?= $row['product_stock_id'] ?>"
                                    data-product="<?= $row['product_id'] ?>"
                                    data-qty="<?= $row['quantity'] ?>"
                                    data-cost="<?= $row['cost_price'] ?>"
                                    data-status="<?= $row['status'] ?>"
                                    data-remarks="<?= htmlspecialchars($row['remarks'] ?? '') ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <a href="budegero.php?page=supplier_purchases&delete=<?= $row['product_stock_id'] ?>"
                                    class="delete-btn"
                                    onclick="return confirm('Delete this purchase?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No purchases found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="budegero.php?page=supplier_purchases&p=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span id="closeAdd" class="close">&times;</span>
        <h2>Add Purchase</h2>

        <form method="POST">
            <input type="hidden" name="add_purchase" value="1">

            <div class="input-group">
                <label>Supplier</label>
                <select name="supplier_id" required>
                    <option value="">Select Supplier</option>
                    <?php
                    $suppliers = $conn->query("SELECT * FROM supplier ORDER BY name ASC");
                    while ($sup = $suppliers->fetch_assoc()) {
                        echo "<option value='{$sup['supplier_id']}'>{$sup['name']} ({$sup['supplier_type']})</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="input-group">
                <label>Product</label>
                <select name="product_id" required>
                    <option value="">Select Product</option>
                    <?php
                    $products = $conn->query("SELECT * FROM product ORDER BY product_name ASC");
                    while ($prod = $products->fetch_assoc()) {
                        echo "<option value='{$prod['product_id']}'>{$prod['product_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label>Quantity</label>
                    <input type="number" name="qty" required>
                </div>

                <div class="input-group">
                    <label>Cost Price (₱)</label>
                    <input type="number" step="0.01" name="cost_price" required>
                </div>
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="pulled">Pulled</option>
                        <option value="out">Out</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Remarks</label>
                    <input type="text" name="remarks" placeholder="Optional">
                </div>
            </div>

            <button type="submit" class="save-btn">Add Purchase</button>
        </form>
    </div>
</div>


<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span id="closeEdit" class="close">&times;</span>
        <h2>Edit Purchase</h2>
        <form method="POST">
            <input type="hidden" name="update_purchase" value="1">
            <input type="hidden" name="product_stock_id" id="edit_id">

            <label>Product</label>
            <select name="product_id" id="edit_product" required>
                <?php
                $products = $conn->query("SELECT * FROM product ORDER BY product_name ASC");
                while ($prod = $products->fetch_assoc()) {
                    echo "<option value='{$prod['product_id']}'>{$prod['product_name']}</option>";
                }
                ?>
            </select>

            <label>Quantity</label>
            <input type="number" name="qty" id="edit_qty" required>

            <label>Cost Price (₱)</label>
            <input type="number" step="0.01" name="cost_price" id="edit_cost" required>

            <label>Status</label>
            <select name="status" id="edit_status" required>
                <option value="pulled">Pulled</option>
                <option value="out">Out</option>
            </select>

            <label>Remarks</label>
            <input type="text" name="remarks" id="edit_remarks">

            <button type="submit" class="save-btn">Save Changes</button>
        </form>
    </div>
</div>

<script>
    const addModal = document.getElementById("addModal");
    const editModal = document.getElementById("editModal");
    const addBtn = document.querySelector(".add-btn");
    const closeAdd = document.getElementById("closeAdd");
    const closeEdit = document.getElementById("closeEdit");

    addBtn.onclick = () => addModal.style.display = "flex";
    closeAdd.onclick = () => addModal.style.display = "none";
    closeEdit.onclick = () => editModal.style.display = "none";

    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            document.getElementById("edit_id").value = btn.dataset.id;
            document.getElementById("edit_product").value = btn.dataset.product;
            document.getElementById("edit_qty").value = btn.dataset.qty;
            document.getElementById("edit_cost").value = btn.dataset.cost;
            document.getElementById("edit_status").value = btn.dataset.status;
            document.getElementById("edit_remarks").value = btn.dataset.remarks;
            editModal.style.display = "flex";
        });
    });

    window.onclick = (e) => {
        if (e.target == addModal) addModal.style.display = "none";
        if (e.target == editModal) editModal.style.display = "none";
    };
</script>