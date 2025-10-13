<?php
session_start();
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

include 'db.php';
include 'header.php';

$errors = [];
$success = '';
$name = $price = $category = $stock = "";
$newCategory = "";

// Fetch distinct existing categories
$categories = [];
$catResult = $conn->query("SELECT DISTINCT Category FROM Products ORDER BY Category ASC");
if ($catResult && $catResult->num_rows > 0) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row['Category'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $price = trim($_POST['Price']);
    $category = trim($_POST['Category']);
    $newCategory = trim($_POST['NewCategory']);
    $stock = trim($_POST['Stock']);
    $imgUrl = null;

    // If new category is provided, use it instead
    if (!empty($newCategory)) {
        $category = $newCategory;
    }

    if (empty($name)) $errors[] = "Product name is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a positive number.";
    if (empty($category)) $errors[] = "Category is required.";
    if (!is_numeric($stock) || $stock < 0) $errors[] = "Stock must be a valid non-negative number.";

    // Handle image upload
    if (isset($_FILES['ImgFile']) && $_FILES['ImgFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['ImgFile']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['ImgFile']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];

        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG, or GIF files are allowed.";
        } else {
            $targetDir = "Img/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            // Find next available number for filename
            $files = scandir($targetDir);
            $maxNum = 0;
            foreach ($files as $file) {
                if (preg_match('/^(\d+)\.\w+$/', $file, $matches)) {
                    $num = intval($matches[1]);
                    if ($num > $maxNum) $maxNum = $num;
                }
            }
            $nextNum = $maxNum + 1;
            $fileName = $nextNum . "." . $fileExt;
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                $imgUrl = $targetFile;
            } else {
                $errors[] = "Failed to move uploaded file.";
            }
        }
    }

    // Insert into database if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Products (Name, Price, Category, Stock, ImgUrl) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdiss", $name, $price, $category, $stock, $imgUrl);
        if ($stmt->execute()) {
            $success = "Product added successfully!";
            $name = $price = $category = $stock = $newCategory = '';
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Add New Product</h2>

<?php if (!empty($errors)): ?>
    <div style="color:red;">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div style="color:green;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <label>Product Name:</label><br>
    <input type="text" name="Name" value="<?= htmlspecialchars($name) ?>" required><br><br>

    <label>Price:</label><br>
    <input type="number" step="0.01" name="Price" value="<?= htmlspecialchars($price) ?>" required><br><br>

    <label>Category (choose existing):</label><br>
    <select name="Category">
        <option value="">-- Select a category --</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Or Add New Category:</label><br>
    <input type="text" name="NewCategory" value="<?= htmlspecialchars($newCategory) ?>"><br><br>

    <label>Stock:</label><br>
    <input type="number" name="Stock" value="<?= htmlspecialchars($stock) ?>" required><br><br>

    <label>Upload Product Image:</label><br>
    <input type="file" name="ImgFile" accept="image/*"><br><br>

    <button type="submit">Add Product</button>
</form>
