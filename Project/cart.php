<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
include 'header.php';

$user_id = $_SESSION['UserID'];

// Get user's cart
$cart_stmt = $conn->prepare("SELECT CartID FROM Cart WHERE UserID = ?");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

$grand_total = 0;
$cart_items = [];

if ($cart_result->num_rows > 0) {
    $cart = $cart_result->fetch_assoc();
    $cart_id = $cart['CartID'];

    // Get cart items and product details
    $items_stmt = $conn->prepare("SELECT ci.CartItemID, ci.Quantity, p.ProductID, p.Name, p.Price, p.ImgUrl, p.Stock, (ci.Quantity * p.Price) AS Total
                                  FROM CartItems ci
                                  JOIN Products p ON ci.ProductID = p.ProductID
                                  WHERE ci.CartID = ?");
    $items_stmt->bind_param("i", $cart_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $cart_items = $items_result->fetch_all(MYSQLI_ASSOC);

    foreach ($cart_items as $item) {
        $grand_total += $item['Total'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <style>
@media (min-width: 769px) {
      .menu-toggle {
        display: none !important;
      }
    }
    </style>
    <title>Your Cart - Kumar Kailey Hair & Beauty</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="cart-header">
        <h1>Shopping Cart</h1>
    </div>

    <a href="homepage.php" class="button secondary">Back to homepage</a>

    <?php if (!empty($cart_items)): ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price (R)</th>
                    <th>Quantity</th>
                    <th>Total (R)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['Name']); ?></td>
                        <td><?php echo number_format($item['Price'], 2); ?></td>
                        <td>
                            <form method="POST" action="update_quantity.php" style="display:inline-flex; gap:5px;">
                                <input type="hidden" name="cart_item_id" value="<?php echo $item['CartItemID']; ?>">
                                <input type="number" name="quantity" value="<?php echo $item['Quantity']; ?>" min="1" max="<?php echo $item['Stock']; ?>" required style="width: 60px;">
                                <button type="submit" class="button small">Update</button>
                            </form>
                        </td>
                        <td><?php echo number_format($item['Total'], 2); ?></td>
                        <td><a href="remove_from_cart.php?cart_item_id=<?php echo $item['CartItemID']; ?>" class="button danger">Remove</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="total-container">
            <h4>Total: R<?php echo number_format($grand_total, 2); ?></h4>
            <a href="payment.php?total=<?php echo number_format($grand_total, 2, '.', ''); ?>" class="button primary">Proceed to Payment</a>
        </div>
    <?php else: ?>
        <div class="alert">Your cart is empty.</div>
    <?php endif; ?>
</body>
</html>

