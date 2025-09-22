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
    $userID = $_POST['UserID'];
    $productID = $_POST['ProductID'];
    $quantity = $_POST['Quantity'];
    $status = $_POST['Status'] ?? 'Pending';

    if (!is_numeric($quantity) || $quantity <= 0) $errors[] = "Quantity must be a positive number.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Orders (UserID, ProductID, Quantity, Status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $userID, $productID, $quantity, $status);
        if ($stmt->execute()) {
            $success = "Order added successfully!";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch users and products for dropdown
$users = $conn->query("SELECT UserID, Name FROM User WHERE IsDeleted=0");
$products = $conn->query("SELECT ProductID, Name FROM Products WHERE IsDeleted=0");
?>

<h2>Add New Order</h2>

<?php
if ($errors) {
    echo "<div style='color:red;'><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul></div>";
}
if ($success) {
    echo "<div style='color:green;'>" . htmlspecialchars($success) . "</div>";
}
?>

<form method="POST">
    <label>User:</label><br>
    <select name="UserID" required>
        <?php while($u = $users->fetch_assoc()) {
            echo "<option value='{$u['UserID']}'>{$u['Name']}</option>";
        } ?>
    </select><br><br>

    <label>Product:</label><br>
    <select name="ProductID" required>
        <?php while($p = $products->fetch_assoc()) {
            echo "<option value='{$p['ProductID']}'>{$p['Name']}</option>";
        } ?>
    </select><br><br>

    <label>Quantity:</label><br>
    <input type="number" name="Quantity" min="1" required><br><br>

    <label>Status:</label><br>
    <select name="Status">
        <option value="Pending">Pending</option>
        <option value="Completed">Completed</option>
        <option value="Cancelled">Cancelled</option>
    </select><br><br>

    <button type="submit">Add Order</button>
</form>

