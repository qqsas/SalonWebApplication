<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit;
}

$userID = $_SESSION['UserID'];


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

// Insert into Orders
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

// Clear user's cart
$stmt = $conn->prepare("DELETE FROM CartItems WHERE CartID = ?");
$stmt->bind_param("i", $cartID);
$stmt->execute();
$stmt->close();

// Redirect to orders page with success message
header("Location: orders.php?msg=OrderPlaced");
exit;


