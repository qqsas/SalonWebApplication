<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['cart_item_id']) && isset($_POST['quantity'])) {
    $cart_item_id = intval($_POST['cart_item_id']);
    $new_quantity = max(1, intval($_POST['quantity'])); // minimum 1

    // Get product ID and stock for this cart item
    $stmt = $conn->prepare("
        SELECT ci.ProductID, p.Stock
        FROM CartItems ci
        JOIN Products p ON ci.ProductID = p.ProductID
        WHERE ci.CartItemID = ?
    ");
    $stmt->bind_param("i", $cart_item_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $product_id = $row['ProductID'];
        $stock = $row['Stock'];

        // Ensure quantity does not exceed stock
        if ($new_quantity > $stock) {
            $new_quantity = $stock;
        }

        // Update quantity in CartItems
        $update_stmt = $conn->prepare("UPDATE CartItems SET Quantity = ? WHERE CartItemID = ?");
        $update_stmt->bind_param("ii", $new_quantity, $cart_item_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    $stmt->close();

    // Redirect back to cart
    header("Location: cart.php");
    exit();
} else {
    echo "Invalid request.";
}
?>

