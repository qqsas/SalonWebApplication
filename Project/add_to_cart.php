<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

if (isset($_POST['product_id']) && isset($_POST['quantity'])) {
    $product_id = intval($_POST['product_id']);
    $quantity_requested = max(1, intval($_POST['quantity']));
    $user_id = $_SESSION['UserID'];
    
    // Start transaction for atomic operations
    $conn->begin_transaction();
    
    try {
        // Get product stock with FOR UPDATE to lock the row
        $stock_stmt = $conn->prepare("SELECT Stock FROM Products WHERE ProductID = ? AND IsDeleted = 0 FOR UPDATE");
        $stock_stmt->bind_param("i", $product_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();
        $product = $stock_result->fetch_assoc();
        
        if (!$product) {
            throw new Exception("Product not found or has been removed.");
        }

        $available_stock = $product['Stock'];
        
        if ($quantity_requested > $available_stock) {
            throw new Exception("Requested quantity exceeds available stock. Only {$available_stock} items available.");
        }

        // Get or create cart for user
        $cart_stmt = $conn->prepare("SELECT CartID FROM Cart WHERE UserID = ? AND IsDeleted = 0");
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();

        if ($cart_result->num_rows > 0) {
            $cart = $cart_result->fetch_assoc();
            $cart_id = $cart['CartID'];
        } else {
            // Create new cart
            $create_cart_stmt = $conn->prepare("INSERT INTO Cart (UserID) VALUES (?)");
            $create_cart_stmt->bind_param("i", $user_id);
            $create_cart_stmt->execute();
            $cart_id = $conn->insert_id;
        }

        // Check if item already exists in cart
        $item_stmt = $conn->prepare("SELECT Quantity FROM CartItems WHERE CartID = ? AND ProductID = ? AND IsDeleted = 0");
        $item_stmt->bind_param("ii", $cart_id, $product_id);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();

        if ($item_result->num_rows > 0) {
            $existing_item = $item_result->fetch_assoc();
            $new_quantity = $existing_item['Quantity'] + $quantity_requested;

            if ($new_quantity > $available_stock) {
                $new_quantity = $available_stock; // cap at stock
            }

            $update_stmt = $conn->prepare("UPDATE CartItems SET Quantity = ? WHERE CartID = ? AND ProductID = ?");
            $update_stmt->bind_param("iii", $new_quantity, $cart_id, $product_id);
            $update_stmt->execute();
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO CartItems (CartID, ProductID, Quantity) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iii", $cart_id, $product_id, $quantity_requested);
            $insert_stmt->execute();
        }

        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['cart_message'] = "Product added to cart successfully!";
        $_SESSION['message_type'] = "success";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['cart_message'] = $e->getMessage();
        $_SESSION['message_type'] = "error";
    }

    // Redirect back
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "index.php";
    header("Location: " . $redirect);
    exit();
    
} else {
    $_SESSION['cart_message'] = "No product selected or quantity not specified.";
    $_SESSION['message_type'] = "error";
    header("Location: " . (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "index.php"));
    exit();
}
?>
