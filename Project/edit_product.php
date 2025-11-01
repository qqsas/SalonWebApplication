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

// Parse existing categories for this product
$currentCategories = [];
if (!empty($product['Category'])) {
    // If it's a JSON string, decode it
    if (is_string($product['Category']) && $product['Category'][0] === '[') {
        $currentCategories = json_decode($product['Category'], true) ?: [];
    } 
    // If it's already an array, use it directly
    elseif (is_array($product['Category'])) {
        $currentCategories = $product['Category'];
    }
    // If it's a single string (legacy data), wrap it in an array
    elseif (is_string($product['Category'])) {
        $currentCategories = [$product['Category']];
    }
}

// Clean current categories
$currentCategories = array_map(function($cat) {
    return trim(str_replace(['\"', '"', '[', ']', '\\'], '', $cat));
}, $currentCategories);
$currentCategories = array_filter($currentCategories);

// --- Handle form submission ---
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $imgUrl = $product['ImgUrl']; // default to existing image
    
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

    // Validate fields
    if (empty($name)) $errors[] = "Product name is required.";
    if (empty($categories)) $errors[] = "At least one category is required.";
    if ($price < 0) $errors[] = "Price must be a non-negative number.";
    if ($stock < 0) $errors[] = "Stock must be a non-negative number.";

    // Handle image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['product_image']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];

        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG, GIF, and WebP files are allowed.";
        } elseif ($_FILES['product_image']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image must be less than 2MB.";
        } else {
            $targetDir = "Img/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            // Generate unique filename
            $fileName = uniqid() . "." . $fileExt;
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                // Delete old image if it exists and is not the default
                if (!empty($product['ImgUrl']) && $product['ImgUrl'] !== 'default-product.jpg') {
                    $oldFile = $targetDir . $product['ImgUrl'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                $imgUrl = $fileName; // Store just the filename
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    // Update database if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE Products SET Name=?, Price=?, Category=?, Stock=?, ImgUrl=? WHERE ProductID=?");
        $stmt->bind_param("sdsssi", $name, $price, $categoryJson, $stock, $imgUrl, $product_id);
        if ($stmt->execute()) {
            $success = "Product updated successfully.";
            $product['Name'] = $name;
            $product['Price'] = $price;
            $product['Category'] = $categoryJson;
            $product['Stock'] = $stock;
            $product['ImgUrl'] = $imgUrl;
            
            // Update current categories for display
            $currentCategories = $categories;
        } else {
            $errors[] = "Error updating product: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<?php include 'header.php'; ?>
<div class="container">
    <link href="addedit.css" rel="stylesheet">
    <h2>Edit Product</h2>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <ul>
            <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Product Name:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product['Name']); ?>" required>
        </div>

        <div class="form-group">
            <label for="price">Price (R):</label>
            <input type="number" step="0.01" min="0" name="price" id="price" value="<?php echo htmlspecialchars($product['Price']); ?>" required>
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
                                            <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo in_array($cat, $currentCategories) ? 'checked' : ''; ?>>
                                            <span class="checkbox-text"><?php echo htmlspecialchars($cat); ?></span>
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
                           value="<?php echo htmlspecialchars($_POST['new_category'] ?? ''); ?>">
                    <small class="help-text">You can add multiple categories by checking existing ones and/or entering a new one.</small>
                </div>
                
                <?php if (!empty($currentCategories)): ?>
                    <div class="current-categories">
                        <label class="sub-label">Current Categories:</label>
                        <div class="current-categories-list">
                            <?php foreach ($currentCategories as $cat): ?>
                                <span class="category-tag"><?php echo htmlspecialchars($cat); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="stock">Stock:</label>
            <input type="number" min="0" name="stock" id="stock" value="<?php echo htmlspecialchars($product['Stock']); ?>" required>
        </div>

        <div class="form-group">
            <label>Current Image:</label>
            <?php if (!empty($product['ImgUrl'])): ?>
                <div class="current-image">
                    <img src="<?php echo htmlspecialchars($product['ImgUrl']); ?>" 
                         alt="<?php echo htmlspecialchars($product['Name']); ?>" 
                         style="max-width:150px; height: auto; border-radius: 4px;">
                    <div class="image-info">Current image</div>
                </div>
            <?php else: ?>
                <span>No image uploaded.</span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="product_image">Upload New Image (optional):</label>
            <input type="file" name="product_image" id="product_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <small class="help-text">Leave empty to keep current image. Allowed formats: JPG, JPEG, PNG, GIF, WebP</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Update Product</button>
            <a href="admin_dashboard.php?view=products" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
