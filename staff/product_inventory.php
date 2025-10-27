<?php
include '../config.php';

// Pagination & Filters
$limit = 10;
$page = max(1, (int)($_GET['page_num'] ?? 1));
$start = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$filter_category = (int)($_GET['category_filter'] ?? 0);

// Fetch categories for filter
$categories = $conn->query("SELECT * FROM category ORDER BY category_name ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>IMS - Product Inventory</title>
    <link rel="stylesheet" href="../css/pdct_inv.css">
    <link rel="icon" href="../images/logo-teal.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .out-of-stock {
            background: #ffe6e6;
        }

        .status-sufficient {
            color: green;
        }

        .status-low {
            color: orange;
        }

        .status-critical {
            color: red;
        }

        .status-out\ of\ stock {
            color: darkred;
        }
    </style>
</head>

<body>
    <h1 class="page-title">Products & Inventory</h1>

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
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Total Qty</th>
                    <th>Reserved</th>
                    <th>Available</th>
                    <th>Status</th>
                    <th>Retail Price</th>
                </tr>
            </thead>
            <tbody id="inventory-body">
                <tr>
                    <td colspan="9">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="pagination">

    </div>

    <script>
        const search = encodeURIComponent('<?= $search ?>');
        const category = <?= $filter_category ?>;

        function fetchInventory() {
            fetch(`product_inventory_live.php?search=${search}&category_filter=${category}`)

                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('inventory-body');
                    tbody.innerHTML = '';

                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="10">No products found.</td></tr>';
                        return;
                    }

                    data.forEach(row => {
                        const tr = document.createElement('tr');
                        if (row.available_quantity <= 0) tr.classList.add('out-of-stock');

                        const statusLabel = row.status_label ?? 'Unknown';

                        tr.innerHTML = `
                        <td>${row.product_id}</td>
                        <td>${row.product_code}</td>
                        <td>${row.product_name}</td>
                        <td>${row.category_name}</td>
                        <td>${row.total_quantity}</td>
                        <td>${row.reserved_qty}</td>
                        <td>${row.available_quantity}</td>
                        <td class="status-${statusLabel.toLowerCase().replace(/ /g,'-')}">${statusLabel}</td>
                        <td>₱${parseFloat(row.retail_price).toFixed(2)}</td>
                    `;
                        tbody.appendChild(tr);
                    });


                })
                .catch(err => console.error('Error fetching inventory:', err));
        }

        // Initial fetch
        fetchInventory();

        // Auto-refresh every 5 seconds
        setInterval(fetchInventory, 5000);
    </script>
</body>

</html>