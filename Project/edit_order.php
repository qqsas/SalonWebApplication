<?php
session_start();
include 'db.php';
include 'mail.php'; // PHPMailer setup

// --- Access Control: only admins ---
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

// --- Validate and sanitize order ID ---
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id || $order_id <= 0) {
    $_SESSION['error'] = "Invalid order ID.";
    header("Location: admin_dashboard.php?view=orders");
    exit();
}

// --- Initialize variables ---
$form_errors = [];
$calculated_total = 0;

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Sanitize and validate input data ---
    $status = trim(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING));
    $totalPrice = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
    
    // --- Validation checks ---
    $allowed_statuses = ['Pending', 'confirmed', 'Shipped', 'Completed', 'Cancelled'];
    if (empty($status) || !in_array($status, $allowed_statuses)) {
        $form_errors[] = "Please select a valid status.";
    }
    
    if ($totalPrice === false || $totalPrice < 0) {
        $form_errors[] = "Total price must be a valid number greater than or equal to 0.";
    }
    
    // Validate total price is reasonable (not more than 100,000 for example)
    if ($totalPrice > 100000) {
        $form_errors[] = "Total price seems unreasonably high. Please verify the amount.";
    }

    // --- If no validation errors, proceed with update ---
    if (empty($form_errors)) {
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("UPDATE Orders SET Status=?, TotalPrice=? WHERE OrderID=?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("sdi", $status, $totalPrice, $order_id);

            if ($stmt->execute()) {
                // --- Send email notification to customer ---
                $stmtUser = $conn->prepare("SELECT u.Name, u.Email FROM Orders o JOIN User u ON o.UserID = u.UserID WHERE o.OrderID=?");
                if (!$stmtUser) {
                    throw new Exception("User query prepare failed: " . $conn->error);
                }
                
                $stmtUser->bind_param("i", $order_id);
                if (!$stmtUser->execute()) {
                    throw new Exception("User query execute failed: " . $stmtUser->error);
                }
                
                $user_result = $stmtUser->get_result();
                $user = $user_result->fetch_assoc();
                $stmtUser->close();

                if ($user) {
                    $mail = getMailer(); // PHPMailer object
                    try {
                        $mail->addAddress($user['Email'], $user['Name']);
                        $mail->isHTML(true);
                        $mail->Subject = "Your Order #{$order_id} has been updated";
                        $mail->Body = "
                            <h2>Order Update</h2>
                            <p>Dear " . htmlspecialchars($user['Name']) . ",</p>
                            <p>Your order <strong>#{$order_id}</strong> has been updated.</p>
                            <p><strong>Status:</strong> " . htmlspecialchars($status) . "</p>
                            <p><strong>Total Price:</strong> R" . number_format($totalPrice, 2) . "</p>
                            <p>Thank you for shopping with us!</p>
                        ";
                        $mail->AltBody = "Dear " . htmlspecialchars($user['Name']) . ",\nYour order #{$order_id} has been updated.\nStatus: " . htmlspecialchars($status) . "\nTotal Price: R" . number_format($totalPrice, 2);

                        if (!$mail->send()) {
                            error_log("Mail Error: {$mail->ErrorInfo}");
                            // Don't throw exception for email failure, just log it
                        }
                    } catch (Exception $e) {
                        error_log("Mail Exception: {$e->getMessage()}");
                        // Continue with order update even if email fails
                    }
                }

                $conn->commit();
                $_SESSION['success'] = "Order updated successfully.";
                
                // Redirect to prevent form resubmission
                header("Location: edit_order.php?id=" . $order_id);
                exit();
                
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Order update error: " . $e->getMessage());
            $form_errors[] = "Error updating order: " . $e->getMessage();
        }
    }
}

// --- Fetch order details with prepared statement ---
$stmt = $conn->prepare("SELECT o.*, u.Name AS CustomerName, u.Email 
                        FROM Orders o 
                        JOIN User u ON o.UserID = u.UserID 
                        WHERE o.OrderID=?");
if (!$stmt) {
    error_log("Order details prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: admin_dashboard.php?view=orders");
    exit();
}

$stmt->bind_param("i", $order_id);
if (!$stmt->execute()) {
    error_log("Order details execute failed: " . $stmt->error);
    $_SESSION['error'] = "Failed to fetch order details.";
    header("Location: admin_dashboard.php?view=orders");
    exit();
}

$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['error'] = "Order not found.";
    header("Location: admin_dashboard.php?view=orders");
    exit();
}

// --- Fetch order items with prepared statement ---
$stmt = $conn->prepare("SELECT oi.*, p.Name AS ProductName, p.Price 
                        FROM OrderItems oi 
                        JOIN Products p ON oi.ProductID = p.ProductID 
                        WHERE oi.OrderID=?");
if (!$stmt) {
    error_log("Order items prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: admin_dashboard.php?view=orders");
    exit();
}

$stmt->bind_param("i", $order_id);
if (!$stmt->execute()) {
    error_log("Order items execute failed: " . $stmt->error);
    $_SESSION['error'] = "Failed to fetch order items.";
    header("Location: admin_dashboard.php?view=orders");
    exit();
}

$order_items_result = $stmt->get_result();
$stmt->close();

// Calculate total from order items
$order_items = [];
while ($item = $order_items_result->fetch_assoc()) {
    $order_items[] = $item;
    $calculated_total += $item['Price'] * $item['Quantity'];
}
?>

<?php include 'header.php'; ?>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .edit-order-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .edit-order-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .order-message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .order-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .order-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .order-customer-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce7ff; color: #004085; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-items-section {
            margin-bottom: 20px;
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .order-items-table th,
        .order-items-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .order-items-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .order-total-summary {
            background: #e9ecef;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 1.1em;
        }
        
        .edit-order-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .status-select, .total-price-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        
        .total-price-input {
            background: #fff;
        }
        
        .total-price-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-cancel, .btn-update {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-update {
            background: #007bff;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #545b62;
        }
        
        .btn-update:hover {
            background: #0056b3;
        }
        
        .form-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
        }
        
        .price-discrepancy {
            background: #fff3cd;
            color: #856404;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 8px;
            border: 1px solid #ffeaa7;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
<div class="edit-order-container">
    <div class="edit-order-header">
        <h2>Edit Order #<?php echo htmlspecialchars($order['OrderID']); ?></h2>
    </div>

    <!-- Display session messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="order-message success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="order-message error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Display form validation errors -->
    <?php if (!empty($form_errors)): ?>
        <div class="order-message error">
            <ul>
                <?php foreach ($form_errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="order-customer-info">
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['CustomerName']); ?> (<?php echo htmlspecialchars($order['Email']); ?>)</p>
        <p><strong>Current Status:</strong> <span class="status-badge status-<?php echo strtolower(htmlspecialchars($order['Status'])); ?>"><?php echo htmlspecialchars($order['Status']); ?></span></p>
        <p><strong>Created At:</strong> <?php echo htmlspecialchars($order['CreatedAt']); ?></p>
    </div>

    <div class="order-items-section">
        <h3>Order Items</h3>
        <table class="order-items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_items as $item): ?>
                    <?php $subtotal = $item['Price'] * $item['Quantity']; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['ProductName']); ?></td>
                        <td>R<?php echo number_format($item['Price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($item['Quantity']); ?></td>
                        <td>R<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="order-total-summary">
        <p><strong>Calculated Total:</strong> R<?php echo number_format($calculated_total, 2); ?></p>
        <?php if (abs($calculated_total - $order['TotalPrice']) > 0.01): ?>
            <div class="price-discrepancy">
                <strong>Note:</strong> The calculated total (R<?php echo number_format($calculated_total, 2); ?>) 
                differs from the current total price (R<?php echo number_format($order['TotalPrice'], 2); ?>).
            </div>
        <?php endif; ?>
    </div>

    <form method="post" class="edit-order-form" novalidate>
        <div class="form-row">
            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" class="status-select" required>
                    <option value="">Select a status</option>
                    <option value="Pending"   <?= (($_POST['status'] ?? $order['Status']) == "Pending") ? "selected" : "" ?>>Pending</option>
                    <option value="confirmed" <?= (($_POST['status'] ?? $order['Status']) == "confirmed") ? "selected" : "" ?>>confirmed</option>
                    <option value="Completed" <?= (($_POST['status'] ?? $order['Status']) == "Completed") ? "selected" : "" ?>>Completed</option>
                    <option value="Cancelled" <?= (($_POST['status'] ?? $order['Status']) == "Cancelled") ? "selected" : "" ?>>Cancelled</option>
                </select>
                <small class="form-text">Required. Select the current order status.</small>
            </div>

            <div class="form-group">
                <label for="total_price">Total Price:</label>
                <input type="number" step="0.01" min="0" max="100000" name="total_price" id="total_price" class="total-price-input"
                       value="<?php echo htmlspecialchars($_POST['total_price'] ?? $order['TotalPrice']); ?>" 
                       required>
                <small class="form-text">Must be 0 or greater. Calculated total: R<?php echo number_format($calculated_total, 2); ?></small>
                <?php if (abs($calculated_total - ($_POST['total_price'] ?? $order['TotalPrice'])) > 0.01): ?>
                    <div class="price-discrepancy">
                        <strong>Warning:</strong> This price differs from the calculated total of R<?php echo number_format($calculated_total, 2); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="admin_dashboard.php?view=orders" class="btn btn-cancel">Cancel</a>
            <button type="submit" class="btn-update">Update Order</button>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
