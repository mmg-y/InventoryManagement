<?php
include '../config.php';

$sql = "
SELECT c.cart_id, c.seller, c.total, c.status, c.created_at,
       ci.product_id, ci.qty, ci.price, p.product_name
FROM carts c
JOIN cart_items ci ON c.cart_id = ci.cart_id
JOIN product p ON ci.product_id = p.product_id
WHERE c.status = 'completed'
ORDER BY c.created_at DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Sales Record</title>
    <link rel="stylesheet" href="../css/sales_record.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <div class="main">
        <h1>Sales Record</h1>
        <div class="table-container">

            <div class="actions-bar">
                <form class="search-form" method="GET">
                    <input type="text" name="search" placeholder="Search by seller, product...">
                    <button type="submit"><i class="fa fa-search"></i></button>
                </form>
                <button class="add-btn"><i class="fa fa-plus"></i> Add Sale</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Seller</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Sale Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentCart = 0;
                    $subtotal = 0;

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            if ($currentCart != $row['cart_id']) {
                                if ($currentCart != 0) {
                                    echo "<tr>
                                        <td colspan='5' style='text-align:right;'><strong>Subtotal:</strong></td>
                                        <td><strong>$subtotal</strong></td>
                                        <td colspan='3'></td>
                                      </tr>";
                                }
                                $currentCart = $row['cart_id'];
                                $subtotal = 0;
                            }

                            $lineTotal = $row['qty'] * $row['price'];
                            $subtotal += $lineTotal;

                            echo "<tr>
                                <td>{$row['cart_id']}</td>
                                <td>{$row['seller']}</td>
                                <td>{$row['product_name']}</td>
                                <td>{$row['qty']}</td>
                                <td>{$row['price']}</td>
                                <td>$lineTotal</td>
                                <td>{$row['status']}</td>
                                <td>{$row['created_at']}</td>
                                <td class='actions'>
                                    <button class='edit-btn'><i class='fa fa-edit'></i></button>
                                    <button class='delete-btn'><i class='fa fa-trash'></i></button>
                                </td>
                              </tr>";
                        }

                        echo "<tr>
                            <td colspan='5' style='text-align:right;'><strong>Subtotal:</strong></td>
                            <td><strong>$subtotal</strong></td>
                            <td colspan='3'></td>
                          </tr>";
                    } else {
                        echo "<tr><td colspan='9'>No sales records found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>