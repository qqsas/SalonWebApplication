<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit;
}

$UserID = $_SESSION['UserID'];

if (!isset($_GET['AppointmentID'])) {
    die("No appointment selected.");
}

$appointmentID = (int)$_GET['AppointmentID'];

// Fetch appointment
$stmt = $conn->prepare("SELECT UserID, Time, Status FROM Appointment WHERE AppointmentID=?");
$stmt->bind_param("i", $appointmentID);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) die("Appointment not found.");
if ($appointment['UserID'] != $UserID) die("Unauthorized.");

// Check if appointment is today or in the past
$appointmentDate = date('Y-m-d', strtotime($appointment['Time']));
$today = date('Y-m-d');
if ($appointmentDate <= $today) die("Cannot cancel appointment on the day of or after the appointment.");

// Update status to cancelled
$stmt = $conn->prepare("UPDATE Appointment SET Status='Cancelled' WHERE AppointmentID=?");
$stmt->bind_param("i", $appointmentID);
$stmt->execute();
$stmt->close();

header("Location: view_appointment.php?message=Appointment+cancelled+successfully");
exit;
?>

