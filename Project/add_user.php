<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // PHPMailer setup
include 'header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $email = trim($_POST['Email']);
    $number = trim($_POST['Number']);
    $role = $_POST['Role'] ?? 'client';
    $password = trim($_POST['Password']);

    // Basic validation
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($password)) $errors[] = "Password is required.";

    // Check if email already exists
    $stmt = $conn->prepare("SELECT UserID FROM User WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = "Email already exists.";
    $stmt->close();

    if (empty($errors)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert into DB
        $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Role, Password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $number, $role, $hashedPassword);

        if ($stmt->execute()) {
            $success = "User added successfully!";
            $name = $email = $number = $role = $password = '';

            // --- Send welcome email ---
            $mail = getMailer(); // PHPMailer object

            try {
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = "Welcome to Kumar Kailey Hair & Beauty";
                $mail->Body = "
                    <h2>Welcome, {$name}!</h2>
                    <p>Your account has been created successfully.</p>
                    <p><strong>Email:</strong> {$email}</p>
                    <p>You can now log in and start using our services.</p>
                    <p>Thank you for joining us!</p>
                ";
                $mail->AltBody = "Welcome, {$name}! Your account has been created successfully. Email: {$email}";

                $mail->send();
            } catch (Exception $e) {
                error_log("Mail Error: {$mail->ErrorInfo}");
            }

        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New User - Admin</title>
    <link href="addedit.css" rel="stylesheet">
</head>
<body>

<div class="form-container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Add New User</h2>

    <?php if (!empty($errors)): ?>
        <div style='color:red; margin-bottom: 20px;'>
            <ul>
            <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style='color:green; margin-bottom: 20px;'><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label>Name:</label><br>
        <input type="text" name="Name" value="<?= isset($name) ? htmlspecialchars($name) : '' ?>" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="Email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required><br><br>

        <label>Phone Number:</label><br>
        <input type="text" name="Number" value="<?= isset($number) ? htmlspecialchars($number) : '' ?>"><br><br>

        <label>Password:</label><br>
        <input type="password" name="Password" required><br><br>

        <div class="button-group">
            <button type="submit">Add User</button>
            <a href="admin_dashboard.php" class="btn" style="text-align: center;">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>
