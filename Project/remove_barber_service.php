<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

$barberID = $_SESSION['BarberID'];
$barberServiceID = $_GET['id'] ?? null;
$redirect = $_GET['redirect'] ?? 'barber_dashboard.php?view=services';

if (!$barberServiceID) {
    header("Location: $redirect&message=Invalid service ID&success=0");
    exit();
}

// Verify this service belongs to the barber
$stmt = $conn->prepare("SELECT * FROM BarberServices WHERE BarberServiceID = ? AND BarberID = ? AND IsDeleted = 0");
$stmt->bind_param("ii", $barberServiceID, $barberID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: $redirect&message=Service not found&success=0");
    exit();
}

// Soft delete the service
$stmt = $conn->prepare("UPDATE BarberServices SET IsDeleted = 1 WHERE BarberServiceID = ?");
$stmt->bind_param("i", $barberServiceID);

if ($stmt->execute()) {
    header("Location: $redirect&message=Service removed successfully&success=1");
} else {
    header("Location: $redirect&message=Error removing service&success=0");
}
exit();
?>
