<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

$unavailabilityID = $_POST['UnavailabilityID'];
$redirect = $_POST['redirect'] ?? 'barber_dashboard.php';

$stmt = $conn->prepare("DELETE FROM BarberUnavailability WHERE UnavailabilityID = ?");
$stmt->bind_param("i", $unavailabilityID);
$stmt->execute();

header("Location: $redirect&success=1&message=Unavailability removed");
exit();
?>

