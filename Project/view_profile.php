<?php
session_start();
include 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['UserID'];

// Fetch user info
$stmt = $conn->prepare("SELECT Name, Email, Number, Role, CreatedAt FROM User WHERE UserID = ? AND IsDeleted = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $number, $role, $createdAt);
$stmt->fetch();
$stmt->close();
?>

<?php include 'header.php'; ?>

<div class="container">
    <h2>My Profile</h2>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Name</th>
            <td><?php echo htmlspecialchars($name); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo htmlspecialchars($email); ?></td>
        </tr>
        <tr>
            <th>Phone Number</th>
            <td><?php echo htmlspecialchars($number); ?></td>
        </tr>
        <tr>
            <th>Role</th>
            <td><?php echo htmlspecialchars($role); ?></td>
        </tr>
        <tr>
            <th>Member Since</th>
            <td><?php echo htmlspecialchars($createdAt); ?></td>
        </tr>
    </table>

    <br>
    <a href="edit_profile.php">Edit Profile</a>
</div>

<?php include 'footer.php'; ?>

