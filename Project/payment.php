<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit;
}

$userID = $_SESSION['UserID'];

// Fetch cart items for display
$stmt = $conn->prepare("
    SELECT ci.CartItemID, p.ProductID, p.Name, p.Price, ci.Quantity
    FROM CartItems ci
    JOIN Products p ON ci.ProductID = p.ProductID
    JOIN Cart c ON ci.CartID = c.CartID
    WHERE c.UserID = ?
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$cartItems = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = 0;
foreach ($cartItems as $item) {
    $total += $item['Price'] * $item['Quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Your Order</title>
    <link rel="stylesheet" href="styles2.css">
    <style>
        .container { max-width: 800px; margin: auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .btn { padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; }
        .btn:hover { background: #218838; }
        .info { background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Confirm Your Order</h2>

    <div class="info">
        <p>All purchases must be done <strong>in person</strong> at our store. By confirming this order, you are reserving the selected products. Payment and pickup will be handled in store.</p>
    </div>

    <?php if (empty($cartItems)): ?>
        <p>Your cart is empty. <a href="products.php" class="btn">Browse Products</a></p>
    <?php else: ?>
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
                <?php foreach ($cartItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['Name']); ?></td>
                        <td>$<?php echo number_format($item['Price'], 2); ?></td>
                        <td><?php echo $item['Quantity']; ?></td>
                        <td>$<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="text-align:right;"><strong>Total:</strong></td>
                    <td>$<?php echo number_format($total, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <form method="POST" action="place_order.php">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn">Confirm Reservation</button>
            <a href="orders.php" class="btn" style="background:#dc3545;">Cancel</a>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

