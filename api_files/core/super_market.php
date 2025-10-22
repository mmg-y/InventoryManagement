<?php

    class SuperMarket {
        private $conn;
        private $table = 'user';

        public $username;
        public $password;

        public function __construct($db) {
            $this->conn = $db;
        }




        public function login() {
            $query = "SELECT id, first_name, last_name, contact, email, username, password, type, created_at
                    FROM " . $this->table . " 
                    WHERE username = ? 
                    LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $this->username = htmlspecialchars(strip_tags($this->username));
            $stmt->bind_param('s', $this->username);
            $stmt->execute();

            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                // verify typed password against stored hash
                if (password_verify($this->password, $user['password'])) {
                    // remove password before returning
                    unset($user['password']);
                    return $user;   
                }
            }
            return null;  // login failed
        }


        public function getCategoriesAndItems() {
            // Query to get categories and their products
            $query = "
                SELECT 
                    c.category_id,
                    c.category_name,
                    p.product_id,
                    p.product_code,
                    p.product_name,
                    p.product_picture,
                    p.quantity,
                    p.reserved_qty,
                    p.price,
                    p.notice_status,
                    p.threshold
                FROM category c
                LEFT JOIN product p ON c.category_id = p.category
                ORDER BY c.category_name, p.product_name
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            $categories = [];

            while ($row = $result->fetch_assoc()) {
                $catId = $row['category_id'];

                if (!isset($categories[$catId])) {
                    $categories[$catId] = [
                        'category_id' => $catId,
                        'category_name' => $row['category_name'],
                        'products' => []
                    ];
                }

                // If product exists, add it to category
                if ($row['product_id'] != null) {
                    $categories[$catId]['products'][] = [
                        'product_id' => $row['product_id'],
                        'product_code' => $row['product_code'],
                        'product_name' => $row['product_name'],
                        'product_picture' => $row['product_picture'],
                        'quantity' => $row['quantity'],
                        'reserved_qty' => $row['reserved_qty'],
                        'price' => $row['price'],
                        'notice_status' => $row['notice_status'],
                        'threshold' => $row['threshold']
                    ];
                }
            }

            return array_values($categories);
        }


        //GET PRODUCTS WITH STATUS
        public function getProductsWithStatus() {
            $query = "
                SELECT 
                    p.product_id,
                    p.product_code,
                    p.product_name,
                    p.product_picture,
                    p.quantity,
                    p.reserved_qty,
                    p.price,
                    p.notice_status,
                    p.threshold,
                    c.category_id,
                    c.category_name,
                    s.status_id,
                    s.status_label
                FROM product p
                INNER JOIN category c ON p.category = c.category_id
                INNER JOIN status s ON p.notice_status = s.status_id
                ORDER BY c.category_name, p.product_name
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }

            return $products;
        }


        //Get Warehouse Inventory (Batches + Products + Suppliers)
        public function getWarehouseInventory() {
            $query = "
                SELECT 
                    ps.product_stock_id AS batchId,
                        ps.batch_number AS batchCode,
                        p.product_name AS itemName,
                        s.name AS supplierName,
                        ps.remaining_qty AS quantity,
                        ps.quantity AS totalUnits,
                        ps.status AS status,
                        ps.updated_at AS dateAdded,
                        ps.cost_price AS pricePerUnit,
                        (ps.cost_price * ps.quantity) AS totalCost,
                        (ps.remaining_qty * ps.cost_price) AS totalValue
                    FROM product_stocks ps
                    JOIN product p ON ps.product_id = p.product_id
                    JOIN supplier s ON ps.supplier_id = s.supplier_id
                    WHERE ps.status IN ('active', 'pulled out')
                    ORDER BY ps.updated_at DESC;
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            $batches = [];
            while ($row = $result->fetch_assoc()) {
                $batches[] = $row;
            }

            return $batches;
        }


        public function updateBatchStatus($batchId, $status)
        {
            try {
                $this->conn->begin_transaction();

                //Get product_id of the batch being updated
                $queryGetProduct = "SELECT product_id FROM product_stocks WHERE product_stock_id = ?";
                $stmt = $this->conn->prepare($queryGetProduct);
                $stmt->bind_param('i', $batchId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    throw new Exception("Batch not found.");
                }

                $row = $result->fetch_assoc();
                $productId = (int)$row['product_id'];

                //FIFO: If setting to 'active', deactivate currently active batches of the same product
                if (strtolower($status) === 'active') {
                    $queryDeactivate = "
                        UPDATE product_stocks 
                        SET status = 'pulled out', updated_at = NOW() 
                        WHERE product_id = ? AND status = 'active' AND product_stock_id != ?";
                    $stmtDeactivate = $this->conn->prepare($queryDeactivate);
                    $stmtDeactivate->bind_param('ii', $productId, $batchId);
                    $stmtDeactivate->execute();
                }

                //Update the selected batch's status
                $queryUpdate = "
                    UPDATE product_stocks 
                    SET status = ?, updated_at = NOW() 
                    WHERE product_stock_id = ?";
                $stmtUpdate = $this->conn->prepare($queryUpdate);
                $stmtUpdate->bind_param('si', $status, $batchId);

                if (!$stmtUpdate->execute()) {
                    throw new Exception("Failed to update batch: " . $stmtUpdate->error);
                }

                //Recalculate totals and average cost from all active batches
                $queryRecalc = "
                    SELECT 
                        COALESCE(SUM(remaining_qty), 0) AS total_qty,
                        COALESCE(AVG(cost_price), 0) AS avg_cost
                    FROM product_stocks
                    WHERE product_id = ? AND status = 'active'
                ";
                $stmtRecalc = $this->conn->prepare($queryRecalc);
                $stmtRecalc->bind_param('i', $productId);
                $stmtRecalc->execute();
                $res = $stmtRecalc->get_result()->fetch_assoc();
                $totalQty = (float)$res['total_qty'];
                $avgCost = (float)$res['avg_cost'];

                //Only update the product total & cost price if status is 'active'
                if (strtolower($status) === 'active') {
                    $queryUpdateProduct = "
                        UPDATE product p
                        JOIN retail_variables r ON p.retail_id = r.retail_id
                        SET 
                            p.total_quantity = ?, 
                            p.cost_price = ROUND((? + (? * r.percent / 100)), 2),
                            p.updated_at = NOW()
                        WHERE p.product_id = ?
                    ";
                    $stmtUpdateProduct = $this->conn->prepare($queryUpdateProduct);
                    $stmtUpdateProduct->bind_param('dddi', $totalQty, $avgCost, $avgCost, $productId);

                    if (!$stmtUpdateProduct->execute()) {
                        throw new Exception("Failed to update product cost/quantity: " . $stmtUpdateProduct->error);
                    }
                } else {
                    //Just recalclate total quantity for active batches (no cost update)
                    $queryUpdateProduct = "
                        UPDATE product
                        SET total_quantity = ?, updated_at = NOW()
                        WHERE product_id = ?
                    ";
                    $stmtUpdateProduct = $this->conn->prepare($queryUpdateProduct);
                    $stmtUpdateProduct->bind_param('di', $totalQty, $productId);
                    $stmtUpdateProduct->execute();
                }

                $this->conn->commit();
                return true;

            } catch (Exception $e) {
                $this->conn->rollback();
                error_log("updateBatchStatus error: " . $e->getMessage());
                return false;
            }
        }

        public function cancelOrder($batchId, $cancelDetails)
        {
            $query = "UPDATE product_stocks 
                    SET status = 'cancelled', cancel_details = ?, updated_at = NOW() 
                    WHERE product_stock_id = ?";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('si', $cancelDetails, $batchId);

            return $stmt->execute();
        }


        public function getSupplierPurchases()
        {
            $query = "
                SELECT 
                    ps.product_stock_id AS orderId,
                    ps.batch_number AS batchNumber,
                    s.name AS supplierName,
                    s.contact as supplierContact,
                    s.email as supplieremail,
                    p.product_name AS itemName,
                    ps.quantity AS quantity,
                    ps.batch_number as batchNumber,
                    ps.remaining_qty AS remainingQuantity,
                    ps.cost_price AS unitCost,
                    (ps.quantity * ps.cost_price) AS totalAmount,
                    ps.status AS status,
                    ps.created_at AS orderDate,
                    ps.updated_at AS arrivedDate,
                    ps.cancel_details AS cancelDetails,
                    c.category_name AS categoryName
                FROM product_stocks ps
                JOIN supplier s ON ps.supplier_id = s.supplier_id
                JOIN product p ON ps.product_id = p.product_id
                LEFT JOIN category c ON p.category = c.category_id
                WHERE ps.status IN ('ordered', 'cancelled', 'received')
                ORDER BY ps.updated_at DESC
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            $purchases = [];

            while ($row = $result->fetch_assoc()) {
                $purchases[] = [
                    "orderId" => $row["orderId"],
                    "batchNumber" => $row["batchNumber"],
                    "supplierName" => $row["supplierName"],
                    "supplierContact" => $row["supplierContact"],
                    "supplieremail" => $row["supplieremail"],
                    "itemName" => $row["itemName"],
                    "quantity" => $row["quantity"],
                    "batchNumber" => $row["batchNumber"],
                    "remainingQuantity" => $row["remainingQuantity"],
                    "unitCost" => $row["unitCost"],
                    "totalAmount" => $row["totalAmount"],
                    "status" => $row["status"],
                    "orderDate" => $row["orderDate"],
                    "arrivedDate" => $row["arrivedDate"],
                    "cancelDetails" => $row["cancelDetails"],
                    "categoryName" => $row["categoryName"]
                ];
            }

            return $purchases;
        }



         //Get all products for dropdown
        public function getAllProducts() {
            $query = "
                SELECT 
                    product_id,
                    product_code,
                    product_name,
                    category,
                    total_quantity,
                    reserved_qty,
                    threshold
                FROM product 
                ORDER BY product_name
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }

            return $products;
        }

        //Get suppliers for a specific product
        public function getSuppliersByProduct($productId) {
            $query = "
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
            ";

            $stmt = $this->conn->prepare($query);
            $productId = htmlspecialchars(strip_tags($productId));
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $result = $stmt->get_result();

            $suppliers = [];
            while ($row = $result->fetch_assoc()) {
                $suppliers[] = $row;
            }

            return $suppliers;
        }

        //Get default cost price for product-supplier combination
        public function getDefaultCostPrice($productId, $supplierId) {
            $query = "
                SELECT default_cost_price 
                FROM product_suppliers 
                WHERE product_id = ? AND supplier_id = ?
                LIMIT 1
            ";

            $stmt = $this->conn->prepare($query);
            $productId = htmlspecialchars(strip_tags($productId));
            $supplierId = htmlspecialchars(strip_tags($supplierId));
            $stmt->bind_param('ii', $productId, $supplierId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return $row['default_cost_price'];
            }

            return 0;
        }

        public function createNewOrder($productId, $supplierId, $quantity, $costPrice, $remarks) {
            // Generate a unique batch number
            $batchNumber = 'BATCH-' . date('Ymd-His') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);

            // SQL to insert the new batch
            $query = "INSERT INTO product_stocks (
                            product_id,
                            supplier_id,
                            batch_number,
                            quantity,
                            remaining_qty,
                            cost_price,
                            remarks,
                            status,
                            created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ordered', NOW())";

            $stmt = $this->conn->prepare($query);
            //remaining_qty should be the same as quantity initially for new orders
            $stmt->bind_param('iisidss', 
                $productId, 
                $supplierId, 
                $batchNumber, 
                $quantity, 
                $quantity, // remaining_qty same as quantity for new orders
                $costPrice, 
                $remarks
            );

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'New batch order created successfully',
                    'batch_number' => $batchNumber
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create new order: ' . $stmt->error
                ];
            }
        }

        public function markOrderAsArrived($orderId) {
            // Start transaction
            $this->conn->begin_transaction();

            try {
                //Update the batch itself
                $query1 = "UPDATE product_stocks 
                        SET status = 'received', 
                            remaining_qty = quantity, 
                            updated_at = NOW() 
                        WHERE product_stock_id = ?";
                $stmt1 = $this->conn->prepare($query1);
                $stmt1->bind_param('i', $orderId);
                if (!$stmt1->execute()) {
                    throw new Exception("Failed to update product_stocks: " . $stmt1->error);
                }

                //Commit transaction
                $this->conn->commit();

                return [
                    'success' => true,
                    'message' => 'Order marked as arrived successfully and product total recalculated.'
                ];

            } catch (Exception $e) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Failed to mark order as arrived: ' . $e->getMessage()
                ];
            }
        }

        public function getExistingOrder($userId){

            $query = "
                SELECT * FROM carts where carts.seller = ? AND status = 'pending'
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            if ($result->num_rows > 0) {

                $query2 = "
                    SELECT 
                        c.cart_id,
                        c.seller,
                        c.status AS cart_status,
                        c.order_code,
                        c.total,

                        ci.cart_items_id,
                        ci.product_id,
                        ci.qty,
                        ci.price,
                        ci.batch_id,
                        ci.earning,
                        ci.created_at AS item_created_at,
                        ci.updated_at AS item_updated_at,

                        p.product_code,
                        p.product_name,
                        p.category,
                        p.retail_id,
                        p.product_picture,
                        p.total_quantity,
                        p.reserved_qty,
                        p.threshold_id,
                        p.threshold,
                        p.created_at AS product_created_at,
                        p.updated_at AS product_updated_at

                    FROM carts AS c
                    LEFT JOIN cart_items AS ci 
                        ON c.cart_id = ci.cart_id
                    LEFT JOIN product AS p 
                        ON ci.product_id = p.product_id
                    WHERE c.seller = ? AND c.status = 'pending';

                ";
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->bind_param('i', $userId);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                $cart_items = [];
                while ($row = $result2->fetch_assoc()) {
                    $cart_items[] = [
                        "cartId" => $row["cart_id"],
                        "seller" => $row["seller"],
                        "cartStatus" => $row["cart_status"],
                        "orderCode" => $row["order_code"],
                        "total" => $row["total"],
                        "cartItemsId" => $row["cart_items_id"],
                        "productId" => $row["product_id"],
                        "qty" => $row["qty"],
                        "price" => $row["price"],
                        "batchId" => $row["batch_id"],
                        "earning" => $row["earning"],
                        "createdAt" => $row["item_created_at"],
                        "updatedAt" => $row["item_updated_at"],
                        "productCode" => $row["product_code"],
                        "productName" => $row["product_name"],
                        "category" => $row["category"],
                        "retailId" => $row["retail_id"],
                        "productImage" => !empty($row["product_picture"]) ? $row["product_picture"] : null,
                        "totalQuantity" => $row["total_quantity"],
                        "reservedQty" => $row["reserved_qty"],
                        "thresholdId" => $row["threshold_id"],
                        "threshold" => $row["threshold"],
                        "productCreatedAt" => $row["product_created_at"],
                        "productUpdatedAt" => $row["product_updated_at"]
                    ];
                }

                return $cart_items;
            }

            else {
                $today = date('Ymd');
                $query = "SELECT order_code
                            FROM carts
                            ORDER BY cart_id DESC
                            LIMIT 1;";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $result = $stmt->get_result();
                $nextCode = '';
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $lastCode = $row['order_code'];

                    $parts = explode('-', $lastCode);
                    $lastNumber = end($parts);

                    $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

                    $nextCode = $today . '-' . $nextNumber;
                }
                else{
                    $nextCode = $today . '-0001';
                }


                $query2 = "INSERT INTO `carts` (`seller`, `status`, `order_code`, `total`, `failed_desc`, `total_earning`, `created_at`, `updated_at`) 
                VALUES (?, 'pending', ?, 0, NULL, 0, current_timestamp(), current_timestamp()) ";
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->bind_param('is', $userId, $nextCode);
                $stmt2->execute();

                $query3 = "
                    SELECT 
                        c.cart_id,
                        c.seller,
                        c.status AS cart_status,
                        c.order_code,
                        c.total,

                        ci.cart_items_id,
                        ci.product_id,
                        ci.qty,
                        ci.price,
                        ci.batch_id,
                        ci.earning,
                        ci.created_at AS item_created_at,
                        ci.updated_at AS item_updated_at,

                        p.product_code,
                        p.product_name,
                        p.category,
                        p.retail_id,
                        p.total_quantity,
                        p.reserved_qty,
                        p.threshold_id,
                        p.threshold,
                        p.created_at AS product_created_at,
                        p.updated_at AS product_updated_at

                    FROM carts AS c
                    LEFT JOIN cart_items AS ci 
                        ON c.cart_id = ci.cart_id
                    LEFT JOIN product AS p 
                        ON ci.product_id = p.product_id
                    WHERE c.seller = ? AND c.status = 'pending';
                ";
                $stmt3 = $this->conn->prepare($query3);
                $stmt3->bind_param('i', $userId);
                $stmt3->execute();
                $result2 = $stmt3->get_result();
                
                $cart_items = [];


                while ($row = $result2->fetch_assoc()) {
                    $cart_items[] = [
                        "cartId" => $row["cart_id"],
                        "seller" => $row["seller"],
                        "cartStatus" => $row["cart_status"],
                        "orderCode" => $row["order_code"],
                        "total" => $row["total"],
                        "cartItemsId" => $row["cart_items_id"],
                        "productId" => $row["product_id"],
                        "qty" => $row["qty"],
                        "price" => $row["price"],
                        "batchId" => $row["batch_id"],
                        "earning" => $row["earning"],
                        "createdAt" => $row["item_created_at"],
                        "updatedAt" => $row["item_updated_at"],
                        "productCode" => $row["product_code"],
                        "productName" => $row["product_name"],
                        "category" => $row["category"],
                        "retailId" => $row["retail_id"],
                        "totalQuantity" => $row["total_quantity"],
                        "reservedQty" => $row["reserved_qty"],
                        "thresholdId" => $row["threshold_id"],
                        "threshold" => $row["threshold"],
                        "productCreatedAt" => $row["product_created_at"],
                        "productUpdatedAt" => $row["product_updated_at"]
                    ];
                }

                return $cart_items;
            }
        }

        public function getProductByQr($product_code, $user_id) {

                //Get product details
                $query = "
                    SELECT p.*, r.percent 
                    FROM product p
                    LEFT JOIN retail_variables r ON p.retail_id = r.retail_id
                    WHERE p.product_code = ?
                ";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('s', $product_code);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    throw new Exception("Product not found.");
                }

                $row = $result->fetch_assoc();
                $productId = $row["product_id"];
                $retailPercent = $row["percent"] ?? 0;
                $product_image = $row["product_picture"];

                //Get the latest or active batch for this product
                $queryBatch = "
                    SELECT product_stock_id, cost_price, remaining_qty
                    FROM product_stocks
                    WHERE product_id = ? AND status = 'active'
                    ORDER BY updated_at DESC
                    LIMIT 1
                ";
                $stmtBatch = $this->conn->prepare($queryBatch);
                $stmtBatch->bind_param('i', $productId);
                $stmtBatch->execute();
                $batchRes = $stmtBatch->get_result();

                if ($batchRes->num_rows === 0) {
                    throw new Exception("No active batch found for this product.");
                }

                $batchRow = $batchRes->fetch_assoc();
                $batchId = $batchRow['product_stock_id'];
                $costPrice = (float) $batchRow['cost_price'];

                //Compute price and earning
                $price = $costPrice + ($costPrice * ($retailPercent / 100));
                $earning = $price - $costPrice;

                //Get the user's active cart (pending)
                $queryCart = "
                    SELECT cart_id
                    FROM carts
                    WHERE seller = ? AND status = 'pending'
                    ORDER BY cart_id DESC
                    LIMIT 1
                ";
                if (!$user_id) throw new Exception("Missing user_id.");

                $stmtCart = $this->conn->prepare($queryCart);
                $stmtCart->bind_param('i', $user_id);
                $stmtCart->execute();
                $cartRes = $stmtCart->get_result();

                if ($cartRes->num_rows === 0) {
                    throw new Exception("No active cart found for user.");
                }

                $cartRow = $cartRes->fetch_assoc();
                $cartId = $cartRow['cart_id'];

                //Insert new item into cart_items
                $queryInsert = "
                    INSERT INTO cart_items 
                        (cart_id, product_id, qty, price, batch_id, cost_price, earning, created_at, updated_at)
                    VALUES (?, ?, 1, ?, ?, ?, ?, NOW(), NOW())
                ";
                $stmtInsert = $this->conn->prepare($queryInsert);
                $stmtInsert->bind_param('iidddd', $cartId, $productId, $price, $batchId, $costPrice, $earning);
                if (!$stmtInsert->execute()) {
                    throw new Exception("Failed to insert item: " . $stmtInsert->error);
                }

                //Update product stock quantities
                $queryUpdate = "
                    UPDATE product
                    SET reserved_qty = reserved_qty + 1,
                        total_quantity = total_quantity - 1,
                        updated_at = NOW()
                    WHERE product_id = ?
                ";
                $stmtUpdate = $this->conn->prepare($queryUpdate);
                $stmtUpdate->bind_param('i', $productId);
                if (!$stmtUpdate->execute()) {
                    throw new Exception("Failed to update product stock: " . $stmtUpdate->error);
                }

                // //update product_stocks remaining_qty
                // $queryUpdateStock = "
                //     UPDATE product_stocks
                //     SET remaining_qty = remaining_qty - 1,
                //         updated_at = NOW()
                //     WHERE product_stock_id = ?
                // ";
                // $stmtUpdateStock = $this->conn->prepare($queryUpdateStock);
                // $stmtUpdateStock->bind_param('i', $batchId);
                // $stmtUpdateStock->execute(); // don't need to throw unless critical
                $addToCart = [];
                //Return product info
                $addToCart[] = [
                    "productId" => $productId,
                    "productCode" => $row["product_code"],
                    "productName" => $row["product_name"],
                    "productImage" => !empty($product_image) ? $product_image : null,
                    "category" => $row["category"],
                    "retailId" => $row["retail_id"],
                    "costPrice" => $costPrice,
                    "price" => $price,
                    "earning" => $earning,
                    "batchId" => $batchId,
                    "totalQuantity" => $row["total_quantity"] - 1,
                    "reservedQty" => $row["reserved_qty"] + 1,
                    "threshold" => $row["threshold"],
                    "created_at" => $row["created_at"],
                    "updated_at" => date('Y-m-d H:i:s')
                ];

                return $addToCart;



        }


        public function updateCartItem($cart_items_id, $action) {
            try {
                // Validate input
                if (!$cart_items_id || !in_array($action, ['increment', 'decrement', 'remove'])) {
                    throw new Exception("Invalid parameters or action.");
                }

                //Get cart item and product info
                $querySelect = "
                    SELECT 
                        ci.cart_items_id,
                        ci.product_id,
                        ci.qty,
                        ci.price,
                        ci.cost_price,
                        ci.batch_id,
                        p.total_quantity,
                        p.reserved_qty
                    FROM cart_items ci
                    INNER JOIN product p ON ci.product_id = p.product_id
                    WHERE ci.cart_items_id = ?
                ";
                $stmt = $this->conn->prepare($querySelect);
                $stmt->bind_param('i', $cart_items_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    throw new Exception("Cart item not found.");
                }

                $item = $result->fetch_assoc();

                $productId = $item["product_id"];
                $batchId = $item["batch_id"];
                $oldQty = (int)$item["qty"];
                $pricePerUnit = (float)$item["price"];
                $costPrice = (float)$item["cost_price"];

                $newQty = $oldQty;
                $qtyDiff = 0;

                //Determine the action
                if ($action === 'increment') {
                    $newQty = $oldQty + 1;
                    $qtyDiff = 1;

                } elseif ($action === 'decrement') {
                    if ($oldQty <= 1) {
                        // If qty is 1, treat as remove and restore that 1 to stock
                        $qtyDiff = -$oldQty; // return all quantity back
                        $action = 'remove';
                    } else {
                        $newQty = $oldQty - 1;
                        $qtyDiff = -1;
                    }
                } elseif ($action === 'remove') {
                    // Remove entire item, return all qty back to stock
                    $qtyDiff = -$oldQty;
                }

                //Begin transaction
                $this->conn->begin_transaction();

                //Handle updates per action
                if ($action === 'increment' || $action === 'decrement') {

                    $newEarning = ($pricePerUnit - $costPrice) * $newQty;

                    //Update cart item
                    $queryUpdateCart = "
                        UPDATE cart_items
                        SET qty = ?, earning = ?, updated_at = NOW()
                        WHERE cart_items_id = ?
                    ";
                    $stmtUpdateCart = $this->conn->prepare($queryUpdateCart);
                    $stmtUpdateCart->bind_param('idi', $newQty, $newEarning, $cart_items_id);

                    if (!$stmtUpdateCart->execute()) {
                        throw new Exception("Failed to update cart item: " . $stmtUpdateCart->error);
                    }

                } elseif ($action === 'remove') {

                    //Delete the cart item entirely
                    $queryDelete = "DELETE FROM cart_items WHERE cart_items_id = ?";
                    $stmtDelete = $this->conn->prepare($queryDelete);
                    $stmtDelete->bind_param('i', $cart_items_id);

                    if (!$stmtDelete->execute()) {
                        throw new Exception("Failed to remove cart item: " . $stmtDelete->error);
                    }
                }

                //Update product stock quantities
                $queryUpdateProduct = "
                    UPDATE product
                    SET 
                        reserved_qty = reserved_qty + ?,
                        total_quantity = total_quantity - ?,
                        updated_at = NOW()
                    WHERE product_id = ?
                ";
                $stmtUpdateProduct = $this->conn->prepare($queryUpdateProduct);
                $stmtUpdateProduct->bind_param('iii', $qtyDiff, $qtyDiff, $productId);

                if (!$stmtUpdateProduct->execute()) {
                    throw new Exception("Failed to update product stock: " . $stmtUpdateProduct->error);
                }

                //Update batch stock too
                $queryUpdateBatch = "
                    UPDATE product_stocks
                    SET remaining_qty = remaining_qty - ?, updated_at = NOW()
                    WHERE product_stock_id = ?
                ";
                $stmtUpdateBatch = $this->conn->prepare($queryUpdateBatch);
                $stmtUpdateBatch->bind_param('ii', $qtyDiff, $batchId);
                $stmtUpdateBatch->execute(); // not critical

                //Commit transaction
                $this->conn->commit();

                $updatedCart = [];
                $updatedCart[] = [
                    "cartItemId" => $cart_items_id,
                    "productId" => $productId,
                    "batchId" => $batchId,
                    "oldQty" => $oldQty,
                    "newQty" => $action === 'remove' ? 0 : $newQty,
                    "pricePerUnit" => $pricePerUnit,
                    "totalPrice" => $action === 'remove' ? 0 : ($pricePerUnit * $newQty),
                    "earning" => $action === 'remove' ? 0 : ($pricePerUnit - $costPrice) * $newQty,
                    "action" => $action,
                    "updatedAt" => date('Y-m-d H:i:s')
                ];

                return $updatedCart;

            } catch (Exception $e) {
                if ($this->conn->in_transaction) {
                    $this->conn->rollback();
                }

                return [
                    "status" => "error",
                    "message" => $e->getMessage()
                ];
            }
        }

        public function checkoutCart($userId, $totalAmount, $totalEarning, $items) {
            try {
                if (empty($userId) || empty($items)) {
                    throw new Exception("Missing required checkout data.");
                }

                $this->conn->begin_transaction();

                //Insert new record in `sales`
                $querySales = "
                    INSERT INTO sales (total_amount, total_earning, sale_date, cashier_id, remarks)
                    VALUES (?, ?, NOW(), ?, 'Checkout completed')
                ";
                $stmtSales = $this->conn->prepare($querySales);
                $stmtSales->bind_param('ddi', $totalAmount, $totalEarning, $userId);

                if (!$stmtSales->execute()) {
                    throw new Exception("Failed to create sale: " . $stmtSales->error);
                }

                $saleId = $this->conn->insert_id;

                //Insert each item into `sales_items` table
                $queryItems = "
                    INSERT INTO sales_items 
                        (sale_id, product_id, batch_id, quantity, unit_price, cost_price, earning, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Sold from POS')
                ";
                $stmtItem = $this->conn->prepare($queryItems);

                foreach ($items as $item) {
                    // support both array and object
                    if (is_object($item)) {
                        $productId  = (int)$item->product_id;
                        $batchId    = (int)$item->batch_id;
                        $quantity   = (int)$item->quantity;
                        $unitPrice  = (float)$item->unit_price;
                        $costPrice  = isset($item->cost_price) ? (float)$item->cost_price : 0;
                        $earning    = isset($item->earning) ? (float)$item->earning : ($unitPrice - $costPrice);
                    } else {
                        $productId  = (int)$item['product_id'];
                        $batchId    = (int)$item['batch_id'];
                        $quantity   = (int)$item['quantity'];
                        $unitPrice  = (float)$item['unit_price'];
                        $costPrice  = isset($item['cost_price']) ? (float)$item['cost_price'] : 0;
                        $earning    = isset($item['earning']) ? (float)$item['earning'] : ($unitPrice - $costPrice);
                    }

                    $stmtItem->bind_param('iiiiddd', $saleId, $productId, $batchId, $quantity, $unitPrice, $costPrice, $earning);

                    if (!$stmtItem->execute()) {
                        throw new Exception("Failed to insert sale item: " . $stmtItem->error);
                    }

                    //Update product totals
                    $queryUpdateProduct = "
                        UPDATE product
                        SET reserved_qty = reserved_qty - ?, 
                            updated_at = NOW()
                        WHERE product_id = ?
                    ";
                    $stmtUpdateProduct = $this->conn->prepare($queryUpdateProduct);
                    $stmtUpdateProduct->bind_param('ii', $quantity, $productId);
                    $stmtUpdateProduct->execute();

                    //Update batch remaining_qty
                    $queryUpdateStock = "
                        UPDATE product_stocks
                        SET remaining_qty = remaining_qty - ?, updated_at = NOW()
                        WHERE product_stock_id = ?
                    ";
                    $stmtUpdateStock = $this->conn->prepare($queryUpdateStock);
                    $stmtUpdateStock->bind_param('ii', $quantity, $batchId);
                    $stmtUpdateStock->execute();
                }

                //Mark users cart as completed
                $queryUpdateCart = "
                    UPDATE carts
                    SET status = 'completed', total = ?, total_earning = ?, updated_at = NOW()
                    WHERE seller = ? AND status = 'pending'
                ";
                $stmtUpdateCart = $this->conn->prepare($queryUpdateCart);
                $stmtUpdateCart->bind_param('ddi', $totalAmount, $totalEarning, $userId);

                if (!$stmtUpdateCart->execute()) {
                    throw new Exception("Failed to update cart: " . $stmtUpdateCart->error);
                }

                $this->conn->commit();

                return [
                    "status" => "success",
                    "message" => "Checkout completed successfully.",
                    "sale_id" => $saleId
                ];

            } catch (Exception $e) {
                if ($this->conn->in_transaction) {
                    $this->conn->rollback();
                }

                return [
                    "status" => "error",
                    "message" => $e->getMessage()
                ];
            }
        }

        public function cashierDashboard($userId) {
            $totalAmountToday = 0;
            $totalEarningToday = 0;
            $totalAmountWeek = 0;
            $totalEarningWeek = 0;
            $weekStart = '';
            $weekEnd = '';
            $totalAmountMonth = 0;
            $totalEarningMonth = 0;
            $monthStart = '';
            $monthEnd = '';
            $firstName = '';
            $lastName = '';
            $username = '';
            $email = '';
            $totalCount = 0;

            $query = 'SELECT 
                SUM(s.total_amount) AS "TotalAmount",
                SUM(s.total_earning) AS "TotalEarning"
                FROM user AS u
                INNER JOIN sales AS s ON s.cashier_id = u.id
                WHERE u.id = ?
                AND DATE(s.sale_date) = CURDATE()
                ';

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0){
                $row = $result->fetch_assoc();
                $totalAmountToday = $row["TotalAmount"] = null ?  $row["TotalAmount"]: 0;
                $totalEarningToday = $row["TotalEarning"] = null ? $row["TotalEarning"] : 0;
            }

            $query2 = "SELECT count(*) AS TotalCount
                        FROM carts AS c
                        INNER JOIN user AS u ON u.id = c.seller
                        WHERE c.status = 'completed'
                        AND u.id = ?";
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if($result2->num_rows > 0){
                $row2 = $result2->fetch_assoc();
                $totalCount = $row2["TotalCount"];

            }

            $query3 = "SELECT
                SUM(s.total_amount) AS 'TotalAmount',
                SUM(s.total_earning) AS 'TotalEarning',
                DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AS 'WeekStart',
  				DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY) AS 'WeekEnd'
                FROM user AS u
                INNER JOIN sales AS s ON s.cashier_id = u.id
                WHERE u.id = ?
                AND YEARWEEK(CURDATE(), 1) = YEARWEEK(s.sale_date, 1)";

            $stmt3 = $this->conn->prepare($query3);
            $stmt3->bind_param('i', $userId);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            if($result3->num_rows > 0){
                $row3 = $result3->fetch_assoc();
                $totalAmountWeek = $row3["TotalAmount"];
                $totalEarningWeek = $row3["TotalEarning"];
                $weekStart = $row3["WeekStart"];
                $weekEnd = $row3["WeekEnd"];
            }

            $stmt4 = "SELECT 
                        SUM(s.total_amount) AS TotalAmount,
                        SUM(s.total_earning) AS TotalEarning,
                        
                        DATE_FORMAT(CURDATE(), '%Y-%m-01') AS MonthStart,
                        LAST_DAY(CURDATE()) AS MonthEnd

                        FROM user AS u
                        INNER JOIN sales AS s ON s.cashier_id = u.id
                        WHERE u.id = ?
                        AND MONTH(s.sale_date) = MONTH(CURDATE())
                        AND YEAR(s.sale_date) = YEAR(CURDATE())";
            $stmt4 = $this->conn->prepare($stmt4);
            $stmt4->bind_param('i', $userId);
            $stmt4->execute();
            $result4 = $stmt4->get_result();
            if($result4->num_rows > 0){
                $row4 = $result4->fetch_assoc();
                $totalAmountMonth = $row4["TotalAmount"] = null ? $row4["TotalAmount"] : 0;
                $totalEarningMonth = $row4["TotalEarning"] = null ? $row4["TotalEarning"] : 0;
                $monthStart = $row4["MonthStart"];
                $monthEnd = $row4["MonthEnd"];
            }

            $query5 = "SELECT
                        u.first_name AS FirstName,
                        u.last_name AS LastName,
                        u.username AS Username,
                        u.email AS Email
                        FROM user AS u
                        WHERE u.id = ?";
            $stmt5 = $this->conn->prepare($query5);
            $stmt5->bind_param('i', $userId);
            $stmt5->execute();
            $result5 = $stmt5->get_result();
            if($result5->num_rows > 0){
                $row5 = $result5->fetch_assoc();
                $firstName = $row5["FirstName"];
                $lastName = $row5["LastName"];
                $username = $row5["Username"];
                $email = $row5["Email"];
            }


            $sales = [];
            $sales[] = [
                "totalAmountToday" => $totalAmountToday,
                "totalEarningToday" => $totalEarningToday,
                "totalAmountWeek" => $totalAmountWeek,
                "totalEarningWeek" => $totalEarningWeek,
                "weekStart" => $weekStart,
                "weekEnd" => $weekEnd,
                "totalAmountMonth" => $totalAmountMonth,
                "totalEarningMonth" => $totalEarningMonth,
                "monthStart" => $monthStart,
                "monthEnd" => $monthEnd,
                "firstName" => $firstName,
                "lastName" => $lastName,
                "username" => $username,
                "email" => $email,
                "totalCount" => $totalCount
            ];

            return $sales;
        }



        public function getTransactionHistory($cashier_id){
            $query1 = "SELECT c.cart_id,
                                c.seller,
                                c.status,
                                c.order_code,
                                c.total,
                                c.failed_desc,
                                c.total_earning,
                                c.created_at,
                                c.updated_at
                        FROM carts AS c
                        WHERE seller = ?
                        AND status = 'completed'";
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->bind_param('i', $cashier_id);
            $stmt1->execute();
            $result1 = $stmt1->get_result();
            $transaction = [];
            if ($result1 -> num_rows > 0) {
                while($row = $result1->fetch_assoc()){
                    $transaction[] = [
                        "cartId" => $row["cart_id"],
                        "seller" => $row["seller"],
                        "status" => $row["status"],
                        "orderCode" => $row["order_code"],
                        "total" => $row["total"],
                        "failedDesc" => $row["failed_desc"],
                        "totalEarning" => $row["total_earning"],
                        "createdAt" => $row["created_at"],
                        "updatedAt" => $row["updated_at"]
                    ];
                }
            }
            else{
                $transaction[] = [
                    "cartId" => null,
                    "seller" => null,
                    "status" => null,
                    "orderCode" => null,
                    "total" => null,
                    "failedDesc" => null,
                    "totalEarning" => null,
                    "createdAt" => null,
                    "updatedAt" => null
                ];
            }
            return $transaction;

        }


        public function getTransactionItems($cart_id, $seller_id){
            $query1 = "SELECT ci.cart_items_id,
                                c.seller,
                                ci.cart_id,
                                ci.product_id,
                                ci.qty,
                                ci.price,
                                ci.batch_id,
                                ci.cost_price,
                                ci.earning,
                                ci.created_at,
                                ci.updated_at,
                                p.product_name,
                                p.product_code
                        FROM cart_items AS ci
                        INNER JOIN carts AS c ON c.cart_id = ci.cart_id
                        INNER JOIN product AS p ON p.product_id = ci.product_id
                        WHERE c.seller = ?
                        AND ci.cart_id = ?";
            $query1 = $this->conn->prepare($query1);
            $query1->bind_param('ii', $seller_id, $cart_id);
            $query1->execute();
            $result1 = $query1->get_result();
            $transactionItems = [];
            if ($result1 -> num_rows > 0) {
                while($row = $result1->fetch_assoc()){
                    $transactionItems[] = [
                        "cartItemsId" => $row["cart_items_id"],
                        "seller" => $row["seller"],
                        "cartId" => $row["cart_id"],
                        "productId" => $row["product_id"],
                        "qty" => $row["qty"],
                        "price" => $row["price"],
                        "batchId" => $row["batch_id"],
                        "costPrice" => $row["cost_price"],
                        "earning" => $row["earning"],
                        "createdAt" => $row["created_at"],
                        "updatedAt" => $row["updated_at"],
                        "productName" => $row["product_name"],
                        "productCode" => $row["product_code"]
                    ];
                }
            }
            else{
                $transactionItems[] = [
                    "cartItemsId" => null,
                    "seller" => null,
                    "cartId" => null,
                    "productId" => null,
                    "qty" => null,
                    "price" => null,
                    "batchId" => null,
                    "costPrice" => null,
                    "earning" => null,
                    "createdAt" => null,
                    "updatedAt" => null,
                    "productName" => null,
                    "productCode" => null
                ];
            }

            return $transactionItems;

        }

        public function getProfile($user_id){
            $query = "SELECT profile_pic FROM user
                        WHERE id = ?";
            $queryStmt = $this->conn->prepare($query);
            $queryStmt->bind_param('i', $user_id);
            $queryStmt->execute();
            $result = $queryStmt->get_result();
            $profile = [];
            if($result->num_rows > 0){
                $row = $result->fetch_assoc();
                $profile[] = [
                    "profilePic" => $row["profile_pic"]
                ];
            }
            else{
                $profile[] = [
                    "profilePic" => null
                ];
            }

            return $profile;
        }

        




    }