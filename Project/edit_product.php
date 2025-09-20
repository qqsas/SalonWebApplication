<?php
session_start();
include 'db.php';

// --- Only allow admin access ---
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    echo "Invalid product ID.";
    exit();
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $stock = intval($_POST['stock']);
    $imgUrl = trim($_POST['imgUrl']);

    if ($name && $price >= 0 && $stock >= 0 && $category) {
        $stmt = $conn->prepare("UPDATE Products SET Name=?, Price=?, Category=?, Stock=?, ImgUrl=? WHERE ProductID=?");
        $stmt->bind_param("sdsisi", $name, $price, $category, $stock, $imgUrl, $product_id);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Product updated successfully.</p>";
        } else {
            echo "<p style='color:red;'>Error updating product: " . $conn->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>All fields except image are required.</p>";
    }
}

// --- Fetch product details ---
$stmt = $conn->prepare("SELECT * FROM Products WHERE ProductID=?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "Product not found.";
    exit();
}
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Product</h2>

    <form method="post">
        <div>
            <label for="name">Product Name:</label><br>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product['Name']); ?>" required>
        </div>

        <div>
            <label for="price">Price:</label><br>
            <input type="number" step="0.01" name="price" id="price" value="<?php echo htmlspecialchars($product['Price']); ?>" required>
        </div>

        <div>
            <label for="category">Category:</label><br>
            <input type="text" name="category" id="category" value="<?php echo htmlspecialchars($product['Category']); ?>" required>
        </div>

        <div>
            <label for="stock">Stock:</label><br>
            <input type="number" name="stock" id="stock" value="<?php echo htmlspecialchars($product['Stock']); ?>" required>
        </div>

        <div>
            <label for="imgUrl">Image URL (optional):</label><br>
            <input type="text" name="imgUrl" id="imgUrl" value="<?php echo htmlspecialchars($product['ImgUrl']); ?>">
        </div>

        <br>
        <button type="submit">Update Product</button>
    </form>
</div>
<?php include 'footer.php'; ?>

