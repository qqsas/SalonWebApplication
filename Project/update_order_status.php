<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderID = intval($_POST['OrderID']);
    $status = $_POST['Status'] ?? '';
    $redirect = $_POST['redirect'] ?? 'admin_dashboard.php?view=orders';

    if ($orderID > 0 && in_array($status, ['Pending','Processing','Completed','Cancelled'])) {
        $stmt = $conn->prepare("UPDATE Orders SET Status = ? WHERE OrderID = ?");
        $stmt->bind_param("si", $status, $orderID);
        $stmt->execute();
    }

    header("Location: $redirect");
    exit();
} else {
    header("Location: admin_dashboard.php?view=orders");
    exit();
}
?>

