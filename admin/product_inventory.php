<?php
include '../config.php';

// Auto-generate next product code 
$lastProduct = $conn->query("SELECT product_code FROM product ORDER BY product_id DESC LIMIT 1")->fetch_assoc();
$nextCode = $lastProduct ? 'P' . (intval(substr($lastProduct['product_code'], 1)) + 1) : 'P1001';

// Fetch categories for dropdown 
$categories = $conn->query("SELECT * FROM category ORDER BY category_name ASC");

// Handle Add Product 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $code = trim($_POST['product_code']);
    $name = trim($_POST['product_name']);
    $category = (int)$_POST['category'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $threshold = (int)$_POST['threshold'];

    // Handle picture upload
    $picture = '';
    if (isset($_FILES['product_picture']) && $_FILES['product_picture']['error'] === 0) {
        $ext = pathinfo($_FILES['product_picture']['name'], PATHINFO_EXTENSION);
        $picture = 'uploads/' . uniqid('prod_') . '.' . $ext;
        move_uploaded_file($_FILES['product_picture']['tmp_name'], '../' . $picture);
    }

    // Determine status
    $status_label = 'sufficient';

    if ($quantity <= $threshold && $quantity > 0) {
        $status_label = 'low';
    } elseif ($quantity == 0) {
        $status_label = 'out of stock';
    } elseif ($quantity < 0) {
        $status_label = 'critical';
    }


    // Get status_id
    $status_row = $conn->query("SELECT status_id FROM status WHERE status_label='$status_label'")->fetch_assoc();
    $status_id = $status_row ? $status_row['status_id'] : 1;

    // Insert product
    $sql = $conn->prepare("INSERT INTO product (product_code, product_name, product_picture, category, quantity, price, threshold, notice_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $sql->bind_param("sssiddii", $code, $name, $picture, $category, $quantity, $price, $threshold, $status_id);
    $sql->execute();

    header("Location: admin.php?page=product_inventory&msg=added");
    exit;
}

// Handle Update Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $id = (int)$_POST['product_id'];
    $code = trim($_POST['product_code']);
    $name = trim($_POST['product_name']);
    $category = (int)$_POST['category'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $threshold = (int)$_POST['threshold'];

    // Handle picture upload
    $picture = '';
    if (isset($_FILES['product_picture']) && $_FILES['product_picture']['error'] === 0) {
        $ext = pathinfo($_FILES['product_picture']['name'], PATHINFO_EXTENSION);
        $picture = 'uploads/' . uniqid('prod_') . '.' . $ext;
        move_uploaded_file($_FILES['product_picture']['tmp_name'], '../' . $picture);
    }

    // Determine status
    $status_label = 'sufficient';
    if ($quantity <= $threshold && $quantity > 0) $status_label = 'low';
    elseif ($quantity <= 0) $status_label = 'critical';

    // Get status_id
    $status_row = $conn->query("SELECT status_id FROM status WHERE status_label='$status_label'")->fetch_assoc();
    $status_id = $status_row ? $status_row['status_id'] : 1;

    // Build SQL updates
    $updates = [
        "product_code='$code'",
        "product_name='$name'",
        "category='$category'",
        "quantity='$quantity'",
        "price='$price'",
        "threshold='$threshold'",
        "notice_status='$status_id'",
        "updated_at=NOW()"
    ];

    if ($picture !== '') {
        $updates[] = "product_picture='$picture'";
    }

    $sql = "UPDATE product SET " . implode(", ", $updates) . " WHERE product_id='$id'";
    $conn->query($sql);

    header("Location: admin.php?page=product_inventory&msg=updated");
    exit;
}

// Pagination & Search 
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Get search and filter 
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category_filter']) ? (int)$_GET['category_filter'] : 0;

// Build WHERE clause 
$where = [];
if ($search !== '') {
    $search_safe = $conn->real_escape_string($search);
    $where[] = "(p.product_code LIKE '%$search_safe%' OR p.product_name LIKE '%$search_safe%')";
}
if ($filter_category > 0) {
    $where[] = "p.category = $filter_category";
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

// Count total products for pagination 
$total = $conn->query("SELECT COUNT(*) as count FROM product p $where_sql")->fetch_assoc()['count'];
$pages = ceil($total / $limit);

// Sorting 
$allowed_sort = ['product_id', 'product_code', 'product_picture', 'product_name', 'category', 'quantity', 'price', 'notice_status', 'threshold'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'product_id';
$order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'ASC';

// Toggle order for next click
$toggle_order = ($order === 'ASC') ? 'DESC' : 'ASC';


// Fetch products 
$result = $conn->query("
    SELECT p.*, c.category_name, c.category_id, s.status_label, s.status_id
    FROM product p
    LEFT JOIN category c ON p.category = c.category_id
    LEFT JOIN status s ON p.notice_status = s.status_id
    $where_sql
    ORDER BY $sort $order
    LIMIT $start, $limit
");


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
    <div class="main">
        <h1 class="page-title">Products & Inventory</h1>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert <?= ($_GET['msg'] == 'added' || $_GET['msg'] == 'updated') ? 'success' : ($_GET['msg'] == 'deleted' ? 'danger' : 'error') ?>">
                <?php
                if ($_GET['msg'] == 'added') echo "Product added successfully!";
                elseif ($_GET['msg'] == 'updated') echo "Product updated successfully!";
                elseif ($_GET['msg'] == 'deleted') echo "Product deleted successfully!";
                elseif ($_GET['msg'] == 'error' && isset($_GET['details'])) echo "⚠️ " . htmlspecialchars($_GET['details']);
                ?>
            </div>
        <?php endif; ?>

        <div class="actions-bar">
            <form method="GET" class="search-form">
                <input type="hidden" name="page" value="product_inventory">
                <input type="text" name="search" placeholder="Search by code or name..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fa fa-search"></i></button>
                <div class="filter-wrapper">
                    <select name="category_filter" onchange="this.form.submit()" class="category-filter">
                        <option value="0">Categories</option>
                        <?php
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()):
                            $selected = ($filter_category == $cat['category_id']) ? 'selected' : '';
                        ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $selected ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <i class="fa fa-filter filter-icon"></i>
                </div>
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
                            'product_picture' => 'Picture',
                            'product_name' => 'Name',
                            'category' => 'Category',
                            'quantity' => 'Quantity',
                            'price' => 'Price',
                            'notice_status' => 'Status',
                            'threshold' => 'Threshold'
                        ];

                        foreach ($columns as $col => $label) {
                            $indicator = ($sort == $col) ? ($order === 'ASC' ? '▲' : '▼') : '';
                            $newOrder = ($sort == $col && $order === 'ASC') ? 'DESC' : 'ASC';
                            echo "<th>
            <a href='admin.php?page=product_inventory&sort=$col&order=$newOrder&search=" . urlencode($search) . "&category_filter=$filter_category'>
                <span class='sort-indicator'>$indicator</span> $label
            </a>
          </th>";
                        }
                        ?>

                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['product_id'] ?></td>
                                <td><?= $row['product_code'] ?></td>
                                <td>
                                    <?php if ($row['product_picture']): ?>
                                        <img src="../<?= $row['product_picture'] ?>" alt="<?= htmlspecialchars($row['product_name']) ?>" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['product_name'] ?></td>
                                <td><?= $row['category_name'] ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= $row['price'] ?></td>

                                <?php $status_label = $row['status_label'] ?? 'available'; ?>
                                <td class="<?php
                                            if ($status_label == 'sufficient') echo 'status-available';
                                            elseif ($status_label == 'low') echo 'status-low';
                                            elseif ($status_label == 'out of stock') echo 'status-out';
                                            elseif ($status_label == 'critical') echo 'status-critical';
                                            ?>">
                                    <?= ucfirst($status_label) ?>
                                </td>

                                <td><?= $row['threshold'] ?></td>
                                <td class="actions">
                                    <button class="edit-btn"
                                        data-id="<?= $row['product_id'] ?>"
                                        data-code="<?= $row['product_code'] ?>"
                                        data-picture="<?= $row['product_picture'] ?>"
                                        data-name="<?= $row['product_name'] ?>"
                                        data-category="<?= $row['category_id'] ?>"
                                        data-quantity="<?= $row['quantity'] ?>"
                                        data-price="<?= $row['price'] ?>"
                                        data-threshold="<?= $row['threshold'] ?>"
                                        data-status="<?= $row['status_id'] ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <a href="?delete=<?= $row['product_id'] ?>" class="delete-btn" onclick="return confirm('Delete this product?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="10">No products found.</td>
                        </tr>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="?page=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>" class="<?= ($i == $page ? 'active' : '') ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="modal">
            <div class="modal-content">
                <span class="close" id="closeAdd">&times;</span>
                <h2>Add Product</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_product" value="1">

                    <label>Product Code</label>
                    <input type="text" name="product_code" value="<?= $nextCode ?>" readonly>

                    <label>Product Picture</label>
                    <input type="file" name="product_picture" id="add_picture" accept="image/*">
                    <img id="add_picture_preview" src="" alt="Preview" style="display:none; width:80px; height:80px; border-radius:6px; object-fit:cover; margin-top:10px; border:1px solid #ccc;">

                    <label>Product Name</label>
                    <input type="text" name="product_name" required>

                    <label>Category</label>
                    <select name="category" id="add_category" required>
                        <?php $categories->data_seek(0); ?>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= $cat['category_name'] ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label>Quantity</label>
                    <input type="number" name="quantity" required>

                    <label>Price</label>
                    <input type="number" step="0.01" name="price" required>

                    <label>Threshold</label>
                    <input type="number" name="threshold">

                    <button type="submit" class="save-btn">Add Product</button>
                </form>
            </div>
        </div>


        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" id="closeEdit">&times;</span>
                <h2>Edit Product</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_product" value="1">
                    <input type="hidden" name="product_id" id="edit_id">
                    <label>Product Code</label>
                    <input type="text" name="product_code" id="edit_code" required>
                    <label>Product Picture</label>
                    <input type="file" name="product_picture" id="edit_picture" accept="image/*">
                    <img id="edit_picture_preview" src="" alt="Preview" style="display:none; width:80px; height:80px; border-radius:6px; object-fit:cover; margin-top:10px; border:1px solid #ccc;">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="edit_name" required>
                    <label>Category</label>
                    <select name="category" id="edit_category" required>
                        <?php $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= $cat['category_name'] ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label>Quantity</label>
                    <input type="number" name="quantity" id="edit_quantity" required>
                    <label>Price</label>
                    <input type="number" step="0.01" name="price" id="edit_price" required>
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <?php
                        $statuses = $conn->query("SELECT * FROM status");
                        while ($s = $statuses->fetch_assoc()): ?>
                            <option value="<?= $s['status_id'] ?>"><?= $s['status_label'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    <label>Threshold</label>
                    <input type="number" name="threshold" id="edit_threshold">
                    <button type="submit" class="save-btn">Save Changes</button>
                </form>
            </div>
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
                document.getElementById("edit_code").value = btn.dataset.code;
                document.getElementById("edit_name").value = btn.dataset.name;
                document.getElementById("edit_category").value = btn.dataset.category;
                document.getElementById("edit_quantity").value = btn.dataset.quantity;
                document.getElementById("edit_price").value = btn.dataset.price;
                document.getElementById("edit_threshold").value = btn.dataset.threshold;
                document.getElementById("edit_status").value = btn.dataset.status;

                const preview = document.getElementById("edit_picture_preview");
                if (btn.dataset.picture) {
                    preview.src = "../" + btn.dataset.picture;
                    preview.style.display = "block";
                } else preview.style.display = "none";

                editModal.style.display = "flex";
            });
        });

        document.getElementById('add_picture').onchange = function(e) {
            const preview = document.getElementById('add_picture_preview');
            preview.src = URL.createObjectURL(e.target.files[0]);
            preview.style.display = 'block';
        };

        document.getElementById('edit_picture').onchange = function(e) {
            const preview = document.getElementById('edit_picture_preview');
            preview.src = URL.createObjectURL(e.target.files[0]);
            preview.style.display = 'block';
        };

        window.onclick = (e) => {
            if (e.target == addModal) addModal.style.display = "none";
            if (e.target == editModal) editModal.style.display = "none";
        };
    </script>

    <script>
        // Add Product Preview 
        document.getElementById('add_picture').onchange = function(e) {
            const preview = document.getElementById('add_picture_preview');
            preview.src = URL.createObjectURL(e.target.files[0]);
            preview.style.display = 'block';
        };

        // Edit Product Preview 
        document.getElementById('edit_picture').onchange = function(e) {
            const preview = document.getElementById('edit_picture_preview');
            preview.src = URL.createObjectURL(e.target.files[0]);
            preview.style.display = 'block';
        };

        //  Show current image when editing 
        document.querySelectorAll(".edit-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                document.getElementById("edit_id").value = btn.dataset.id;
                document.getElementById("edit_code").value = btn.dataset.code;
                document.getElementById("edit_name").value = btn.dataset.name;
                document.getElementById("edit_category").value = btn.dataset.category;
                document.getElementById("edit_quantity").value = btn.dataset.quantity;
                document.getElementById("edit_price").value = btn.dataset.price;
                document.getElementById("edit_threshold").value = btn.dataset.threshold;

                const preview = document.getElementById("edit_picture_preview");
                if (btn.dataset.picture) {
                    preview.src = "../" + btn.dataset.picture;
                    preview.style.display = "block";
                } else {
                    preview.style.display = "none";
                }

                editModal.style.display = "flex";
            });
        });
    </script>
</body>

</html>