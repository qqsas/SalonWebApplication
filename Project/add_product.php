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
$name = $price = $stock = "";
$categories = [];
$newCategory = "";

// Get all unique categories from existing products
$allCategories = [];

// First, try to extract categories from JSON arrays
$jsonCategoryQuery = $conn->query("
    SELECT Category 
    FROM Products 
    WHERE Category IS NOT NULL 
    AND Category != 'null' 
    AND Category LIKE '[%'
");

if ($jsonCategoryQuery) {
    while ($row = $jsonCategoryQuery->fetch_assoc()) {
        if (!empty($row['Category'])) {
            try {
                // Decode the JSON array
                $catArray = json_decode($row['Category'], true);
                if (is_array($catArray)) {
                    foreach ($catArray as $category) {
                        if (!empty($category) && is_string($category)) {
                            $allCategories[] = trim($category);
                        }
                    }
                }
            } catch (Exception $e) {
                // If JSON decoding fails, skip this entry
                continue;
            }
        }
    }
}

// Also get legacy single categories
$legacyCategoryQuery = $conn->query("
    SELECT Category 
    FROM Products 
    WHERE Category IS NOT NULL 
    AND Category != '' 
    AND Category NOT LIKE '[%'
");

if ($legacyCategoryQuery) {
    while ($row = $legacyCategoryQuery->fetch_assoc()) {
        if (!empty($row['Category'])) {
            $category = trim($row['Category']);
            // Clean up any escaped quotes or JSON artifacts
            $category = str_replace(['\"', '"', '[', ']'], '', $category);
            if (!empty($category)) {
                $allCategories[] = $category;
            }
        }
    }
}

// Clean and deduplicate categories
$allCategories = array_map(function($cat) {
    // Remove any remaining JSON artifacts and trim
    $cleanCat = trim(str_replace(['\"', '"', '[', ']', '\\'], '', $cat));
    return $cleanCat;
}, $allCategories);

$allCategories = array_filter($allCategories); // Remove empty values
$allCategories = array_unique($allCategories); // Remove duplicates
sort($allCategories); // Sort alphabetically

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $price = trim($_POST['Price']);
    $stock = trim($_POST['Stock']);
    $imgUrl = null;
    
    // Handle multiple categories
    $categories = [];
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        $categories = array_filter($_POST['categories']); // Remove empty values
        $categories = array_map('trim', $categories); // Trim whitespace
    }
    
    // Handle new category input
    $newCategory = trim($_POST['new_category'] ?? '');
    if (!empty($newCategory)) {
        $categories[] = $newCategory;
    }
    
    // Remove duplicates and empty values
    $categories = array_unique(array_filter($categories));
    
    // Convert to JSON for storage
    $categoryJson = !empty($categories) ? json_encode(array_values($categories)) : null;

    // Validation
    if (empty($name)) $errors[] = "Product name is required.";
    if (empty($categories)) $errors[] = "At least one category is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a valid positive number.";
    if (!is_numeric($stock) || $stock < 0) $errors[] = "Stock must be a valid non-negative number.";

    // Handle image upload
    if (isset($_FILES['ImgFile']) && $_FILES['ImgFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['ImgFile']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['ImgFile']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];

        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG, GIF, and WebP files are allowed.";
        } else {
            $targetDir = "Img/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            // Generate unique filename
            $fileName = uniqid() . "." . $fileExt;
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                $imgUrl = "Img/" . $fileName; // Store full path including Img/ folder
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    // Insert into database if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Products (Name, Price, Category, Stock, ImgUrl) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsss", $name, $price, $categoryJson, $stock, $imgUrl);
        if ($stmt->execute()) {
            $success = "Product added successfully!";
            // Clear form
            $name = $price = $stock = '';
            $categories = [];
            $newCategory = '';
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Add New Product</h2>
<link href="addedit.css" rel="stylesheet">

<?php if (!empty($errors)): ?>
    <div class="error-message">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success-message"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <div class="form-group">
        <label for="Name">Product Name:</label>
        <input type="text" name="Name" id="Name" value="<?= htmlspecialchars($name) ?>" required>
    </div>

    <div class="form-group">
        <label for="Price">Price (R):</label>
        <input type="number" step="0.01" min="0" name="Price" id="Price" value="<?= htmlspecialchars($price) ?>" required>
    </div>

    <div class="form-group">
        <label>Categories:</label>
        <div class="categories-container">
            <?php if (!empty($allCategories)): ?>
                <div class="existing-categories">
                    <label class="sub-label">Select from existing categories:</label>
                    <div class="categories-checkboxes">
                        <?php foreach ($allCategories as $cat): ?>
                            <?php if (!empty(trim($cat))): ?>
                                <div class="category-checkbox-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($cat); ?>" 
                                            <?= (isset($categories) && in_array($cat, $categories)) ? 'checked' : ''; ?>>
                                        <span class="checkbox-text"><?= htmlspecialchars($cat); ?></span>
                                    </label>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-categories">
                    <p>No existing categories found. Add your first category below.</p>
                </div>
            <?php endif; ?>
            
            <div class="new-category">
                <label class="sub-label">Or add a new category:</label>
                <input type="text" name="new_category" id="new_category" placeholder="Enter new category name" 
                       value="<?= htmlspecialchars($newCategory) ?>">
                <small class="help-text">You can add multiple categories by checking existing ones and/or entering a new one.</small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="Stock">Stock:</label>
        <input type="number" min="0" name="Stock" id="Stock" value="<?= htmlspecialchars($stock) ?>" required>
    </div>

    <div class="form-group">
        <label for="ImgFile">Upload Product Image:</label>
        <input type="file" name="ImgFile" id="ImgFile" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
        <small class="help-text">Allowed formats: JPG, JPEG, PNG, GIF, WebP</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Add Product</button>
        <a href="admin_dashboard.php?view=products" class="btn-cancel">Cancel</a>
    </div>
</form>

<?php include 'footer.php'; ?>
