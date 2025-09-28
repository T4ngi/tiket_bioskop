<?php
session_start();
require 'config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_page.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("DELETE FROM pemesanan WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header("Location: admin_page.php");
exit();
?>
