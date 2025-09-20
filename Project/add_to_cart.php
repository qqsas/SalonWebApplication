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

    // Get product stock
    $stock_stmt = $conn->prepare("SELECT Stock FROM Products WHERE ProductID = ?");
    $stock_stmt->bind_param("i", $product_id);
    $stock_stmt->execute();
    $stock_result = $stock_stmt->get_result();
    $product = $stock_result->fetch_assoc();
    if (!$product) {
        die("Product not found.");
    }

    $available_stock = $product['Stock'];
    if ($quantity_requested > $available_stock) {
        die("Requested quantity exceeds available stock.");
    }

    // Get or create cart for user
    $cart_stmt = $conn->prepare("SELECT CartID FROM Cart WHERE UserID = ?");
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
    $item_stmt = $conn->prepare("SELECT Quantity FROM CartItems WHERE CartID = ? AND ProductID = ?");
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

    // Redirect back
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "homepage.php";
    header("Location: " . $redirect);
    exit();
} else {
    echo "No product selected or quantity not specified.";
}
?>

