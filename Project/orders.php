<?php
session_start();
include 'db.php';
include 'header.php';
// Ensure user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit;
}

$userID = $_SESSION['UserID'];
$isAdmin = ($_SESSION['Role'] ?? '') === 'admin';

// Fetch orders
if ($isAdmin) {
    // Admin sees all orders
    $stmt = $conn->prepare("
        SELECT o.OrderID, o.UserID, o.TotalPrice, o.Status, o.CreatedAt, u.Name AS UserName
        FROM Orders o
        JOIN User u ON o.UserID = u.UserID
        ORDER BY o.CreatedAt DESC
    ");
} else {
    // Regular user sees only their orders
    $stmt = $conn->prepare("
        SELECT o.OrderID, o.TotalPrice, o.Status, o.CreatedAt
        FROM Orders o
        WHERE o.UserID = ?
        ORDER BY o.CreatedAt DESC
    ");
    $stmt->bind_param("i", $userID);
}

$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Orders</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        th {
            background: #f4f4f4;
        }
        .btn {
            padding: 5px 10px;
            background: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h2>Your Orders</h2>

    <?php if (empty($orders)): ?>
        <p>No orders found.</p>
        <a href="products.php" class="btn">Browse Products</a>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <h3>Order #<?php echo $order['OrderID']; ?></h3>
                <?php if ($isAdmin): ?>
                    <p>Customer: <?php echo htmlspecialchars($order['UserName']); ?></p>
                <?php endif; ?>
                <p>Status: <?php echo htmlspecialchars($order['Status']); ?></p>
                <p>Total: $<?php echo number_format($order['TotalPrice'], 2); ?></p>
                <p>Date: <?php echo $order['CreatedAt']; ?></p>

                <!-- Fetch order items -->
                <?php
                $stmt = $conn->prepare("
                    SELECT p.Name, p.Price, oi.Quantity
                    FROM OrderItems oi
                    JOIN Products p ON oi.ProductID = p.ProductID
                    WHERE oi.OrderID = ?
                ");
                $stmt->bind_param("i", $order['OrderID']);
                $stmt->execute();
                $itemsResult = $stmt->get_result();
                $items = $itemsResult->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                ?>
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
                                <td><?php echo htmlspecialchars($item['Name']); ?></td>
                                <td>$<?php echo number_format($item['Price'], 2); ?></td>
                                <td><?php echo $item['Quantity']; ?></td>
                                <td>$<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>

