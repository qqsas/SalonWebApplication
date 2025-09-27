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

    $stmt = $conn->prepare("SELECT UserID, Status, CreatedAt FROM Orders WHERE OrderID = ?");
    $stmt->bind_param("i", $orderID);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orderData) die("Order not found.");
    if ($orderData['UserID'] != $userID) die("Unauthorized action.");

    $createdTime = strtotime($orderData['CreatedAt']);
    if (($action !== 'edit') && ((time() - $createdTime) > 2 * 24 * 60 * 60)) die("Cannot modify orders older than 2 days.");

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
        header("Location: edit_orderC.php?OrderID=" . $orderID);
        exit;
    }
}

// Fetch orders
if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT o.OrderID, o.UserID, o.TotalPrice, o.Status, o.CreatedAt, u.Name AS UserName
        FROM Orders o
        JOIN User u ON o.UserID = u.UserID
        ORDER BY o.CreatedAt DESC
    ");
} else {
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
                <h3>Order #<?= $order['OrderID']; ?></h3>
                <?php if ($isAdmin): ?>
                    <p>Customer: <?= htmlspecialchars($order['UserName']); ?></p>
                <?php endif; ?>
                <p>Status: <?= htmlspecialchars($order['Status']); ?></p>
                <p>Total: $<?= number_format($order['TotalPrice'], 2); ?></p>
                <p>Date: <?= $order['CreatedAt']; ?></p>

                <!-- Fetch order items -->
                <?php
                $stmt = $conn->prepare("
                    SELECT p.ProductID, p.Name, p.Price, oi.Quantity
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
                            <th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            // Check if review exists for this product & user
                            $stmt = $conn->prepare("SELECT ReviewID FROM Reviews WHERE ProductID=? AND UserID=? AND IsDeleted=0");
                            $stmt->bind_param("ii", $item['ProductID'], $userID);
                            $stmt->execute();
                            $reviewRes = $stmt->get_result();
                            $hasReview = $reviewRes->num_rows > 0;
                            $stmt->close();
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($item['Name']); ?></td>
                                <td>$<?= number_format($item['Price'], 2); ?></td>
                                <td><?= $item['Quantity']; ?></td>
                                <td>$<?= number_format($item['Price'] * $item['Quantity'], 2); ?></td>
                                <td>
                                    <?php if ($hasReview): ?>
                                        <span style="color:green;">Reviewed</span>
                                    <?php else: ?>
                                        <a href="make_review.php?ProductID=<?= $item['ProductID']; ?>" class="btn">Make Review</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($canModify): ?>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="OrderID" value="<?= $order['OrderID']; ?>">
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

