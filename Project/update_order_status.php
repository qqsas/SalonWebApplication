<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // Include the mail functions

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderID = intval($_POST['OrderID']);
    $status = $_POST['Status'] ?? '';
    $redirect = $_POST['redirect'] ?? 'admin_dashboard.php?view=orders';

    if ($orderID > 0 && in_array($status, ['Pending','Processing','Completed','Cancelled'])) {
        
        // Start transaction for data consistency
        $conn->begin_transaction();
        
        try {
            // Get current order status and user details first
            $checkStmt = $conn->prepare("
                SELECT o.*, u.Email, u.Name as UserName 
                FROM Orders o 
                JOIN User u ON o.UserID = u.UserID 
                WHERE o.OrderID = ?
            ");
            $checkStmt->bind_param("i", $orderID);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $currentOrder = $result->fetch_assoc();
            $checkStmt->close();
            
            if (!$currentOrder) {
                throw new Exception("Order not found");
            }
            
            $oldStatus = $currentOrder['Status'] ?? '';
            
            // Update order status
            $stmt = $conn->prepare("UPDATE Orders SET Status = ? WHERE OrderID = ?");
            $stmt->bind_param("si", $status, $orderID);
            $stmt->execute();
            $stmt->close();
            
            // Handle stock management based on status changes
            if ($status === 'Completed' && $oldStatus !== 'Completed') {
                // Deduct stock when order is marked as completed
                deductStockFromOrder($conn, $orderID);
            } elseif ($oldStatus === 'Completed' && $status !== 'Completed') {
                // Restore stock when order is no longer completed
                restoreStockFromOrder($conn, $orderID);
            }
            
            // Send email notification to customer
            sendOrderStatusEmail($currentOrder, $status, $oldStatus);
            
            // Commit transaction if everything succeeded
            $conn->commit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            header("Location: $redirect&message=Error+updating+order:+".urlencode($e->getMessage())."&success=0");
            exit();
        }
    }

    header("Location: $redirect&message=Order+status+updated+successfully&success=1");
    exit();
} else {
    header("Location: admin_dashboard.php?view=orders");
    exit();
}

/**
 * Deduct stock for all items in an order when it's completed
 */
function deductStockFromOrder($conn, $order_id) {
    // Get all order items with their quantities
    $stmt = $conn->prepare("
        SELECT oi.ProductID, oi.Quantity, p.Stock, p.Name 
        FROM OrderItems oi 
        JOIN Products p ON oi.ProductID = p.ProductID 
        WHERE oi.OrderID = ? AND oi.IsDeleted = 0
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($order_items as $item) {
        $new_stock = $item['Stock'] - $item['Quantity'];
        
        // Prevent negative stock
        if ($new_stock < 0) {
            throw new Exception("Insufficient stock for '{$item['Name']}'. Available: {$item['Stock']}, Requested: {$item['Quantity']}");
        }

        // Update product stock
        $updateStmt = $conn->prepare("UPDATE Products SET Stock = ? WHERE ProductID = ?");
        $updateStmt->bind_param("ii", $new_stock, $item['ProductID']);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update stock for '{$item['Name']}'");
        }
        $updateStmt->close();
    }
}

/**
 * Restore stock for all items in an order when it's no longer completed
 */
function restoreStockFromOrder($conn, $order_id) {
    // Get all order items with their quantities
    $stmt = $conn->prepare("
        SELECT oi.ProductID, oi.Quantity, p.Stock, p.Name 
        FROM OrderItems oi 
        JOIN Products p ON oi.ProductID = p.ProductID 
        WHERE oi.OrderID = ? AND oi.IsDeleted = 0
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($order_items as $item) {
        $new_stock = $item['Stock'] + $item['Quantity'];
        
        // Update product stock
        $updateStmt = $conn->prepare("UPDATE Products SET Stock = ? WHERE ProductID = ?");
        $updateStmt->bind_param("ii", $new_stock, $item['ProductID']);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to restore stock for '{$item['Name']}'");
        }
        $updateStmt->close();
    }
}

/**
 * Send email notification for order status change
 */
function sendOrderStatusEmail($order, $newStatus, $oldStatus) {
    $userEmail = $order['Email'];
    $userName = $order['UserName'];
    $orderID = $order['OrderID'];
    $orderDate = date('F j, Y', strtotime($order['CreatedAt']));
    $totalAmount = number_format($order['TotalPrice'], 2);
    
    // Map status to friendly names and colors
    $statusInfo = [
        'Pending' => ['name' => 'Pending', 'color' => '#FFA500', 'icon' => '⏳'],
        'Processing' => ['name' => 'Processing', 'color' => '#007BFF', 'icon' => '🔄'],
        'Completed' => ['name' => 'Completed', 'color' => '#28A745', 'icon' => '✅'],
        'Cancelled' => ['name' => 'Cancelled', 'color' => '#DC3545', 'icon' => '❌']
    ];
    
    $statusName = $statusInfo[$newStatus]['name'] ?? $newStatus;
    $statusColor = $statusInfo[$newStatus]['color'] ?? '#6C757D';
    $statusIcon = $statusInfo[$newStatus]['icon'] ?? '📦';
    
    $subject = "Order #{$orderID} Status Update - {$statusName} {$statusIcon}";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
            .status-badge { 
                display: inline-block; 
                padding: 10px 20px; 
                background: {$statusColor}; 
                color: white; 
                border-radius: 25px; 
                font-weight: bold; 
                margin: 15px 0; 
                font-size: 16px;
            }
            .order-details { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .order-details table { width: 100%; border-collapse: collapse; }
            .order-details td { padding: 8px 0; border-bottom: 1px solid #dee2e6; }
            .order-details td:first-child { font-weight: bold; width: 40%; }
            .status-update { 
                background: #e7f3ff; 
                padding: 15px; 
                border-left: 4px solid #007BFF; 
                margin: 15px 0; 
            }
            .footer { 
                margin-top: 20px; 
                padding-top: 20px; 
                border-top: 1px solid #dee2e6; 
                text-align: center; 
                color: #6c757d; 
                font-size: 14px; 
            }
            .next-steps { background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .cancellation-notice { background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Order Status Update</h2>
                <p>Order #{$orderID}</p>
            </div>
            
            <p>Hello {$userName},</p>
            
            <p>The status of your order has been updated:</p>
            
            <div style='text-align: center;'>
                <div class='status-badge'>
                    {$statusIcon} {$statusName}
                </div>
            </div>
            
            <div class='order-details'>
                <h3>Order Summary:</h3>
                <table>
                    <tr>
                        <td>Order Number:</td>
                        <td>#{$orderID}</td>
                    </tr>
                    <tr>
                        <td>Order Date:</td>
                        <td>{$orderDate}</td>
                    </tr>
                    <tr>
                        <td>Total Amount:</td>
                        <td>\${$totalAmount}</td>
                    </tr>
                    <tr>
                        <td>Previous Status:</td>
                        <td>{$oldStatus}</td>
                    </tr>
                    <tr>
                        <td>Current Status:</td>
                        <td><strong>{$statusName}</strong></td>
                    </tr>
                </table>
            </div>";
    
    // Add status-specific information and next steps
    switch($newStatus) {
        case 'Processing':
            $htmlBody .= "
            <div class='status-update'>
                <h4>📦 Your Order is Being Processed</h4>
                <p>We've received your order and our team is now preparing your items for shipment.</p>
                <p><strong>What to expect next:</strong></p>
                <ul>
                    <li>We'll notify you when your order ships</li>
                    <li>You'll receive tracking information</li>
                    <li>Expected processing time: 1-2 business days</li>
                </ul>
            </div>";
            break;
            
        case 'Completed':
            $htmlBody .= "
            <div class='status-update'>
                <h4>🎉 Order Completed!</h4>
                <p>Your order has been successfully completed and delivered.</p>
                <p>We hope you're enjoying your products! If you have any questions about your items or need assistance, please don't hesitate to contact us.</p>
            </div>
            <div class='next-steps'>
                <p><strong>Share Your Experience:</strong> We'd love to hear your feedback about our products and service.</p>
            </div>";
            break;
            
        case 'Cancelled':
            $htmlBody .= "
            <div class='status-update'>
                <h4>Order Cancelled</h4>
                <p>Your order has been cancelled.</p>
            </div>
            <div class='cancellation-notice'>
                <p><strong>Refund Information:</strong> If you paid for this order, your refund will be processed automatically and should appear in your account within 5-7 business days.</p>
                <p>If you didn't request this cancellation or have any questions, please contact our support team immediately.</p>
            </div>";
            break;
            
        default:
            $htmlBody .= "
            <div class='status-update'>
                <p>We're currently handling your order. You'll receive another update when there are changes to your order status.</p>
                <p>If you have any questions about this status update, please contact our customer service team.</p>
            </div>";
    }
    
    $htmlBody .= "
            <p>Thank you for shopping with us!</p>
            <p>Best regards,<br>The Barber Shop Team</p>
            
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
                <p>If you have any questions about your order, please contact our customer service team or visit your account dashboard.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Optional: Add BCC for admin notifications
    $bcc = ['orders@yourbarbershop.com'];
    
    // Send the email
    return sendEmail($userEmail, $subject, $htmlBody, $bcc);
}
?>
