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
        <p class="<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST" id="profileForm">
        <label for="name">Name:</label><br>
        <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>"><br><br>

        <label for="number">Phone Number:</label><br>
        <input type="text" name="number" value="<?php echo htmlspecialchars($number); ?>"
               pattern="[0-9+\-\s()]{10,}" title="Please enter a valid phone number"><br><br>

        <hr>
        <h3>Password Change (optional)</h3>

        <label for="current_password">Current Password:</label><br>
        <input type="password" name="current_password" id="current_password"><br><br>

        <label for="password">New Password:</label><br>
        <input type="password" name="password" id="password" minlength="6"
               pattern=".{6,}" title="Password must be at least 6 characters"><br><br>

        <label for="confirm_password">Confirm New Password:</label><br>
        <input type="password" name="confirm_password" id="confirm_password" minlength="6"
               pattern=".{6,}" title="Password must be at least 6 characters"><br>
        <small>Leave both fields blank if you don’t want to change your password</small><br><br>

        <button type="submit">Update Profile</button>
        <a href="view_profile.php" style="margin-left: 10px;">Cancel</a>
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
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const email = document.querySelector('input[name="email"]').value.trim();
    const number = document.querySelector('input[name="number"]').value.trim();
    const password = document.querySelector('input[name="password"]').value.trim();
    const confirm = document.querySelector('input[name="confirm_password"]').value.trim();

    if (!email && !number) {
        alert('Please provide at least an email or phone number.');
        e.preventDefault();
        return false;
    }

    if (password && password.length < 6) {
        alert('Password must be at least 6 characters long.');
        e.preventDefault();
        return false;
    }

    if (password && password !== confirm) {
        alert('New passwords do not match.');
        e.preventDefault();
        return false;
    }
});
</script>

<?php include 'footer.php'; ?>

