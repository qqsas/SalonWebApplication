<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Validate session and role
if (!isset($_SESSION['UserID']) || (strtolower($_SESSION['Role'] ?? '') !== 'barber')) {
    header("Location: Login.php");
    exit();
}

include 'db.php';

// Initialize variables
$message = '';
$success = false;
$barberID = null;
$errors = [];

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate and sanitize barber ID from session or database
if (!empty($_SESSION['BarberID'])) {
    $barberID = filter_var($_SESSION['BarberID'], FILTER_VALIDATE_INT);
    if ($barberID === false || $barberID <= 0) {
        $errors[] = "Invalid barber session. Please log in again.";
        $barberID = null;
    }
} else {
    // Look up BarberID from the Barber table using the logged-in UserID
    $userID = filter_var($_SESSION['UserID'] ?? 0, FILTER_VALIDATE_INT);
    if ($userID === false || $userID <= 0) {
        $errors[] = "Invalid user session. Please log in again.";
    } else {
        $stmt = $conn->prepare("SELECT BarberID FROM Barber WHERE UserID = ? AND IsDeleted = 0 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userID);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $barberID = (int) $row['BarberID'];
                    $_SESSION['BarberID'] = $barberID;
                } else {
                    $errors[] = "Could not find your active barber profile. Please contact administrator.";
                }
            } else {
                $errors[] = "Database error while looking up barber profile.";
                error_log("Barber lookup error: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = "Database preparation error.";
            error_log("Prepare statement error: " . $conn->error);
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $barberID && empty($errors)) {
    // CSRF Validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Security token validation failed. Please try again.";
    } else {
        // Validate and sanitize service ID
        $serviceID = isset($_POST['ServicesID']) ? filter_var($_POST['ServicesID'], FILTER_VALIDATE_INT) : 0;
        
        if ($serviceID === false || $serviceID <= 0) {
            $errors[] = "Please select a valid service.";
        } else {
            // Verify service exists and is active
            $serviceCheck = $conn->prepare("SELECT ServicesID, Name FROM Services WHERE ServicesID = ? AND IsDeleted = 0");
            if (!$serviceCheck) {
                $errors[] = "Database error while verifying service.";
                error_log("Service check prepare error: " . $conn->error);
            } else {
                $serviceCheck->bind_param("i", $serviceID);
                if ($serviceCheck->execute()) {
                    $serviceResult = $serviceCheck->get_result();
                    if (!$serviceResult->num_rows) {
                        $errors[] = "Selected service does not exist or has been removed.";
                    }
                } else {
                    $errors[] = "Database error while checking service availability.";
                    error_log("Service check execute error: " . $serviceCheck->error);
                }
                $serviceCheck->close();
            }
        }

        // If no errors, proceed with adding/restoring service
        if (empty($errors)) {
            // Check if service relationship already exists
            $checkStmt = $conn->prepare("SELECT BarberServiceID, IsDeleted FROM BarberServices WHERE BarberID = ? AND ServicesID = ? LIMIT 1");
            if (!$checkStmt) {
                $errors[] = "Database error while checking existing services.";
                error_log("Check statement prepare error: " . $conn->error);
            } else {
                $checkStmt->bind_param("ii", $barberID, $serviceID);
                
                if ($checkStmt->execute()) {
                    $checkResult = $checkStmt->get_result();
                    
                    if ($row = $checkResult->fetch_assoc()) {
                        // Service relationship exists
                        if ((int)$row['IsDeleted'] === 1) {
                            // Restore soft-deleted relationship
                            $checkStmt->close();
                            $updateStmt = $conn->prepare("UPDATE BarberServices SET IsDeleted = 0 WHERE BarberID = ? AND ServicesID = ?");
                            if ($updateStmt) {
                                $updateStmt->bind_param("ii", $barberID, $serviceID);
                                if ($updateStmt->execute()) {
                                    $message = "Service successfully restored to your offerings.";
                                    $success = true;
                                    
                                    // Regenerate CSRF token after successful operation
                                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                } else {
                                    $errors[] = "Failed to restore service. Please try again.";
                                    error_log("Service restore error: " . $updateStmt->error);
                                }
                                $updateStmt->close();
                            } else {
                                $errors[] = "Database error while restoring service.";
                                error_log("Update statement prepare error: " . $conn->error);
                            }
                        } else {
                            $errors[] = "This service is already in your offerings.";
                        }
                    } else {
                        // No existing relationship - insert new one
                        $checkStmt->close();
                        $insertStmt = $conn->prepare("INSERT INTO BarberServices (BarberID, ServicesID) VALUES (?, ?)");
                        if ($insertStmt) {
                            $insertStmt->bind_param("ii", $barberID, $serviceID);
                            if ($insertStmt->execute()) {
                                $message = "Service successfully added to your offerings.";
                                $success = true;
                                
                                // Regenerate CSRF token after successful operation
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            } else {
                                // Check for duplicate entry error
                                if ($insertStmt->errno === 1062) { // MySQL duplicate entry error code
                                    $errors[] = "This service is already in your offerings.";
                                } else {
                                    $errors[] = "Failed to add service. Please try again.";
                                    error_log("Service insert error: " . $insertStmt->error);
                                }
                            }
                            $insertStmt->close();
                        } else {
                            $errors[] = "Database error while adding service.";
                            error_log("Insert statement prepare error: " . $conn->error);
                        }
                    }
                } else {
                    $errors[] = "Database error while checking service relationship.";
                    error_log("Check statement execute error: " . $checkStmt->error);
                }
                if (isset($checkStmt) && !$checkStmt->closed) {
                    $checkStmt->close();
                }
            }
        }
    }
}

// Get available services with error handling
$services = [];
$servicesQuery = $conn->query("SELECT ServicesID, Name, Price, Time FROM Services WHERE IsDeleted = 0 ORDER BY Name");
if ($servicesQuery) {
    $services = $servicesQuery->fetch_all(MYSQLI_ASSOC);
    $servicesQuery->free();
} else {
    if (empty($message) && empty($errors)) {
        $errors[] = "Unable to load available services. Please try again later.";
    }
    error_log("Services query error: " . $conn->error);
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service - Barber Dashboard</title>
    <link rel="stylesheet" href="addedit.css">
    <style>
        .error { 
            background-color: #ffebee; 
            color: #c62828; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #c62828;
            margin-bottom: 15px;
        }
        .success { 
            background-color: #e8f5e8; 
            color: #2e7d32; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #2e7d32;
            margin-bottom: 15px;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        select, button, .button { 
            width: 100%; 
            padding: 10px; 
            margin: 5px 0; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        button { 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            cursor: pointer; 
            font-size: 16px;
        }
        button:hover { 
            background-color: #45a049; 
        }
        .button { 
            display: inline-block; 
            text-align: center; 
            background-color: #6c757d; 
            color: white; 
            text-decoration: none; 
            padding: 10px;
        }
        .button:hover { 
            background-color: #5a6268; 
        }
        .container { 
            max-width: 600px; 
            margin: 20px auto; 
            padding: 20px; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .service-option {
            display: block;
            padding: 8px 0;
        }
        .service-details {
            font-size: 0.9em;
            color: #666;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1>Add Service to My Offerings</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="<?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($barberID && empty($errors)): ?>
            <form method="POST" id="serviceForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="ServicesID">Select Service:</label>
                    <select name="ServicesID" id="ServicesID" required>
                        <option value="">-- Choose a Service --</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo (int)$service['ServicesID']; ?>">
                                <span class="service-option">
                                    <?php echo htmlspecialchars($service['Name']); ?>
                                    <span class="service-details">
                                        - $<?php echo htmlspecialchars(number_format($service['Price'], 2)); ?> 
                                        (<?php echo htmlspecialchars($service['Time']); ?> mins)
                                    </span>
                                </span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" id="submitBtn">Add Service</button>
                    <a href="barber_dashboard.php?view=services" class="button">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <div class="error">
                Unable to load service addition form. Please contact administrator if this issue persists.
            </div>
            <a href="barber_dashboard.php" class="button">Return to Dashboard</a>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>

    <script>
        // Client-side validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('serviceForm');
            const select = document.getElementById('ServicesID');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!select.value) {
                        e.preventDefault();
                        alert('Please select a service to add.');
                        select.focus();
                    }
                });
                
                // Disable button on submit to prevent double submission
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Adding Service...';
                });
            }
        });
    </script>
</body>
</html>
