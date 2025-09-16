<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: login.php");
    exit();
}

include 'db.php';
include 'header.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT cart.cart_id, products.name, products.price, cart.quantity, (products.price * cart.quantity) AS total
        FROM cart
        JOIN products ON cart.product_id = products.product_id
        WHERE cart.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$grand_total = 0;
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
    <title>Your Cart</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet"  media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="cart-header">
        <h1>Shopping Cart</h1>
    </div>

    <a href="homepage.php" class="button secondary">Back to homepage</a>

    <?php if ($result->num_rows > 0): ?>
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
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php $grand_total += $row['total']; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo number_format($row['price'], 2); ?></td>
                        <td>
                            <form method="POST" action="update_quantity.php" style="display:inline-flex; gap:5px;">
                                <input type="hidden" name="cart_id" value="<?php echo $row['cart_id']; ?>">
                                <input type="number" name="quantity" value="<?php echo $row['quantity']; ?>" min="1" required style="width: 60px;">
                                <button type="submit" class="button small">Update</button>
                            </form>
                        </td>
                        <td><?php echo number_format($row['total'], 2); ?></td>
                        <td><a href="remove_from_cart.php?cart_id=<?php echo $row['cart_id']; ?>" class="button danger">Remove</a></td>
                    </tr>
                <?php endwhile; ?>
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
