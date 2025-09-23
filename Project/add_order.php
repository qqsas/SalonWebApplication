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
    $status = $_POST['Status'] ?? 'Pending';

    $productIDs = $_POST['ProductID'] ?? [];
    $quantities = $_POST['Quantity'] ?? [];

    if (empty($productIDs) || empty($quantities)) {
        $errors[] = "Please select at least one product.";
    }

    // Validate quantities
    foreach ($quantities as $q) {
        if (!is_numeric($q) || $q <= 0) {
            $errors[] = "Quantities must be positive numbers.";
            break;
        }
    }

    if (empty($errors)) {
        // Insert into Orders table
        $stmt = $conn->prepare("INSERT INTO Orders (UserID, Status, CreatedAt) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $userID, $status);
        if ($stmt->execute()) {
            $orderID = $stmt->insert_id;
            $stmt->close();

            // Insert each product into OrderItems table
            $stmt = $conn->prepare("INSERT INTO OrderItems (OrderID, ProductID, Quantity) VALUES (?, ?, ?)");
            foreach ($productIDs as $i => $productID) {
                $quantity = $quantities[$i];
                $stmt->bind_param("iii", $orderID, $productID, $quantity);
                $stmt->execute();
            }
            $stmt->close();

            $success = "Order with multiple items added successfully!";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
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
            echo "<option value='{$u['UserID']}'>" . htmlspecialchars($u['Name']) . "</option>";
        } ?>
    </select><br><br>

    <div id="productContainer">
        <div class="productRow">
            <label>Product:</label><br>
            <select name="ProductID[]" required>
                <?php
                $products->data_seek(0); // rewind result set
                while($p = $products->fetch_assoc()) {
                    echo "<option value='{$p['ProductID']}'>" . htmlspecialchars($p['Name']) . "</option>";
                } ?>
            </select><br>

            <label>Quantity:</label><br>
            <input type="number" name="Quantity[]" min="1" required><br><br>
        </div>
    </div>

    <button type="button" onclick="addProductRow()">+ Add Another Product</button><br><br>

    <label>Status:</label><br>
    <select name="Status">
        <option value="Pending">Pending</option>
        <option value="Completed">Completed</option>
        <option value="Cancelled">Cancelled</option>
    </select><br><br>

    <button type="submit">Add Order</button>
</form>

<script>
function addProductRow() {
    const container = document.getElementById('productContainer');
    const row = document.querySelector('.productRow').cloneNode(true);
    container.appendChild(row);
}
</script>

