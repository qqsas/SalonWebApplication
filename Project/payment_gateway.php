<?php
session_start();
include 'db.php';
include 'header.php';

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
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script>
        function togglePaymentFields() {
            const method = document.getElementById('payment_method').value;
            document.getElementById('eft_section').style.display = (method === 'EFT') ? 'block' : 'none';
            document.getElementById('card_section').style.display = (method === 'Card') ? 'block' : 'none';
        }

        function validateForm(event) {
            const method = document.getElementById('payment_method').value;

            if (method === "Card") {
                const cardHolder = document.getElementById('card_holder').value.trim();
                const cardNumber = document.getElementById('card_number').value.trim();
                const expiry = document.getElementById('card_expiry').value.trim();
                const cvv = document.getElementById('card_cvv').value.trim();
                
                const cardPattern = /^\d{16}$/;
                const expiryPattern = /^(0[1-9]|1[0-2])\/\d{2}$/;
                const cvvPattern = /^\d{3}$/;
                const namePattern = /^[a-zA-Z\s]{2,50}$/;

                if (!namePattern.test(cardHolder)) { 
                    alert("Enter valid cardholder name (letters and spaces only, 2-50 characters)."); 
                    event.preventDefault(); 
                    return false; 
                }
                if (!cardPattern.test(cardNumber)) { 
                    alert("Enter a valid 16-digit card number."); 
                    event.preventDefault(); 
                    return false; 
                }
                if (!expiryPattern.test(expiry)) { 
                    alert("Enter expiry in MM/YY format."); 
                    event.preventDefault(); 
                    return false; 
                }
                if (!cvvPattern.test(cvv)) { 
                    alert("Enter 3-digit CVV."); 
                    event.preventDefault(); 
                    return false; 
                }
            }

            if (method === "EFT") {
                const eftRef = document.getElementById('eft_reference').value.trim();
                if (eftRef === "") { 
                    alert("Enter EFT reference number."); 
                    event.preventDefault(); 
                    return false; 
                }
            }

            // Validate that a payment method is selected
            if (method === "") {
                alert("Please select a payment method.");
                event.preventDefault();
                return false;
            }
        }

        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.substring(0, 16);
            value = value.replace(/(\d{4})/g, '$1 ').trim();
            input.value = value;
        }

        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            input.value = value;
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('payment_method').addEventListener('change', togglePaymentFields);
            document.querySelector('form#online_payment_form').addEventListener('submit', validateForm);
            
            const cardNumberInput = document.getElementById('card_number');
            const cardExpiryInput = document.getElementById('card_expiry');
            
            if (cardNumberInput) cardNumberInput.addEventListener('input', function() { formatCardNumber(this); });
            if (cardExpiryInput) cardExpiryInput.addEventListener('input', function() { formatExpiry(this); });
            
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
                <label for="payment_method" class="form-label">Payment Method *</label>
                <select name="payment_method" id="payment_method" class="form-input" required>
                    <option value="">Select Payment Method</option>
                    <option value="EFT">EFT/Bank Transfer</option>
                    <option value="Card">Credit/Debit Card</option>
                </select>
            </div>

            <!-- EFT -->
            <div id="eft_section" style="display:none;">
                <div class="form-group">
                    <label for="eft_reference" class="form-label">EFT Reference Number *</label>
                    <input type="text" name="eft_reference" id="eft_reference" class="form-input" 
                           placeholder="Enter your bank reference number" maxlength="20">
                    <small class="form-text">Please use this reference when making your bank transfer.</small>
                </div>
            </div>

            <!-- Card -->
            <div id="card_section" style="display:none;">
                <div class="form-group">
                    <label for="card_holder" class="form-label">Cardholder Name *</label>
                    <input type="text" name="card_holder" id="card_holder" class="form-input" 
                           placeholder="Name as it appears on card" maxlength="50" 
                           pattern="[a-zA-Z\s]{2,50}" title="Enter cardholder name (letters and spaces only)">
                </div>
                <div class="form-group">
                    <label for="card_number" class="form-label">Card Number *</label>
                    <input type="text" name="card_number" id="card_number" class="form-input" 
                           placeholder="1234 5678 9012 3456" maxlength="19" 
                           pattern="\d{16}" title="16-digit card number required">
                </div>
                <div class="form-group-row">
                    <div class="form-group half-width">
                        <label for="card_expiry" class="form-label">Expiry Date *</label>
                        <input type="text" name="card_expiry" id="card_expiry" class="form-input" 
                               placeholder="MM/YY" maxlength="5" 
                               pattern="(0[1-9]|1[0-2])\/\d{2}" title="Format: MM/YY">
                    </div>
                    <div class="form-group half-width">
                        <label for="card_cvv" class="form-label">CVV *</label>
                            <input type="text" name="card_cvv" id="card_cvv" class="form-input" 
                            placeholder="123" maxlength="3" pattern="\d{3}" 
                            title="3-digit security code"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0,3);">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-place">Place Order & Pay</button>
                <a href="cart.php" class="btn btn-cancel">Return to Cart</a>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

