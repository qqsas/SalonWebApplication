<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$UserID = $_SESSION['UserID'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: book_appointment.php");
    exit();
}

// Validate required fields
$required_fields = ['ServicesID', 'BarberID', 'selected_time'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo "Missing required field: $field";
        exit();
    }
}

$ServicesID = (int)$_POST['ServicesID'];
$BarberID = (int)$_POST['BarberID'];
$selected_time = $_POST['selected_time'];

// Verify the selected time is valid
if (!strtotime($selected_time)) {
    echo "Invalid time format.";
    exit();
}

// Verify barber offers this service
$checkServiceStmt = $conn->prepare("
    SELECT 1 FROM BarberServices WHERE BarberID = ? AND ServicesID = ?
");
$checkServiceStmt->bind_param("ii", $BarberID, $ServicesID);
$checkServiceStmt->execute();
$checkResult = $checkServiceStmt->get_result();
if ($checkResult->num_rows === 0) {
    echo "Selected barber does not provide this service.";
    exit();
}

// Fetch service info
$serviceStmt = $conn->prepare("SELECT Name, Price, Time FROM Services WHERE ServicesID = ?");
$serviceStmt->bind_param("i", $ServicesID);
$serviceStmt->execute();
$serviceResult = $serviceStmt->get_result();
$service = $serviceResult->fetch_assoc();
if (!$service) {
    echo "Service not found.";
    exit();
}

// Fetch barber info
$barberStmt = $conn->prepare("SELECT Name FROM Admin WHERE BarberID = ?");
$barberStmt->bind_param("i", $BarberID);
$barberStmt->execute();
$barberResult = $barberStmt->get_result();
$barber = $barberResult->fetch_assoc();
if (!$barber) {
    echo "Barber not found.";
    exit();
}

// Fetch user info
$userStmt = $conn->prepare("SELECT Name, Email FROM User WHERE UserID = ?");
$userStmt->bind_param("i", $UserID);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
if (!$user) {
    echo "User not found.";
    exit();
}

// Check if the selected time slot is still available
$appointmentStmt = $conn->prepare("
    SELECT 1 FROM Appointment 
    WHERE BarberID = ? AND Time = ? AND Status != 'Cancelled'
");
$appointmentStmt->bind_param("is", $BarberID, $selected_time);
$appointmentStmt->execute();
$appointmentResult = $appointmentStmt->get_result();
if ($appointmentResult->num_rows > 0) {
    echo "Sorry, the selected time slot is no longer available. Please choose another time.";
    exit();
}

// Insert the appointment into the database
$insertStmt = $conn->prepare("
    INSERT INTO Appointment (UserID, BarberID, ForName, ForAge, Type, Time, Duration, Status, Cost)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)
");

// For this example, we'll use the user's name and a default age
// In a real application, you would collect this information from the user
$for_name = $user['Name'];
$for_age = 30; // Default age - you should collect this from the user
$type = $service['Name'];
$duration = $service['Time'];
$cost = $service['Price'];

$insertStmt->bind_param("iissssid", $UserID, $BarberID, $for_name, $for_age, $type, $selected_time, $duration, $cost);

if ($insertStmt->execute()) {
    $appointment_id = $conn->insert_id;
    
    // Send confirmation email (pseudo-code)
    // sendConfirmationEmail($user['Email'], $user['Name'], $barber['Name'], $service['Name'], $selected_time);
    
    // Display confirmation page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Appointment Confirmed</title>
        <link href="styles.css" rel="stylesheet">
        <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    </head>
    <body>
        <?php include 'header.php'; ?>
        
        <div style="max-width: 600px; margin: 50px auto; padding: 20px; text-align: center;">
            <h1>Appointment Confirmed!</h1>
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>Appointment Details</h2>
                <p><strong>Service:</strong> <?= htmlspecialchars($service['Name']) ?></p>
                <p><strong>Barber:</strong> <?= htmlspecialchars($barber['Name']) ?></p>
                <p><strong>Date & Time:</strong> <?= date('F j, Y \a\t g:i A', strtotime($selected_time)) ?></p>
                <p><strong>Duration:</strong> <?= $service['Time'] ?> minutes</p>
                <p><strong>Cost:</strong> $<?= number_format($service['Price'], 2) ?></p>
	    </div>

	<div>
            
	    <p>A confirmation email has been sent to <?= htmlspecialchars($user['Email']) ?></p>
	</div>
            
            <div style="margin-top: 30px;">
                <a href="my_appointments.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">
                    View My Appointments
                </a>
                <a href="index.php" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
                    Return to Home
                </a>
            </div>
        </div>
        
        <?php include 'footer.php'; ?>
    </body>
    </html>
    <?php
} else {
    echo "Error booking appointment: " . $conn->error;
}

// Close connections
$insertStmt->close();
$conn->close();
?>
