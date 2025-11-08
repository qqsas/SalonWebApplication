<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

// Validate POST input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reviewID = $_POST['ReviewID'] ?? null;
    $status = $_POST['Status'] ?? null; // e.g., 'Pending', 'Approved', 'Rejected'
    $redirectView = $_POST['view'] ?? 'reviews'; // keep the same tab

    if (!$reviewID || !$status) {
        $_SESSION['flash_error'] = "Invalid request.";
        header("Location: admin_dashboard.php?view=" . urlencode($redirectView));
        exit();
    }

    // Update review status in database
    $stmt = $conn->prepare("UPDATE Reviews SET Status = ? WHERE ReviewID = ?");
    $stmt->bind_param("si", $status, $reviewID);

    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Review status updated successfully.";
    } else {
        $_SESSION['flash_error'] = "Failed to update status: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    // Redirect back to admin_dashboard on the same tab
    header("Location: admin_dashboard.php?view=" . urlencode($redirectView));
    exit();
} else {
    // Invalid access
    $_SESSION['flash_error'] = "Invalid request method.";
    header("Location: admin_dashboard.php");
    exit();
}
?>

