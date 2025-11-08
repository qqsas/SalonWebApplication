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
$selectedOption = 'existing'; // Default to existing service selection

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
        $selectedOption = $_POST['service_option'] ?? 'existing';
        
        if ($selectedOption === 'existing') {
            // Handle existing service selection
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
        } else {
            // Handle new service creation
            $serviceName = trim($_POST['new_service_name'] ?? '');
            $serviceDescription = trim($_POST['new_service_description'] ?? '');
            $servicePrice = filter_var($_POST['new_service_price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $serviceTime = filter_var($_POST['new_service_time'] ?? 0, FILTER_VALIDATE_INT);
            $serviceCategory = trim($_POST['new_service_category'] ?? '');
            
            // Validate new service data
            if (empty($serviceName)) {
                $errors[] = "Service name is required.";
            } elseif (strlen($serviceName) > 100) {
                $errors[] = "Service name must be less than 100 characters.";
            }
            
            if (strlen($serviceDescription) > 500) {
                $errors[] = "Service description must be less than 500 characters.";
            }
            
            if ($servicePrice === false || $servicePrice < 0) {
                $errors[] = "Please enter a valid price (0 or greater).";
            }
            
            if ($servicePrice > 10000) {
                $errors[] = "Price seems unreasonably high. Please verify the amount.";
            }
            
            if ($serviceTime === false || $serviceTime < 1) {
                $errors[] = "Please enter a valid duration in minutes (1 or greater).";
            }
            
            if ($serviceTime > 480) {
                $errors[] = "Duration cannot exceed 480 minutes (8 hours).";
            }
            
            if (empty($serviceCategory)) {
                $errors[] = "Service category is required.";
            } elseif (strlen($serviceCategory) > 100) {
                $errors[] = "Service category must be less than 100 characters.";
            }
            
            // If no errors, create new service and link to barber
            if (empty($errors)) {
                $conn->begin_transaction();
                
                try {
                    // Create new service
                    $createStmt = $conn->prepare("INSERT INTO Services (Name, Description, Price, Time, Category, PriceType) VALUES (?, ?, ?, ?, ?, 'fixed')");
                    if (!$createStmt) {
                        throw new Exception("Failed to prepare service creation: " . $conn->error);
                    }
                    
                    $createStmt->bind_param("ssdis", $serviceName, $serviceDescription, $servicePrice, $serviceTime, $serviceCategory);
                    
                    if (!$createStmt->execute()) {
                        throw new Exception("Failed to create service: " . $createStmt->error);
                    }
                    
                    $newServiceID = $createStmt->insert_id;
                    $createStmt->close();
                    
                    // Link service to barber
                    $linkStmt = $conn->prepare("INSERT INTO BarberServices (BarberID, ServicesID) VALUES (?, ?)");
                    if (!$linkStmt) {
                        throw new Exception("Failed to prepare service linking: " . $conn->error);
                    }
                    
                    $linkStmt->bind_param("ii", $barberID, $newServiceID);
                    
                    if (!$linkStmt->execute()) {
                        throw new Exception("Failed to link service to barber: " . $linkStmt->error);
                    }
                    
                    $linkStmt->close();
                    
                    $conn->commit();
                    
                    $message = "New service '$serviceName' created successfully and added to your offerings!";
                    $success = true;
                    
                    // Regenerate CSRF token after successful operation
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Clear form data
                    $serviceName = $serviceDescription = $serviceCategory = '';
                    $servicePrice = $serviceTime = 0;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = "Failed to create new service. Please try again.";
                    error_log("Service creation error: " . $e->getMessage());
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
        select, input, textarea, button, .button { 
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
        .option-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .option-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .option-tab:first-child {
            border-radius: 4px 0 0 0;
        }
        .option-tab:last-child {
            border-radius: 0 4px 0 0;
        }
        .option-tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .option-content {
            display: none;
        }
        .option-content.active {
            display: block;
        }
        .form-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .help-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
        }
        .required {
            color: #dc3545;
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
            <div class="option-tabs">
                <div class="option-tab <?php echo $selectedOption === 'existing' ? 'active' : ''; ?>" data-option="existing">
                    Select Existing Service
                </div>
                <div class="option-tab <?php echo $selectedOption === 'new' ? 'active' : ''; ?>" data-option="new">
                    Create New Service
                </div>
            </div>
            
            <form method="POST" id="serviceForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="service_option" id="service_option" value="<?php echo htmlspecialchars($selectedOption); ?>">
                
                <!-- Existing Service Selection -->
                <div class="option-content <?php echo $selectedOption === 'existing' ? 'active' : ''; ?>" id="existing-service">
                    <div class="form-group">
                        <label for="ServicesID">Select Service: <span class="required">*</span></label>
                        <select name="ServicesID" id="ServicesID" required>
                            <option value="">-- Choose a Service --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo (int)$service['ServicesID']; ?>">
                                    <span class="service-option">
                                        <?php echo htmlspecialchars($service['Name']); ?>
                                        <span class="service-details">
                                            - R<?php echo htmlspecialchars(number_format($service['Price'], 2)); ?> 
                                            (<?php echo htmlspecialchars($service['Time']); ?> mins)
                                        </span>
                                    </span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="help-text">Choose from our list of pre-defined services</small>
                    </div>
                </div>
                
                <!-- New Service Creation -->
                <div class="option-content <?php echo $selectedOption === 'new' ? 'active' : ''; ?>" id="new-service">
                    <div class="form-section">
                        <div class="form-group">
                            <label for="new_service_name">Service Name: <span class="required">*</span></label>
                            <input type="text" name="new_service_name" id="new_service_name" 
                                   value="<?php echo htmlspecialchars($_POST['new_service_name'] ?? ''); ?>" 
                                   maxlength="100" required>
                            <small class="help-text">Enter a descriptive name for your service (max 100 characters)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_service_description">Description:</label>
                            <textarea name="new_service_description" id="new_service_description" 
                                      rows="3" maxlength="500"><?php echo htmlspecialchars($_POST['new_service_description'] ?? ''); ?></textarea>
                            <small class="help-text">Describe what this service includes (max 500 characters)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_service_price">Price (R): <span class="required">*</span></label>
                            <input type="number" name="new_service_price" id="new_service_price" 
                                   value="<?php echo htmlspecialchars($_POST['new_service_price'] ?? ''); ?>" 
                                   step="0.01" min="0" max="10000" required>
                            <small class="help-text">Enter the price for this service</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_service_time">Duration (minutes): <span class="required">*</span></label>
                            <input type="number" name="new_service_time" id="new_service_time" 
                                   value="<?php echo htmlspecialchars($_POST['new_service_time'] ?? ''); ?>" 
                                   min="1" max="480" required>
                            <small class="help-text">How long does this service take? (1-480 minutes)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_service_category">Category: <span class="required">*</span></label>
                            <input type="text" name="new_service_category" id="new_service_category" 
                                   value="<?php echo htmlspecialchars($_POST['new_service_category'] ?? ''); ?>" 
                                   maxlength="100" required>
                            <small class="help-text">e.g., "Haircut", "Beard Trim", "Styling"</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" id="submitBtn">
                        <?php echo $selectedOption === 'existing' ? 'Add Service' : 'Create & Add Service'; ?>
                    </button>
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
        // Client-side validation and tab switching
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('serviceForm');
            const optionTabs = document.querySelectorAll('.option-tab');
            const optionInput = document.getElementById('service_option');
            const submitBtn = document.getElementById('submitBtn');
            
            // Tab switching functionality
            optionTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const option = this.getAttribute('data-option');
                    
                    // Update active tab
                    optionTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update hidden input
                    optionInput.value = option;
                    
                    // Show/hide appropriate content
                    document.querySelectorAll('.option-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(option + '-service').classList.add('active');
                    
                    // Update button text
                    submitBtn.textContent = option === 'existing' ? 'Add Service' : 'Create & Add Service';
                    
                    // Clear validation messages
                    clearValidation();
                });
            });
            
            // Form validation
            if (form) {
                form.addEventListener('submit', function(e) {
                    const option = optionInput.value;
                    let isValid = true;
                    
                    if (option === 'existing') {
                        const select = document.getElementById('ServicesID');
                        if (!select.value) {
                            isValid = false;
                            showError(select, 'Please select a service to add.');
                        }
                    } else {
                        // Validate new service fields
                        const name = document.getElementById('new_service_name');
                        const price = document.getElementById('new_service_price');
                        const time = document.getElementById('new_service_time');
                        const category = document.getElementById('new_service_category');
                        
                        if (!name.value.trim()) {
                            isValid = false;
                            showError(name, 'Service name is required.');
                        }
                        
                        if (!price.value || parseFloat(price.value) < 0) {
                            isValid = false;
                            showError(price, 'Please enter a valid price.');
                        }
                        
                        if (!time.value || parseInt(time.value) < 1) {
                            isValid = false;
                            showError(time, 'Please enter a valid duration.');
                        }
                        
                        if (!category.value.trim()) {
                            isValid = false;
                            showError(category, 'Service category is required.');
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Disable button on submit to prevent double submission
                    submitBtn.disabled = true;
                    submitBtn.textContent = option === 'existing' ? 'Adding Service...' : 'Creating Service...';
                });
            }
            
            function showError(element, message) {
                // Remove existing error
                const existingError = element.parentNode.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add new error
                const error = document.createElement('div');
                error.className = 'field-error';
                error.style.color = '#dc3545';
                error.style.fontSize = '0.85em';
                error.style.marginTop = '4px';
                error.textContent = message;
                element.parentNode.appendChild(error);
                
                // Highlight field
                element.style.borderColor = '#dc3545';
                element.focus();
            }
            
            function clearValidation() {
                // Clear all field errors
                document.querySelectorAll('.field-error').forEach(error => error.remove());
                
                // Reset field borders
                document.querySelectorAll('input, select, textarea').forEach(field => {
                    field.style.borderColor = '#ddd';
                });
            }
            
            // Real-time validation for new service fields
            const newServiceFields = ['new_service_name', 'new_service_price', 'new_service_time', 'new_service_category'];
            newServiceFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', function() {
                        // Remove error when user starts typing
                        const error = this.parentNode.querySelector('.field-error');
                        if (error) {
                            error.remove();
                            this.style.borderColor = '#ddd';
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
