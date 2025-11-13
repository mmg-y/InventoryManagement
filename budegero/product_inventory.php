<?php
include '../config.php';

// Auto-generate next product code
$lastProduct = $conn->query("SELECT product_code FROM product ORDER BY product_id DESC LIMIT 1")->fetch_assoc();
$nextCode = $lastProduct ? 'P' . (intval(substr($lastProduct['product_code'], 1)) + 1) : 'P1001';

// Fetch dropdown data
$lastProduct = $conn->query("SELECT product_code FROM product ORDER BY product_id DESC LIMIT 1")->fetch_assoc();
$nextCode = $lastProduct ? 'P' . (intval(substr($lastProduct['product_code'], 1)) + 1) : 'P1001';

// Fetch dropdown data
$categories = $conn->query("SELECT * FROM category ORDER BY category_name ASC");
$retails = $conn->query("SELECT * FROM retail_variables ORDER BY name ASC");
$statuses = $conn->query("SELECT * FROM status ORDER BY status_label ASC");

// Helper function to calculate status based on threshold
function getStatusId($threshold, $conn)
{
    $statuses = [];
    $res = $conn->query("SELECT * FROM status");
    while ($row = $res->fetch_assoc()) {
        $statuses[strtolower($row['status_label'])] = $row['status_id'];
    }

    // Define thresholds 
    if ($threshold >= 50) return $statuses['sufficient'];
    if ($threshold >= 20) return $statuses['low'];
    if ($threshold >= 5)  return $statuses['critical'];
    return $statuses['out of stock'];
}

// Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $code      = trim($_POST['product_code']);
    $name      = trim($_POST['product_name']);
    $category  = (int)($_POST['category_id'] ?? 0);
    $retail    = (int)($_POST['retail_id'] ?? 0);
    $quantity  = (int)($_POST['total_quantity'] ?? 0);
    $threshold = (int)($_POST['threshold'] ?? 0);

    // Handle file upload
    $picturePath = '';
    if (isset($_FILES['product_picture']) && $_FILES['product_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['product_picture']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $fileExtension;
        $picturePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['product_picture']['tmp_name'], $picturePath)) {
            $picturePath = 'uploads/products/' . $fileName;
        } else {
            $picturePath = '';
        }
    }

    // Determine status automatically
    $status_id = getStatusId($threshold, $conn);

    // Insert product
    $sql = $conn->prepare("
        INSERT INTO product 
        (product_code, product_name, product_picture, category, retail_id, total_quantity, reserved_qty, threshold_id, threshold, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())
    ");
    $sql->bind_param("sssiiiii", $code, $name, $picturePath, $category, $retail, $quantity, $status_id, $threshold);
    $sql->execute();

    echo "<script>window.location.href='budegero.php?page=product_inventory&msg=added';</script>";
    exit;
}

// Delete Product
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get product picture path before deleting to remove the file
    $product = $conn->query("SELECT product_picture FROM product WHERE product_id = $id")->fetch_assoc();
    if ($product && !empty($product['product_picture']) && file_exists('../' . $product['product_picture'])) {
        unlink('../' . $product['product_picture']);
    }
    
    $conn->query("DELETE FROM product WHERE product_id = $id");
    echo "<script>window.location.href='budegero.php?page=product_inventory&msg=deleted';</script>";
    exit;
}

// Update Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $id         = (int)$_POST['product_id'];
    $code       = trim($_POST['product_code']);
    $name       = trim($_POST['product_name']);
    $categoryId = (int)($_POST['category'] ?? 0);
    $retailId   = (int)($_POST['retail_id'] ?? 0);
    $quantity   = (int)($_POST['quantity'] ?? 0);
    $threshold  = (int)($_POST['threshold'] ?? 0);

    // Handle file upload
    $picturePath = '';
    if (isset($_FILES['product_picture']) && $_FILES['product_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Delete old picture if exists
        $oldProduct = $conn->query("SELECT product_picture FROM product WHERE product_id = $id")->fetch_assoc();
        if ($oldProduct && !empty($oldProduct['product_picture']) && file_exists('../' . $oldProduct['product_picture'])) {
            unlink('../' . $oldProduct['product_picture']);
        }
        
        $fileExtension = pathinfo($_FILES['product_picture']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $fileExtension;
        $picturePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['product_picture']['tmp_name'], $picturePath)) {
            $picturePath = 'uploads/products/' . $fileName;
            
            // Update with new picture
            $sql = $conn->prepare("
                UPDATE product 
                SET product_code=?, product_name=?, product_picture=?, category=?, retail_id=?, total_quantity=?, threshold_id=?, threshold=?, updated_at=NOW()
                WHERE product_id=?
            ");
            $sql->bind_param("sssiiiiii", $code, $name, $picturePath, $categoryId, $retailId, $quantity, $status_id, $threshold, $id);
        } else {
            // Update without changing picture
            $sql = $conn->prepare("
                UPDATE product 
                SET product_code=?, product_name=?, category=?, retail_id=?, total_quantity=?, threshold_id=?, threshold=?, updated_at=NOW()
                WHERE product_id=?
            ");
            $sql->bind_param("ssiiiiii", $code, $name, $categoryId, $retailId, $quantity, $status_id, $threshold, $id);
        }
    } else {
        // Update without changing picture
        $sql = $conn->prepare("
            UPDATE product 
            SET product_code=?, product_name=?, category=?, retail_id=?, total_quantity=?, threshold_id=?, threshold=?, updated_at=NOW()
            WHERE product_id=?
        ");
        $sql->bind_param("ssiiiiii", $code, $name, $categoryId, $retailId, $quantity, $status_id, $threshold, $id);
    }

    // Determine status automatically
    $status_id = getStatusId($threshold, $conn);

    $sql->execute();

    echo "<script>window.location.href='budegero.php?page=product_inventory&msg=updated';</script>";
    exit;
}

// Pagination & Filters
$limit = 10;
$page = max(1, (int)($_GET['page_num'] ?? 1));
$start = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$filter_category = (int)($_GET['category_filter'] ?? 0);

$where = [];
if ($search !== '') {
    $searchSafe = $conn->real_escape_string($search);
    $where[] = "(p.product_code LIKE '%$searchSafe%' OR p.product_name LIKE '%$searchSafe%')";
}
if ($filter_category > 0) {
    $where[] = "p.category = $filter_category";
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

$total = $conn->query("SELECT COUNT(*) as c FROM product p $where_sql")->fetch_assoc()['c'];
$pages = ceil($total / $limit);

// Sorting
$allowed_sort = ['product_id', 'product_code', 'product_name', 'category', 'total_quantity', 'threshold'];
$sort = in_array($_GET['sort'] ?? '', $allowed_sort) ? $_GET['sort'] : 'product_id';
$order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

// Fetch Products
$result = $conn->query("
    SELECT p.*, 
           c.category_name, 
           s.status_label, 
           r.name AS retail_type, 
           r.percent AS retail_percent
    FROM product p
    LEFT JOIN category c ON p.category = c.category_id
    LEFT JOIN status s ON p.threshold_id = s.status_id
    LEFT JOIN retail_variables r ON p.retail_id = r.retail_id
    $where_sql
    ORDER BY $sort $order
    LIMIT $start, $limit
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>IMS - Product Inventory</title>
    <link rel="stylesheet" href="../css/product_inv.css">
    <link rel="icon" href="../images/logo-teal.png" type="images/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <h1 class="page-title"> Products & Inventory</h1>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert <?= ($_GET['msg'] == 'added' || $_GET['msg'] == 'updated') ? 'success' : ($_GET['msg'] == 'deleted' ? 'danger' : 'error') ?>">
            <?= ($_GET['msg'] == 'added') ? "Product added successfully!" : (($_GET['msg'] == 'updated') ? "Product updated successfully!" : (($_GET['msg'] == 'deleted') ? "Product deleted successfully!" : "")) ?>
        </div>
    <?php endif; ?>

    <div class="actions-bar">
        <form method="GET" class="search-form">
            <input type="hidden" name="page" value="product_inventory">
            <input type="text" name="search" placeholder="Search by code or name..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fa fa-search"></i></button>

            <div class="filter-wrapper">
                <select name="category_filter" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php $categories->data_seek(0);
                    while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $filter_category == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <i class="fa fa-filter"></i>
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
                        'product_name' => 'Name',
                        'product_picture' => 'Picture',
                        'category' => 'Category',
                        'total_quantity' => 'Total Quantity',
                        'reserved_qty' => 'Reserved',
                        'threshold_id' => 'Status',
                        'threshold' => 'Threshold',
                        'retail_id' => 'Retail Type'
                    ];
                    foreach ($columns as $col => $label):
                        if ($col === 'product_picture') {
                            echo "<th>$label</th>";
                            continue;
                        }
                        $indicator = ($sort == $col) ? ($order === 'ASC' ? '▲' : '▼') : '';
                        $newOrder = ($sort == $col && $order === 'ASC') ? 'DESC' : 'ASC';
                        echo "<th><a href='?page=product_inventory&sort=$col&order=$newOrder&search=" . urlencode($search) . "&category_filter=$filter_category'>$label $indicator</a></th>";
                    endforeach;
                    ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['product_id'] ?></td>
                            <td><?= $row['product_code'] ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td class="product-picture-cell">
                                <?php if (!empty($row['product_picture'])): ?>
                                    <img src="../<?= htmlspecialchars($row['product_picture']) ?>" 
                                         alt="<?= htmlspecialchars($row['product_name']) ?>" 
                                         class="product-picture">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                            <td><?= $row['total_quantity'] ?></td>
                            <td><?= $row['reserved_qty'] ?></td>
                            <td class="status-<?= strtolower($row['status_label']) ?>">
                                <?= ucfirst($row['status_label']) ?>
                            </td>
                            <td><?= $row['threshold'] ?></td>
                            <td><?= htmlspecialchars($row['retail_type']) ?> (<?= $row['retail_percent'] ?>%)</td>
                            <td class="actions">
                                <button class="edit-btn"
                                    data-id="<?= $row['product_id'] ?>"
                                    data-code="<?= $row['product_code'] ?>"
                                    data-name="<?= htmlspecialchars($row['product_name']) ?>"
                                    data-category="<?= $row['category'] ?>"
                                    data-retail="<?= $row['retail_id'] ?>"
                                    data-quantity="<?= $row['total_quantity'] ?>"
                                    data-threshold="<?= $row['threshold'] ?>"
                                    data-status="<?= $row['threshold_id'] ?>"
                                    data-picture="<?= !empty($row['product_picture']) ? '../' . htmlspecialchars($row['product_picture']) : '' ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <a href="?page=product_inventory&delete=<?= $row['product_id'] ?>" onclick="return confirm('Delete this product?')" class="delete-btn"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="11">No products found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=product_inventory&page_num=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>" class="<?= ($i == $page ? 'active' : '') ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeAdd">&times;</span>
            <h2>Add Product</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_product" value="1">

                <div class="input-group">
                    <label>Product Code</label>
                    <input type="text" name="product_code" value="<?= $nextCode ?>" readonly>
                </div>

                <div class="input-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" required>
                </div>

                <div class="input-group">
                    <label>Product Picture</label>
                    <input type="file" name="product_picture" accept="image/*" id="add_picture_input">
                    <img id="add_picture_preview" src="" alt="Preview"
                        style="display:none; width:80px; height:80px; border-radius:6px; object-fit:cover; margin-top:10px; border:1px solid #ccc;">
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label>Category</label>
                        <select name="category_id" required>
                            <?php $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Retail Type</label>
                        <select name="retail_id" required>
                            <?php $retails->data_seek(0);
                            while ($r = $retails->fetch_assoc()): ?>
                                <option value="<?= $r['retail_id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= $r['percent'] ?>%)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label>Total Quantity</label>
                        <input type="number" name="total_quantity" required>
                    </div>

                    <div class="input-group">
                        <label>Threshold</label>
                        <input type="number" name="threshold" required>
                    </div>
                </div>

                <button type="submit" class="save-btn">Add Product</button>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeEdit">&times;</span>
            <h2>Edit Product</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_product" value="1">
                <input type="hidden" name="product_id" id="edit_id">

                <div class="input-group">
                    <label>Product Code</label>
                    <input type="text" name="product_code" id="edit_code" required>
                </div>

                <div class="input-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="edit_name" required>
                </div>

                <div class="input-group">
                    <label>Product Picture</label>
                    <input type="file" name="product_picture" accept="image/*" id="edit_picture_input">
                    <img id="edit_picture_preview" src="" alt="Preview"
                        style="width:80px; height:80px; border-radius:6px; object-fit:cover; margin-top:10px; border:1px solid #ccc;">
                    <div style="font-size:12px; color:#666; margin-top:5px;">Leave empty to keep current picture</div>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label>Category</label>
                        <select name="category_id" id="edit_category" required>
                            <?php $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Retail Type</label>
                        <select name="retail_id" id="edit_retail" required>
                            <?php $retails->data_seek(0);
                            while ($r = $retails->fetch_assoc()): ?>
                                <option value="<?= $r['retail_id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= $r['percent'] ?>%)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label>Total Quantity</label>
                        <input type="number" name="quantity" id="edit_quantity" required>
                    </div>

                    <div class="input-group">
                        <label>Threshold</label>
                        <input type="number" name="threshold" id="edit_threshold" required>
                    </div>
                </div>

                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        // Modals
        const addModal = document.getElementById("addModal");
        const editModal = document.getElementById("editModal");

        // Buttons
        const addBtn = document.querySelector(".add-btn");
        const closeAdd = document.getElementById("closeAdd");
        const closeEdit = document.getElementById("closeEdit");

        // Open modals
        addBtn.onclick = () => addModal.style.display = "flex";

        // Close buttons
        closeAdd.onclick = () => addModal.style.display = "none";
        closeEdit.onclick = () => editModal.style.display = "none";

        // Click outside modal to close
        [addModal, editModal].forEach(modal => {
            modal.onclick = e => {
                if (e.target === modal) modal.style.display = "none";
            };
        });

        // Edit button population
        document.querySelectorAll(".edit-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                document.getElementById("edit_id").value = btn.dataset.id;
                document.getElementById("edit_code").value = btn.dataset.code;
                document.getElementById("edit_name").value = btn.dataset.name;
                document.getElementById("edit_category").value = btn.dataset.category;
                document.getElementById("edit_retail").value = btn.dataset.retail;
                document.getElementById("edit_quantity").value = btn.dataset.quantity;
                document.getElementById("edit_threshold").value = btn.dataset.threshold;
                
                // Set picture preview
                const editPreview = document.getElementById("edit_picture_preview");
                if (btn.dataset.picture) {
                    editPreview.src = btn.dataset.picture;
                    editPreview.style.display = "block";
                } else {
                    editPreview.src = "";
                    editPreview.style.display = "none";
                }

                // Open modal
                editModal.style.display = "flex";
            });
        });

        // Add modal picture preview
        const addPictureInput = document.getElementById("add_picture_input");
        const addPicturePreview = document.getElementById("add_picture_preview");

        addPictureInput.addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    addPicturePreview.src = e.target.result;
                    addPicturePreview.style.display = "block";
                };
                reader.readAsDataURL(file);
            } else {
                addPicturePreview.src = "";
                addPicturePreview.style.display = "none";
            }
        });

        // Edit modal picture preview
        const editPictureInput = document.getElementById("edit_picture_input");
        const editPicturePreview = document.getElementById("edit_picture_preview");

        editPictureInput.addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    editPicturePreview.src = e.target.result;
                    editPicturePreview.style.display = "block";
                };
                reader.readAsDataURL(file);
            }
        });
    </script>

</body>

</html>