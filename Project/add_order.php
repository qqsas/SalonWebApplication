<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Validate session and role
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // PHPMailer setup
include 'header.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$maxProducts = 20; // Limit number of products per order

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Security token validation failed. Please try again.";
    } else {
        // Validate and sanitize inputs
        $userID = filter_var($_POST['UserID'] ?? 0, FILTER_VALIDATE_INT);
        $status = trim($_POST['Status'] ?? 'Pending');
        $productIDs = $_POST['ProductID'] ?? [];
        $quantities = $_POST['Quantity'] ?? [];

        // User validation
        if ($userID === false || $userID <= 0) {
            $errors[] = "Please select a valid user.";
        } else {
            // Verify user exists and is active
            $userCheck = $conn->prepare("SELECT UserID, Name, Email FROM User WHERE UserID = ? AND IsDeleted = 0");
            if ($userCheck) {
                $userCheck->bind_param("i", $userID);
                $userCheck->execute();
                $userResult = $userCheck->get_result();
                if ($userResult->num_rows === 0) {
                    $errors[] = "Selected user does not exist or has been deleted.";
                } else {
                    $userInfo = $userResult->fetch_assoc();
                }
                $userCheck->close();
            } else {
                $errors[] = "Database error while verifying user.";
                error_log("User check prepare error: " . $conn->error);
            }
        }

        // Status validation
        $allowedStatuses = ['Pending', 'Completed', 'Cancelled', 'Processing', 'Shipped'];
        if (!in_array($status, $allowedStatuses)) {
            $errors[] = "Invalid order status selected.";
        }

        // Products validation
        if (empty($productIDs) || empty($quantities)) {
            $errors[] = "Please select at least one product.";
        } elseif (count($productIDs) !== count($quantities)) {
            $errors[] = "Product and quantity arrays do not match.";
        } elseif (count($productIDs) > $maxProducts) {
            $errors[] = "Maximum {$maxProducts} products per order allowed.";
        } else {
            // Validate each product and quantity
            $validProducts = [];
            $totalQuantity = 0;
            
            foreach ($productIDs as $index => $productID) {
                $quantity = $quantities[$index] ?? 0;
                
                // Validate product ID
                $productID = filter_var($productID, FILTER_VALIDATE_INT);
                if ($productID === false || $productID <= 0) {
                    $errors[] = "Invalid product selected.";
                    break;
                }
                
                // Validate quantity
                $quantity = filter_var($quantity, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 1000]
                ]);
                if ($quantity === false) {
                    $errors[] = "Quantity must be a positive number between 1 and 1000.";
                    break;
                }
                
                $totalQuantity += $quantity;
                
                // Check for duplicate products
                if (in_array($productID, array_column($validProducts, 'id'))) {
                    $errors[] = "Duplicate products are not allowed. Please adjust quantities instead.";
                    break;
                }
                
                $validProducts[] = ['id' => $productID, 'quantity' => $quantity];
            }
            
            // Validate total quantity
            if ($totalQuantity > 10000) {
                $errors[] = "Total order quantity cannot exceed 10,000 items.";
            }
            
            // Verify products exist and have sufficient stock
            if (empty($errors)) {
                $placeholders = str_repeat('?,', count($validProducts) - 1) . '?';
                $productCheck = $conn->prepare("
                    SELECT ProductID, Name, Price, Stock 
                    FROM Products 
                    WHERE ProductID IN ($placeholders) AND IsDeleted = 0
                ");
                
                if ($productCheck) {
                    $productIDsOnly = array_column($validProducts, 'id');
                    $productCheck->bind_param(str_repeat('i', count($productIDsOnly)), ...$productIDsOnly);
                    $productCheck->execute();
                    $productResult = $productCheck->get_result();
                    $availableProducts = [];
                    
                    while ($product = $productResult->fetch_assoc()) {
                        $availableProducts[$product['ProductID']] = $product;
                    }
                    
                    // Check if all products exist and have sufficient stock
                    foreach ($validProducts as $item) {
                        if (!isset($availableProducts[$item['id']])) {
                            $errors[] = "One or more selected products are unavailable or have been deleted.";
                            break;
                        }
                        
                        $product = $availableProducts[$item['id']];
                        if ($product['Stock'] < $item['quantity']) {
                            $errors[] = "Insufficient stock for {$product['Name']}. Available: {$product['Stock']}, Requested: {$item['quantity']}";
                        }
                    }
                    
                    $productCheck->close();
                } else {
                    $errors[] = "Database error while verifying products.";
                    error_log("Product check prepare error: " . $conn->error);
                }
            }
        }

        // Process order if no errors
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // Calculate total price
                $totalPrice = 0;
                foreach ($validProducts as $item) {
                    $product = $availableProducts[$item['id']];
                    $totalPrice += $product['Price'] * $item['quantity'];
                }
                
                // Insert into Orders table
                $orderStmt = $conn->prepare("INSERT INTO Orders (UserID, TotalPrice, Status) VALUES (?, ?, ?)");
                if (!$orderStmt) {
                    throw new Exception("Failed to prepare order insert: " . $conn->error);
                }
                
                $orderStmt->bind_param("ids", $userID, $totalPrice, $status);
                if (!$orderStmt->execute()) {
                    throw new Exception("Failed to insert order: " . $orderStmt->error);
                }
                
                $orderID = $conn->insert_id;
                $orderStmt->close();

                // Insert each product into OrderItems table and update stock
                $itemStmt = $conn->prepare("INSERT INTO OrderItems (OrderID, ProductID, Quantity) VALUES (?, ?, ?)");
                $stockStmt = $conn->prepare("UPDATE Products SET Stock = Stock - ? WHERE ProductID = ? AND IsDeleted = 0");
                
                if (!$itemStmt || !$stockStmt) {
                    throw new Exception("Failed to prepare order items statements: " . $conn->error);
                }
                
                foreach ($validProducts as $item) {
                    // Insert order item
                    $itemStmt->bind_param("iii", $orderID, $item['id'], $item['quantity']);
                    if (!$itemStmt->execute()) {
                        throw new Exception("Failed to insert order item: " . $itemStmt->error);
                    }
                    
                    // Update product stock
                    $stockStmt->bind_param("ii", $item['quantity'], $item['id']);
                    if (!$stockStmt->execute()) {
                        throw new Exception("Failed to update product stock: " . $stockStmt->error);
                    }
                    
                    // Check if update affected any rows
                    if ($stockStmt->affected_rows === 0) {
                        throw new Exception("Failed to update stock for product ID: " . $item['id']);
                    }
                }
                
                $itemStmt->close();
                $stockStmt->close();
                
                $conn->commit();
                $success = "Order #{$orderID} with " . count($validProducts) . " items added successfully!";
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // --- Send confirmation email ---
                if (isset($userInfo)) {
                    $mail = getMailer(); // PHPMailer object
                    
                    try {
                        $mail->addAddress($userInfo['Email'], $userInfo['Name']);
                        $mail->isHTML(true);
                        $mail->Subject = "Order Confirmation - Order #$orderID";
                        
                        // Build items list with prices
                        $itemsList = "";
                        $emailTotal = 0;
                        foreach ($validProducts as $item) {
                            $product = $availableProducts[$item['id']];
                            $itemTotal = $product['Price'] * $item['quantity'];
                            $emailTotal += $itemTotal;
                            $itemsList .= "<tr>
                                <td>{$product['Name']}</td>
                                <td>{$item['quantity']}</td>
                                <td>R " . number_format($product['Price'], 2) . "</td>
                                <td>R " . number_format($itemTotal, 2) . "</td>
                            </tr>";
                        }
                        
                        $mail->Body = "
                            <h2>Order Confirmation</h2>
                            <p>Dear {$userInfo['Name']},</p>
                            <p>Your order has been successfully placed with the following items:</p>
                            <table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>
                                <thead>
                                    <tr style='background-color: #f8f9fa;'>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    $itemsList
                                </tbody>
                                <tfoot>
                                    <tr style='background-color: #f8f9fa; font-weight: bold;'>
                                        <td colspan='3' style='text-align: right;'>Grand Total:</td>
                                        <td>R " . number_format($emailTotal, 2) . "</td>
                                    </tr>
                                </tfoot>
                            </table>
                            <p><strong>Order Status:</strong> {$status}</p>
                            <p><strong>Order ID:</strong> #{$orderID}</p>
                            <p>Thank you for shopping with us!</p>
                        ";
                        
                        $mail->AltBody = "Your order #$orderID has been placed. Total: R " . number_format($emailTotal, 2) . ". Status: {$status}";
                        $mail->send();
                        
                    } catch (Exception $e) {
                        error_log("Mail Error for Order #{$orderID}: {$mail->ErrorInfo}");
                        // Don't show email error to user, just log it
                    }
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Failed to create order: " . $e->getMessage();
                error_log("Order creation error: " . $e->getMessage());
            }
        }
    }
}

// Fetch users and products for dropdown
$users = $conn->query("SELECT UserID, Name, Email FROM User WHERE IsDeleted = 0 ORDER BY Name");
$products = $conn->query("SELECT ProductID, Name, Price, Stock FROM Products WHERE IsDeleted = 0 AND Stock > 0 ORDER BY Name");

if (!$users || !$products) {
    if (!$users) error_log("Users query error: " . $conn->error);
    if (!$products) error_log("Products query error: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Order - Admin</title>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .error { 
            background: #ffebee; 
            color: #c62828; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #c62828;
            margin-bottom: 15px;
        }
        .success { 
            background: #e8f5e8; 
            color: #2e7d32; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #2e7d32;
            margin-bottom: 15px;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        select, input, button, .btn { 
            width: 100%; 
            padding: 10px; 
            margin: 5px 0; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        button { 
            background: #4CAF50; 
            color: white; 
            border: none; 
            cursor: pointer; 
            font-size: 16px;
        }
        button:hover { 
            background: #45a049; 
        }
        .btn { 
            display: inline-block; 
            text-align: center; 
            background: #6c757d; 
            color: white; 
            text-decoration: none; 
        }
        .btn:hover { 
            background: #5a6268; 
        }
        .product-row { 
            border: 1px solid #e0e0e0; 
            padding: 15px; 
            margin-bottom: 15px; 
            border-radius: 4px; 
            background: #fafafa;
        }
        .remove-product { 
            background: #f44336; 
            color: white; 
            border: none; 
            padding: 5px 10px; 
            border-radius: 3px; 
            cursor: pointer; 
            margin-top: 10px;
        }
        .remove-product:hover { 
            background: #d32f2f; 
        }
        .add-product-btn { 
            background: #2196F3; 
            color: white; 
            border: none; 
            padding: 10px; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-bottom: 20px;
        }
        .add-product-btn:hover { 
            background: #1976D2; 
        }
        .add-product-btn:disabled { 
            background: #ccc; 
            cursor: not-allowed;
        }
        .product-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .stock-warning {
            color: #f44336;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="form-container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Add New Order</h2>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>Please correct the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="orderForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <div class="form-group">
            <label for="UserID">Customer:</label>
            <select name="UserID" id="UserID" required>
                <option value="">-- Select Customer --</option>
                <?php if ($users): while($user = $users->fetch_assoc()): ?>
                    <option value="<?= $user['UserID'] ?>" <?= ($userID ?? '') == $user['UserID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['Name']) ?> (<?= htmlspecialchars($user['Email']) ?>)
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <h3>Order Items</h3>
        <div id="productContainer">
            <div class="product-row">
                <div class="form-group">
                    <label>Product:</label>
                    <select name="ProductID[]" class="product-select" required>
                        <option value="">-- Select Product --</option>
                        <?php if ($products): 
                            $products->data_seek(0); 
                            while($product = $products->fetch_assoc()): ?>
                            <option value="<?= $product['ProductID'] ?>" 
                                    data-price="<?= $product['Price'] ?>" 
                                    data-stock="<?= $product['Stock'] ?>">
                                <?= htmlspecialchars($product['Name']) ?> - 
                                R <?= number_format($product['Price'], 2) ?> 
                                (Stock: <?= $product['Stock'] ?>)
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <div class="product-info">
                        Price: <span class="product-price">R 0.00</span> | 
                        Stock: <span class="product-stock">0</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" name="Quantity[]" class="quantity-input" min="1" max="1000" value="1" required>
                    <div class="product-info">
                        Total: <span class="item-total">R 0.00</span>
                    </div>
                </div>
                
                <button type="button" class="remove-product" onclick="removeProductRow(this)" style="display: none;">Remove</button>
            </div>
        </div>

        <button type="button" class="add-product-btn" id="addProductBtn" onclick="addProductRow()">+ Add Another Product</button>

        <div class="form-group">
            <label for="Status">Order Status:</label>
            <select name="Status" id="Status" required>
                <option value="Pending" <?= ($status ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Processing" <?= ($status ?? '') === 'Processing' ? 'selected' : '' ?>>Processing</option>
                <option value="Completed" <?= ($status ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= ($status ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="Shipped" <?= ($status ?? '') === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
            </select>
        </div>

        <div class="form-group">
            <div style="background: #e8f5e8; padding: 15px; border-radius: 4px;">
                <strong>Order Summary:</strong><br>
                Total Items: <span id="totalItems">1</span><br>
                Grand Total: <span id="grandTotal">R 0.00</span>
            </div>
        </div>

        <div class="button-group">
            <button type="submit" id="submitBtn">Create Order</button>
            <a href="admin_dashboard.php" class="btn" style="text-align: center;">Cancel</a>
        </div>
    </form>
</div>

<script>
let maxProducts = <?= $maxProducts ?>;
let productOptions = '';

// Store product options for cloning
document.addEventListener('DOMContentLoaded', function() {
    const firstSelect = document.querySelector('.product-select');
    if (firstSelect) {
        productOptions = firstSelect.innerHTML;
    }
    updateProductInfo(document.querySelector('.product-row'));
    updateOrderSummary();
});

function addProductRow() {
    const container = document.getElementById('productContainer');
    const currentRows = container.querySelectorAll('.product-row').length;
    
    if (currentRows >= maxProducts) {
        alert(`Maximum ${maxProducts} products per order allowed.`);
        return;
    }
    
    const newRow = document.querySelector('.product-row').cloneNode(true);
    
    // Clear values
    const select = newRow.querySelector('.product-select');
    select.innerHTML = productOptions;
    select.selectedIndex = 0;
    
    const quantity = newRow.querySelector('.quantity-input');
    quantity.value = 1;
    
    // Show remove button for new rows (hide for first row)
    newRow.querySelector('.remove-product').style.display = 'block';
    
    container.appendChild(newRow);
    
    // Add event listeners to new row
    addRowEventListeners(newRow);
    updateOrderSummary();
    updateAddButton();
}

function removeProductRow(button) {
    const row = button.closest('.product-row');
    const container = document.getElementById('productContainer');
    const rows = container.querySelectorAll('.product-row');
    
    if (rows.length > 1) {
        row.remove();
        updateOrderSummary();
        updateAddButton();
    }
}

function addRowEventListeners(row) {
    const select = row.querySelector('.product-select');
    const quantity = row.querySelector('.quantity-input');
    
    select.addEventListener('change', function() {
        updateProductInfo(row);
        updateOrderSummary();
    });
    
    quantity.addEventListener('input', function() {
        updateProductInfo(row);
        updateOrderSummary();
    });
}

function updateProductInfo(row) {
    const select = row.querySelector('.product-select');
    const selectedOption = select.options[select.selectedIndex];
    const priceSpan = row.querySelector('.product-price');
    const stockSpan = row.querySelector('.product-stock');
    const quantityInput = row.querySelector('.quantity-input');
    const itemTotalSpan = row.querySelector('.item-total');
    
    if (selectedOption.value) {
        const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        const quantity = parseInt(quantityInput.value) || 0;
        const itemTotal = price * quantity;
        
        priceSpan.textContent = 'R ' + price.toFixed(2);
        stockSpan.textContent = stock;
        itemTotalSpan.textContent = 'R ' + itemTotal.toFixed(2);
        
        // Validate stock
        if (quantity > stock) {
            quantityInput.style.borderColor = '#f44336';
            itemTotalSpan.className = 'item-total stock-warning';
        } else {
            quantityInput.style.borderColor = '';
            itemTotalSpan.className = 'item-total';
        }
        
        // Set max quantity based on stock
        quantityInput.max = Math.min(stock, 1000);
    } else {
        priceSpan.textContent = 'R 0.00';
        stockSpan.textContent = '0';
        itemTotalSpan.textContent = 'R 0.00';
    }
}

function updateOrderSummary() {
    const rows = document.querySelectorAll('.product-row');
    let totalItems = 0;
    let grandTotal = 0;
    
    rows.forEach(row => {
        const select = row.querySelector('.product-select');
        const quantity = parseInt(row.querySelector('.quantity-input').value) || 0;
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value) {
            const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            totalItems += quantity;
            grandTotal += price * quantity;
        }
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('grandTotal').textContent = 'R ' + grandTotal.toFixed(2);
}

function updateAddButton() {
    const currentRows = document.querySelectorAll('.product-row').length;
    const addButton = document.getElementById('addProductBtn');
    
    if (currentRows >= maxProducts) {
        addButton.disabled = true;
        addButton.title = `Maximum ${maxProducts} products allowed`;
    } else {
        addButton.disabled = false;
        addButton.title = '';
    }
}

// Add event listeners to initial row
document.querySelectorAll('.product-row').forEach(row => {
    addRowEventListeners(row);
});

// Form submission handling
document.getElementById('orderForm').addEventListener('submit', function() {
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = 'Creating Order...';
});

// Initialize
updateAddButton();
</script>

</body>
</html>
