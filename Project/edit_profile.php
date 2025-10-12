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
$message_type = ""; // success or error

// Fetch current user data
$stmt = $conn->prepare("SELECT Name, Email, Number FROM User WHERE UserID = ? AND IsDeleted = 0");
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
    $hashed_password = null;

    // Validate required fields
    if (empty($new_name)) {
        $message = "Name is required.";
        $message_type = "error";
    }
    // Check that at least one contact method is filled
    elseif (empty($new_email) && empty($new_number)) {
        $message = "Please provide at least an email or phone number.";
        $message_type = "error";
    }
    // Check if email already exists (if changing email)
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
    
    // If no errors, proceed with update
    if (empty($message)) {
        // Handle password update
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $message = "Password must be at least 6 characters long.";
                $message_type = "error";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            }
        }

        if (empty($message)) {
            // Prepare update query based on whether password is being changed
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
                    
                    // Update current variables
                    $name = $new_name;
                    $email = $new_email;
                    $number = $new_number;
                    
                    // Update session name if changed
                    $_SESSION['UserName'] = $new_name;

                    // Send email notification only if email changed and email provided
                    if (!empty($new_email) && $new_email !== $user['Email']) {
                        try {
                            $mail->clearAddresses();
                            $mail->addAddress($new_email, $new_name);
                            $mail->Subject = "Your Profile Has Been Updated";
                            $mail->Body = "Hello $new_name,\n\nYour profile information has been successfully updated.\n\n".
                                          "Name: $new_name\nEmail: $new_email\nPhone: " . ($new_number ?: 'Not provided') . 
                                          "\n\nIf you did not make this change, please contact support immediately.";
                            $mail->send();
                        } catch (Exception $e) {
                            error_log("Mail Error: " . $mail->ErrorInfo);
                            // Don't show mail error to user, just log it
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
               pattern="[0-9+\-\s()]{10,}" 
               title="Please enter a valid phone number"><br><br>

        <label for="password">New Password (leave blank to keep current):</label><br>
        <input type="password" name="password" id="password" 
               minlength="6" 
               pattern=".{6,}" 
               title="Password must be at least 6 characters"><br>
        <small>Minimum 6 characters</small><br><br>

        <button type="submit">Update Profile</button>
        <a href="dashboard.php" style="margin-left: 10px;">Cancel</a>
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
    small {
        color: #666;
        font-size: 0.9em;
    }
</style>

<script>
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const email = document.querySelector('input[name="email"]').value;
    const number = document.querySelector('input[name="number"]').value;
    const password = document.querySelector('input[name="password"]').value;
    
    // Validate at least one contact method
    if (!email && !number) {
        alert('Please provide at least an email or phone number.');
        e.preventDefault();
        return false;
    }
    
    // Validate password length if provided
    if (password && password.length < 6) {
        alert('Password must be at least 6 characters long.');
        e.preventDefault();
        return false;
    }
});
</script>

<?php include 'footer.php'; ?>
