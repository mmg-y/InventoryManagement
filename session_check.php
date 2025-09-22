<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['type'])) {
    header("Location: index.php");
    exit();
}
