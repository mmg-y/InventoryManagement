<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $code = trim($_POST['product_code']);
    $name = trim($_POST['product_name']);
    $quantity = trim($_POST['quantity']);
    $price = trim($_POST['price']);
    $status = trim($_POST['notice_status']);
    $threshold = trim($_POST['threshold']);

    $errors = [];

    if (empty($code)) $errors[] = "Product code is required.";
    if (empty($name)) $errors[] = "Product name is required.";
    if (!is_numeric($quantity) || $quantity < 0) $errors[] = "Quantity must be a valid number (0 or greater).";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a valid positive number.";
    if (!empty($threshold) && (!is_numeric($threshold) || $threshold < 0)) $errors[] = "Threshold must be a valid number.";

    $check = $conn->query("SELECT * FROM product WHERE product_code = '$code'");
    if ($check && $check->num_rows > 0) {
        $errors[] = "Product code already exists.";
    }

    if (!empty($errors)) {
        $errorStr = urlencode(implode(" ", $errors));
        header("Location: admin.php?page=product_inventory&msg=updated");
        exit;
    }

    $sql = "INSERT INTO product (product_code, product_name, quantity, price, notice_status, threshold, created_at, updated_at)
            VALUES ('$code', '$name', '$quantity', '$price', '$status', '$threshold', NOW(), NOW())";
    $conn->query($sql);
    header("Location: admin.php?page=product_inventory&msg=added");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $id = $_POST['product_id'];
    $code = $_POST['product_code'];
    $name = $_POST['product_name'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $status = $_POST['notice_status'];
    $threshold = $_POST['threshold'];

    $sql = "UPDATE product SET 
                product_code='$code',
                product_name='$name',
                quantity='$quantity',
                price='$price',
                notice_status='$status',
                threshold='$threshold',
                updated_at=NOW()
            WHERE product_id='$id'";
    $conn->query($sql);
    header("Location: admin.php?page=product_inventory&msg=updated");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $id = $_POST['delete_product'];
    $conn->query("DELETE FROM product WHERE product_id='$id'");
    header("Location: admin.php?page=product_inventory&msg=deleted");
    exit;
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$start = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = $search ? "WHERE product_code LIKE '%$search%' OR product_name LIKE '%$search%'" : '';

$valid_columns = ['product_id', 'product_code', 'product_name', 'quantity', 'price', 'notice_status', 'threshold'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_columns) ? $_GET['sort'] : 'product_id';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

$toggle_order = $order === 'ASC' ? 'DESC' : 'ASC';

$total = $conn->query("SELECT COUNT(*) as count FROM product $where")->fetch_assoc()['count'];
$pages = ceil($total / $limit);

$result = $conn->query("SELECT * FROM product $where ORDER BY $sort $order LIMIT $start, $limit");
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Product Inventory</title>
    <link rel="stylesheet" href="../css/product_inv.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <h1 class="page-title">Products & Inventory</h1>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert 
                <?= $_GET['msg'] === 'added' ? 'success' : ($_GET['msg'] === 'updated' ? 'success' : ($_GET['msg'] === 'deleted' ? 'danger' : 'error')) ?>">
            <?php
            if ($_GET['msg'] === 'added') echo "Product added successfully!";
            elseif ($_GET['msg'] === 'updated') echo "Product updated successfully!";
            elseif ($_GET['msg'] === 'deleted') echo "Product deleted successfully!";
            elseif ($_GET['msg'] === 'error' && isset($_GET['details'])) echo "⚠️ " . htmlspecialchars($_GET['details']);
            ?>
        </div>
    <?php endif; ?>

    <div class="actions-bar">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search by code or name..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fa fa-search"></i></button>
        </form>
        <button class="add-btn"><i class="fa-solid fa-plus"></i> Add Product</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <?php
                    $columns = [
                        'product_id' => 'ID',
                        'product_code' => 'Code',
                        'product_name' => 'Name',
                        'quantity' => 'Quantity',
                        'price' => 'Price',
                        'notice_status' => 'Status',
                        'threshold' => 'Threshold'
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
                            <td><?= $row['product_id'] ?></td>
                            <td><?= $row['product_code'] ?></td>
                            <td><?= $row['product_name'] ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td><?= $row['price'] ?></td>
                            <td><?= $row['notice_Status'] ?></td>
                            <td><?= $row['threshold'] ?></td>
                            <td class="actions">
                                <button class="edit-btn"
                                    data-id="<?= $row['product_id'] ?>"
                                    data-code="<?= $row['product_code'] ?>"
                                    data-name="<?= $row['product_name'] ?>"
                                    data-quantity="<?= $row['quantity'] ?>"
                                    data-price="<?= $row['price'] ?>"
                                    data-status="<?= $row['notice_Status'] ?>"
                                    data-threshold="<?= $row['threshold'] ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="delete_product" value="<?= $row['product_id'] ?>">
                                    <button type="submit" class="delete-btn">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No products found.</td>
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

    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeAdd">&times;</span>
            <h2>Add Product</h2>
            <form method="POST">
                <input type="hidden" name="add_product" value="1">
                <label>Product Code</label>
                <input type="text" name="product_code" required>
                <label>Product Name</label>
                <input type="text" name="product_name" required>
                <label>Quantity</label>
                <input type="number" name="quantity" required>
                <label>Price</label>
                <input type="number" step="0.01" name="price" required>
                <label>Status</label>
                <select name="notice_status" required>
                    <option value="available">Available</option>
                    <option value="low stock">Low Stock</option>
                    <option value="out of stock">Out of Stock</option>
                </select>
                <label>Threshold</label>
                <input type="number" name="threshold">
                <button type="submit" class="save-btn">Add Product</button>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" id="closeEdit">&times;</span>
            <h2>Edit Product</h2>
            <form method="POST">
                <input type="hidden" name="update_product" value="1">
                <input type="hidden" name="product_id" id="edit_id">
                <label>Product Code</label>
                <input type="text" name="product_code" id="edit_code" required>
                <label>Product Name</label>
                <input type="text" name="product_name" id="edit_name" required>
                <label>Quantity</label>
                <input type="number" name="quantity" id="edit_quantity" required>
                <label>Price</label>
                <input type="number" step="0.01" name="price" id="edit_price" required>
                <label>Status</label>
                <select name="notice_status" id="edit_status">
                    <option value="available">Available</option>
                    <option value="low stock">Low Stock</option>
                    <option value="out of stock">Out of Stock</option>
                </select>
                <label>Threshold</label>
                <input type="number" name="threshold" id="edit_threshold">
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

        addBtn.onclick = () => {
            addModal.style.display = "flex";
        }

        closeAdd.onclick = () => {
            addModal.style.display = "none";
        }

        closeEdit.onclick = () => {
            editModal.style.display = "none";
        }

        document.querySelectorAll(".edit-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                document.getElementById("edit_id").value = btn.dataset.id;
                document.getElementById("edit_code").value = btn.dataset.code;
                document.getElementById("edit_name").value = btn.dataset.name;  
                document.getElementById("edit_quantity").value = btn.dataset.quantity;
                document.getElementById("edit_price").value = btn.dataset.price;
                document.getElementById("edit_status").value = btn.dataset.status;
                document.getElementById("edit_threshold").value = btn.dataset.threshold;
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
