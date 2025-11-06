<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- USER DATA ---
    $user_name = trim($_POST['UserName']);
    $user_email = trim($_POST['Email']);
    $user_number = trim($_POST['Number']);
    $user_password = trim($_POST['Password']);

    // --- BARBER DATA ---
    $barber_bio = trim($_POST['Bio']);

    // --- SERVICES ---
    $services = $_POST['Services'] ?? [];

    // ==================== VALIDATION CHECKS ====================

    // User Name Validation
    if (empty($user_name)) {
        $errors[] = "User name is required.";
    } elseif (strlen($user_name) > 100) {
        $errors[] = "User name must be less than 100 characters.";
    } elseif (!preg_match('/^[a-zA-Z\s\-\.\']+$/', $user_name)) {
        $errors[] = "User name can only contain letters, spaces, hyphens, dots, and apostrophes.";
    }

    // Email Validation
    if (empty($user_email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (strlen($user_email) > 100) {
        $errors[] = "Email must be less than 100 characters.";
    } else {
        // Check for duplicate email
        $check_email = $conn->prepare("SELECT UserID FROM User WHERE Email = ? AND IsDeleted = 0");
        $check_email->bind_param("s", $user_email);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            $errors[] = "This email address is already registered. Please use a different email.";
        }
        $check_email->close();
    }

    // Phone Number Validation
    if (!empty($user_number)) {
        if (strlen($user_number) > 20) {
            $errors[] = "Phone number must be less than 20 characters.";
        } elseif (!preg_match('/^[0-9+\-\s()]{10,20}$/', $user_number)) {
            $errors[] = "Please enter a valid phone number (10-20 digits, may include + - ( ) and spaces).";
        } else {
            // Check for duplicate phone number
            $check_phone = $conn->prepare("SELECT UserID FROM User WHERE Number = ? AND IsDeleted = 0");
            $check_phone->bind_param("s", $user_number);
            $check_phone->execute();
            $check_phone->store_result();
            if ($check_phone->num_rows > 0) {
                $errors[] = "This phone number is already registered. Please use a different number.";
            }
            $check_phone->close();
        }
    }

    // Password Validation
    if (empty($user_password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($user_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $user_password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $user_password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $user_password)) {
        $errors[] = "Password must contain at least one number.";
    } elseif (strlen($user_password) > 255) {
        $errors[] = "Password must be less than 255 characters.";
    }

    // Bio Validation
    if (!empty($barber_bio) && strlen($barber_bio) > 65535) {
        $errors[] = "Bio is too long. Maximum 65535 characters allowed.";
    }

    // Services Validation
    if (!empty($services)) {
        // Validate that all service IDs are integers and exist in the database
        $valid_services = [];
        $service_check = $conn->prepare("SELECT ServicesID FROM Services WHERE ServicesID = ? AND IsDeleted = 0");
        
        foreach ($services as $service_id) {
            if (!filter_var($service_id, FILTER_VALIDATE_INT)) {
                $errors[] = "Invalid service ID selected.";
                break;
            } else {
                $service_check->bind_param("i", $service_id);
                $service_check->execute();
                $service_check->store_result();
                if ($service_check->num_rows === 0) {
                    $errors[] = "One or more selected services are invalid or have been deleted.";
                    break;
                }
                $valid_services[] = (int)$service_id;
            }
        }
        $service_check->close();
    }

    // Database Constraints Check
    if (empty($errors)) {
        // Additional check to ensure we don't violate unique constraints
        $final_check = $conn->prepare("SELECT UserID FROM User WHERE (Email = ? OR (Number = ? AND Number != '')) AND IsDeleted = 0");
        $final_check->bind_param("ss", $user_email, $user_number);
        $final_check->execute();
        $final_check->store_result();
        if ($final_check->num_rows > 0) {
            $errors[] = "A user with this email or phone number already exists.";
        }
        $final_check->close();
    }

    // ==================== DATABASE OPERATIONS ====================

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // Insert into User table
            $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, ?, ?, 'barber')");
            if (!$stmt) {
                throw new Exception("Failed to prepare user insert: " . $conn->error);
            }
            $stmt->bind_param("ssss", $user_name, $user_email, $user_number, $hashed_password);
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert user: " . $stmt->error);
            }
            $user_id = $stmt->insert_id;
            $stmt->close();

            // Insert into Barber table using User.Name
            $stmt = $conn->prepare("INSERT INTO Barber (UserID, Name, Bio) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare barber insert: " . $conn->error);
            }
            $stmt->bind_param("iss", $user_id, $user_name, $barber_bio);
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert barber: " . $stmt->error);
            }
            $barber_id = $stmt->insert_id;
            $stmt->close();

            // Assign services
            if (!empty($services)) {
                $stmt = $conn->prepare("INSERT INTO BarberServices (BarberID, ServicesID) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Failed to prepare services insert: " . $conn->error);
                }
                
                foreach ($services as $service_id) {
                    $stmt->bind_param("ii", $barber_id, $service_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to assign service: " . $stmt->error);
                    }
                }
                $stmt->close();
            }

            $conn->commit();
            $success = "Barber and user created successfully!";
            
            // Reset form fields
            $user_name = $user_email = $user_number = $user_password = '';
            $barber_bio = '';
            $services = [];
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
            // Log the actual error for debugging
            error_log("Barber creation error: " . $e->getMessage());
        }
    }
}

// Fetch all services for multi-select
$all_services = [];
$result = $conn->query("SELECT ServicesID, Name FROM Services WHERE IsDeleted=0 ORDER BY Name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_services[] = $row;
    }
} else {
    error_log("Failed to fetch services: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Barber - Admin</title>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .error { color: #d32f2f; font-size: 0.9em; margin-top: 5px; }
        .password-requirements { font-size: 0.8em; color: #666; margin-top: 5px; }
        input:invalid, select:invalid { border-color: #ff9800; }
        input:valid, select:valid { border-color: #4caf50; }
    </style>
</head>
<body>

<div class="form-container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Add New Barber with User Account & Services</h2>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <strong>Please fix the following errors:</strong>
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
        <h3>User Account</h3>
        
        <label>Name:</label><br>
        <input type="text" name="UserName" value="<?= htmlspecialchars($user_name ?? '') ?>" 
               maxlength="100" pattern="[a-zA-Z\s\-\.']+" 
               title="Only letters, spaces, hyphens, dots, and apostrophes are allowed" required>
        <div class="error" id="name-error"></div><br>

        <label>Email:</label><br>
        <input type="email" name="Email" value="<?= htmlspecialchars($user_email ?? '') ?>" 
               maxlength="100" required>
        <div class="error" id="email-error"></div><br>

        <label>Phone Number:</label><br>
        <input type="tel" name="Number" value="<?= htmlspecialchars($user_number ?? '') ?>" 
               maxlength="20" pattern="[0-9+\-\s()]{10,20}" 
               title="10-20 digits, may include + - ( ) and spaces">
        <div class="error" id="phone-error"></div><br>

        <label>Password:</label><br>
        <input type="password" name="Password" value="" 
               minlength="8" maxlength="255" 
               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$" required>
        <div class="password-requirements">
            Must be at least 8 characters with uppercase, lowercase, and number
        </div>
        <div class="error" id="password-error"></div><br>

        <h3>Barber Profile</h3>
        <label>Bio:</label><br>
        <textarea name="Bio" maxlength="65535"><?= htmlspecialchars($barber_bio ?? '') ?></textarea>
        <div class="error" id="bio-error"></div><br>

        <h3>Assign Services</h3>
        <label>Select services this barber can provide (hold Ctrl/Cmd to select multiple):</label><br>
        <select name="Services[]" multiple size="5">
            <?php foreach ($all_services as $s): ?>
                <option value="<?= $s['ServicesID'] ?>" 
                    <?= (in_array($s['ServicesID'], $services ?? [])) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="error" id="services-error"></div><br>

        <div class="button-group">
            <button type="submit">Add Barber</button>
            <a href="admin_dashboard.php" class="btn" style="text-align: center;">Cancel</a>
        </div>
    </form>
</div>

<script>
// Client-side validation feedback
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const passwordInput = document.querySelector('input[name="Password"]');
    
    // Real-time password strength feedback
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasMinLength = password.length >= 8;
        
        if (password.length > 0) {
            let feedback = [];
            if (!hasMinLength) feedback.push('at least 8 characters');
            if (!hasUpper) feedback.push('one uppercase letter');
            if (!hasLower) feedback.push('one lowercase letter');
            if (!hasNumber) feedback.push('one number');
            
            if (feedback.length > 0) {
                this.setCustomValidity('Missing: ' + feedback.join(', '));
            } else {
                this.setCustomValidity('');
            }
        }
    });
    
    // Form submission confirmation
    form.addEventListener('submit', function(e) {
        const password = passwordInput.value;
        if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
            e.preventDefault();
            alert('Please ensure the password meets all requirements.');
        }
    });
});
</script>

</body>
</html>
