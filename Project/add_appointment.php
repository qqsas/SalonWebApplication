<?php
session_start();
if (!isset($_SESSION['UserID']) || ($_SESSION['Role'] !== 'admin' && $_SESSION['Role'] !== 'barber')) {
    header("Location: Login.php");
    exit();
}
include 'db.php';
include 'mail.php';
include 'header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Input sanitization ---
    $userID   = isset($_POST['UserID']) && $_POST['UserID'] !== '' ? filter_input(INPUT_POST, 'UserID', FILTER_VALIDATE_INT) : null;
    $newName  = trim($_POST['NewUserName'] ?? '');
    $newEmail = trim($_POST['NewUserEmail'] ?? '');
    $newPhone = trim($_POST['NewUserPhone'] ?? '');
    $barberID = filter_input(INPUT_POST, 'BarberID', FILTER_VALIDATE_INT);
    $forName  = trim($_POST['ForName'] ?? '');
    // Auto-set age to 1
    $forAge   = 1;
    $type     = trim($_POST['Type'] ?? '');
    $time     = trim($_POST['Time'] ?? '');
    $duration = filter_input(INPUT_POST, 'Duration', FILTER_VALIDATE_INT);
    $cost     = filter_input(INPUT_POST, 'Cost', FILTER_VALIDATE_FLOAT);
    $status   = $_POST['Status'] ?? 'Scheduled';

    // --- Handle pseudo user creation ---
// --- Handle pseudo user creation ---
if (!$userID && !empty($newName)) {
    // Validate that at least email or phone is provided
    if (empty($newEmail) && empty($newPhone)) {
        $errors[] = "Please enter either email or phone number for the new user.";
    } elseif (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email for the new user.";
    } elseif (!empty($newPhone) && !preg_match('/^[0-9+\-\s()]{10,20}$/', $newPhone)) {
        $errors[] = "Please enter a valid phone number (10-20 digits).";
    } elseif (strlen($newName) > 100) {
        $errors[] = "New user name must be under 100 characters.";
    } elseif (!empty($newEmail) && strlen($newEmail) > 100) {
        $errors[] = "Email must be under 100 characters.";
    } elseif (!empty($newPhone) && strlen($newPhone) > 20) {
        $errors[] = "Phone number must be under 20 characters.";
    } else {
        // Check if user with same email or phone already exists
        // Build query dynamically based on which fields are provided
        $checkQuery = "SELECT UserID FROM User WHERE IsDeleted = 0 AND (";
        $params = [];
        $types = "";
        
        if (!empty($newEmail) && !empty($newPhone)) {
            $checkQuery .= "Email = ? OR Number = ?";
            $params[] = $newEmail;
            $params[] = $newPhone;
            $types = "ss";
        } elseif (!empty($newEmail)) {
            $checkQuery .= "Email = ?";
            $params[] = $newEmail;
            $types = "s";
        } elseif (!empty($newPhone)) {
            $checkQuery .= "Number = ?";
            $params[] = $newPhone;
            $types = "s";
        }
        
        $checkQuery .= ")";
        
        $checkStmt = $conn->prepare($checkQuery);
        if ($checkStmt) {
            if (!empty($params)) {
                $checkStmt->bind_param($types, ...$params);
            }
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            if ($result->num_rows > 0) {
                $existingUser = $result->fetch_assoc();
                if (!empty($newEmail)) {
                    $errors[] = "A user with this email address already exists. Please select the existing user from the dropdown or use a different email.";
                } else {
                    $errors[] = "A user with this phone number already exists. Please select the existing user from the dropdown or use a different phone number.";
                }
            }
            $checkStmt->close();
        }

        // Create pseudo user if no errors
        if (empty($errors)) {
            // Handle empty values for database insertion
            $dbEmail = !empty($newEmail) ? $newEmail : '';
            $dbPhone = !empty($newPhone) ? $newPhone : '';
            
            $pseudoStmt = $conn->prepare("
                INSERT INTO User (Name, Email, Number, Password, Role, IsDeleted)
                VALUES (?, ?, ?, '', 'pseudo', 0)
            ");
            if ($pseudoStmt) {
                $pseudoStmt->bind_param("sss", $newName, $dbEmail, $dbPhone);
                if ($pseudoStmt->execute()) {
                    $userID = $pseudoStmt->insert_id;
                } else {
                    $errors[] = "Failed to create pseudo user: " . htmlspecialchars($pseudoStmt->error);
                }
                $pseudoStmt->close();
            } else {
                $errors[] = "Database error creating pseudo user: " . htmlspecialchars($conn->error);
            }
        }
    }
}
    // --- Validation ---
    if (!$userID) $errors[] = "Please select a valid user or enter a new one.";
    if (!$barberID) $errors[] = "Please select a valid barber.";
    
    // ForName validation - matches database varchar(100)
    if (empty($forName)) {
        $errors[] = "ForName is required.";
    } elseif (strlen($forName) > 100) {
        $errors[] = "ForName must be under 100 characters.";
    }
    
    // Type validation - database shows varchar(50) but code uses 100
    // FIXED: Changed to match database varchar(50)
    if (empty($type)) {
        $errors[] = "Service type is required.";
    } elseif (strlen($type) > 50) {
        $errors[] = "Service type must be under 50 characters.";
    }
    
    // Time validation
    if (empty($time)) {
        $errors[] = "Please select a valid date and time.";
    } elseif (!strtotime($time)) {
        $errors[] = "Invalid time format.";
    } elseif (strtotime($time) < time()) {
        $errors[] = "Appointment time cannot be in the past.";
    }
    
    // Duration validation - database shows int(11)
    if ($duration <= 0 || $duration > 600) {
        $errors[] = "Duration must be between 1 and 600 minutes.";
    }
    
    // Cost validation - database shows decimal(10,2)
    if ($cost === false || $cost < 0 || $cost > 10000) {
        $errors[] = "Cost must be a positive number under 10,000.";
    }
    
    // Status validation - database shows varchar(50)
    if (!in_array($status, ['Scheduled', 'Completed', 'Cancelled'])) {
        $errors[] = "Invalid status.";
    }

    // --- Check barber availability ---
    if (empty($errors)) {
        // Convert appointment time to check for conflicts
        $appointmentTime = date('Y-m-d H:i:s', strtotime($time));
        $appointmentEndTime = date('Y-m-d H:i:s', strtotime($time . " + $duration minutes"));
        
        // Check for existing appointments for the same barber at the same time
        $conflictCheck = $conn->prepare("
            SELECT AppointmentID FROM Appointment 
            WHERE BarberID = ? 
            AND Status != 'Cancelled'
            AND IsDeleted = 0
            AND (
                (Time <= ? AND DATE_ADD(Time, INTERVAL Duration MINUTE) > ?)
                OR (Time < ? AND DATE_ADD(Time, INTERVAL Duration MINUTE) >= ?)
                OR (Time >= ? AND Time < ?)
            )
        ");
        if ($conflictCheck) {
            $conflictCheck->bind_param("issssss", $barberID, $appointmentTime, $appointmentTime, $appointmentEndTime, $appointmentEndTime, $appointmentTime, $appointmentEndTime);
            $conflictCheck->execute();
            $result = $conflictCheck->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "The selected barber already has an appointment scheduled during this time.";
            }
            $conflictCheck->close();
        }
    }

    // --- Appointment Insert ---
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO Appointment (UserID, BarberID, ForName, ForAge, Type, Time, Duration, Cost, Status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $formattedTime = date('Y-m-d H:i:s', strtotime($time));
            $stmt->bind_param("iissisdds", $userID, $barberID, $forName, $forAge, $type, $formattedTime, $duration, $cost, $status);
            if ($stmt->execute()) {
                $success = "Appointment added successfully!";
                $appointment_id = $conn->insert_id;

                // Fetch user & barber info for mail
                $stmt2 = $conn->prepare("
                    SELECT u.Name AS UserName, u.Email AS UserEmail,
                           b.Name AS BarberName, bu.Email AS BarberEmail
                    FROM Appointment a
                    JOIN User u ON a.UserID = u.UserID
                    JOIN Barber b ON a.BarberID = b.BarberID
                    JOIN User bu ON b.UserID = bu.UserID
                    WHERE a.AppointmentID = ?
                ");
                $stmt2->bind_param("i", $appointment_id);
                $stmt2->execute();
                $emails = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                // --- Send Email Notifications ---
                if ($emails && !empty($emails['UserEmail'])) {
                    $mail = getMailer();
                    try {
                        $mail->addAddress($emails['UserEmail'], $emails['UserName']);
                        $mail->addAddress($emails['BarberEmail'], $emails['BarberName']);
                        $mail->isHTML(true);
                        $mail->Subject = "New Appointment Scheduled";
                        $mail->Body = "
                            <h2>New Appointment Scheduled</h2>
                            <p>Dear Customer/Barber,</p>
                            <p>An appointment has been scheduled for <strong>" . htmlspecialchars($forName) . "</strong> 
                            with <strong>" . htmlspecialchars($emails['BarberName']) . "</strong> 
                            for the service <strong>" . htmlspecialchars($type) . "</strong>.</p>
                            <p><strong>Date & Time:</strong> " . date('F j, Y \a\t g:i A', strtotime($time)) . "</p>
                            <p><strong>Duration:</strong> {$duration} minutes<br>
                            <strong>Cost:</strong> R" . number_format($cost, 2) . "<br>
                            <strong>Status:</strong> {$status}</p>
                            <p>Thank you for choosing our services!</p>
                        ";
                        $mail->AltBody = "Appointment for {$forName} with {$emails['BarberName']} for {$type} on " .
                            date('F j, Y \a\t g:i A', strtotime($time)) . ". Status: {$status}.";
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Mail Error: {$mail->ErrorInfo}");
                    }
                }
            } else {
                $errors[] = "Database error: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = "Database prepare failed: " . htmlspecialchars($conn->error);
        }
    }
}

// --- Fetch dropdown data ---
$users   = $conn->query("SELECT UserID, Name FROM User WHERE IsDeleted = 0 ORDER BY Name ASC");
$barbers = $conn->query("SELECT BarberID, Name FROM Barber WHERE IsDeleted = 0 ORDER BY Name ASC");
$services = $conn->query("SELECT ServicesID, Name, Time, Price FROM Services WHERE IsDeleted = 0 ORDER BY Name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Appointment - Admin</title>
    <link rel="stylesheet" href="addedit.css">
    <style>
        .error ul { margin: 0; padding-left: 20px; }
        input:invalid, select:invalid { border-color: red; }
        .pseudo-section { border: 1px solid #ccc; padding: 10px; border-radius: 8px; margin-top: 10px; }
        .pseudo-section label { display: block; margin-top: 5px; }
        .service-option { display: flex; justify-content: space-between; }
        .service-details { font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
<div class="form-container">
    <h2>Add New Appointment</h2>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
        <div class="form-group">
            <label>Existing User:</label>
            <select name="UserID">
                <option value="">-- Select Existing User (optional) --</option>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <option value="<?= $u['UserID'] ?>"><?= htmlspecialchars($u['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="pseudo-section">
            <label>OR Create New (Pseudo) User:</label>
            <input type="text" name="NewUserName" placeholder="Enter new user's name" required maxlength="100">
            <input type="email" name="NewUserEmail" placeholder="Enter new user's email (optional)" maxlength="100">
            <input type="tel" name="NewUserPhone" placeholder="Enter new user's phone (optional)" pattern="[0-9+\-\s()]{10,20}" maxlength="20">
            <small>Provide either email or phone number. Age will be automatically set to 1.</small>
        </div>

        <div class="form-group">
            <label>Barber:</label>
            <select name="BarberID" required>
                <option value="">-- Select Barber --</option>
                <?php while ($b = $barbers->fetch_assoc()): ?>
                    <option value="<?= $b['BarberID'] ?>"><?= htmlspecialchars($b['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>For Name:</label>
            <input type="text" name="ForName" maxlength="100" required>
        </div>

        <div class="form-group">
            <label>Service Type:</label>
            <select id="ServiceSelect" required>
                <option value="">-- Select Service Type --</option>
                <?php while ($service = $services->fetch_assoc()): ?>
                    <option value="<?= $service['ServicesID'] ?>" 
                            data-name="<?= htmlspecialchars(substr($service['Name'], 0, 50)) ?>"
                            data-duration="<?= $service['Time'] ?>"
                            data-cost="<?= $service['Price'] ?>">
                        <div class="service-option">
                            <span><?= htmlspecialchars($service['Name']) ?></span>
                            <span class="service-details">
                                <?= $service['Time'] ?> min - R<?= number_format($service['Price'], 2) ?>
                            </span>
                        </div>
                    </option>
                <?php endwhile; ?>
                <option value="custom">Custom Service</option>
            </select>
            <input type="text" name="Type" id="Type" maxlength="50" required style="display: none;" placeholder="Enter custom service type">
        </div>

        <div class="form-group">
            <label>Duration (minutes):</label>
            <input type="number" name="Duration" id="Duration" min="1" max="600" required>
        </div>

        <div class="form-group">
            <label>Cost:</label>
            <input type="number" name="Cost" id="Cost" step="0.01" min="0" max="10000" required>
        </div>

        <div class="form-group">
            <label>Time:</label>
            <input type="datetime-local" name="Time" required>
        </div>

        <div class="form-group">
            <label>Status:</label>
            <select name="Status">
                <option value="Scheduled">Scheduled</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>

<div class="button-group">
    <button type="submit" class="btn-primary">Update Product</button>
    <a href="<?php
        if (isset($_SESSION['Role']) && $_SESSION['Role'] === 'barber') {
            echo 'barber_dashboard.php';
        } else {
            echo 'admin_dashboard.php?view=products';
        }
    ?>" class="btn-cancel">Cancel</a>
</div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const serviceSelect = document.getElementById('ServiceSelect');
    const typeInput = document.getElementById('Type');
    const durationInput = document.getElementById('Duration');
    const costInput = document.getElementById('Cost');

    serviceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value === 'custom') {
            // Show custom input and clear auto-filled values
            typeInput.style.display = 'block';
            typeInput.value = '';
            durationInput.value = '';
            costInput.value = '';
            typeInput.required = true;
        } else if (selectedOption.value !== '') {
            // Auto-fill from selected service
            typeInput.style.display = 'none';
            typeInput.value = selectedOption.getAttribute('data-name');
            durationInput.value = selectedOption.getAttribute('data-duration');
            costInput.value = selectedOption.getAttribute('data-cost');
            typeInput.required = false;
        } else {
            // No service selected
            typeInput.style.display = 'none';
            typeInput.value = '';
            durationInput.value = '';
            costInput.value = '';
            typeInput.required = true;
        }
    });

    // Initialize form state
    serviceSelect.dispatchEvent(new Event('change'));
});
</script>
</body>
</html>
