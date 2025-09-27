<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}
include 'db.php';

$enabled = $_POST['features'] ?? [];

// Disable all features first
$conn->query("UPDATE Features SET IsEnabled = 0");

// Enable only selected
if ($enabled) {
    $ids = implode(",", array_map('intval', $enabled));
    $conn->query("UPDATE Features SET IsEnabled = 1 WHERE FeatureID IN ($ids)");
}

header("Location: admin_dashboard.php?view=features&success=1&message=Features updated successfully");
exit();
?>

