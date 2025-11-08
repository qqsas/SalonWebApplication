<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderID = intval($_POST['OrderID']);
    $status = $_POST['Status'] ?? '';
    $redirect = $_POST['redirect'] ?? 'admin_dashboard.php?view=orders';

    if ($orderID > 0 && in_array($status, ['Pending','Processing','Completed','Cancelled'])) {
        
        // Start transaction for data consistency
        $conn->begin_transaction();
        
        try {
            // Get current order status first
            $checkStmt = $conn->prepare("SELECT Status FROM Orders WHERE OrderID = ?");
            $checkStmt->bind_param("i", $orderID);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $currentOrder = $result->fetch_assoc();
            $checkStmt->close();
            
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
?>
