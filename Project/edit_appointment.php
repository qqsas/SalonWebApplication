<?php
session_start();
include 'db.php';
include 'mail.php'; // Include your mail setup

// --- Access Control: Only admin or logged-in user can edit ---
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

// --- Validate and sanitize appointment ID ---
$appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$appointment_id || $appointment_id <= 0) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: admin_dashboard.php");
    exit();
}

// --- Fetch appointment details with prepared statement ---
$stmt = $conn->prepare("SELECT * FROM Appointment WHERE AppointmentID = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: admin_dashboard.php");
    exit();
}

$stmt->bind_param("i", $appointment_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    $_SESSION['error'] = "Failed to fetch appointment details.";
    header("Location: admin_dashboard.php");
    exit();
}

$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found.";
    header("Location: admin_dashboard.php");
    exit();
}

// --- Initialize variables for form data ---
$form_errors = [];
$forName = $forAge = $serviceId = $barberId = $time = $duration = $status = $cost = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Sanitize and validate input data ---
    $forName   = trim(filter_input(INPUT_POST, 'forName', FILTER_SANITIZE_STRING));
    $forAge    = filter_input(INPUT_POST, 'forAge', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);
    $serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $barberId  = filter_input(INPUT_POST, 'barber_id', FILTER_VALIDATE_INT);
    $time      = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_STRING);
    $duration  = filter_input(INPUT_POST, 'duration', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status    = trim(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING));
    $cost      = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
    
    // --- Validation checks ---
    if (empty($forName) || strlen($forName) > 100) {
        $form_errors[] = "Name is required and must be less than 100 characters.";
    }
    
    if (!$forAge) {
        $form_errors[] = "Valid age between 1 and 120 is required.";
    }
    
    if (!$serviceId || $serviceId <= 0) {
        $form_errors[] = "Please select a valid service.";
    }
    
    if (!$barberId || $barberId <= 0) {
        $form_errors[] = "Please select a valid barber.";
    }
    
    if (empty($time) || !strtotime($time)) {
        $form_errors[] = "Please select a valid date and time.";
    } elseif (strtotime($time) < time()) {
        $form_errors[] = "Appointment time cannot be in the past.";
    }
    
    if (!$duration) {
        $form_errors[] = "Valid duration in minutes is required.";
    }
    
    $allowed_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    if (empty($status) || !in_array($status, $allowed_statuses)) {
        $form_errors[] = "Please select a valid status.";
    }
    
    if ($cost === false || $cost < 0) {
        $form_errors[] = "Valid cost amount is required.";
    }
    
    // --- If no validation errors, proceed with update ---
    if (empty($form_errors)) {
        // Fetch service name with validation
        $stmt = $conn->prepare("SELECT Name FROM Services WHERE ServicesID = ?");
        if ($stmt) {
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $service_result = $stmt->get_result();
            $service = $service_result->fetch_assoc();
            $stmt->close();
            $serviceType = $service ? $service['Name'] : 'Custom';
        } else {
            $serviceType = 'Custom';
            error_log("Service fetch failed: " . $conn->error);
        }

        // Update appointment with transaction for data consistency
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("UPDATE Appointment 
                SET ForName=?, ForAge=?, Type=?, BarberID=?, Time=?, Duration=?, Status=?, Cost=? 
                WHERE AppointmentID=?");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("sssisidsi", $forName, $forAge, $serviceType, $barberId, $time, $duration, $status, $cost, $appointment_id);
            
            if ($stmt->execute()) {
                // --- Fetch user and barber emails for notifications ---
                $email_stmt = $conn->prepare("
                    SELECT u.Name AS UserName, u.Email AS UserEmail, b.Name AS BarberName, bu.Email AS BarberEmail
                    FROM Appointment a
                    JOIN User u ON a.UserID = u.UserID
                    JOIN Barber b ON a.BarberID = b.BarberID
                    JOIN User bu ON b.UserID = bu.UserID
                    WHERE a.AppointmentID = ?
                ");
                
                if ($email_stmt) {
                    $email_stmt->bind_param("i", $appointment_id);
                    $email_stmt->execute();
                    $emails = $email_stmt->get_result()->fetch_assoc();
                    $email_stmt->close();
                    
                    if ($emails) {
                        $mail = getMailer(); // PHPMailer object
                        
                        try {
                            // Add recipients
                            $mail->addAddress($emails['UserEmail'], $emails['UserName']);
                            $mail->addAddress($emails['BarberEmail'], $emails['BarberName']);
                            
                            $mail->isHTML(true);
                            $mail->Subject = "Appointment Updated: {$status}";
                            $mail->Body = "
                                <h2>Appointment Update</h2>
                                <p>Dear Customer/Barber,</p>
                                <p>The appointment for <strong>" . htmlspecialchars($forName) . "</strong> with <strong>" . htmlspecialchars($emails['BarberName']) . "</strong> 
                                for the service <strong>" . htmlspecialchars($serviceType) . "</strong> scheduled on <strong>" . date('F j, Y \a\t g:i A', strtotime($time)) . "</strong> 
                                has been updated to status: <strong>" . htmlspecialchars($status) . "</strong>.</p>
                                <p>Duration: {$duration} minutes<br>Cost: R" . number_format($cost, 2) . "</p>
                                <p>Thank you!</p>
                            ";
                            $mail->AltBody = "The appointment for " . htmlspecialchars($forName) . " with " . htmlspecialchars($emails['BarberName']) . " for " . htmlspecialchars($serviceType) . " on " . date('F j, Y \a\t g:i A', strtotime($time)) . " has been updated. Status: " . htmlspecialchars($status) . ".";
                            
                            if (!$mail->send()) {
                                error_log("Mail Error: {$mail->ErrorInfo}");
                            }
                        } catch (Exception $e) {
                            error_log("Mail Exception: {$e->getMessage()}");
                        }
                    }
                }
                
                $conn->commit();
                $_SESSION['success'] = "Appointment updated successfully.";
                
                // Redirect to prevent form resubmission
                header("Location: edit_appointment.php?id=" . $appointment_id);
                exit();
                
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Appointment update error: " . $e->getMessage());
            $form_errors[] = "Error updating appointment: " . $conn->error;
        }
    }
}

// --- Fetch barbers with error handling ---
$barbers_result = $conn->query("SELECT BarberID, Name FROM Barber");
if (!$barbers_result) {
    error_log("Barbers query failed: " . $conn->error);
    $barbers = [];
} else {
    $barbers = $barbers_result;
}

// --- Fetch services with error handling ---
$services_result = $conn->query("SELECT ServicesID, Name, Price, Time FROM Services WHERE IsDeleted=0");
if (!$services_result) {
    error_log("Services query failed: " . $conn->error);
    $services = [];
} else {
    $services = $services_result;
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - Admin</title>
    <link href="addedit.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Edit Appointment</h2>

    <div class="form-container">
        <!-- Display session messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Display form validation errors -->
        <?php if (!empty($form_errors)): ?>
            <div class="message error">
                <ul>
                    <?php foreach ($form_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="forName">For Name:</label>
                <input type="text" name="forName" id="forName" 
                       value="<?= htmlspecialchars($_POST['forName'] ?? $appointment['ForName']) ?>" 
                       required maxlength="100">
                <small class="form-text">Required, max 100 characters</small>
            </div>

            <div class="form-group">
                <label for="forAge">For Age:</label>
                <input type="number" name="forAge" id="forAge" 
                       value="<?= htmlspecialchars($_POST['forAge'] ?? $appointment['ForAge']) ?>" 
                       required min="1" max="120">
                <small class="form-text">Must be between 1 and 120</small>
            </div>

            <div class="form-group">
                <label for="service_id">Service:</label>
                <select name="service_id" id="service_id" required>
                    <option value="">Select a service</option>
                    <?php if ($services): ?>
                        <?php while ($service = $services->fetch_assoc()) { ?>
                            <option value="<?= $service['ServicesID'] ?>" 
                                <?= ($service['ServicesID'] == ($_POST['service_id'] ?? $appointment['Type'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($service['Name']) ?> - R<?= $service['Price'] ?> (<?= $service['Time'] ?> min)
                            </option>
                        <?php } ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="barber_id">Barber:</label>
                <select name="barber_id" id="barber_id" required>
                    <option value="">Select a barber</option>
                    <?php if ($barbers): ?>
                        <?php while ($barber = $barbers->fetch_assoc()) { ?>
                            <option value="<?= $barber['BarberID'] ?>" 
                                <?= ($barber['BarberID'] == ($_POST['barber_id'] ?? $appointment['BarberID'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($barber['Name']) ?>
                            </option>
                        <?php } ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="time">Time:</label>
                <input type="datetime-local" name="time" id="time" 
                       value="<?= htmlspecialchars($_POST['time'] ?? date('Y-m-d\TH:i', strtotime($appointment['Time']))) ?>" 
                       required min="<?= date('Y-m-d\TH:i') ?>">
                <small class="form-text">Cannot be in the past</small>
            </div>

            <div class="form-group">
                <label for="duration">Duration (minutes):</label>
                <input type="number" name="duration" id="duration" 
                       value="<?= htmlspecialchars($_POST['duration'] ?? $appointment['Duration']) ?>" 
                       required min="1">
                <small class="form-text">Must be at least 1 minute</small>
            </div>

            <div class="form-group">
                <label for="cost">Cost:</label>
                <input type="number" step="0.01" name="cost" id="cost" 
                       value="<?= htmlspecialchars($_POST['cost'] ?? $appointment['Cost']) ?>" 
                       required min="0">
                <small class="form-text">Must be 0 or greater</small>
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" required>
                    <option value="">Select status</option>
                    <option value="Pending"   <?= (($_POST['status'] ?? $appointment['Status']) == "Pending") ? "selected" : "" ?>>Pending</option>
                    <option value="Confirmed" <?= (($_POST['status'] ?? $appointment['Status']) == "Confirmed") ? "selected" : "" ?>>Confirmed</option>
                    <option value="Completed" <?= (($_POST['status'] ?? $appointment['Status']) == "Completed") ? "selected" : "" ?>>Completed</option>
                    <option value="Cancelled" <?= (($_POST['status'] ?? $appointment['Status']) == "Cancelled") ? "selected" : "" ?>>Cancelled</option>
                </select>
            </div>

            <div class="button-group">
                <button type="submit" class="btn">Update Appointment</button>
                <a href="admin_dashboard.php" class="btn" style="text-align: center;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
