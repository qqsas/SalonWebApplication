<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';
include 'header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $description = trim($_POST['Description']);
    $price = trim($_POST['Price']);
    $time = trim($_POST['Time']);

    // Validation
    if (empty($name)) $errors[] = "Service name is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a valid positive number.";
    if (empty($time)) $errors[] = "Service time is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Services (Name, Description, Price, Time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $name, $description, $price, $time);
        if ($stmt->execute()) {
            $success = "Service added successfully!";
            // Reset fields
            $name = $description = $price = $time = '';
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Add New Service</h2>

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
    <label>Service Name:</label><br>
    <input type="text" name="Name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required><br><br>

    <label>Description:</label><br>
    <textarea name="Description"><?php echo htmlspecialchars($description ?? ''); ?></textarea><br><br>

    <label>Price:</label><br>
    <input type="number" step="0.01" name="Price" value="<?php echo htmlspecialchars($price ?? ''); ?>" required><br><br>

    <label>Time (minutes):</label><br>
    <input type="number" name="Time" value="<?php echo htmlspecialchars($time ?? ''); ?>" required><br><br>

    <button type="submit">Add Service</button>
</form>

