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

    <h1>Products & Inventory</h1>

    <div class="table-container">
        <button class="add-btn"><i class="fa-solid fa-plus"></i> Add Product</button>
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Price</th>
                    <th>Supplier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>#P1001</td>
                    <td>Wireless Mouse</td>
                    <td>Electronics</td>
                    <td>120</td>
                    <td>$25.00</td>
                    <td>TechSupply Co.</td>
                    <td class="actions">
                        <button class="edit-btn"><i class="fa-solid fa-pen"></i></button>
                        <button class="delete-btn"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
                <tr>
                    <td>#P1002</td>
                    <td>Office Chair</td>
                    <td>Furniture</td>
                    <td>45</td>
                    <td>$120.00</td>
                    <td>FurniPro Ltd.</td>
                    <td class="actions">
                        <button class="edit-btn"><i class="fa-solid fa-pen"></i></button>
                        <button class="delete-btn"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
                <tr>
                    <td>#P1003</td>
                    <td>Notebook</td>
                    <td>Stationery</td>
                    <td>300</td>
                    <td>$3.50</td>
                    <td>PaperWorks</td>
                    <td class="actions">
                        <button class="edit-btn"><i class="fa-solid fa-pen"></i></button>
                        <button class="delete-btn"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</body>

</html>