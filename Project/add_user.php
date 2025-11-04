<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Validate session and role
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // PHPMailer setup
include 'header.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

// Configuration
$maxNameLength = 100;
$maxEmailLength = 100;
$maxNumberLength = 20;
$minPasswordLength = 8;
$maxPasswordLength = 255;
$allowedRoles = ['client', 'barber', 'admin', 'pseudo'];

// Initialize form variables
$name = $email = $number = $password = '';
$role = 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Security token validation failed. Please try again.";
    } else {
        // Validate and sanitize inputs
        $name = trim($_POST['Name'] ?? '');
        $email = trim($_POST['Email'] ?? '');
        $number = trim($_POST['Number'] ?? '');
        $role = $_POST['Role'] ?? 'client';
        $password = trim($_POST['Password'] ?? '');

        // Name validation
        if (empty($name)) {
            $errors[] = "Name is required.";
        } elseif (strlen($name) > $maxNameLength) {
            $errors[] = "Name must be less than {$maxNameLength} characters.";
        } elseif (!preg_match('/^[a-zA-Z\s\-\.\']+$/', $name)) {
            $errors[] = "Name can only contain letters, spaces, hyphens, dots, and apostrophes.";
        }

        // Email validation
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        } elseif (strlen($email) > $maxEmailLength) {
            $errors[] = "Email must be less than {$maxEmailLength} characters.";
        } else {
            // Check if email already exists
            $checkStmt = $conn->prepare("SELECT UserID FROM User WHERE Email = ? AND IsDeleted = 0");
            if ($checkStmt) {
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $errors[] = "A user with this email address already exists.";
                }
                $checkStmt->close();
            } else {
                $errors[] = "Database error while checking email.";
                error_log("Email check prepare error: " . $conn->error);
            }
        }

        // Phone number validation
        if (!empty($number)) {
            if (strlen($number) > $maxNumberLength) {
                $errors[] = "Phone number must be less than {$maxNumberLength} characters.";
            } elseif (!preg_match('/^[0-9+\-\s()]{10,20}$/', $number)) {
                $errors[] = "Please enter a valid phone number (10-20 digits, may include + - ( ) and spaces).";
            } else {
                // Check if phone number already exists
                $phoneCheckStmt = $conn->prepare("SELECT UserID FROM User WHERE Number = ? AND IsDeleted = 0");
                if ($phoneCheckStmt) {
                    $phoneCheckStmt->bind_param("s", $number);
                    $phoneCheckStmt->execute();
                    $phoneCheckStmt->store_result();
                    if ($phoneCheckStmt->num_rows > 0) {
                        $errors[] = "A user with this phone number already exists.";
                    }
                    $phoneCheckStmt->close();
                }
            }
        }

        // Role validation
        if (!in_array($role, $allowedRoles)) {
            $errors[] = "Invalid role selected.";
        }

        // Password validation
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < $minPasswordLength) {
            $errors[] = "Password must be at least {$minPasswordLength} characters long.";
        } elseif (strlen($password) > $maxPasswordLength) {
            $errors[] = "Password must be less than {$maxPasswordLength} characters.";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Password must contain at least one special character.";
        }

        // Insert into database if no errors
        if (empty($errors)) {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into DB
            $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Role, Password) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssss", $name, $email, $number, $role, $hashedPassword);

                if ($stmt->execute()) {
                    $success = "User added successfully!";
                    
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Clear form
                    $name = $email = $number = $password = '';
                    $role = 'client';

                    // --- Send welcome email ---
                    try {
                        $mail = getMailer(); // PHPMailer object
                        $mail->addAddress($email, $name);
                        $mail->isHTML(true);
                        $mail->Subject = "Welcome to Kumar Kailey Hair & Beauty";
                        $mail->Body = "
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                    .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                                    .content { background: #f9f9f9; padding: 20px; }
                                    .footer { text-align: center; padding: 20px; font-size: 0.9em; color: #666; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>Welcome to Kumar Kailey Hair & Beauty</h1>
                                    </div>
                                    <div class='content'>
                                        <h2>Hello, " . htmlspecialchars($name) . "!</h2>
                                        <p>Your account has been successfully created.</p>
                                        <p><strong>Account Details:</strong></p>
                                        <ul>
                                            <li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>
                                            <li><strong>Role:</strong> " . htmlspecialchars(ucfirst($role)) . "</li>
                                        </ul>
                                        <p>You can now log in to our system and start using our services.</p>
                                        <p>If you have any questions, please don't hesitate to contact our support team.</p>
                                    </div>
                                    <div class='footer'>
                                        <p>Thank you for choosing Kumar Kailey Hair & Beauty!</p>
                                        <p>&copy; " . date('Y') . " Kumar Kailey Hair & Beauty. All rights reserved.</p>
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";
                        $mail->AltBody = "Welcome to Kumar Kailey Hair & Beauty!\n\nHello " . $name . "!\n\nYour account has been successfully created.\n\nAccount Details:\n- Email: " . $email . "\n- Role: " . ucfirst($role) . "\n\nYou can now log in to our system and start using our services.\n\nIf you have any questions, please contact our support team.\n\nThank you for choosing Kumar Kailey Hair & Beauty!";

                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Welcome email error for " . $email . ": " . $mail->ErrorInfo);
                        // Don't show email error to user, just log it
                    }

                } else {
                    $errors[] = "Database error: " . $stmt->error;
                    error_log("User creation error: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $errors[] = "Database preparation error: " . $conn->error;
                error_log("User insert prepare error: " . $conn->error);
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Admin</title>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid #c62828;
            margin-bottom: 20px;
        }
        .success-message {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid #2e7d32;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus,
        select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }
        .button-group {
            margin-top: 30px;
            text-align: center;
        }
        button[type="submit"],
        .btn-cancel {
            padding: 12px 30px;
            margin: 0 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            min-width: 140px;
        }
        button[type="submit"] {
            background: #4CAF50;
            color: white;
        }
        button[type="submit"]:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        .page-title {
            margin: 0;
            color: #333;
            font-size: 28px;
            font-weight: bold;
        }
        .help-text {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
            display: block;
        }
        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.9em;
        }
        .password-requirements ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        .password-requirements li {
            margin-bottom: 3px;
        }
        .requirement-met {
            color: #4CAF50;
        }
        .requirement-not-met {
            color: #f44336;
        }
        .required {
            color: #c62828;
        }
        @media (max-width: 768px) {
            .form-container {
                margin: 10px;
                padding: 20px;
            }
            button[type="submit"],
            .btn-cancel {
                display: block;
                width: 100%;
                margin: 10px 0;
                min-width: auto;
            }
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="form-container">
        <!-- Back Button and Title -->
        <div class="page-header">
            <a href="admin_dashboard.php" class="btn-back">← Back to Admin Dashboard</a>
            <h1 class="page-title">Add New User</h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <strong>Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="userForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-group">
                <label for="Name">Full Name <span class="required">*</span></label>
                <input type="text" name="Name" id="Name" 
                       value="<?= htmlspecialchars($name) ?>" 
                       maxlength="<?= $maxNameLength ?>"
                       pattern="[a-zA-Z\s\-\.']+"
                       title="Only letters, spaces, hyphens, dots, and apostrophes are allowed"
                       required>
                <div class="help-text">Maximum <?= $maxNameLength ?> characters</div>
            </div>

            <div class="form-group">
                <label for="Email">Email Address <span class="required">*</span></label>
                <input type="email" name="Email" id="Email" 
                       value="<?= htmlspecialchars($email) ?>" 
                       maxlength="<?= $maxEmailLength ?>"
                       required>
                <div class="help-text">Maximum <?= $maxEmailLength ?> characters</div>
            </div>

            <div class="form-group">
                <label for="Number">Phone Number</label>
                <input type="text" name="Number" id="Number" 
                       value="<?= htmlspecialchars($number) ?>"
                       maxlength="<?= $maxNumberLength ?>"
                       pattern="[0-9+\-\s()]{10,20}"
                       title="10-20 digits, may include + - ( ) and spaces">
                <div class="help-text">Optional - Maximum <?= $maxNumberLength ?> characters</div>
            </div>

            <div class="form-group">
                <label for="Role">User Role <span class="required">*</span></label>
                <select name="Role" id="Role" required>
                    <option value="client" <?= $role === 'client' ? 'selected' : '' ?>>Client</option>
                    <option value="barber" <?= $role === 'barber' ? 'selected' : '' ?>>Barber</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <option value="pseudo" <?= $role === 'pseudo' ? 'selected' : '' ?>>Pseudo User</option>
                </select>
                <div class="help-text">Select the appropriate role for this user</div>
            </div>

            <div class="form-group">
                <label for="Password">Password <span class="required">*</span></label>
                <input type="password" name="Password" id="Password" 
                       minlength="<?= $minPasswordLength ?>"
                       maxlength="<?= $maxPasswordLength ?>"
                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?\":{}|<>
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <ul>
                        <li id="req-length" class="requirement-not-met">At least <?= $minPasswordLength ?> characters</li>
                        <li id="req-upper" class="requirement-not-met">One uppercase letter</li>
                        <li id="req-lower" class="requirement-not-met">One lowercase letter</li>
                        <li id="req-number" class="requirement-not-met">One number</li>
                        <li id="req-special" class="requirement-not-met">One special character</li>
                    </ul>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" id="submitBtn">Add User</button>
                <a href="admin_dashboard.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('Password');
            const form = document.getElementById('userForm');
            const submitBtn = document.getElementById('submitBtn');
            
            // Password strength validation
            function validatePassword(password) {
                const requirements = {
                    length: password.length >= <?= $minPasswordLength ?>,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };
                
                // Update requirement indicators
                Object.keys(requirements).forEach(req => {
                    const element = document.getElementById('req-' + req);
                    if (element) {
                        element.className = requirements[req] ? 'requirement-met' : 'requirement-not-met';
                    }
                });
                
                return Object.values(requirements).every(Boolean);
            }
            
            // Real-time password validation
            passwordInput.addEventListener('input', function() {
                validatePassword(this.value);
            });
            
            // Form submission handling
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                
                if (!validatePassword(password)) {
                    e.preventDefault();
                    alert('Please ensure the password meets all requirements.');
                    passwordInput.focus();
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Adding User...';
            });
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>
