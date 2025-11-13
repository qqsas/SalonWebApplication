<?php
session_start();
include 'db.php';
include 'header.php';
include 'mail.php'; // PHPMailer setup

// Redirect if not logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$user_id = $_SESSION['UserID'];
$message = "";
$message_type = "";

// Fetch current user data
$stmt = $conn->prepare("SELECT Name, Email, Number, Password FROM User WHERE UserID = ? AND IsDeleted = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

$name = $user['Name'];
$email = $user['Email'];
$number = $user['Number'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name']);
    $new_email = trim($_POST['email']);
    $new_number = trim($_POST['number']);
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $current_password = trim($_POST['current_password']);
    $hashed_password = null;

    // --- Normalize phone number ---
    if (!empty($new_number)) {
        // Remove spaces, dashes, parentheses
        $new_number = preg_replace('/[\s\-\(\)]/', '', $new_number);

        // Normalize SA number to +27 format
        if (preg_match('/^0\d{9}$/', $new_number)) {
            $new_number = '+27' . substr($new_number, 1);
        } elseif (preg_match('/^27\d{9}$/', $new_number)) {
            $new_number = '+' . $new_number;
        } elseif (!preg_match('/^\+27\d{9}$/', $new_number)) {
            $message = "Invalid South African phone number format.";
            $message_type = "error";
        }
    }

    // Validate required fields
    if (empty($new_name)) {
        $message = "Name is required.";
        $message_type = "error";
    }
    elseif (empty($new_email) && empty($new_number)) {
        $message = "Please provide at least an email or phone number.";
        $message_type = "error";
    }
    // Check if email already exists (if changing)
    elseif (!empty($new_email) && $new_email !== $email) {
        $check_email = $conn->prepare("SELECT UserID FROM User WHERE Email = ? AND UserID != ? AND IsDeleted = 0");
        $check_email->bind_param("si", $new_email, $user_id);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            $message = "This email is already registered by another user.";
            $message_type = "error";
        }
        $check_email->close();
    }

    // --- Password handling ---
    if (empty($message) && !empty($new_password)) {
        if (strlen($new_password) < 6) {
            $message = "Password must be at least 6 characters long.";
            $message_type = "error";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $message_type = "error";
        } elseif (empty($current_password)) {
            $message = "Please enter your current password to change your password.";
            $message_type = "error";
        } elseif (!password_verify($current_password, $user['Password'])) {
            $message = "Your current password is incorrect.";
            $message_type = "error";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        }
    }

    // --- Apply updates ---
    if (empty($message)) {
        if ($hashed_password) {
            $update = $conn->prepare("UPDATE User SET Name=?, Email=?, Number=?, Password=? WHERE UserID=?");
            $update->bind_param("ssssi", $new_name, $new_email, $new_number, $hashed_password, $user_id);
        } else {
            $update = $conn->prepare("UPDATE User SET Name=?, Email=?, Number=? WHERE UserID=?");
            $update->bind_param("sssi", $new_name, $new_email, $new_number, $user_id);
        }

        if ($update->execute()) {
            if ($update->affected_rows > 0) {
                $message = "Profile updated successfully!";
                $message_type = "success";

                $name = $new_name;
                $email = $new_email;
                $number = $new_number;
                $_SESSION['UserName'] = $new_name;

                // Send notification if email changed
                if (!empty($new_email) && $new_email !== $user['Email']) {
                    try {
                        $mail->clearAddresses();
                        $mail->addAddress($new_email, $new_name);
                        $mail->Subject = "Your Profile Has Been Updated";
                        $mail->Body = "Hello $new_name,\n\nYour profile has been successfully updated.\n\n".
                                      "Name: $new_name\nEmail: $new_email\nPhone: " . ($new_number ?: 'Not provided') . 
                                      "\n\nIf you did not make this change, please contact support.";
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Mail Error: " . $mail->ErrorInfo);
                    }
                }
            } else {
                $message = "No changes were made to your profile.";
                $message_type = "info";
            }
        } else {
            $message = "Error updating profile: " . $conn->error;
            $message_type = "error";
        }
        $update->close();
    }
}
?>

<div class="container">
    <link href="styles.css" rel="stylesheet">
    <h2>Edit Profile</h2>

    <?php if ($message): ?>
        <div class="<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="profileForm">
        <!-- Personal Information -->
        <div class="form-group">
            <label for="name">Full Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <div class="helper-text">We'll never share your email with anyone else.</div>
        </div>

        <div class="form-group">
            <label for="number">Phone Number</label>
            <input type="text" name="number" value="<?php echo htmlspecialchars($number); ?>"
                   pattern="[0-9+\-\s()]{10,}" 
                   title="Please enter a valid phone number (e.g., +27 12 345 6789)">
            <div class="helper-text">South African format: +27 XXX XXX XXXX</div>
        </div>

        <!-- Password Change Section -->
        <div class="password-section">
            <h3>Change Password (Optional)</h3>
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" name="current_password" id="current_password">
                <div class="helper-text">Required only if changing your password</div>
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" minlength="6"
                       pattern=".{6,}" title="Password must be at least 6 characters">
                <div class="helper-text">Minimum 6 characters</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" minlength="6"
                       pattern=".{6,}" title="Password must be at least 6 characters">
                <div class="helper-text">Re-enter your new password</div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
                    <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                </svg>
                Update Profile
            </button>
            <a href="view_profile.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
                Cancel
            </a>
        </div>
    </form>
</div>



<style>
/* === Neat Page Styles - Solid Colors, Clean Design === */

/* Message Styles */
.success {
  color: #22c55e !important;
  font-weight: 600 !important;
  background-color: #f0fdf4 !important;
  padding: 12px 16px !important;
  border-radius: 6px !important;
  border: 1px solid #bbf7d0 !important;
  display: block !important;
  margin: 10px 0 !important;
}

.error {
  color: #ef4444 !important;
  font-weight: 600 !important;
  background-color: #fef2f2 !important;
  padding: 12px 16px !important;
  border-radius: 6px !important;
  border: 1px solid #fecaca !important;
  display: block !important;
  margin: 10px 0 !important;
}

.info {
  color: #3b82f6 !important;
  font-weight: 600 !important;
  background-color: #eff6ff !important;
  padding: 12px 16px !important;
  border-radius: 6px !important;
  border: 1px solid #bfdbfe !important;
  display: block !important;
  margin: 10px 0 !important;
}

/* Form Inputs - Fixed Width */
input[type="text"],
input[type="email"],
input[type="password"],
input[type="number"],
input[type="tel"],
select,
textarea {
  width: 100% !important;
  max-width: 400px !important;
  padding: 12px 16px !important;
  margin: 8px 0 !important;
  border: 1px solid #d1d5db !important;
  border-radius: 6px !important;
  font-size: 15px !important;
  background-color: #ffffff !important;
  color: #374151 !important;
  transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
  box-sizing: border-box !important;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus,
input[type="number"]:focus,
input[type="tel"]:focus,
select:focus,
textarea:focus {
  outline: none !important;
  border-color: #3b82f6 !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

/* Labels */
label {
  display: block !important;
  font-weight: 600 !important;
  color: #374151 !important;
  margin-bottom: 6px !important;
  margin-top: 12px !important;
}

/* Small Text */
small {
  color: #6b7280 !important;
  font-size: 0.875rem !important;
  display: block !important;
  margin-top: 4px !important;
}

/* Horizontal Rule */
hr {
  margin: 24px 0 !important;
  border: none !important;
  border-top: 1px solid #e5e7eb !important;
}

/* Form Container */
form {
  max-width: 500px !important;
  margin: 0 auto !important;
  padding: 24px !important;
  background-color: #ffffff !important;
  border-radius: 8px !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* Form Group */
.form-group {
  margin-bottom: 20px !important;
}

/* Uniform Flat Buttons - Solid Colors */
.form-actions {
  display: flex !important;
  gap: 12px !important;
  margin-top: 24px !important;
  justify-content: flex-start !important;
  align-items: center !important;
  flex-wrap: wrap !important;
}

.form-actions button,
.form-actions a,
.form-actions input[type="submit"] {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  border: none !important;
  border-radius: 6px !important;
  padding: 12px 24px !important;
  font-size: 15px !important;
  font-weight: 600 !important;
  text-decoration: none !important;
  cursor: pointer !important;
  transition: background-color 0.2s ease, transform 0.1s ease !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  min-width: 120px !important;
  text-align: center !important;
}

/* Update Profile / Submit Button - Solid Blue */
.form-actions button,
.form-actions input[type="submit"] {
  background-color: #2563eb !important;
  color: #ffffff !important;
}

.form-actions button:hover,
.form-actions input[type="submit"]:hover {
  background-color: #1d4ed8 !important;
  transform: translateY(-1px) !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15) !important;
}

.form-actions button:active,
.form-actions input[type="submit"]:active {
  transform: translateY(0) !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
}


/* Cancel / Secondary Button - Solid Gray (No Gradients) */
.form-actions a {
  background-color: #6b7280 !important;
  background-image: none !important;
  color: #ffffff !important;
}

.form-actions a:hover {
  background-color: #4b5563 !important;
  background-image: none !important;
  transform: translateY(-1px) !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15) !important;
}

.form-actions a:active {
  background-color: #4b5563 !important;
  background-image: none !important;
  transform: translateY(0) !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
}

/* Cancel button specific class */
.form-actions a.btn-cancel,
.form-actions button.btn-cancel {
  background-color: var(--error-color) !important;
  background-image: none !important;
  color: #ffffff !important;
}

.form-actions a.btn-cancel:hover,
.form-actions button.btn-cancel:hover {
  background-color: var(--error-color) !important;
  background-image: none !important;
}

/* Primary Color Button Alternative */
.form-actions a.btn-primary,
.form-actions button.btn-primary {
  background-color: #1e3a8a !important;
  color: #ffffff !important;
}

.form-actions a.btn-primary:hover,
.form-actions button.btn-primary:hover {
  background-color: #1e40af !important;
}

/* Success Button */
.form-actions button.btn-success,
.form-actions a.btn-success {
  background-color: #22c55e !important;
  color: #ffffff !important;
}

.form-actions button.btn-success:hover,
.form-actions a.btn-success:hover {
  background-color: #16a34a !important;
}

/* Danger Button */
.form-actions button.btn-danger,
.form-actions a.btn-danger {
  background-color: #ef4444 !important;
  color: #ffffff !important;
}

.form-actions button.btn-danger:hover,
.form-actions a.btn-danger:hover {
  background-color: #dc2626 !important;
}

/* Responsive Design */
@media (max-width: 768px) {
  form {
    padding: 20px !important;
    margin: 0 16px !important;
  }

  input[type="text"],
  input[type="email"],
  input[type="password"],
  input[type="number"],
  input[type="tel"],
  select,
  textarea {
    max-width: 100% !important;
  }

  .form-actions {
    flex-direction: column !important;
    width: 100% !important;
  }

  .form-actions button,
  .form-actions a,
  .form-actions input[type="submit"] {
    width: 100% !important;
    min-width: 100% !important;
  }
}

/* Page Container */
.container {
  max-width: 800px !important;
  margin: 40px auto !important;
  padding: 0 20px !important;
}

/* Headings */
h1, h2, h3 {
  color: #1f2937 !important;
  margin-bottom: 16px !important;
}

h1 {
  font-size: 2rem !important;
  font-weight: 700 !important;
}

h2 {
  font-size: 1.75rem !important;
  font-weight: 600 !important;
}

h3 {
  font-size: 1.5rem !important;
  font-weight: 600 !important;
}

/* Paragraphs */
p {
  color: #374151 !important;
  line-height: 1.6 !important;
  margin-bottom: 12px !important;
}
</style>



<script>
// Enhanced form validation and UX
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const currentPasswordInput = document.getElementById('current_password');
    
    // Password strength indicator
    if (passwordInput) {
        const strengthBar = document.createElement('div');
        strengthBar.className = 'password-strength';
        strengthBar.innerHTML = '<div class="password-strength-bar"></div>';
        passwordInput.parentNode.appendChild(strengthBar);
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            strengthBar.className = 'password-strength ' + strength;
        });
    }
    
    // Real-time password confirmation
    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value && passwordInput.value) {
                if (this.value !== passwordInput.value) {
                    this.style.borderColor = 'var(--error-color)';
                } else {
                    this.style.borderColor = 'var(--success-color)';
                }
            }
        });
    }
    
    // Enhanced form validation
    form.addEventListener('submit', function(e) {
        const email = document.querySelector('input[name="email"]').value.trim();
        const number = document.querySelector('input[name="number"]').value.trim();
        const password = passwordInput ? passwordInput.value.trim() : '';
        const confirm = confirmPasswordInput ? confirmPasswordInput.value.trim() : '';
        const currentPassword = currentPasswordInput ? currentPasswordInput.value.trim() : '';
        
        let isValid = true;
        
        // Clear previous error styles
        document.querySelectorAll('.form-group').forEach(group => {
            group.classList.remove('invalid');
        });
        
        // Validate email or phone
        if (!email && !number) {
            showError('Please provide at least an email or phone number.');
            isValid = false;
        }
        
        // Validate password if provided
        if (password) {
            if (password.length < 6) {
                showError('Password must be at least 6 characters long.');
                isValid = false;
            } else if (password !== confirm) {
                showError('New passwords do not match.');
                isValid = false;
            } else if (!currentPassword) {
                showError('Please enter your current password to change your password.');
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            // Scroll to first error
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    
    function calculatePasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        if (strength <= 2) return 'weak';
        if (strength <= 4) return 'medium';
        return 'strong';
    }
    
    function showError(message) {
        // You could enhance this to show errors in a more user-friendly way
        const existingError = document.querySelector('.error-message-global');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error error-message-global';
        errorDiv.textContent = message;
        form.insertBefore(errorDiv, form.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
});
</script>

<?php include 'footer.php'; ?>

