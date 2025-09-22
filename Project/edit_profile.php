<?php
session_start();
include 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['UserID'];
$message = "";

// Fetch current user data
$stmt = $conn->prepare("SELECT Name, Email, Number FROM User WHERE UserID = ? AND IsDeleted = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $number);
$stmt->fetch();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name']);
    $new_email = trim($_POST['email']);
    $new_number = trim($_POST['number']);
    $new_password = trim($_POST['password']);
    $hashed_password = null;

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    }

    if ($hashed_password) {
        $update = $conn->prepare("UPDATE User SET Name=?, Email=?, Number=?, Password=? WHERE UserID=?");
        $update->bind_param("ssssi", $new_name, $new_email, $new_number, $hashed_password, $user_id);
    } else {
        $update = $conn->prepare("UPDATE User SET Name=?, Email=?, Number=? WHERE UserID=?");
        $update->bind_param("sssi", $new_name, $new_email, $new_number, $user_id);
    }

    if ($update->execute()) {
        $message = "Profile updated successfully!";
        $name = $new_name;
        $email = $new_email;
        $number = $new_number;
    } else {
        $message = "Error updating profile: " . $update->error;
    }

    $update->close();
}
?>

<?php include 'header.php'; ?>

<div class="container">
    <h2>Edit Profile</h2>

    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="name">Name:</label><br>
        <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required><br><br>

        <label for="number">Phone Number:</label><br>
        <input type="text" name="number" value="<?php echo htmlspecialchars($number); ?>" required><br><br>

        <label for="password">New Password (leave blank to keep current):</label><br>
        <input type="password" name="password"><br><br>

        <button type="submit">Update Profile</button>
    </form>
</div>

<?php include 'footer.php'; ?>

