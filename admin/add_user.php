<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config.php';

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $type = $_POST['type'];

    // Profile picture handling
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image format. Only JPG, PNG, GIF allowed.";
        } else {
            $filename = uniqid() . '.' . $ext;
            $target_dir = "../uploads/";
            if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $filename)) {
                $errors[] = "Failed to upload profile picture.";
            } else {
                $profile_pic = 'uploads/' . $filename;
            }
        }
    }

    // Basic validation
    if (!$first_name || !$last_name || !$contact || !$email || !$username || !$password || !$type) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check username/email uniqueness
    $stmt = $conn->prepare("SELECT id FROM user WHERE username=? OR email=? LIMIT 1");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Username or email already exists!";
    }
    $stmt->close();

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO user (first_name, last_name, contact, email, username, password, profile_pic, type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("ssssssss", $first_name, $last_name, $contact, $email, $username, $hashed_password, $profile_pic, $type);

        if ($stmt->execute()) {
            $success = "User account created successfully!";
            // Clear form fields
            $first_name = $last_name = $contact = $email = $username = $password = $type = '';
            $profile_pic = null;
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<link rel="stylesheet" href="../css/user.css">

<div class="page-title">Add New User</div>

<div class="card full-width-card">
    <!-- Alerts -->
    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <p><?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" enctype="multipart/form-data">
        <div class="profile-upload-wrapper">
            <div class="profile-preview">
                <img id="profilePreview" src="<?= $profile_pic ?? '../uploads/default.png' ?>" alt="Profile Picture">
            </div>
            <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
        </div>

        <div class="form-fields">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?= isset($first_name) ? htmlspecialchars($first_name) : '' ?>" required>

            <label>Last Name</label>
            <input type="text" name="last_name" value="<?= isset($last_name) ? htmlspecialchars($last_name) : '' ?>" required>

            <label>Contact</label>
            <input type="text" name="contact" value="<?= isset($contact) ? htmlspecialchars($contact) : '' ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>

            <label>Username</label>
            <input type="text" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Role</label>
            <select name="type" required>
                <option value="">Select Role</option>
                <option value="staff" <?= (isset($type) && $type == 'staff') ? 'selected' : '' ?>>Staff</option>
                <option value="bodegero" <?= (isset($type) && $type == 'bodegero') ? 'selected' : '' ?>>Bodegero</option>
            </select>
        </div>

        <button type="submit" name="add_user" class="save-btn">Create Account</button>
    </form>
</div>


<script>
    const profileInput = document.getElementById('profile_pic');
    const profilePreview = document.getElementById('profilePreview');

    profileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                profilePreview.src = e.target.result;
            }
            reader.readAsDataURL(file);
        } else {
            profilePreview.src = '../uploads/default.png';
        }
    });
</script>