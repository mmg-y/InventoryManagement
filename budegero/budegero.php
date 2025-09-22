<?php
include "session_check.php";

// Only allow bodegero
if ($_SESSION['type'] !== "bodegero") {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Bodegero Dashboard</title>
</head>

<body>
    <h1>Welcome Bodegero, <?= $_SESSION['username']; ?>!</h1>
    <a href="logout.php">Logout</a>
</body>

</html>