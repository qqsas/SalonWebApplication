<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db.php';

// Ensure only admins can update roles
if (!isset($_SESSION['UserID']) || ($_SESSION['Role'] ?? '') !== 'admin') {
    header("Location: Login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['UserID'], $_POST['Role'])) {
    $userID = intval($_POST['UserID']);
    $newRole = trim($_POST['Role']);
    $redirect = $_POST['redirect'] ?? 'admin_dashboard.php?view=users';

    // Validate new role
    $allowedRoles = ['client', 'admin', 'barber'];
    if (!in_array($newRole, $allowedRoles)) {
        $msg = urlencode("Invalid role specified");
        header("Location: {$redirect}&message={$msg}&success=0");
        exit();
    }

    // Check if the user exists and is not deleted
    $stmt = $conn->prepare("SELECT UserID, Role FROM User WHERE UserID = ? AND IsDeleted = 0");
    if (!$stmt) {
        $msg = urlencode("Database prepare error");
        header("Location: {$redirect}&message={$msg}&success=0");
        exit();
    }

    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $msg = urlencode("User not found or deleted");
        header("Location: {$redirect}&message={$msg}&success=0");
        $stmt->close();
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Prevent users from changing their own role
    if ($userID === intval($_SESSION['UserID'])) {
        $msg = urlencode("You cannot change your own role");
        header("Location: {$redirect}&message={$msg}&success=0");
        exit();
    }

    // Update the user's role
    $updateStmt = $conn->prepare("UPDATE User SET Role = ? WHERE UserID = ?");
    if (!$updateStmt) {
        $msg = urlencode("Database update prepare failed");
        header("Location: {$redirect}&message={$msg}&success=0");
        exit();
    }

    $updateStmt->bind_param("si", $newRole, $userID);

    if ($updateStmt->execute()) {
        $msg = urlencode("User role updated successfully");
        header("Location: {$redirect}&message={$msg}&success=1");
    } else {
        $msg = urlencode("Failed to update user role");
        header("Location: {$redirect}&message={$msg}&success=0");
    }

    $updateStmt->close();
    exit();

} else {
    $msg = urlencode("Invalid request method or missing data");
    header("Location: admin_dashboard.php?view=users&message={$msg}&success=0");
    exit();
}
?>

