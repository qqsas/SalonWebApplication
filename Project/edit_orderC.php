<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit;
}

$userID = $_SESSION['UserID'];

// Ensure OrderID is provided
if (!isset($_GET['OrderID'])) {
    die("No order selected.");
}

$orderID = (int)$_GET['OrderID'];

// Fetch order
$stmt = $conn->prepare("SELECT UserID, Status, CreatedAt FROM Orders WHERE OrderID=?");
$stmt->bind_param("i", $orderID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) die("Order not found.");

// Permission check
if ($order['UserID'] != $userID) {
    die("Unauthorized access.");
}

// 2-day limit check
$createdTime = strtotime($order['CreatedAt']);
$now = time();
if (($now - $createdTime) > 2 * 24 * 60 * 60) {
    die("You cannot edit orders older than 2 days.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantities'])) {
    $quantities = $_POST['quantities'];
    $totalPrice = 0;

    foreach ($quantities as $itemID => $qty) {
        $qty = max(0, (int)$qty); // prevent negative values
        $stmt = $conn->prepare("SELECT Price FROM OrderItems oi JOIN Products p ON oi.ProductID = p.ProductID WHERE oi.OrderItemID=? AND oi.OrderID=?");
        $stmt->bind_param("ii", $itemID, $orderID);
        $stmt->execute();
        $price = $stmt->get_result()->fetch_assoc()['Price'];
        $stmt->close();

        if ($qty === 0) {
            // Remove item
            $stmt = $conn->prepare("DELETE FROM OrderItems WHERE OrderItemID=? AND OrderID=?");
            $stmt->bind_param("ii", $itemID, $orderID);
            $stmt->execute();
            $stmt->close();
        } else {
            // Update quantity
            $stmt = $conn->prepare("UPDATE OrderItems SET Quantity=? WHERE OrderItemID=? AND OrderID=?");
            $stmt->bind_param("iii", $qty, $itemID, $orderID);
            $stmt->execute();
            $stmt->close();

            $totalPrice += $price * $qty;
        }
    }

    // Update total price in Orders table
    $stmt = $conn->prepare("UPDATE Orders SET TotalPrice=? WHERE OrderID=?");
    $stmt->bind_param("di", $totalPrice, $orderID);
    $stmt->execute();
    $stmt->close();

    echo "<p>Order updated successfully! <a href='orders.php'>Back to Orders</a></p>";
    include 'footer.php';
    exit;
}

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.OrderItemID, p.Name, p.Price, oi.Quantity
    FROM OrderItems oi
    JOIN Products p ON oi.ProductID = p.ProductID
    WHERE oi.OrderID=?
");
$stmt->bind_param("i", $orderID);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    die("No items found in this order.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Order #<?= $orderID ?></title>
<link rel="stylesheet" href="styles.css">
<style>
table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
th, td { padding: 10px; border: 1px solid #ddd; }
th { background: #f4f4f4; }
input.qty { width: 60px; text-align: center; }
.btn { padding: 5px 10px; background: #007BFF; color: white; text-decoration: none; border-radius: 4px; }
.btn:hover { background: #0056b3; }
</style>
</head>
<body>
<div class="container">
    <h2>Edit Order #<?= $orderID ?></h2>
    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['Name']) ?></td>
                    <td>$<?= number_format($item['Price'], 2) ?></td>
                    <td>
                        <input type="number" name="quantities[<?= $item['OrderItemID'] ?>]" class="qty" value="<?= $item['Quantity'] ?>" min="0">
                    </td>
                    <td>$<?= number_format($item['Price'] * $item['Quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="btn">Update Order</button>
        <a href="orders.php" class="btn" style="background:#6c757d;">Cancel</a>
    </form>
</div>
</body>
</html>

