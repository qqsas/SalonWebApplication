<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

$barberID = $_POST['BarberID'];
$date = $_POST['Date'];
$start = $_POST['StartTime'] ?: null;
$end = $_POST['EndTime'] ?: null;
$reason = $_POST['Reason'] ?: null;
$redirect = $_POST['redirect'] ?? 'barber_dashboard.php';

$stmt = $conn->prepare("INSERT INTO BarberUnavailability (BarberID, Date, StartTime, EndTime, Reason) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $barberID, $date, $start, $end, $reason);
$stmt->execute();

header("Location: $redirect&success=1&message=Unavailability added");
exit();
?>

