<?php
session_start();
include '../config.php';

if (!isset($_SESSION['username']) || $_SESSION['type'] !== "cashier") {
    header("Location: ../index.php");
    exit;
}

$_SESSION['open_profile_modal'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $contact    = trim($_POST['contact'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if ($first_name === '' || $last_name === '' || $contact === '' || $email === '' || $username === '') {
        $_SESSION['error'] = "All fields except password are required.";
        header("Location: staff.php");
        exit;
    }

    if ($password !== '' && $password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: staff.php");
        exit;
    }

    // Get current user by session
    $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: staff.php");
        exit;
    }

    $updated_at = date('Y-m-d H:i:s');

    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE user SET first_name=?, last_name=?, contact=?, email=?, username=?, password=?, updated_at=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $first_name, $last_name, $contact, $email, $username, $hashed, $updated_at, $user['id']);
    } else {
        $sql = "UPDATE user SET first_name=?, last_name=?, contact=?, email=?, username=?, updated_at=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $first_name, $last_name, $contact, $email, $username, $updated_at, $user['id']);
    }

    if ($stmt->execute()) {
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
        $_SESSION['contact']    = $contact;
        $_SESSION['email']      = $email;
        $_SESSION['username']   = $username;
        $_SESSION['success']    = "Profile updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating profile: " . $stmt->error;
    }
    $stmt->close();

    if (!empty($_FILES['profile_pic']['name'])) {
        $fileName = $_FILES['profile_pic']['name'];
        $fileTmp  = $_FILES['profile_pic']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExt, $allowed)) {
            $newFileName = 'uploads/profile_' . $user['id'] . '.' . $fileExt;
            if (move_uploaded_file($fileTmp, '../' . $newFileName)) {
                $sqlPic = "UPDATE user SET profile_pic=? WHERE id=?";
                $stmtPic = $conn->prepare($sqlPic);
                $stmtPic->bind_param("si", $newFileName, $user['id']);
                $stmtPic->execute();
                $stmtPic->close();
                $_SESSION['profile_pic'] = $newFileName;
            } else {
                $_SESSION['error'] = "Failed to upload profile picture.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type for profile picture.";
        }
    }

    header("Location: staff.php");
    exit;
}
