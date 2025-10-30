<?php
session_start();
include '../config.php'; // this defines $conn (mysqli)

// Only allow bodegero
if (!isset($_SESSION['username']) || $_SESSION['type'] !== "warehouse_man") {
    header("Location: ../index.php");
    exit;
}

// Prepare response flags
$_SESSION['open_profile_modal'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $contact    = trim($_POST['contact'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Validation
    if ($first_name === '' || $last_name === '' || $contact === '' || $email === '' || $username === '') {
        $_SESSION['error'] = "All fields except password are required.";
        header("Location: budegero.php");
        exit;
    }
    if ($password !== '' && $password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: budegero.php");
        exit;
    }

    // Fetch current user
    $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: budegero.php");
        exit;
    }

    // Handle profile picture upload
    $profile_pic_path = $user['profile_pic'];
    if (!empty($_FILES['profile_pic']['name'])) {
        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $filename = uniqid("profile_", true) . "." . $ext;
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
            $profile_pic_path = "uploads/" . $filename;

            // delete old pic if not default
            if (!empty($user['profile_pic']) && $user['profile_pic'] !== "uploads/default.png" && file_exists("../" . $user['profile_pic'])) {
                unlink("../" . $user['profile_pic']);
            }
        }
    }

    // Build update query
    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE user SET first_name=?, last_name=?, contact=?, email=?, username=?, password=?, profile_pic=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $first_name, $last_name, $contact, $email, $username, $hashed, $profile_pic_path, $user['id']);
    } else {
        $sql = "UPDATE user SET first_name=?, last_name=?, contact=?, email=?, username=?, profile_pic=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $first_name, $last_name, $contact, $email, $username, $profile_pic_path, $user['id']);
    }

    if ($stmt->execute()) {
        // Update session vars
        $_SESSION['first_name']  = $first_name;
        $_SESSION['last_name']   = $last_name;
        $_SESSION['contact']     = $contact;
        $_SESSION['email']       = $email;
        $_SESSION['username']    = $username;
        $_SESSION['profile_pic'] = $profile_pic_path;

        $_SESSION['success'] = "Profile updated successfully.";
        unset($_SESSION['open_profile_modal']);
    } else {
        $_SESSION['error'] = "Error updating profile: " . $stmt->error;
    }
    $stmt->close();

    header("Location: budegero.php");
    exit;
}
?>
