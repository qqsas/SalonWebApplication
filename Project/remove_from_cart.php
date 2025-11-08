<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

if (isset($_GET['cart_item_id'])) {
    $cart_item_id = intval($_GET['cart_item_id']);
    $user_id = $_SESSION['UserID'];

    // Ensure the cart item belongs to the logged-in user
    $stmt = $conn->prepare("
        SELECT ci.CartItemID
        FROM CartItems ci
        JOIN Cart c ON ci.CartID = c.CartID
        WHERE ci.CartItemID = ? AND c.UserID = ?
    ");
    $stmt->bind_param("ii", $cart_item_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Safe to delete
        $delete_stmt = $conn->prepare("DELETE FROM CartItems WHERE CartItemID = ?");
        $delete_stmt->bind_param("i", $cart_item_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }

    $stmt->close();

    // Redirect back to cart
    header("Location: cart.php");
    exit();
} else {
    echo "Invalid request.";
}
?>

