<?php
session_start();
include 'db.php';
include 'mail.php'; // Include your mail setup

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$UserID = $_SESSION['UserID'];

// Ensure form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: book_appointment.php");
    exit();
}

// Validate required fields
$required_fields = ['ServicesID', 'BarberID', 'selected_time'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) die("Missing required field: $field");
}

$ServicesID = (int)$_POST['ServicesID'];
$BarberID = (int)$_POST['BarberID'];
$selected_time = $_POST['selected_time'];

if (!strtotime($selected_time)) die("Invalid time format.");

// Verify barber offers this service
$stmt = $conn->prepare("SELECT 1 FROM BarberServices WHERE BarberID = ? AND ServicesID = ?");
$stmt->bind_param("ii", $BarberID, $ServicesID);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) die("Selected barber does not provide this service.");
$stmt->close();

// Fetch service info
$stmt = $conn->prepare("SELECT Name, Price, Time FROM Services WHERE ServicesID = ?");
$stmt->bind_param("i", $ServicesID);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$service) die("Service not found.");

// Fetch barber info
$stmt = $conn->prepare("SELECT Name FROM Barber WHERE BarberID = ?");
$stmt->bind_param("i", $BarberID);
$stmt->execute();
$barberData = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$barberData) die("Barber not found.");

// Fetch user info
$stmt = $conn->prepare("SELECT Name, Email FROM User WHERE UserID = ?");
$stmt->bind_param("i", $UserID);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$userData) die("User not found.");

// Check time availability
$stmt = $conn->prepare("SELECT 1 FROM Appointment WHERE BarberID = ? AND Time = ? AND Status != 'Cancelled'");
$stmt->bind_param("is", $BarberID, $selected_time);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) die("Sorry, the selected time slot is no longer available.");
$stmt->close();

// Insert appointment
$insertStmt = $conn->prepare("
    INSERT INTO Appointment (UserID, BarberID, ForName, ForAge, Type, Time, Duration, Status, Cost)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)
");

$for_name = $userData['Name'];
$for_age = 30; // Placeholder
$type = $service['Name'];
$duration = (int)$service['Time'];
$cost = (float)$service['Price'];

$insertStmt->bind_param("iissssid", $UserID, $BarberID, $for_name, $for_age, $type, $selected_time, $duration, $cost);

if ($insertStmt->execute()) {
    $appointment_id = $conn->insert_id;

    // --- Send confirmation email using mail.php ---
    $mail = getMailer(); // Get PHPMailer object from mail.php

    try {
        $mail->addAddress($userData['Email'], $userData['Name']);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation';
        $mail->Body    = "
            <h2>Appointment Confirmed!</h2>
            <p>Dear {$userData['Name']},</p>
            <p>Your appointment has been successfully booked with <strong>{$barberData['Name']}</strong> for the service <strong>{$type}</strong>.</p>
            <p><strong>Date & Time:</strong> " . date('F j, Y \a\t g:i A', strtotime($selected_time)) . "</p>
            <p><strong>Duration:</strong> {$duration} minutes</p>
            <p><strong>Cost:</strong> $" . number_format($cost, 2) . "</p>
            <p>Thank you for choosing Kumar Kailey Hair & Beauty!</p>
        ";
        $mail->AltBody = "Appointment Confirmed! Your appointment with {$barberData['Name']} for {$type} is on " . date('F j, Y \a\t g:i A', strtotime($selected_time)) . ".";

        $mail->send();
        $email_sent = true;
    } catch (Exception $e) {
        $email_sent = false;
        error_log("Mail Error: {$mail->ErrorInfo}");
    }

    include 'header.php';
    ?>
    <div style="max-width: 600px; margin: 50px auto; padding: 20px; text-align: center;">
        <h1>Appointment Confirmed!</h1>
        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2>Appointment Details</h2>
            <p><strong>Service:</strong> <?= htmlspecialchars($type) ?></p>
            <p><strong>Barber:</strong> <?= htmlspecialchars($barberData['Name']) ?></p>
            <p><strong>Date & Time:</strong> <?= date('F j, Y \a\t g:i A', strtotime($selected_time)) ?></p>
            <p><strong>Duration:</strong> <?= $duration ?> minutes</p>
            <p><strong>Cost:</strong> $<?= number_format($cost, 2) ?></p>
        </div>
        <p>
            <?php if ($email_sent): ?>
                A confirmation email has been sent to <?= htmlspecialchars($userData['Email']) ?>.
            <?php else: ?>
                Appointment confirmed, but the confirmation email could not be sent.
            <?php endif; ?>
        </p>
        <div style="margin-top: 30px;">
            <a href="view_appointment.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">
                View My Appointments
            </a>
            <a href="homepage.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
                Return to Home
            </a>
        </div>
    </div>
    <?php
    include 'footer.php';
} else {
    die("Error booking appointment: " . $conn->error);
}

$insertStmt->close();
$conn->close();
?>

