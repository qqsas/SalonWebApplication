<?php
session_start();
include 'db.php';

$features = $_SESSION['Features'] ?? [];

// Only allow admin or barber with allowed products
if (!isset($_SESSION['UserID']) || 
    !(
        $_SESSION['Role'] === 'admin' || 
        ($_SESSION['Role'] === 'barber' && empty($features['allow products']))
    )
) {
    echo "Redirecting to login.php"; // for debug
    header("Location: Login.php");
    exit();
}

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    echo "Invalid product ID.";
    exit();
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

// --- Handle form submission ---
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $stock = intval($_POST['stock']);
    $imgUrl = $product['ImgUrl']; // default to existing image

    // Validate fields
    if (!$name) $errors[] = "Product name is required.";
    if ($price < 0) $errors[] = "Price must be a non-negative number.";
    if (!$category) $errors[] = "Category is required.";
    if ($stock < 0) $errors[] = "Stock must be a non-negative number.";

    // Handle image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['product_image']['tmp_name'];
        $fileType = mime_content_type($fileTmp);
        $allowedTypes = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif'];

        if (!isset($allowedTypes[$fileType])) {
            $errors[] = "Only JPG, PNG, or GIF images are allowed.";
        } elseif ($_FILES['product_image']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image must be less than 2MB.";
        } else {
            $targetDir = "Img/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $ext = $allowedTypes[$fileType];
            $newFileName = uniqid() . $ext; 
            $targetFile = $targetDir . $newFileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                $imgUrl = $targetFile;
            } else {
                $errors[] = "Failed to move uploaded file.";
            }
        }
    }

    // Update database if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE Products SET Name=?, Price=?, Category=?, Stock=?, ImgUrl=? WHERE ProductID=?");
        $stmt->bind_param("sdsisi", $name, $price, $category, $stock, $imgUrl, $product_id);
        if ($stmt->execute()) {
            $success = "Product updated successfully.";
            $product['Name'] = $name;
            $product['Price'] = $price;
            $product['Category'] = $category;
            $product['Stock'] = $stock;
            $product['ImgUrl'] = $imgUrl;
        } else {
            $errors[] = "Error updating product: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Product</h2>

    <?php if ($errors): ?>
        <div style="color:red;">
            <ul>
            <?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="color:green;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
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
            <label>Current Image:</label><br>
            <?php if ($product['ImgUrl']): ?>
                <img src="<?php echo htmlspecialchars($product['ImgUrl']); ?>" style="max-width:150px;"><br>
            <?php else: ?>
                <span>No image uploaded.</span><br>
            <?php endif; ?>
        </div>

        <div>
            <label for="product_image">Upload New Image (optional):</label><br>
            <input type="file" name="product_image" id="product_image" accept="image/*">
        </div>

        <br>
        <button type="submit">Update Product</button>
    </form>
</div>
<?php include 'footer.php'; ?>

