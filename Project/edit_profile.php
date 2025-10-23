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
    <link href="styles2.css" rel="stylesheet">
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
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.info { color: blue; font-weight: bold; }
input[type="text"], input[type="email"], input[type="password"] {
    width: 100%;
    max-width: 400px;
    padding: 8px;
    margin: 5px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}
small { color: #666; font-size: 0.9em; }
hr { margin: 20px 0; }
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

