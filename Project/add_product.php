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
    $price = trim($_POST['Price']);
    $category = trim($_POST['Category']);
    $stock = trim($_POST['Stock']);

    // Validation
    if (empty($name)) $errors[] = "Product name is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a valid positive number.";
    if (empty($category)) $errors[] = "Category is required.";
    if (!is_numeric($stock) || $stock < 0) $errors[] = "Stock must be a valid non-negative number.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Products (Name, Price, Category, Stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdsi", $name, $price, $category, $stock);
        if ($stmt->execute()) {
            $success = "Product added successfully!";
            // Reset fields
            $name = $price = $category = $stock = '';
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Add New Product</h2>

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
    <label>Product Name:</label><br>
    <input type="text" name="Name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required><br><br>

    <label>Price:</label><br>
    <input type="number" step="0.01" name="Price" value="<?php echo htmlspecialchars($price ?? ''); ?>" required><br><br>

    <label>Category:</label><br>
    <input type="text" name="Category" value="<?php echo htmlspecialchars($category ?? ''); ?>" required><br><br>

    <label>Stock:</label><br>
    <input type="number" name="Stock" value="<?php echo htmlspecialchars($stock ?? ''); ?>" required><br><br>

    <button type="submit">Add Product</button>
</form>

