<?php
session_start();
include "config.php";

$loginMessage = "";
$signupMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            // Fetch the user without archived filtering
            $stmt = $conn->prepare("SELECT * FROM user WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // ✅ Handle both hashed or plain-text passwords
                $validPassword = false;

                if (password_verify($password, $row['password'])) {
                    $validPassword = true;
                } elseif ($password === $row['password']) {
                    $validPassword = true;
                }

                if ($validPassword) {
                    // Set session data
                    $_SESSION['id']          = $row['id'];
                    $_SESSION['username']    = $row['username'];
                    $_SESSION['first_name']  = $row['first_name'];
                    $_SESSION['last_name']   = $row['last_name'];
                    $_SESSION['contact']     = $row['contact'];
                    $_SESSION['email']       = $row['email'];
                    $_SESSION['type']        = $row['type'];
                    $_SESSION['profile_pic'] = !empty($row['profile_pic']) ? $row['profile_pic'] : 'uploads/default.png';

                    // Redirect based on user type
                    switch ($row['type']) {
                        case 'admin':
                            header("Location: admin/admin.php");
                            break;
                        case 'cashier':
                            header("Location: staff/staff.php");
                            break;
                        case 'warehouse_man':
                            header("Location: budegero/budegero.php");
                            break;
                        default:
                            $loginMessage = "Unknown account type!";
                    }
                    exit;
                } else {
                    $loginMessage = "Invalid password!";
                }
            } else {
                $loginMessage = "No account found!";
            }
            $stmt->close();
        } else {
            $loginMessage = "Please fill in all login fields!";
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
    <link rel="icon" href="images/logo-teal.png" type="images/png">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

    <div class="container">
        <div class="right" id="rightPanel">
            <div class="logo">
                <img src="images/logo-teal.png" alt="Logo">
                <span>MartIQ</span>
            </div>
            <div class="form-container" id="signin">
                <h1>Welcome Back</h1>
                <p>Enter your username and password to access your account</p>

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
                        <!-- <a href="#">Forgot Password</a> -->
                    </div>

                    <button class="btn btn-primary" type="submit">Sign In</button>
                </form>
            </div>
        </div>
        <div class="left">
            <div class="overlay"></div>
            <div class="quote">
                <h2>Inventory in Sales Management</h2>
                <p>Organize your stock, streamline your sales, and watch your business grow.</p>
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