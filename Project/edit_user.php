<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';
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

<div class="container">
    <h2>Edit User</h2>
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

        <button type="submit">Update User</button>
    </form>
</div>

<?php include 'footer.php'; ?>

