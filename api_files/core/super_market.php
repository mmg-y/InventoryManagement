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

    }