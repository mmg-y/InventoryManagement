<?php
session_start();
include "config.php";

$loginMessage = "";
$signupMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'signup') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $contact    = trim($_POST['contact'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $type       = trim($_POST['type'] ?? '');

        if ($first_name && $last_name && $contact && $email && $username && $password && $type) {
            $stmt = $conn->prepare("SELECT id FROM user WHERE username=? OR email=? LIMIT 1");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $signupMessage = "⚠ Username or Email already exists!";
            } else {
                $password_hashed = password_hash($password, PASSWORD_BCRYPT);
                $created_at = date("Y-m-d H:i:s");

                $stmt = $conn->prepare("INSERT INTO user 
                    (first_name, last_name, contact, email, username, password, type, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $first_name, $last_name, $contact, $email, $username, $password_hashed, $type, $created_at);

                if ($stmt->execute()) {
                    $signupMessage = "✅ Signup successful! Please log in.";
                } else {
                    $signupMessage = "❌ Error: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            $signupMessage = "❌ Please fill in all signup fields!";
        }
    }

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            $stmt = $conn->prepare("SELECT * FROM user WHERE username=? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password'])) {
                    $_SESSION['id']          = $row['id'];
                    $_SESSION['username']    = $row['username'];
                    $_SESSION['first_name']  = $row['first_name'];
                    $_SESSION['last_name']   = $row['last_name'];
                    $_SESSION['contact']     = $row['contact'];
                    $_SESSION['email']       = $row['email'];
                    $_SESSION['type']        = $row['type'];
                    $_SESSION['profile_pic'] = !empty($row['profile_pic']) ? $row['profile_pic'] : 'uploads/default.png';

                    if ($row['type'] === "admin") {
                        header("Location: admin/admin.php");
                    } elseif ($row['type'] === "staff") {
                        header("Location: staff/staff.php");
                    } elseif ($row['type'] === "bodegero") {
                        header("Location: budegero/budegero.php");
                    }
                    exit;
                } else {
                    $loginMessage = "❌ Invalid password!";
                }
            } else {
                $loginMessage = "❌ No account found!";
            }
            $stmt->close();
        } else {
            $loginMessage = "❌ Please fill in all login fields!";
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS - Login Page</title>
    <link rel="icon" href="" type="images/png">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

    <div class="container">
        <div class="left">
            <div class="overlay"></div>
            <div class="top-text">IMS</div>
            <div class="quote">
                <h2>Inventory in Sales Management</h2>
                <p>Organize your stock, streamline your sales, and watch your business grow.</p>
            </div>
        </div>

        <div class="right" id="rightPanel">
            <div class="logo">
                <img src="images/logo-b.png" alt="Logo">
                <span>IMS</span>
            </div>
            <div class="form-container" id="signin">
                <h1>Welcome Back</h1>
                <p>Enter your email and password to access your account</p>

                <?php if (!empty($loginMessage)): ?>
                    <div class="message"><?= htmlspecialchars($loginMessage) ?></div>
                <?php endif; ?>


                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <input name="username" type="text" placeholder="Enter your username" required>
                    </div>
                    <div class="form-group">
                        <input name="password" type="password" placeholder="Enter your password" required>
                    </div>

                    <div class="options">
                        <label><input type="checkbox"> Remember me</label>
                        <a href="#">Forgot Password</a>
                    </div>

                    <button class="btn btn-primary" type="submit">Sign In</button>
                    <div class="toggle-link">Don’t have an account? <a id="showSignup">Sign Up</a></div>
                </form>
            </div>

            <div class="form-container" id="signup">
                <h1>Create Account</h1>
                <p>Fill in the details below to create your account</p>

                <?php if (!empty($signupMessage)): ?>
                    <div class="message"><?= htmlspecialchars($signupMessage) ?></div>
                <?php endif; ?>

                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="signup">

                    <div class="input-group">
                        <div class="form-group">
                            <input name="last_name" type="text" placeholder="Last Name" required>
                        </div>
                        <div class="form-group">
                            <input name="first_name" type="text" placeholder="First Name" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <div class="form-group">
                            <input name="contact" type="text" placeholder="Contact" required>
                        </div>
                        <div class="form-group">
                            <input name="email" type="email" placeholder="Email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <input name="username" type="text" placeholder="Username" required>
                    </div>
                    <div class="input-group">
                        <div class="form-group">
                            <input name="password" type="password" placeholder="Password" required>
                        </div>
                        <div class="form-group">
                            <select name="type" required>
                                <option value="">Select Type</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                                <option value="bodegero">Bodegero</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">Sign Up</button>
                    <div class="toggle-link">Already have an account? <a id="showSignin">Sign In</a></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const rightPanel = document.getElementById('rightPanel');
        const showSignup = document.getElementById('showSignup');
        const showSignin = document.getElementById('showSignin');

        showSignup.addEventListener('click', () => rightPanel.classList.add('active'));
        showSignin.addEventListener('click', () => rightPanel.classList.remove('active'));
    </script>
</body>

</html>