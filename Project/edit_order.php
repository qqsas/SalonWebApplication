<?php
session_start();
include 'db.php';
include 'mail.php'; // PHPMailer setup

// --- Access Control: only admins ---
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    echo "Invalid order ID.";
    exit();
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status     = trim($_POST['status']);
    $totalPrice = floatval($_POST['total_price']);

    if ($status) {
        $stmt = $conn->prepare("UPDATE Orders SET Status=?, TotalPrice=? WHERE OrderID=?");
        $stmt->bind_param("sdi", $status, $totalPrice, $order_id);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Order updated successfully.</p>";

            // --- Send email notification to customer ---
            $stmtUser = $conn->prepare("SELECT u.Name, u.Email FROM Orders o JOIN User u ON o.UserID = u.UserID WHERE o.OrderID=?");
            $stmtUser->bind_param("i", $order_id);
            $stmtUser->execute();
            $user = $stmtUser->get_result()->fetch_assoc();
            $stmtUser->close();

            if ($user) {
                $mail = getMailer(); // PHPMailer object
                try {
                    $mail->addAddress($user['Email'], $user['Name']);
                    $mail->isHTML(true);
                    $mail->Subject = "Your Order #{$order_id} has been updated";
                    $mail->Body = "
                        <h2>Order Update</h2>
                        <p>Dear {$user['Name']},</p>
                        <p>Your order <strong>#{$order_id}</strong> has been updated.</p>
                        <p><strong>Status:</strong> {$status}</p>
                        <p><strong>Total Price:</strong> R" . number_format($totalPrice, 2) . "</p>
                        <p>Thank you for shopping with us!</p>
                    ";
                    $mail->AltBody = "Dear {$user['Name']},\nYour order #{$order_id} has been updated.\nStatus: {$status}\nTotal Price: R" . number_format($totalPrice, 2);

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mail Error: {$mail->ErrorInfo}");
                }
            }

        } else {
            echo "<p style='color:red;'>Error updating order: " . $conn->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>Status is required.</p>";
    }
}

// --- Fetch order details ---
$stmt = $conn->prepare("SELECT o.*, u.Name AS CustomerName, u.Email 
                        FROM Orders o 
                        JOIN User u ON o.UserID = u.UserID 
                        WHERE o.OrderID=?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "Order not found.";
    exit();
}

// --- Fetch order items ---
$stmt = $conn->prepare("SELECT oi.*, p.Name AS ProductName, p.Price 
                        FROM OrderItems oi 
                        JOIN Products p ON oi.ProductID = p.ProductID 
                        WHERE oi.OrderID=?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result();
$stmt->close();
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Order #<?php echo htmlspecialchars($order['OrderID']); ?></h2>
    <link href="addedit.css" rel="stylesheet">

    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['CustomerName']); ?> (<?php echo htmlspecialchars($order['Email']); ?>)</p>
    <p><strong>Current Status:</strong> <?php echo htmlspecialchars($order['Status']); ?></p>
    <p><strong>Created At:</strong> <?php echo htmlspecialchars($order['CreatedAt']); ?></p>

    <h3>Order Items</h3>
    <table border="1" cellpadding="5">
        <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Subtotal</th>
        </tr>
        <?php 
        $calculated_total = 0;
        while ($item = $order_items->fetch_assoc()) { 
            $subtotal = $item['Price'] * $item['Quantity'];
            $calculated_total += $subtotal;
        ?>
        <tr>
            <td><?php echo htmlspecialchars($item['ProductName']); ?></td>
            <td>R<?php echo number_format($item['Price'], 2); ?></td>
            <td><?php echo $item['Quantity']; ?></td>
            <td>R<?php echo number_format($subtotal, 2); ?></td>
        </tr>
        <?php } ?>
    </table>

    <p><strong>Calculated Total:</strong> R<?php echo number_format($calculated_total, 2); ?></p>

    <form method="post">
        <div>
            <label for="status">Status:</label><br>
            <select name="status" id="status" required>
                <option value="Pending"   <?php if ($order['Status']=="Pending") echo "selected"; ?>>Pending</option>
                <option value="Processing" <?php if ($order['Status']=="Processing") echo "selected"; ?>>Processing</option>
                <option value="Shipped"   <?php if ($order['Status']=="Shipped") echo "selected"; ?>>Shipped</option>
                <option value="Completed" <?php if ($order['Status']=="Completed") echo "selected"; ?>>Completed</option>
                <option value="Cancelled" <?php if ($order['Status']=="Cancelled") echo "selected"; ?>>Cancelled</option>
            </select>
        </div>

        <div>
            <label for="total_price">Total Price (can override):</label><br>
            <input type="number" step="0.01" name="total_price" id="total_price" 
                   value="<?php echo htmlspecialchars($order['TotalPrice']); ?>" required>
        </div>

        <br>
        <button type="submit">Update Order</button>
    </form>
</div>
<?php include 'footer.php'; ?>

