<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_purchase'])) {
    $po_num = $_POST['po_num'];
    $supplier_id = $_POST['supplier_id'];
    $product_name = $_POST['product_name'];
    $qty = $_POST['qty'];
    $status = $_POST['status'];

    $sql = "INSERT INTO stock (po_num, bodegero, product_name, qty, status, created_at, updated_at)
            VALUES ('$po_num', '$supplier_id', '$product_name', '$qty', '$status', NOW(), NOW())";
    $conn->query($sql);
    header("Location: admin.php?msg=added");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_purchase'])) {
    $id = $_POST['stock_id'];
    $po_num = $_POST['po_num'];
    $product_name = $_POST['product_name'];
    $qty = $_POST['qty'];
    $status = $_POST['status'];

    $sql = "UPDATE stock SET po_num='$po_num', product_name='$product_name', qty='$qty', status='$status', updated_at=NOW() WHERE stock_id='$id'";
    $conn->query($sql);
    header("Location: admin.php?msg=updated");
    exit;
}
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM stock WHERE stock_id='$id'");
    header("Location: admin.php?msg=deleted");
    exit;
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = $search ? "WHERE s.po_num LIKE '%$search%' OR s.product_name LIKE '%$search%' OR sp.supplier_type LIKE '%$search%'" : '';

$valid_columns = ['stock_id', 'po_num', 'supplier_type', 'product_name', 'qty', 'status', 'created_at'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_columns) ? $_GET['sort'] : 'stock_id';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';
$toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

$total = $conn->query("SELECT COUNT(*) as count FROM stock s LEFT JOIN supplier sp ON s.bodegero = sp.supplier_id $where")->fetch_assoc()['count'];
$pages = ceil($total / $limit);

$result = $conn->query("SELECT s.*, sp.supplier_type, sp.contact, sp.email FROM stock s LEFT JOIN supplier sp ON s.bodegero = sp.supplier_id $where ORDER BY $sort $order LIMIT $start, $limit");
$result = $conn->query("SELECT s.*, sp.name AS supplier_name, sp.supplier_type, sp.contact, sp.email 
    FROM stock s 
    LEFT JOIN supplier sp ON s.bodegero = sp.supplier_id 
    $where 
    ORDER BY $sort $order 
    LIMIT $start, $limit");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Supplier Purchases</title>
    <link rel="stylesheet" href="../css/supplier_purch.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <h1>Supplier Purchases</h1>

    <div class="actions-bar">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search PO, Supplier, or Product..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fa fa-search"></i></button>
        </form>
        <button class="add-btn"><i class="fa-solid fa-plus"></i> Add Purchase</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <?php
                    $columns = [
                        'stock_id' => 'ID',
                        'po_num' => 'PO Number',
                        'supplier_type' => 'Supplier',
                        'product_name' => 'Product',
                        'qty' => 'Quantity',
                        'status' => 'Status',
                        'created_at' => 'Date'
                    ];
                    foreach ($columns as $col => $label) {
                        $indicator = ($sort === $col) ? ($order === 'ASC' ? '▲' : '▼') : '';
                        echo "<th><a href='?sort=$col&order=$toggle_order&search=" . urlencode($search) . "'>$label <span class='sort-indicator'>$indicator</span></a></th>";
                    }
                    ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $row['stock_id'] ?></td>
                            <td><?= $row['po_num'] ?></td>
                            <td><?= $row['supplier_name'] ?> (<?= $row['supplier_type'] ?>)</td>
                            <td><?= $row['product_name'] ?></td>
                            <td><?= $row['qty'] ?></td>
                            <td><?= $row['status'] ?></td>
                            <td><?= $row['created_at'] ?></td>
                            <td class="actions">
                                <button class="edit-btn"
                                    data-id="<?= $row['stock_id'] ?>"
                                    data-po="<?= $row['po_num'] ?>"
                                    data-supplier="<?= $row['supplier_type'] ?>"
                                    data-product="<?= $row['product_name'] ?>"
                                    data-qty="<?= $row['qty'] ?>"
                                    data-status="<?= $row['status'] ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <a href="?delete=<?= $row['stock_id'] ?>" class="delete-btn" onclick="return confirm('Delete this purchase?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
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
            <a href="?page=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>" class="<?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeAdd">&times;</span>
            <h2>Add Purchase</h2>
            <form method="POST">
                <input type="hidden" name="add_purchase" value="1">
                <label>PO Number</label>
                <input type="text" name="po_num" required>
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
                <label>Product</label>
                <input type="text" name="product_name" required>
                <label>Quantity</label>
                <input type="number" name="qty" required>
                <label>Status</label>
                <select name="status">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
                <button type="submit" class="save-btn">Add Purchase</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" id="closeEdit">&times;</span>
            <h2>Edit Purchase</h2>
            <form method="POST">
                <input type="hidden" name="update_purchase" value="1">
                <input type="hidden" name="stock_id" id="edit_id">

                <label>PO Number</label>
                <input type="text" name="po_num" id="edit_po" required>

                <label>Supplier</label>
                <input type="text" id="edit_supplier" readonly>

                <label>Product</label>
                <input type="text" name="product_name" id="edit_product" required>

                <label>Quantity</label>
                <input type="number" name="qty" id="edit_qty" required>

                <label>Status</label>
                <select name="status" id="edit_status">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
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
                document.getElementById("edit_po").value = btn.dataset.po;
                document.getElementById("edit_supplier").value = btn.dataset.supplier;
                document.getElementById("edit_product").value = btn.dataset.product;
                document.getElementById("edit_qty").value = btn.dataset.qty;
                document.getElementById("edit_status").value = btn.dataset.status;
                editModal.style.display = "flex";
            });
        });

        window.onclick = (e) => {
            if (e.target == addModal) addModal.style.display = "none";
            if (e.target == editModal) editModal.style.display = "none";
        };
    </script>
</body>

</html>