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


        

        // ---- B. Hashed password version (recommended) ----
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
    }