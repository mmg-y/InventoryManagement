<?php
include '../config.php';


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_suppliers'])) {
    $product_id = $_GET['product_id'];
    
    $stmt = $conn->prepare("
        SELECT 
            ps.product_supplier_id,
            ps.default_cost_price,
            s.supplier_id,
            s.name as supplier_name,
            s.contact,
            s.email,
            s.supplier_type
        FROM product_suppliers ps
        JOIN supplier s ON ps.supplier_id = s.supplier_id
        WHERE ps.product_id = ?
        ORDER BY s.name
    ");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $supplier_list = [];
    while ($sup = $result->fetch_assoc()) {
        $supplier_list[] = $sup;
    }
    
    header('Content-Type: application/json');
    echo json_encode($supplier_list);
    exit;
}





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
    $remarks = $_POST['remarks'] ?? null;

    if (!empty($supplier_id) && !empty($product_id) && $qty > 0 && $cost_price > 0) {
        $batch_number = generateBatchNumber();

        $stmt = $conn->prepare("INSERT INTO product_stocks 
            (product_id, supplier_id, batch_number, quantity, remaining_qty, cost_price, remarks, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'ordered', NOW(), NOW())");
        $stmt->bind_param("iisiddss", $product_id, $supplier_id, $batch_number, $qty, $qty, $cost_price, $remarks);
        $stmt->execute();
        $stmt->close();

        echo "<script>window.location.href='budegero.php?page=supplier_purchases&success=Purchase order created successfully';</script>";
        exit;
    } else {
        echo "<script>window.location.href='budegero.php?page=supplier_purchases&error=Invalid Input';</script>";
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
                    <th>Action</th>
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
                                    data-remarks="<?= htmlspecialchars($row['remarks'] ?? '') ?>"
                                    data-cancel="<?= htmlspecialchars($row['cancel_details'] ?? '') ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <!-- <a href="javascript:void(0)" class="delete-btn" onclick="deletePurchase(<?= $row['product_stock_id'] ?>)">
                                    <i class="fa fa-trash"></i>
                                </a> -->
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
        <h2>Create New Purchase Order</h2>

        <form method="POST" id="purchaseForm">
            <input type="hidden" name="add_purchase" value="1">

            <div class="input-group">
                <label>Product</label>
                <select name="product_id" id="productSelect" required>
                    <option value="">Select Product First</option>
                    <?php
                    $products = $conn->query("SELECT * FROM product ORDER BY product_name ASC");
                    while ($prod = $products->fetch_assoc()) {
                        echo "<option value='{$prod['product_id']}'>{$prod['product_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="input-group">
                <label>Supplier</label>
                <select name="supplier_id" id="supplierSelect" required disabled>
                    <option value="">Select Product First</option>
                </select>
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label>Cost Price (₱)</label>
                    <input type="number" step="0.01" id="costPrice" name="cost_price" readonly>
                </div>

                <div class="input-group">
                    <label>Batch Quantity</label>
                    <input type="number" name="qty" id="batchQty" required min="1" oninput="calculateTotal()">
                </div>
            </div>

            <div class="input-group">
                <label>Total Price (₱)</label>
                <input type="text" id="totalPrice" readonly class="total-price">
            </div>

            <div class="input-group">
                <label>Remarks</label>
                <input type="text" name="remarks" placeholder="Optional notes...">
            </div>

            <button type="submit" class="save-btn">Create Purchase Order</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span id="closeEdit" class="close">&times;</span>
    <h2>Edit Purchase</h2>
    <form method="POST">
      <input type="hidden" name="update_status_only" value="1">
      <input type="hidden" name="product_stock_id" id="edit_id">

      <label>Product</label>
      <select name="product_id" id="edit_product" disabled>
        <?php
        $products = $conn->query("SELECT * FROM product ORDER BY product_name ASC");
        while ($prod = $products->fetch_assoc()) {
          echo "<option value='{$prod['product_id']}'>{$prod['product_name']}</option>";
        }
        ?>
      </select>

      <label>Quantity</label>
      <input type="number" name="qty" id="edit_qty" readonly>

      <label>Cost Price (₱)</label>
      <input type="number" step="0.01" name="cost_price" id="edit_cost" readonly>

      <div class="status-section">
        <label id="statusLabel">Status</label>
        <select name="status" id="edit_status" required></select>
      </div>

      <div class="input-group" id="remarksGroup">
        <label id="remarksLabel">Remarks</label>
        <input type="text" name="remarks" id="edit_remarks" readonly>
      </div>

      <div class="input-group" id="cancelDetailsGroup" style="display:none;">
        <label>Cancellation Reason</label>
        <textarea id="cancelDetails" name="cancel_details" rows="3" placeholder="Empty details"></textarea>
      </div>

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

    const productSelect = document.getElementById('productSelect');
    const supplierSelect = document.getElementById('supplierSelect');
    const costPriceInput = document.getElementById('costPrice');
    const batchQtyInput = document.getElementById('batchQty');
    const totalPriceInput = document.getElementById('totalPrice');

    addBtn.onclick = () => addModal.style.display = "flex";
    closeAdd.onclick = () => addModal.style.display = "none";
    closeEdit.onclick = () => editModal.style.display = "none";



    productSelect.addEventListener('change', function() {
        const productId = this.value;
        
        if (productId) {
            fetch(`ajax/get_suppliers.php?product_id=${productId}`)
                .then(response => response.json())
                .then(suppliers => {
                    supplierSelect.innerHTML = '<option value="">Select Supplier</option>';
                    supplierSelect.disabled = false;
                    
                    suppliers.forEach(supplier => {
                        const option = document.createElement('option');
                        option.value = supplier.supplier_id;
                        option.textContent = `${supplier.supplier_name} (${supplier.supplier_type})`;
                        option.dataset.cost = supplier.default_cost_price;
                        supplierSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching suppliers:', error);
                    supplierSelect.innerHTML = '<option value="">Error loading suppliers</option>';
                });
        } else {
            supplierSelect.innerHTML = '<option value="">Select Product First</option>';
            supplierSelect.disabled = true;
            costPriceInput.value = '';
            totalPriceInput.value = '';
        }
    });

    supplierSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const costPrice = selectedOption.dataset.cost;
        
        if (costPrice) {
            costPriceInput.value = parseFloat(costPrice).toFixed(2);
            calculateTotal();
        } else {
            costPriceInput.value = '';
            totalPriceInput.value = '';
        }
    });

    function calculateTotal() {
        const costPrice = parseFloat(costPriceInput.value) || 0;
        const quantity = parseInt(batchQtyInput.value) || 0;
        const total = costPrice * quantity;
        
        totalPriceInput.value = `₱${total.toFixed(2)}`;
    }

    batchQtyInput.addEventListener('input', calculateTotal);

    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const id = btn.dataset.id;
            const product = btn.dataset.product;
            const qty = btn.dataset.qty;
            const cost = btn.dataset.cost;
            const status = btn.dataset.status.toLowerCase();
            const remarks = btn.dataset.remarks || "";
            const cancelText = btn.dataset.cancel || "";
            const cancelGroup = document.getElementById("cancelDetailsGroup");
            const cancelInput = document.getElementById("cancelDetails");

            if (!cancelGroup || !cancelInput) {
                console.error("Cancel reason elements missing in DOM");
                return;
            }

            document.getElementById("edit_id").value = id;
            document.getElementById("edit_product").value = product;
            document.getElementById("edit_qty").value = qty;
            document.getElementById("edit_cost").value = cost;
            document.getElementById("edit_remarks").value = remarks;
            

            const statusSelect = document.getElementById("edit_status");
            const remarksLabel = document.getElementById("remarksLabel");
            const statusLabel = document.getElementById("statusLabel");
            const remarksGroup = document.getElementById("remarksGroup");
            const saveBtn = document.querySelector("#editModal .save-btn");

            editModal.classList.remove("cancelled");
            statusSelect.innerHTML = "";
            statusSelect.disabled = false;
            document.getElementById("edit_remarks").readOnly = true;
            saveBtn.disabled = true;

            let optionsHTML = "";

            optionsHTML += `<option value="${status}" selected>${status.charAt(0).toUpperCase() + status.slice(1)}</option>`;

            if (status === "ordered") {
                optionsHTML += `
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                `;
                statusLabel.textContent = "Update Status";
                remarksLabel.textContent = "Remarks";
            } 
            else if (status === "received") {
                optionsHTML += `
                    <option value="active">Active</option>
                    <option value="pulled out">Pulled Out</option>
                `;
                statusLabel.textContent = "Update Status";
                remarksLabel.textContent = "Remarks";
            } 
            else if (status === "active") {
                optionsHTML += `<option value="pulled out">Pulled Out</option>`;
                statusLabel.textContent = "Update Status";
                remarksLabel.textContent = "Remarks";
            } 
            else if (status === "pulled out") {
                optionsHTML += `<option value="active">Active</option>`;
                statusLabel.textContent = "Update Status";
                remarksLabel.textContent = "Remarks";
            } 
            else if (status === "cancelled") {
                optionsHTML = `<option value="cancelled" selected>Cancelled</option>`;
                statusLabel.textContent = "Status";
                statusSelect.disabled = true;

                remarksLabel.textContent = "Remarks";
                cancelInput.value = cancelText;
                cancelInput.readOnly = true;
                cancelGroup.style.display = "block";

                document.getElementById("edit_remarks").readOnly = false;
                editModal.classList.add("cancelled");
            }
            else {
                optionsHTML = `<option value="${status}" selected>${status.charAt(0).toUpperCase() + status.slice(1)}</option>`;
                statusSelect.disabled = true;
                remarksLabel.textContent = "Remarks";
            }

            statusSelect.innerHTML = optionsHTML;

            

            if (status === "cancelled") {
                cancelGroup.style.display = "block";
                cancelInput.required = false;
                cancelInput.readOnly = true;
            } else {
                cancelGroup.style.display = "none";
                cancelInput.required = false;
                cancelInput.readOnly = false;
            }

            statusSelect.addEventListener("change", () => {
                if (statusSelect.value === "cancelled") {
                    cancelGroup.style.display = "block";
                    cancelInput.required = true;
                    cancelInput.readOnly = false;
                } else {
                    cancelGroup.style.display = "none";
                    cancelInput.required = false;
                    cancelInput.readOnly = false;
                }
            });

            const opt = Array.from(statusSelect.options).find(o => o.value === status);
            if (opt) opt.selected = true;

            const originalStatus = status;




            statusSelect.addEventListener("change", () => {
                saveBtn.disabled = (statusSelect.value === originalStatus);
            }, { once: true });

            const modalHeader = editModal.querySelector("h2");
            if (status === "pulled out") {
                modalHeader.textContent = "Reactivate Batch";
            } else if (status === "active") {
                modalHeader.textContent = "Pull Out Batch";
            } else {
                modalHeader.textContent = "Edit Purchase";
            }

            editModal.style.display = "flex";
        });
    });

    window.onclick = (e) => {
        if (e.target == addModal) addModal.style.display = "none";
        if (e.target == editModal) editModal.style.display = "none";
    };

    addModal.addEventListener('hide', function() {
        document.getElementById("purchaseForm").reset();
        supplierSelect.innerHTML = '<option value="">Select Product First</option>';
        supplierSelect.disabled = true;
        costPriceInput.value = '';
        totalPriceInput.value = '';
    });


    document.getElementById("purchaseForm").addEventListener("submit", e => {
        e.preventDefault();
            const formData = new FormData(e.target);

            fetch("ajax/add_purchase.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.error);
                }
            })
            .catch(err => console.error('Error adding purchase:', err));
        });

      document.querySelector("#editModal form").addEventListener("submit", e => {
            e.preventDefault();

            const formData = new FormData();
            formData.append("update_status_only", 1);
            formData.append("product_stock_id", document.getElementById("edit_id").value);
            formData.append("status", document.getElementById("edit_status").value);
            formData.append("remarks", document.getElementById("edit_remarks").value || "");
            formData.append("cancel_details", document.getElementById("cancelDetails").value || "");

            fetch("ajax/update_status.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert("❌ " + (data.error || "Unknown error"));
                }
            })
            .catch(err => console.error("Error updating status:", err));
        });

    function deletePurchase(id) {
        if (!confirm("Delete this purchase?")) return;

        fetch(`ajax/delete_purchase.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.error);
                }
            })
            .catch(err => console.error('Error deleting purchase:', err));
    }

    const cancelDetails = document.getElementById("cancelDetails");
    if (cancelDetails) {
    cancelDetails.addEventListener("input", function () {
        this.style.height = "auto";
        this.style.height = this.scrollHeight + "px";
    });
    }

</script>