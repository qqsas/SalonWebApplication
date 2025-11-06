<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

include 'db.php';
include 'mail.php'; // PHPMailer setup (sendEmail function)
include 'header.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$message = '';
$errors = [
    'name' => '',
    'contact' => '',
    'password' => '',
    'confirm_password' => '',
    'general' => ''
];
$form_data = [
    'name' => '',
    'contact' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors['general'] = "Security token validation failed. Please try again.";
    } else {
        // Sanitize and validate input
        $name = trim(filter_input(INPUT_POST, "name", FILTER_SANITIZE_STRING) ?? '');
        $contact = trim(filter_input(INPUT_POST, "contact", FILTER_SANITIZE_STRING) ?? '');
        $password = $_POST["password"] ?? '';
        $confirm_password = $_POST["confirm_password"] ?? '';

        // Store form data for repopulation
        $form_data['name'] = $name;
        $form_data['contact'] = $contact;

        $email = null;
        $number = null;
        $hasError = false;

        if (!$conn) {
            error_log("Database connection failed");
            $errors['general'] = "System temporarily unavailable. Please try again later.";
            $hasError = true;
        }

        // Name validation
        if (empty($name)) {
            $errors['name'] = "Name is required.";
            $hasError = true;
        } elseif (strlen($name) < 2) {
            $errors['name'] = "Name must be at least 2 characters long.";
            $hasError = true;
        } elseif (strlen($name) > 100) {
            $errors['name'] = "Name must be less than 100 characters.";
            $hasError = true;
        } elseif (!preg_match('/^[\p{L}\s\-\'\.]+$/u', $name)) {
            $errors['name'] = "Name can only contain letters, spaces, hyphens, apostrophes, and periods.";
            $hasError = true;
        }

        // Contact validation
        if (empty($contact)) {
            $errors['contact'] = "Please provide either an email or phone number.";
            $hasError = true;
        } else {
            // Determine if contact is email or phone
            $contact_lower = strtolower($contact);
            if (filter_var($contact_lower, FILTER_VALIDATE_EMAIL)) {
                $email = $contact_lower;
                
                // Additional email validation
                if (strlen($email) > 100) {
                    $errors['contact'] = "Email must be less than 100 characters.";
                    $hasError = true;
                } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
                    $errors['contact'] = "Please enter a valid email address.";
                    $hasError = true;
                }
            } else {
                // Phone number validation and normalization
                $clean_number = preg_replace('/\D/', '', $contact);
                
                if (empty($clean_number)) {
                    $errors['contact'] = "Please enter a valid phone number.";
                    $hasError = true;
                } elseif (strlen($clean_number) < 9) {
                    $errors['contact'] = "Phone number is too short.";
                    $hasError = true;
                } elseif (strlen($clean_number) > 15) {
                    $errors['contact'] = "Phone number is too long.";
                    $hasError = true;
                } else {
                    // Normalize phone number to +27 format
                    if (substr($clean_number, 0, 1) === '0' && strlen($clean_number) === 10) {
                        $number = '+27' . substr($clean_number, 1);
                    } elseif (substr($clean_number, 0, 2) === '27' && strlen($clean_number) === 11) {
                        $number = '+' . $clean_number;
                    } elseif (substr($clean_number, 0, 3) === '271' && strlen($clean_number) === 12) {
                        $number = '+' . $clean_number;
                    } else {
                        // International format or other - validate length
                        if (strlen($clean_number) >= 9 && strlen($clean_number) <= 15) {
                            $number = '+' . $clean_number;
                        } else {
                            $errors['contact'] = "Please enter a valid phone number.";
                            $hasError = true;
                        }
                    }
                }
            }
        }

        // Check email uniqueness if no errors so far
        if (!$hasError && !empty($email)) {
            $stmt = $conn->prepare("SELECT UserID FROM User WHERE Email = ? AND IsDeleted = 0 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $errors['contact'] = "This email is already registered. Please use a different email or login.";
                        $hasError = true;
                    }
                } else {
                    error_log("Email uniqueness check failed: " . $stmt->error);
                    $errors['general'] = "System error. Please try again.";
                    $hasError = true;
                }
                $stmt->close();
            } else {
                error_log("Email uniqueness prepare failed: " . $conn->error);
                $errors['general'] = "System error. Please try again.";
                $hasError = true;
            }
        }

        // Check number uniqueness if no errors so far
        if (!$hasError && !empty($number)) {
            $stmt = $conn->prepare("SELECT UserID FROM User WHERE Number = ? AND IsDeleted = 0 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $number);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $errors['contact'] = "This phone number is already registered. Please use a different number or login.";
                        $hasError = true;
                    }
                } else {
                    error_log("Number uniqueness check failed: " . $stmt->error);
                    $errors['general'] = "System error. Please try again.";
                    $hasError = true;
                }
                $stmt->close();
            } else {
                error_log("Number uniqueness prepare failed: " . $conn->error);
                $errors['general'] = "System error. Please try again.";
                $hasError = true;
            }
        }

        // Password validation
        if (empty($password)) {
            $errors['password'] = "Password is required.";
            $hasError = true;
        } else {
            if (strlen($password) < 8) {
                $errors['password'] = "Password must be at least 8 characters long.";
                $hasError = true;
            }
            if (strlen($password) > 255) {
                $errors['password'] = "Password is too long.";
                $hasError = true;
            }
            if (!preg_match('/[\W_]/', $password)) {
                $errors['password'] = "Password must include at least one special character.";
                $hasError = true;
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors['password'] = "Password must include at least one uppercase letter.";
                $hasError = true;
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors['password'] = "Password must include at least one lowercase letter.";
                $hasError = true;
            }
            if (!preg_match('/\d/', $password)) {
                $errors['password'] = "Password must include at least one number.";
                $hasError = true;
            }
            
            // Check for common passwords (basic check)
            $common_passwords = ['password', '12345678', 'qwertyui', 'admin123'];
            if (in_array(strtolower($password), $common_passwords)) {
                $errors['password'] = "This password is too common. Please choose a stronger password.";
                $hasError = true;
            }
        }

        // Confirm password validation
        if (empty($confirm_password)) {
            $errors['confirm_password'] = "Please confirm your password.";
            $hasError = true;
        } elseif ($password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
            $hasError = true;
        }

        // Insert into database if no errors
        if (!$hasError) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = "customer";

            $emailToInsert = $email ?? '';
            $numberToInsert = $number ?? '';

            $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssss", $name, $emailToInsert, $numberToInsert, $hashed_password, $role);

                if ($stmt->execute()) {
                    // Regenerate CSRF token after successful registration
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Send welcome email if email was provided
                    if (!empty($email)) {
                        try {
                            $subject = "Welcome to Our Store!";
                            $body = "<h2>Welcome to Our Store!</h2>
                                    <p>Hi " . htmlspecialchars($name) . ",</p>
                                    <p>Thank you for registering at our store. Your account is now active!</p>
                                    <p>We look forward to serving you.</p>
                                    <p><small>If you did not create this account, please contact us immediately.</small></p>";
                            $adminEmails = ['store@example.com'];
                            sendEmail($email, $subject, $body, $adminEmails);
                        } catch (Exception $e) {
                            // Log email error but don't fail registration
                            error_log("Welcome email failed: " . $e->getMessage());
                        }
                    }

                    $stmt->close();
                    $conn->close();

                    // Clear form data
                    $form_data = ['name' => '', 'contact' => ''];
                    
                    header("Location: Login.php?msg=Registered&success=1");
                    exit;
                } else {
                    // Check for duplicate entry error
                    if ($stmt->errno === 1062) {
                        $errors['contact'] = "This contact information is already registered. Please use a different email/phone or login.";
                    } else {
                        error_log("User insertion failed: " . $stmt->error);
                        $errors['general'] = "Registration failed. Please try again.";
                    }
                }
                $stmt->close();
            } else {
                error_log("User insertion prepare failed: " . $conn->error);
                $errors['general'] = "Registration failed. Please try again.";
            }
        }
    }

    // Regenerate CSRF token on form submission (for security)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    if ($conn) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Create Account</title>
    <link href="styles.css" rel="stylesheet">
<<<<<<< HEAD
=======
    <style>
        /* Hide error messages by default */
        .error {
            color: #d9534f;
            font-size: 0.9rem;
            margin-top: 4px;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .error.visible {
            display: block;
            opacity: 1;
        }

        .password-wrapper {
          position: relative;
          width: 100%;
        }

        .password-wrapper input {
          width: 100%;
          padding-right: 35px;
          box-sizing: border-box;
        }

        .toggle-eye {
          position: absolute;
          right: 10px;
          top: 50%;
          transform: translateY(-50%);
          cursor: pointer;
          font-size: 18px;
          color: #777;
        }

        .toggle-eye:hover {
          color: #000;
        }

        .password-requirements {
          font-size: 0.85rem;
          color: #666;
          margin-top: 5px;
        }
    </style>
>>>>>>> 4b6f4278448f60b19817c73d02221d816ec19c69
</head>
<body class="bg-light">
<div class="container">
  <div class="card">
    <div class="card-body">
      <h2 class="text-center">Create Your Account</h2>
      
      <?php if (!empty($errors['general'])): ?>
        <div class="error general-error"><?php echo htmlspecialchars($errors['general']); ?></div>
      <?php endif; ?>
      
      <form method="POST" action="" id="registrationForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <div class="form-group">
<<<<<<< HEAD
          <label for="name">Full Name <span class="required">*</span></label>
          <input type="text" name="name" id="name" class="form-control <?php echo !empty($errors['name']) ? 'error-field' : ''; ?>" 
                 value="<?php echo htmlspecialchars($form_data['name']); ?>" 
                 required maxlength="100">
          <?php if (!empty($errors['name'])): ?>
            <div class="error"><?php echo htmlspecialchars($errors['name']); ?></div>
          <?php endif; ?>
=======
          <label>Full Name</label>
          <input type="text" name="name" class="form-control" 
                 value="<?= htmlspecialchars($_POST["name"] ?? '') ?>" required>
          <span class="error <?= !empty($nameError) ? 'visible' : '' ?>">
            <?= htmlspecialchars($nameError) ?>
          </span>
>>>>>>> 4b6f4278448f60b19817c73d02221d816ec19c69
        </div>

        <div class="contact-note">Please provide either an email address or a phone number</div>
        <div class="form-group">
<<<<<<< HEAD
          <label for="contact">Email or Phone Number <span class="required">*</span></label>
          <input type="text" name="contact" id="contact" class="form-control <?php echo !empty($errors['contact']) ? 'error-field' : ''; ?>" 
                 value="<?php echo htmlspecialchars($form_data['contact']); ?>" 
                 required maxlength="100">
          <?php if (!empty($errors['contact'])): ?>
            <div class="error"><?php echo htmlspecialchars($errors['contact']); ?></div>
          <?php endif; ?>
=======
          <label>Email or Phone</label>
          <input type="text" name="contact" class="form-control" 
                 value="<?= htmlspecialchars($_POST["contact"] ?? '') ?>" required>
          <span class="error <?= !empty($contactError) ? 'visible' : '' ?>">
            <?= htmlspecialchars($contactError) ?>
          </span>
>>>>>>> 4b6f4278448f60b19817c73d02221d816ec19c69
        </div>

        <div class="form-group">
          <label for="password">Password <span class="required">*</span></label>
          <input type="password" name="password" id="password" class="form-control <?php echo !empty($errors['password']) ? 'error-field' : ''; ?>" 
                 required minlength="8" maxlength="255">
                 <input type="checkbox" id="togglePassword"> Show Password

          <?php if (!empty($errors['password'])): ?>
            <div class="error"><?php echo htmlspecialchars($errors['password']); ?></div>
          <?php endif; ?>
          <div class="password-requirements">
              <strong>Password Requirements:</strong>
              <ul>
                  <li>At least 8 characters long</li>
                  <li>At least one uppercase letter</li>
                  <li>At least one lowercase letter</li>
                  <li>At least one number</li>
                  <li>At least one special character</li>
              </ul>
          </div>
<<<<<<< HEAD
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm Password <span class="required">*</span></label>
          <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo !empty($errors['confirm_password']) ? 'error-field' : ''; ?>" 
                 required>
               <input type="checkbox" id="toggleConfirmPassword"> Show Password
  
          <?php if (!empty($errors['confirm_password'])): ?>
            <div class="error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
          <?php endif; ?>
=======
          <span class="error <?= !empty($passwordError) ? 'visible' : '' ?>">
            <?= $passwordError ?>
          </span>
        </div>

        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
          <span class="error <?= !empty($confirmPasswordError) ? 'visible' : '' ?>">
            <?= htmlspecialchars($confirmPasswordError) ?>
          </span>
>>>>>>> 4b6f4278448f60b19817c73d02221d816ec19c69
        </div>

        <button type="submit" class="btn" id="submitBtn">Create Account</button>
      </form>

      <p class="text-center mt-3">Already have an account? <a href="Login.php">Sign in here</a></p>
    </div>
  </div>
</div>

<script>
<<<<<<< HEAD
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    const submitBtn = document.getElementById('submitBtn');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (form) {
        // Real-time password confirmation validation
        confirmPasswordInput.addEventListener('input', function() {
            if (passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });
        
        // Enhanced form validation
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Clear previous error styles
            document.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('error-field');
            });
            
            // Name validation
            const name = document.getElementById('name').value.trim();
            if (name.length < 2) {
                showError('name', 'Name must be at least 2 characters long');
                isValid = false;
            }
            
            // Contact validation
            const contact = document.getElementById('contact').value.trim();
            if (!contact) {
                showError('contact', 'Please provide either an email or phone number');
                isValid = false;
            }
            
            // Password validation
            const password = passwordInput.value;
            if (password.length < 8) {
                showError('password', 'Password must be at least 8 characters long');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';
        });
=======
document.addEventListener("DOMContentLoaded", function () {
  const pass = document.querySelector('input[name="password"]');
  const confirm = document.querySelector('input[name="confirm_password"]');
  const contact = document.querySelector('input[name="contact"]');
  const form = document.querySelector("form");
  const toggle = document.querySelector(".toggle-eye");

  // Toggle password visibility
  toggle.addEventListener("click", () => {
    const isPassword = pass.type === "password";
    pass.type = confirm.type = isPassword ? "text" : "password";
    toggle.textContent = isPassword ? "🚫" : "👁";
  });

  // Match password validation
  confirm.addEventListener("input", () => {
    confirm.style.borderColor = confirm.value === pass.value ? "green" : "red";
  });

  // Detect input type for contact field
  contact.addEventListener("input", () => {
    const val = contact.value.trim();
    if (val.includes("@")) contact.style.borderColor = "green";
    else if (/^\d+$/.test(val)) contact.style.borderColor = "blue";
    else contact.style.borderColor = "";
  });

  // Show red border if PHP error exists
  document.querySelectorAll('.error.visible').forEach(err => {
    const input = err.closest('.form-group').querySelector('input');
    if (input) input.style.borderColor = 'red';
  });

  // Prevent form submit if passwords don't match
  form.addEventListener("submit", (e) => {
    if (pass.value !== confirm.value) {
      e.preventDefault();
      alert("Passwords do not match!");
>>>>>>> 4b6f4278448f60b19817c73d02221d816ec19c69
    }
    
    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        field.classList.add('error-field');
        
        // Remove existing error message
        const existingError = field.parentNode.querySelector('.error');
        if (existingError) {
            existingError.remove();
        }
        
        // Add new error message
        const error = document.createElement('div');
        error.className = 'error';
        error.textContent = message;
        field.parentNode.appendChild(error);
        
        field.focus();
    }
    
    // Real-time validation feedback
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error-field');
            const error = this.parentNode.querySelector('.error');
            if (error) {
                error.remove();
            }
        });
    });
});
</script>
<script>
document.getElementById('togglePassword').addEventListener('change', function() {
    const passwordField = document.getElementById('password');
    passwordField.type = this.checked ? 'text' : 'password';
});

document.getElementById('toggleConfirmPassword').addEventListener('change', function() {
    const confirmField = document.getElementById('confirm_password');
    confirmField.type = this.checked ? 'text' : 'password';
});
</script>

</body>
</html>