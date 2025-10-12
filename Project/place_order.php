<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db.php';
include 'mail.php'; // Make sure this contains the PHPMailer setup

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit;
}

$userID = $_SESSION['UserID'];

// --- Flag to indicate online purchase (true) or reservation (false) ---
// For example, sent via POST from the checkout form
$isOnlinePurchase = isset($_POST['online_purchase']) && $_POST['online_purchase'] == '1';

// Fetch user's cart
$stmt = $conn->prepare("SELECT CartID FROM Cart WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$cart = $result->fetch_assoc();
$stmt->close();

if (!$cart) {
    die("No cart found.");
}

$cartID = $cart['CartID'];

// Calculate total price
$stmt = $conn->prepare("
    SELECT SUM(p.Price * ci.Quantity) AS total
    FROM CartItems ci
    JOIN Products p ON ci.ProductID = p.ProductID
    WHERE ci.CartID = ?
");
$stmt->bind_param("i", $cartID);
$stmt->execute();
$result = $stmt->get_result();
$totalRow = $result->fetch_assoc();
$totalPrice = $totalRow['total'] ?? 0;
$stmt->close();

if ($totalPrice <= 0) {
    die("Cart is empty.");
}

// Insert into Orders (no purchase type column needed)
$stmt = $conn->prepare("INSERT INTO Orders (UserID, TotalPrice, Status) VALUES (?, ?, 'Pending')");
$stmt->bind_param("id", $userID, $totalPrice);
$stmt->execute();
$orderID = $stmt->insert_id;
$stmt->close();

// Copy items from CartItems to OrderItems
$stmt = $conn->prepare("
    INSERT INTO OrderItems (OrderID, ProductID, Quantity)
    SELECT ?, ProductID, Quantity
    FROM CartItems
    WHERE CartID = ?
");
$stmt->bind_param("ii", $orderID, $cartID);
$stmt->execute();
$stmt->close();

// --- Reduce stock ONLY if it’s an online purchase ---
if ($isOnlinePurchase) {
    $stmt = $conn->prepare("
        SELECT ProductID, Quantity 
        FROM CartItems 
        WHERE CartID = ?
    ");
    $stmt->bind_param("i", $cartID);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    $cartItems = $itemsResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($cartItems as $item) {
        $stmt = $conn->prepare("
            UPDATE Products 
            SET Stock = Stock - ? 
            WHERE ProductID = ? AND Stock >= ?
        ");
        $stmt->bind_param("iii", $item['Quantity'], $item['ProductID'], $item['Quantity']);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch order items for email
$stmt = $conn->prepare("
    SELECT p.Name, p.Price, ci.Quantity
    FROM CartItems ci
    JOIN Products p ON ci.ProductID = p.ProductID
    WHERE ci.CartID = ?
");
$stmt->bind_param("i", $cartID);
$stmt->execute();
$orderItemsResult = $stmt->get_result();
$orderItems = $orderItemsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Clear user's cart
$stmt = $conn->prepare("DELETE FROM CartItems WHERE CartID = ?");
$stmt->bind_param("i", $cartID);
$stmt->execute();
$stmt->close();

// Fetch customer info
$stmt = $conn->prepare("SELECT Name, Email FROM User WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Prepare email content
$body = "<p>Hi " . htmlspecialchars($user['Name']) . ",</p>
<p>Your " . ($isOnlinePurchase ? "purchase" : "reservation") . " has been successfully placed! Here are the details:</p>
<table border='1' cellpadding='5'>
<tr><th>Product</th><th>Qty</th><th>Subtotal</th></tr>";

foreach ($orderItems as $item) {
    $subtotal = $item['Price'] * $item['Quantity'];
    $body .= "<tr>
        <td>" . htmlspecialchars($item['Name']) . "</td>
        <td>{$item['Quantity']}</td>
        <td>R" . number_format($subtotal, 2) . "</td>
    </tr>";
}

$body .= "<tr>
<td colspan='2' style='text-align:right;'><strong>Total:</strong></td>
<td>R" . number_format($totalPrice, 2) . "</td>
</tr></table>
<p>Please visit our store to complete payment and pickup.</p>";

// Send email to customer + admins
$adminEmails = ['store@example.com', 'manager@example.com']; // replace with actual emails
sendEmail($user['Email'], "Order Confirmation #$orderID", $body, $adminEmails);

// Redirect to orders page with success message
header("Location: orders.php?msg=OrderPlaced");
exit;
?>

