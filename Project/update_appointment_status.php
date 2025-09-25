<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Validate POST input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentID = $_POST['AppointmentID'] ?? null;
    $status = $_POST['Status'] ?? null;
    $redirectView = $_POST['view'] ?? 'appointments'; // keeps the same tab

    if (!$appointmentID || !$status) {
        $_SESSION['flash_error'] = "Invalid request.";
        header("Location: admin_dashboard.php?view=" . urlencode($redirectView));
        exit();
    }

    // Update status in database
    $stmt = $conn->prepare("UPDATE Appointment SET Status = ? WHERE AppointmentID = ?");
    $stmt->bind_param("si", $status, $appointmentID);

    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Appointment status updated successfully.";
    } else {
        $_SESSION['flash_error'] = "Failed to update status: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    // Redirect back to the admin dashboard on the same tab
    header("Location: admin_dashboard.php?view=" . urlencode($redirectView));
    exit();
} else {
    // Invalid access
    $_SESSION['flash_error'] = "Invalid request method.";
    header("Location: admin_dashboard.php");
    exit();
}
?>

