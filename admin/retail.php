<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_retail'])) {
    $name = trim($_POST['name']);
    $percent = floatval($_POST['percent']);

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO retail_variables (name, percent, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("sd", $name, $percent);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: ?page=retail_values");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_retail'])) {
    $id = intval($_POST['retail_id']);
    $percent = floatval($_POST['percent']);
    $name = trim($_POST['name']);

    $stmt = $conn->prepare("UPDATE retail_variables SET name = ?, percent = ?, updated_at = NOW() WHERE retail_id = ?");
    $stmt->bind_param("sdi", $name, $percent, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: ?page=retail_values");
    exit;
}

$retailValues = $conn->query("SELECT * FROM retail_variables ORDER BY retail_id ASC");
?>

<link rel="stylesheet" href="../css/retail.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="content">
    <h2 class="page-title">Retail Value</h2>

    <!-- <div class="card">
        <h3>Add New Retail Value</h3>
        <form method="POST" class="form-inline">
            <input type="text" name="name" placeholder="Retail Type Name (e.g. Special Retail)" required>
            <input type="number" step="0.01" name="percent" placeholder="Percent (e.g. 10 for 10%)" required>
            <button type="submit" name="add_retail" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add
            </button>
        </form>
    </div> -->

    <div class="card">
        <h3>Existing Retail Values</h3>
        <table class="styled-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Retail Type</th>
                    <th>Percent (%)</th>
                    <th>Last Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $retailValues->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['retail_id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['percent'] ?></td>
                        <td><?= $row['updated_at'] ?></td>
                        <td>
                            <button class="btn btn-primary editBtn"
                                data-id="<?= $row['retail_id'] ?>"
                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                data-percent="<?= $row['percent'] ?>">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Retail Value</h3>
        <form method="POST">
            <input type="hidden" name="retail_id" id="edit_id">
            <div class="form-group">
                <label>Retail Type Name</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Percent (%)</label>
                <input type="number" step="0.01" name="percent" id="edit_percent" required>
            </div>
            <button type="submit" name="update_retail" class="btn-save">
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
            document.getElementById('edit_percent').value = btn.dataset.percent;
            modal.style.display = 'flex';
        });
    });

    closeModal.onclick = () => modal.style.display = 'none';
    window.onclick = e => {
        if (e.target == modal) modal.style.display = 'none';
    }
</script>