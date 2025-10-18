<?php

    class SuperMarket {
        private $conn;
        private $table = 'user';

        // user properties
        public $username;
        public $password;

        public function __construct($db) {
            $this->conn = $db;
        }




        public function login() {
            // First, fetch user by username only
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
                    return $user;   // return user array
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


        // --- NEW: GET PRODUCTS WITH STATUS ---
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


        // --- NEW: Get Warehouse Inventory (Batches + Products + Suppliers) ---
        public function getWarehouseInventory() {
            $query = "
                SELECT 
                    ps.product_stock_id AS batchId,
                        ps.batch_number AS batchCode,
                        p.product_name AS itemName,
                        s.name AS supplierName,
                        ps.remaining_qty AS quantity,
                        ps.status AS status,
                        ps.updated_at AS dateAdded,
                        ps.cost_price AS pricePerUnit,
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
                // 1️⃣ Get product_id of the batch being updated
                $queryGetProduct = "SELECT product_id FROM product_stocks WHERE product_stock_id = ?";
                $stmt = $this->conn->prepare($queryGetProduct);
                $stmt->bind_param('i', $batchId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    throw new Exception("Batch not found.");
                }

                $row = $result->fetch_assoc();
                $productId = $row['product_id'];

                // 2️⃣ FIFO: If setting to 'active', deactivate currently active batch(es) of the same product
                if (strtolower($status) === 'active') {
                    $queryDeactivate = "
                        UPDATE product_stocks 
                        SET status = 'pulled out', updated_at = NOW() 
                        WHERE product_id = ? AND status = 'active' AND product_stock_id != ?";
                    
                    $stmtDeactivate = $this->conn->prepare($queryDeactivate);
                    $stmtDeactivate->bind_param('ii', $productId, $batchId);
                    $stmtDeactivate->execute();
                }

                // 3️⃣ Update the selected batch's status
                $queryUpdate = "
                    UPDATE product_stocks 
                    SET status = ?, updated_at = NOW() 
                    WHERE product_stock_id = ?";
                
                $stmtUpdate = $this->conn->prepare($queryUpdate);
                $stmtUpdate->bind_param('si', $status, $batchId);

                if (!$stmtUpdate->execute()) {
                    throw new Exception("Failed to update batch: " . $stmtUpdate->error);
                }

                // 4️⃣ Recalculate total_quantity from all active batches
                $queryRecalc = "
                    SELECT COALESCE(SUM(remaining_qty), 0) AS total_qty
                    FROM product_stocks
                    WHERE product_id = ? AND status = 'active'
                ";
                $stmtRecalc = $this->conn->prepare($queryRecalc);
                $stmtRecalc->bind_param('i', $productId);
                $stmtRecalc->execute();
                $resQty = $stmtRecalc->get_result()->fetch_assoc();
                $totalQty = $resQty['total_qty'];

                // 5️⃣ Update the product table
                $queryUpdateProduct = "
                    UPDATE product
                    SET total_quantity = ?, updated_at = NOW()
                    WHERE product_id = ?
                ";
                $stmtUpdateProduct = $this->conn->prepare($queryUpdateProduct);
                $stmtUpdateProduct->bind_param('ii', $totalQty, $productId);

                if (!$stmtUpdateProduct->execute()) {
                    throw new Exception("Failed to update product quantity: " . $stmtUpdateProduct->error);
                }

                // 6️⃣ Return success
                return true;

            } catch (Exception $e) {
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



         // --- NEW: Get all products for dropdown ---
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

        // --- NEW: Get suppliers for a specific product ---
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

        // --- NEW: Get default cost price for product-supplier combination ---
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

        // ... your existing methods (login, getCategoriesAndItems, etc.) ...

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
            // Note: remaining_qty should be the same as quantity initially for new orders
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
            // First query: Update product_stocks status and set remaining_qty = quantity
            $query1 = "UPDATE product_stocks 
                    SET status = 'received', 
                        remaining_qty = quantity, 
                        updated_at = NOW() 
                    WHERE product_stock_id = ?";
            
            $stmt1 = $this->conn->prepare($query1);
            $orderId = htmlspecialchars(strip_tags($orderId));
            $stmt1->bind_param('i', $orderId);
            
            if (!$stmt1->execute()) {
                throw new Exception("Failed to update product_stocks: " . $stmt1->error);
            }

            // Second query: Update product total_quantity
            $query2 = "UPDATE product 
                    SET total_quantity = total_quantity + (
                        SELECT quantity 
                        FROM product_stocks 
                        WHERE product_stock_id = ?
                    ),
                    updated_at = NOW()
                    WHERE product_id = (
                        SELECT product_id 
                        FROM product_stocks 
                        WHERE product_stock_id = ?
                    )";
            
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->bind_param('ii', $orderId, $orderId);
            
            if (!$stmt2->execute()) {
                throw new Exception("Failed to update product quantity: " . $stmt2->error);
            }

            // Commit transaction
            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Order marked as arrived successfully'
            ];

        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => 'Failed to mark order as arrived: ' . $e->getMessage()
            ];
        }
    }

        




    }