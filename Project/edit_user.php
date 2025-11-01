<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // PHPMailer setup
include 'header.php';

// Check if we got a UserID
if (!isset($_GET['UserID'])) {
    echo "<p>No user selected.</p>";
    include 'footer.php';
    exit();
}

$user_id = intval($_GET['UserID']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $number = trim($_POST['number']);
    $role = trim($_POST['role']);

    $stmt = $conn->prepare("UPDATE User SET Name = ?, Email = ?, Number = ?, Role = ? WHERE UserID = ?");
    $stmt->bind_param("ssssi", $name, $email, $number, $role, $user_id);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>User updated successfully.</p>";

        // --- Send email notification ---
        $mail = getMailer();
        try {
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = "Your Account Details Have Been Updated";
            $mail->Body = "
                <h2>Account Updated</h2>
                <p>Dear {$name},</p>
                <p>Your account details have been updated by an administrator. Here are your current details:</p>
                <ul>
                    <li><strong>Name:</strong> {$name}</li>
                    <li><strong>Email:</strong> {$email}</li>
                    <li><strong>Phone Number:</strong> {$number}</li>
                    <li><strong>Role:</strong> {$role}</li>
                </ul>
                <p>If you did not request this change, please contact support immediately.</p>
            ";
            $mail->AltBody = "Dear {$name},\nYour account details have been updated.\nName: {$name}\nEmail: {$email}\nPhone Number: {$number}\nRole: {$role}";

            $mail->send();
        } catch (Exception $e) {
            error_log("Mail Error: {$mail->ErrorInfo}");
        }

    } else {
        echo "<p style='color: red;'>Error updating user: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// Fetch current user info
$stmt = $conn->prepare("SELECT UserID, Name, Email, Number, Role, CreatedAt FROM User WHERE UserID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p>User not found.</p>";
    include 'footer.php';
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User - Admin</title>
    <link href="addedit.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Edit User</h2>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($stmt)): ?>
        <?php if ($stmt->execute()): ?>
            <div class="message success">User updated successfully.</div>
        <?php else: ?>
            <div class="message error">Error updating user: <?php echo $stmt->error; ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="name">Name:</label><br>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['Name']); ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required><br><br>

        <label for="number">Phone Number:</label><br>
        <input type="text" id="number" name="number" value="<?php echo htmlspecialchars($user['Number']); ?>" required><br><br>

        <label for="role">Role:</label><br>
        <select id="role" name="role" required>
            <option value="user" <?php if ($user['Role'] === 'user') echo 'selected'; ?>>User</option>
            <option value="barber" <?php if ($user['Role'] === 'barber') echo 'selected'; ?>>Barber</option>
            <option value="admin" <?php if ($user['Role'] === 'admin') echo 'selected'; ?>>Admin</option>
        </select><br><br>

        <div class="button-group">
            <button type="submit">Update User</button>
            <a href="admin_dashboard.php" class="btn" style="text-align: center;">Cancel</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
