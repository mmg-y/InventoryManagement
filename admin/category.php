<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $retail_id = intval($_POST['retail_id']);

    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO category (category_name, retail_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("si", $category_name, $retail_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: ?page=category");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);
    $retail_id = intval($_POST['retail_id']);

    $stmt = $conn->prepare("UPDATE category SET category_name = ?, retail_id = ?, updated_at = NOW() WHERE category_id = ?");
    $stmt->bind_param("sii", $category_name, $retail_id, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: ?page=category");
    exit;
}

if (isset($_POST['delete_category'])) {
    $id = intval($_POST['category_id']);
    $conn->query("DELETE FROM category WHERE category_id = $id");
    header("Location: ?page=category");
    exit;
}

$categories = $conn->query("
    SELECT c.*, r.name AS retail_name, r.retail_id
    FROM category c
    LEFT JOIN retail_variables r ON c.retail_id = r.retail_id
    ORDER BY c.category_id ASC
");

$retailValues = $conn->query("SELECT * FROM retail_variables ORDER BY name ASC");
?>

<link rel="stylesheet" href="../css/category.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="content">
    <h2 class="page-title">Category Management</h2>

    <div class="card">
        <h3>Add New Category</h3>
        <form method="POST" class="form-inline">
            <input type="text" name="category_name" placeholder="Category Name (e.g. Beverages)" required>
            <select name="retail_id" required>
                <option value="">Select Retail Type</option>
                <?php while ($r = $retailValues->fetch_assoc()): ?>
                    <option value="<?= $r['retail_id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= $r['percent'] ?>%)</option>
                <?php endwhile; ?>
            </select>
            <button type="submit" name="add_category" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing Categories</h3>
        <table class="styled-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category Name</th>
                    <th>Retail Type</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <tr>
                        <td><?= $cat['category_id'] ?></td>
                        <td><?= htmlspecialchars($cat['category_name']) ?></td>
                        <td><?= htmlspecialchars($cat['retail_name'] ?? 'N/A') ?></td>
                        <td><?= $cat['created_at'] ?></td>
                        <td><?= $cat['updated_at'] ?></td>
                        <td class="table-actions">
                            <button
                                class="btn btn-primary editBtn"
                                data-id="<?= $cat['category_id'] ?>"
                                data-name="<?= htmlspecialchars($cat['category_name']) ?>"
                                data-retail="<?= $cat['retail_id'] ?>">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                                <button type="submit" name="delete_category" class="btn btn-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Category</h3>
        <form method="POST">
            <input type="hidden" name="category_id" id="edit_id">
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="category_name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Retail Type</label>
                <select name="retail_id" id="edit_retail" required>
                    <option value="">Select Retail Type</option>
                    <?php
                    $retailValues2 = $conn->query("SELECT * FROM retail_variables ORDER BY name ASC");
                    while ($r2 = $retailValues2->fetch_assoc()):
                    ?>
                        <option value="<?= $r2['retail_id'] ?>"><?= htmlspecialchars($r2['name']) ?> (<?= $r2['percent'] ?>%)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" name="update_category" class="btn-save">
                Save Changes
            </button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('editModal');
    const closeModal = document.querySelector('.close');

    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_id').value = btn.dataset.id;
            document.getElementById('edit_name').value = btn.dataset.name;
            document.getElementById('edit_retail').value = btn.dataset.retail;
            modal.style.display = 'flex';
        });
    });

    closeModal.onclick = () => modal.style.display = 'none';
    window.onclick = e => {
        if (e.target == modal) modal.style.display = 'none';
    }
</script>