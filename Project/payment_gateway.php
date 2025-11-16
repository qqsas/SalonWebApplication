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
    <link href="styles.css" rel="stylesheet">
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

<style>


body > .container {
  max-width: 900px !important;
  margin: 50px auto !important;
  background: #ffffff !important;
  border-radius: 12px !important;
  padding: 40px !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
}

body > .container h2 {
  color: #1e3a8a !important;
  text-align: center !important;
  font-size: 1.9rem !important;
  margin-bottom: 30px !important;
  border-bottom: 2px solid #e5e7eb !important;
  padding-bottom: 12px !important;
  position: relative !important;
}

body > .container h2::after {
  display: none !important;
}

/* === Table - Proper Styling - Solid Colors Only === */
body > .container table {
  width: 100% !important;
  border-collapse: collapse !important;
  margin-bottom: 30px !important;
  font-size: 0.95rem !important;
  background: #ffffff !important;
  background-image: none !important;
  border: 1px solid #d1d5db !important;
  border-radius: 8px !important;
  overflow: hidden !important;
}

body > .container table thead {
  background-color: #1e3a8a !important;
  background-image: none !important;
}

body > .container table th {
  background-color: #1e3a8a !important;
  background-image: none !important;
  color: #ffffff !important;
  font-weight: 600 !important;
  padding: 16px 14px !important;
  text-align: left !important;
  vertical-align: middle !important;
  text-transform: none !important;
  letter-spacing: normal !important;
  border: none !important;
  border-bottom: 2px solid #1e40af !important;
}

body > .container table th:not(:last-child) {
  border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
}

body > .container table tbody tr {
  background-color: #ffffff !important;
  background-image: none !important;
  border-bottom: 1px solid #e5e7eb !important;
}

body > .container table tbody tr:hover {
  background-color: #f9fafb !important;
  background-image: none !important;
}

body > .container table tbody tr:last-child {
  border-bottom: none !important;
  font-weight: 600 !important;
  background-color: #f3f4f6 !important;
  background-image: none !important;
}

body > .container table td {
  padding: 14px !important;
  text-align: left !important;
  vertical-align: middle !important;
  border: none !important;
  color: #374151 !important;
  background-color: transparent !important;
  background-image: none !important;
}

body > .container table td:not(:last-child) {
  border-right: 1px solid #e5e7eb !important;
}

body > .container table tbody tr:last-child td {
  border-top: 2px solid #d1d5db !important;
  border-right: none !important;
  padding-top: 16px !important;
  padding-bottom: 16px !important;
}

body > .container table td img.product-img {
  width: 60px !important;
  height: 60px !important;
  object-fit: cover !important;
  border-radius: 4px !important;
  border: 1px solid #d1d5db !important;
  background: #ffffff !important;
}

/* === Info Section === */
body > .container .info {
  background: #f9fafb !important;
  padding: 18px 20px !important;
  border-radius: 8px !important;
  border: 1px solid #e5e7eb !important;
  margin-bottom: 30px !important;
  color: #374151 !important;
  font-size: 0.95rem !important;
  line-height: 1.6 !important;
}

/* === Forms === */
body > .container form {
  background: #f9fafb !important;
  padding: 25px !important;
  border-radius: 10px !important;
  border: 1px solid #e5e7eb !important;
  margin-bottom: 30px !important;
}

body > .container .form-group {
  margin-bottom: 20px !important;
}

body > .container .form-group-row {
  display: flex !important;
  flex-direction: column !important;
  gap: 15px !important;
}

body > .container .form-group.half-width {
  width: 100% !important;
}

body > .container .form-label {
  display: block !important;
  font-weight: 600 !important;
  color: #1f2937 !important;
  margin-bottom: 8px !important;
}

body > .container .form-input {
  width: 100% !important;
  padding: 12px 14px !important;
  border: 1px solid #d1d5db !important;
  border-radius: 6px !important;
  background-color: #ffffff !important;
  font-size: 0.95rem !important;
  transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
  color: #374151 !important;
}

body > .container .form-input:focus {
  border-color: #3b82f6 !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
  outline: none !important;
}

body > .container .form-text {
  font-size: 0.8rem !important;
  color: #6b7280 !important;
  margin-top: 4px !important;
}

/* === Buttons - Properly Aligned === */
body > .container .btn {
  display: inline-block !important;
  text-decoration: none !important;
  padding: 12px 28px !important;
  border-radius: 8px !important;
  font-weight: 600 !important;
  font-size: 0.95rem !important;
  transition: background-color 0.2s ease, transform 0.2s ease !important;
  border: none !important;
  cursor: pointer !important;
  text-align: center !important;
  min-width: 180px !important;
  vertical-align: middle !important;
}

body > .container .btn:hover {
  transform: translateY(-1px) !important;
}

body > .container .btn:active {
  transform: translateY(0) !important;
}

/* Reserve In-Store Button */
body > .container .btn-reserve {
  background-color: #2563eb !important;
  color: #ffffff !important;
  box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2) !important;
}

body > .container .btn-reserve:hover {
  background-color: #1d4ed8 !important;
  box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3) !important;
}

/* Place Order Button */
body > .container .btn-place {
  background-color: #1e3a8a !important;
  color: #ffffff !important;
  box-shadow: 0 2px 4px rgba(30, 58, 138, 0.2) !important;
}

body > .container .btn-place:hover {
  background-color: #1e40af !important;
  box-shadow: 0 4px 6px rgba(30, 58, 138, 0.3) !important;
}

/* Cancel / Return Button */
body > .container .btn-cancel {
  background-color: var(--error-color) !important;
  color: #ffffff !important;
  box-shadow: 0 2px 4px rgba(107, 114, 128, 0.2) !important;
}

body > .container .btn-cancel:hover {
  background-color: #4b5563 !important;
  box-shadow: 0 4px 6px rgba(107, 114, 128, 0.3) !important;
}

/* Button Containers - Proper Alignment */
body > .container form .form-group:last-of-type {
  display: flex !important;
  justify-content: center !important;
  align-items: center !important;
  gap: 15px !important;
  flex-wrap: wrap !important;
  margin-top: 25px !important;
}

body > .container form .btn {
  flex: 0 1 auto !important;
  max-width: 240px !important;
}

/* Reserve form buttons alignment */
body > .container form[method="POST"]:first-of-type {
  display: flex !important;
  justify-content: center !important;
  align-items: center !important;
  gap: 15px !important;
  flex-wrap: wrap !important;
  margin-bottom: 20px !important;
  padding: 0 !important;
  background: transparent !important;
  border: none !important;
}

body > .container form[method="POST"]:first-of-type .btn {
  margin: 0 !important;
}

/* === Responsive === */
@media (max-width: 768px) {
  body > .container {
    padding: 25px !important;
    margin: 1.5rem auto !important;
  }

  body > .container h2 {
    font-size: 1.6rem !important;
  }

  body > .container table {
    font-size: 0.85rem !important;
    border: 1px solid #d1d5db !important;
  }

  body > .container table th,
  body > .container table td {
    padding: 10px 8px !important;
    font-size: 0.85rem !important;
  }

  body > .container table th:not(:last-child),
  body > .container table td:not(:last-child) {
    border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
  }

  body > .container table td:not(:last-child) {
    border-right: 1px solid #e5e7eb !important;
  }

  body > .container .btn {
    width: 100% !important;
    max-width: 100% !important;
    margin-bottom: 10px !important;
    display: block !important;
  }

  body > .container form .form-group:last-of-type {
    flex-direction: column !important;
    gap: 10px !important;
  }

  body > .container form[method="POST"]:first-of-type {
    flex-direction: column !important;
    gap: 10px !important;
  }

  body > .container .form-group-row {
    flex-direction: column !important;
  }
}
</style>