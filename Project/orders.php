<?php
session_start();
include 'db.php';
include 'header.php';

// Ensure user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit;
}

$userID = $_SESSION['UserID'];

// Handle actions: cancel, restore, edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['OrderID'])) {
    $orderID = (int)$_POST['OrderID'];
    $action = $_POST['action'];

    // Fetch order date and user
    $stmt = $conn->prepare("SELECT UserID, Status, CreatedAt FROM Orders WHERE OrderID = ?");
    $stmt->bind_param("i", $orderID);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orderData) {
        die("Order not found.");
    }

    // Check ownership for non-admin users
    if ( $orderData['UserID'] != $userID) {
        die("Unauthorized action.");
    }

    // Check 2-day restriction
    $createdTime = strtotime($orderData['CreatedAt']);
    $now = time();
    if (($now - $createdTime) > 2 * 24 * 60 * 60) {
        die("You cannot modify orders older than 2 days.");
    }

    // Perform action
    if ($action === 'cancel' && $orderData['Status'] !== 'Cancelled') {
        $stmt = $conn->prepare("UPDATE Orders SET Status='Cancelled' WHERE OrderID=?");
        $stmt->bind_param("i", $orderID);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'restore' && $orderData['Status'] === 'Cancelled') {
        $stmt = $conn->prepare("UPDATE Orders SET Status='Pending' WHERE OrderID=?");
        $stmt->bind_param("i", $orderID);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'edit') {
        // Redirect to edit page
        header("Location: edit_orderC.php?OrderID=" . $orderID);
        exit;
    }
}

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
    table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
    th, td { padding: 10px; border: 1px solid #ddd; }
    th { background: #f4f4f4; }
    .btn { padding: 5px 10px; background: #007BFF; color: white; text-decoration: none; border-radius: 4px; margin-right: 5px; display:inline-block; }
    .btn:hover { background: #0056b3; }
    .btn-cancel { background: #dc3545; }
    .btn-cancel:hover { background: #b02a37; }
    .btn-restore { background: #28a745; }
    .btn-restore:hover { background: #1e7e34; }
</style>
</head>
<body>
<div class="container">
    <h2>Your Orders</h2>

    <?php if (empty($orders)): ?>
        <p>No orders found.</p>
        <a href="products.php" class="btn">Browse Products</a>
    <?php else: ?>
        <?php foreach ($orders as $order): 
            $canModify = (time() - strtotime($order['CreatedAt'])) <= 2 * 24 * 60 * 60;
        ?>
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

                <?php if ($canModify): ?>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="OrderID" value="<?php echo $order['OrderID']; ?>">
                        <?php if ($order['Status'] !== 'Cancelled'): ?>
                            <button type="submit" name="action" value="cancel" class="btn btn-cancel">Cancel</button>
                            <button type="submit" name="action" value="edit" class="btn">Edit</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="restore" class="btn btn-restore">Restore</button>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p><em>Order modification period has expired.</em></p>
                <?php endif; ?>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>

