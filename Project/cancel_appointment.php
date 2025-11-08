<?php
session_start();
include 'db.php';
include 'mail.php'; // Include your PHPMailer setup

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit;
}

$UserID = $_SESSION['UserID'];

if (!isset($_GET['AppointmentID'])) {
    die("No appointment selected.");
}

$appointmentID = (int)$_GET['AppointmentID'];

// Fetch appointment details including barber and user emails
$stmt = $conn->prepare("
    SELECT a.UserID, a.BarberID, a.Time, a.Status, u.Name AS UserName, u.Email AS UserEmail, 
           b.Name AS BarberName, bu.Email AS BarberEmail
    FROM Appointment a
    JOIN User u ON a.UserID = u.UserID
    JOIN Barber b ON a.BarberID = b.BarberID
    JOIN User bu ON b.UserID = bu.UserID
    WHERE a.AppointmentID = ?
");
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

// Send emails
$mail = getMailer();

try {
    // To customer
    $mail->addAddress($appointment['UserEmail'], $appointment['UserName']);
    // To barber
    $mail->addAddress($appointment['BarberEmail'], $appointment['BarberName']);

    $mail->Subject = "Appointment Cancelled";
    $mail->Body    = "Hello,\n\nThe appointment scheduled on " . date('d M Y H:i', strtotime($appointment['Time'])) . 
                     " has been cancelled.\n\nRegards,\nYour Company";

    $mail->send();
} catch (Exception $e) {
    // You can log errors instead of showing them to users
    error_log("Mailer Error: {$mail->ErrorInfo}");
}

// Redirect
header("Location: view_appointment.php?message=Appointment+cancelled+successfully");
exit;
?>

