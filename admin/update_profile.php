<?php
session_start();
include '../config.php'; // must set $conn (mysqli)

// ensure user logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

$userId   = (int) $_SESSION['id'];

// Safely get POST values (avoid undefined index warnings)
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$contact    = trim($_POST['contact'] ?? '');
$email      = trim($_POST['email'] ?? '');
$username   = trim($_POST['username'] ?? '');
$password   = $_POST['password'] ?? '';
$confirm    = $_POST['confirm_password'] ?? '';

$errors = [];

// Password handling
$hashedPassword = null;
if ($password !== '') {
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    }
}

// Get current profile_pic from DB (fallback)
$old_pic = '';
$stmt = $conn->prepare("SELECT profile_pic FROM user WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $old_pic = $row['profile_pic'];
}
$stmt->close();

$profile_pic_path = $old_pic;

// Handle profile picture upload (if any)
if (isset($_FILES['profile_pic']) && isset($_FILES['profile_pic']['error']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    // Server path to uploads directory (admin/../uploads)
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $tmpName  = $_FILES['profile_pic']['tmp_name'];
    $origName = basename($_FILES['profile_pic']['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed  = ['jpg','jpeg','png','gif'];

    if (!in_array($ext, $allowed)) {
        $errors[] = "Only JPG, JPEG, PNG and GIF are allowed for the profile picture.";
    } else {
        // generate safe filename
        try {
            $random = bin2hex(random_bytes(6));
        } catch (Exception $e) {
            $random = time();
        }
        $newName = time() . "_" . $random . "." . $ext;
        $target  = $uploadDir . $newName;

        if (move_uploaded_file($tmpName, $target)) {
            // save relative path for DB (relative to project root)
            $profile_pic_path = 'uploads/' . $newName;

            // optionally delete old image if exists and not default
            if (!empty($old_pic) && strpos($old_pic, 'default') === false) {
                $oldFull = __DIR__ . '/../' . $old_pic;
                if (file_exists($oldFull)) @unlink($oldFull);
            }
        } else {
            $errors[] = "Failed to move uploaded file.";
        }
    }
}

// If no validation errors, prepare the UPDATE
if (empty($errors)) {
    // Build SQL dynamically based on whether password is provided
    $sql = "UPDATE user SET first_name=?, last_name=?, contact=?, email=?, username=?, profile_pic=?, updated_at=NOW()";
    $types = "ssssss";
    $params = [$first_name, $last_name, $contact, $email, $username, $profile_pic_path];

    if ($hashedPassword !== null) {
        $sql .= ", password=?";
        $types .= "s";
        $params[] = $hashedPassword;
    }

    $sql .= " WHERE id=?";
    $types .= "i";
    $params[] = $userId;

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $_SESSION['error'] = "DB prepare failed: " . $conn->error;
        $_SESSION['open_profile_modal'] = true;
        header("Location: admin.php?page=dashboard");
        exit;
    }

    // bind params dynamically
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // update session values so topbar/modal reflect changes immediately
        $_SESSION['first_name']  = $first_name;
        $_SESSION['last_name']   = $last_name;
        $_SESSION['contact']     = $contact;
        $_SESSION['email']       = $email;
        $_SESSION['username']    = $username;
        $_SESSION['profile_pic'] = $profile_pic_path;

        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = "DB execute failed: " . $stmt->error;
    }

    $stmt->close();
} else {
    $_SESSION['error'] = implode("<br>", $errors);
}

// make modal re-open so user can see errors / success
$_SESSION['open_profile_modal'] = true;
header("Location: admin.php?page=dashboard");
exit;
