<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit;
}

$userID = $_SESSION['UserID'];

// Fetch cart items
$stmt = $conn->prepare("
    SELECT ci.CartItemID, p.ProductID, p.Name, p.Price, ci.Quantity, p.Stock, p.ImgUrl
    FROM CartItems ci
    JOIN Products p ON ci.ProductID = p.ProductID
    JOIN Cart c ON ci.CartID = c.CartID
    WHERE c.UserID = ?
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$cartItems = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = 0;
foreach ($cartItems as $item) {
    $total += $item['Price'] * $item['Quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Kumar Kailey Hair & Beauty</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .container { max-width: 900px; margin: auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .btn { padding: 10px 20px; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; }
        .btn:hover { opacity: 0.9; }
        .btn-reserve { background: #28a745; }
        .btn-cancel { background: #dc3545; }
        .btn-place { background: #007bff; }
        .info { background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-input, select { width: 100%; padding: 8px; }
        img.product-img { max-width: 80px; max-height: 80px; object-fit: contain; }
    </style>

    <script>
        function togglePaymentFields() {
            const method = document.getElementById('payment_method').value;
            document.getElementById('eft_section').style.display = (method === 'EFT') ? 'block' : 'none';
            document.getElementById('card_section').style.display = (method === 'Card') ? 'block' : 'none';
            document.getElementById('paypal_section').style.display = (method === 'PayPal') ? 'block' : 'none';
            document.getElementById('stripe_section').style.display = (method === 'Stripe') ? 'block' : 'none';
        }

        function validateForm(event) {
            const method = document.getElementById('payment_method').value;

            if (method === "Card") {
                const cardNumber = document.getElementById('card_number').value.trim();
                const expiry = document.getElementById('card_expiry').value.trim();
                const cvv = document.getElementById('card_cvv').value.trim();
                const cardPattern = /^\d{16}$/;
                const expiryPattern = /^(0[1-9]|1[0-2])\/\d{2}$/;
                const cvvPattern = /^\d{3}$/;
                if (!cardPattern.test(cardNumber)) { alert("Enter a valid 16-digit card number."); event.preventDefault(); return false; }
                if (!expiryPattern.test(expiry)) { alert("Enter expiry in MM/YY."); event.preventDefault(); return false; }
                if (!cvvPattern.test(cvv)) { alert("Enter 3-digit CVV."); event.preventDefault(); return false; }
            }

            if (method === "EFT") {
                const eftRef = document.getElementById('eft_reference').value.trim();
                if (eftRef === "") { alert("Enter EFT reference number."); event.preventDefault(); return false; }
            }

            if (method === "PayPal") {
                const paypalEmail = document.getElementById('paypal_email').value.trim();
                if (paypalEmail === "") { alert("Enter your PayPal email."); event.preventDefault(); return false; }
            }

            if (method === "Stripe") {
                const stripeToken = document.getElementById('stripe_token').value.trim();
                if (stripeToken === "") { alert("Enter Stripe token."); event.preventDefault(); return false; }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('payment_method').addEventListener('change', togglePaymentFields);
            document.querySelector('form#online_payment_form').addEventListener('submit', validateForm);
            togglePaymentFields();
        });
    </script>
</head>
<body>
<div class="container">
    <h2>Checkout</h2>

    <?php if (empty($cartItems)): ?>
        <p>Your cart is empty. <a href="products.php" class="btn btn-place">Browse Products</a></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Image</th>
                    <th>Price (R)</th>
                    <th>Quantity</th>
                    <th>Subtotal (R)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['Name']); ?></td>
                        <td>
                            <?php if ($item['ImgUrl']): ?>
                                <img src="<?php echo htmlspecialchars($item['ImgUrl']); ?>" class="product-img" alt="Product Image">
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($item['Price'],2); ?></td>
                        <td><?php echo $item['Quantity']; ?></td>
                        <td><?php echo number_format($item['Price']*$item['Quantity'],2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4" style="text-align:right;"><strong>Total:</strong></td>
                    <td><strong>R<?php echo number_format($total,2); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="info">
            <p>You can reserve your items for <strong>in-person pickup</strong> or pay online using one of the available payment methods.</p>
        </div>

        <!-- Reserve In-Person -->
        <form method="POST" action="place_order.php" style="margin-bottom:20px;">
            <input type="hidden" name="reserve_only" value="1">
            <button type="submit" class="btn btn-reserve">Reserve In-Store</button>
            <a href="cart.php" class="btn btn-cancel">Cancel</a>
        </form>

        <!-- Online Payment Form -->
        <form method="POST" action="place_order.php" id="online_payment_form">
            <div class="form-group">
                <label for="payment_method" class="form-label">Payment Method</label>
                <select name="payment_method" id="payment_method" class="form-input" required>
                    <option value="">Select</option>
                    <option value="EFT">EFT</option>
                    <option value="Card">Card</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Stripe">Stripe</option>
                </select>
            </div>

            <!-- EFT -->
            <div id="eft_section" style="display:none;">
                <div class="form-group">
                    <label for="eft_reference" class="form-label">EFT Reference Number</label>
                    <input type="text" name="eft_reference" id="eft_reference" class="form-input" pattern="\d+" inputmode="numeric">
                </div>
            </div>

            <!-- Card -->
            <div id="card_section" style="display:none;">
                <div class="form-group">
                    <label for="card_number" class="form-label">Card Number</label>
                    <input type="text" name="card_number" id="card_number" class="form-input" maxlength="16" pattern="\d{16}" inputmode="numeric">
                </div>
                <div class="form-group">
                    <label for="card_expiry" class="form-label">Expiry (MM/YY)</label>
                    <input type="text" name="card_expiry" id="card_expiry" class="form-input" maxlength="5" pattern="(0[1-9]|1[0-2])\/\d{2}">
                </div>
                <div class="form-group">
                    <label for="card_cvv" class="form-label">CVV</label>
                    <input type="text" name="card_cvv" id="card_cvv" class="form-input" maxlength="3" pattern="\d{3}" inputmode="numeric">
                </div>
            </div>

            <!-- PayPal -->
            <div id="paypal_section" style="display:none;">
                <div class="form-group">
                    <label for="paypal_email" class="form-label">PayPal Email</label>
                    <input type="email" name="paypal_email" id="paypal_email" class="form-input">
                </div>
            </div>

            <!-- Stripe -->
            <div id="stripe_section" style="display:none;">
                <div class="form-group">
                    <label for="stripe_token" class="form-label">Stripe Token</label>
                    <input type="text" name="stripe_token" id="stripe_token" class="form-input">
                </div>
            </div>

            <button type="submit" class="btn btn-place">Place Order</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

