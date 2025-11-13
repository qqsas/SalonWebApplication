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

// Validate required fields - ADDED 'appointment_name'
$required_fields = ['ServicesID', 'BarberID', 'selected_time', 'appointment_name'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) die("Missing required field: $field");
}

$ServicesID = (int)$_POST['ServicesID'];
$BarberID = (int)$_POST['BarberID'];
$selected_time = $_POST['selected_time'];
$appointment_name = trim($_POST['appointment_name']); // ADDED: Get appointment name

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

// Fetch user info - CHANGED: Now we only need email for sending confirmation
$stmt = $conn->prepare("SELECT Email FROM User WHERE UserID = ?");
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

// Insert appointment - UPDATED: Using appointment_name instead of user's name
$insertStmt = $conn->prepare("
    INSERT INTO Appointment (UserID, BarberID, ForName, ForAge, Type, Time, Duration, Status, Cost)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)
");

// UPDATED: Use the appointment_name from form instead of user's name
$for_name = $appointment_name;
$for_age = 30; // Placeholder - you might want to remove this field if not used
$type = $service['Name'];
$duration = (int)$service['Time'];
$cost = (float)$service['Price'];

$insertStmt->bind_param("iissssid", $UserID, $BarberID, $for_name, $for_age, $type, $selected_time, $duration, $cost);

if ($insertStmt->execute()) {
    $appointment_id = $conn->insert_id;

    // --- Send confirmation email using mail.php ---
    $mail = getMailer(); // Get PHPMailer object from mail.php

    try {
        $mail->addAddress($userData['Email']);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation';
        $mail->Body    = "
            <h2>Appointment Confirmed!</h2>
            <p>Dear {$appointment_name},</p>
            <p>Your appointment has been successfully booked with <strong>{$barberData['Name']}</strong> for the service <strong>{$type}</strong>.</p>
            <p><strong>Date & Time:</strong> " . date('F j, Y \a\t g:i A', strtotime($selected_time)) . "</p>
            <p><strong>Duration:</strong> {$duration} minutes</p>
            <p><strong>Cost:</strong> R" . number_format($cost, 2) . "</p>
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
<div class="confirmation-container">
    <link href="styles.css" rel="stylesheet">
    <h1>Appointment Confirmed!</h1>
    
    <div class="confirmation-details">
        <h2>Appointment Details</h2>
        <p>
            <strong>Service:</strong>
            <span><?= htmlspecialchars($type) ?></span>
        </p>
        <p>
            <strong>Barber:</strong>
            <span><?= htmlspecialchars($barberData['Name']) ?></span>
        </p>
        <p>
            <strong>For:</strong>
            <span><?= htmlspecialchars($appointment_name) ?></span> <!-- ADDED: Show appointment name -->
        </p>
        <p>
            <strong>Date & Time:</strong>
            <span><?= date('F j, Y \a\t g:i A', strtotime($selected_time)) ?></span>
        </p>
        <p>
            <strong>Duration:</strong>
            <span><?= $duration ?> minutes</span>
        </p>
        <p>
            <strong>Cost:</strong>
            <span>R<?= number_format($cost, 2) ?></span>
        </p>
    </div>
    
    <p class="confirmation-message <?= $email_sent ? 'success' : 'warning' ?>">
        <?php if ($email_sent): ?>
            ✓ A confirmation email has been sent to <?= htmlspecialchars($userData['Email']) ?>.
        <?php else: ?>
            ⚠ Appointment confirmed, but the confirmation email could not be sent.
        <?php endif; ?>
    </p>
    
    <div class="confirmation-actions">
        <a href="view_appointment.php" class="confirmation-btn view">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
            </svg>
            View My Appointments
        </a>
        <a href="index.php" class="confirmation-btn home">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
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
