<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
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
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Add New User</h2>

<?php
if (!empty($errors)) {
    echo "<div style='color:red;'><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul></div>";
}

if ($success) {
    echo "<div style='color:green;'>" . htmlspecialchars($success) . "</div>";
}
?>

<form method="POST" action="">
    <label>Name:</label><br>
    <input type="text" name="Name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="Email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required><br><br>

    <label>Phone Number:</label><br>
    <input type="text" name="Number" value="<?php echo isset($number) ? htmlspecialchars($number) : ''; ?>"><br><br>

    <label>Password:</label><br>
    <input type="password" name="Password" required><br><br>

    <button type="submit">Add User</button>
</form>

