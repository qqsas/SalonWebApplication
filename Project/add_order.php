<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // PHPMailer setup
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

            // --- Send confirmation email ---
            $stmt2 = $conn->prepare("
                SELECT u.Name AS UserName, u.Email AS UserEmail, p.Name AS ProductName, oi.Quantity
                FROM OrderItems oi
                JOIN Products p ON oi.ProductID = p.ProductID
                JOIN Orders o ON oi.OrderID = o.OrderID
                JOIN User u ON o.UserID = u.UserID
                WHERE oi.OrderID = ?
            ");
            $stmt2->bind_param("i", $orderID);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $userInfo = null;
            $itemsList = "";
            while ($row = $result->fetch_assoc()) {
                if (!$userInfo) $userInfo = $row;
                $itemsList .= "<li>" . htmlspecialchars($row['ProductName']) . " - Qty: " . intval($row['Quantity']) . "</li>";
            }
            $stmt2->close();

            if ($userInfo) {
                $mail = getMailer(); // PHPMailer object

                try {
                    $mail->addAddress($userInfo['UserEmail'], $userInfo['UserName']);
                    $mail->isHTML(true);
                    $mail->Subject = "Order Confirmation - Order #$orderID";
                    $mail->Body = "
                        <h2>Order Confirmation</h2>
                        <p>Dear {$userInfo['UserName']},</p>
                        <p>Your order has been successfully placed with the following items:</p>
                        <ul>$itemsList</ul>
                        <p><strong>Status:</strong> {$status}</p>
                        <p>Thank you for shopping with us!</p>
                    ";
                    $mail->AltBody = "Your order #$orderID has been placed. Items: " . strip_tags(str_replace("<li>", "- ", $itemsList));

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mail Error: {$mail->ErrorInfo}");
                }
            }

        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
    }
}

// Fetch users and products for dropdown
$users = $conn->query("SELECT UserID, Name FROM User WHERE IsDeleted=0");
$products = $conn->query("SELECT ProductID, Name FROM Products WHERE IsDeleted=0");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Order - Admin</title>
    <link href="addedit.css" rel="stylesheet">
</head>
<body>

<div class="form-container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Add New Order</h2>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <ul>
            <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>User:</label>
            <select name="UserID" required>
                <?php while($u = $users->fetch_assoc()): ?>
                    <option value="<?= $u['UserID'] ?>"><?= htmlspecialchars($u['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div id="productContainer">
            <div class="product-row">
                <div class="form-group">
                    <label>Product:</label>
                    <select name="ProductID[]" required>
                        <?php $products->data_seek(0); while($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['ProductID'] ?>"><?= htmlspecialchars($p['Name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" name="Quantity[]" min="1" required>
                </div>
            </div>
        </div>

        <button type="button" class="add-product-btn" onclick="addProductRow()">+ Add Another Product</button>

        <div class="form-group">
            <label>Status:</label>
            <select name="Status">
                <option value="Pending">Pending</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>

        <div class="button-group">
            <button type="submit">Add Order</button>
            <a href="admin_dashboard.php" class="btn" style="text-align: center;">Cancel</a>
        </div>
    </form>
</div>

<script>
function addProductRow() {
    const container = document.getElementById('productContainer');
    const row = document.querySelector('.product-row').cloneNode(true);
    
    // Clear the values in the cloned row
    const inputs = row.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (input.type === 'number') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
    });
    
    container.appendChild(row);
}
</script>

</body>
</html>
