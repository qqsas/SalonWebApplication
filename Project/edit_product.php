<?php
session_start();
include 'db.php';

// --- Access Control ---
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

// Check if user has permission to edit products
$features = $_SESSION['Features'] ?? [];
$isAdmin = $_SESSION['Role'] === 'admin';
$isBarberWithProducts = $_SESSION['Role'] === 'barber' && $features['allow products']==1;


// --- Validate and sanitize product ID ---
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id || $product_id <= 0) {
    $_SESSION['error'] = "Invalid product ID.";
    header("Location: admin_dashboard.php?view=products");
    exit();
}

// --- Fetch product details with prepared statement ---
$stmt = $conn->prepare("SELECT * FROM Products WHERE ProductID = ?");
if (!$stmt) {
    error_log("Product fetch prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: admin_dashboard.php?view=products");
    exit();
}

$stmt->bind_param("i", $product_id);
if (!$stmt->execute()) {
    error_log("Product fetch execute failed: " . $stmt->error);
    $_SESSION['error'] = "Failed to fetch product details.";
    header("Location: admin_dashboard.php?view=products");
    exit();
}

$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['error'] = "Product not found.";
    header("Location: admin_dashboard.php?view=products");
    exit();
}

// --- Get all unique categories from existing products ---
$allCategories = [];

// Fetch categories from JSON arrays
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
                $catArray = json_decode($row['Category'], true);
                if (is_array($catArray)) {
                    foreach ($catArray as $category) {
                        if (!empty($category) && is_string($category)) {
                            $cleanCat = trimCategory($category);
                            if (!empty($cleanCat)) {
                                $allCategories[] = $cleanCat;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Category JSON decode error: " . $e->getMessage());
                continue;
            }
        }
    }
}

// Fetch legacy single categories
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
            $cleanCat = trimCategory($row['Category']);
            if (!empty($cleanCat)) {
                $allCategories[] = $cleanCat;
            }
        }
    }
}

// Clean and deduplicate categories
$allCategories = array_unique(array_filter($allCategories));
sort($allCategories);

// --- Parse existing categories for this product ---
$currentCategories = [];
if (!empty($product['Category'])) {
    if (is_string($product['Category']) && $product['Category'][0] === '[') {
        $currentCategories = json_decode($product['Category'], true) ?: [];
    } elseif (is_array($product['Category'])) {
        $currentCategories = $product['Category'];
    } elseif (is_string($product['Category'])) {
        $currentCategories = [$product['Category']];
    }
}

// Clean current categories
$currentCategories = array_map('trimCategory', $currentCategories);
$currentCategories = array_filter($currentCategories);

// --- Handle form submission ---
$form_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Sanitize and validate input data ---
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $imgUrl = $product['ImgUrl']; // default to existing image
    
    // Handle multiple categories
    $categories = [];
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        foreach ($_POST['categories'] as $category) {
            $cleanCat = trimCategory($category);
            if (!empty($cleanCat)) {
                $categories[] = $cleanCat;
            }
        }
    }
    
    // Handle new category input
    $newCategory = trim(filter_input(INPUT_POST, 'new_category', FILTER_SANITIZE_STRING));
    if (!empty($newCategory)) {
        $cleanNewCat = trimCategory($newCategory);
        if (!empty($cleanNewCat)) {
            $categories[] = $cleanNewCat;
        }
    }
    
    // Remove duplicates and empty values
    $categories = array_unique(array_filter($categories));
    
    // Convert to JSON for storage
    $categoryJson = !empty($categories) ? json_encode(array_values($categories)) : null;

    // --- Validation checks ---
    if (empty($name) || strlen($name) > 255) {
        $form_errors[] = "Product name is required and must be less than 255 characters.";
    }
    
    if ($price === false || $price < 0) {
        $form_errors[] = "Price must be a valid non-negative number.";
    }
    
    if ($price > 100000) {
        $form_errors[] = "Price seems unreasonably high. Please verify the amount.";
    }
    
    if ($stock === false || $stock < 0) {
        $form_errors[] = "Stock must be a valid non-negative number.";
    }
    
    if ($stock > 100000) {
        $form_errors[] = "Stock quantity seems unreasonably high. Please verify the amount.";
    }
    
    if (empty($categories)) {
        $form_errors[] = "At least one category is required.";
    }
    
    // Validate category lengths
    foreach ($categories as $category) {
        if (strlen($category) > 100) {
            $form_errors[] = "Category names must be less than 100 characters.";
            break;
        }
    }

    // --- Handle image upload with validation ---
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_errors = validateImageUpload($_FILES['product_image']);
        
        if (!empty($upload_errors)) {
            $form_errors = array_merge($form_errors, $upload_errors);
        } else {
            $newImgPath = handleImageUpload($_FILES['product_image'], 'Img/', $product['ImgUrl']);
            if ($newImgPath) {
                $imgUrl = $newImgPath;
            } else {
                $form_errors[] = "Failed to upload image. Please try again.";
            }
        }
    } elseif ($_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $form_errors[] = "File upload error: " . getUploadError($_FILES['product_image']['error']);
    }

    // --- Update database if no errors ---
    if (empty($form_errors)) {
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("UPDATE Products SET Name=?, Price=?, Category=?, Stock=?, ImgUrl=? WHERE ProductID=?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("sdsssi", $name, $price, $categoryJson, $stock, $imgUrl, $product_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['success'] = "Product updated successfully.";
                
                // Update local product data for display
                $product['Name'] = $name;
                $product['Price'] = $price;
                $product['Category'] = $categoryJson;
                $product['Stock'] = $stock;
                $product['ImgUrl'] = $imgUrl;
                $currentCategories = $categories;
                
                // Redirect to prevent form resubmission
                header("Location: edit_product.php?id=" . $product_id);
                exit();
                
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Product update error: " . $e->getMessage());
            $form_errors[] = "Error updating product: " . $e->getMessage();
        }
    }
}

// --- Helper functions ---
function trimCategory($category) {
    if (!is_string($category)) return '';
    $clean = trim(str_replace(['\"', '"', '[', ']', '\\'], '', $category));
    return $clean;
}

function validateImageUpload($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed: " . getUploadError($file['error']);
        return $errors;
    }
    
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    $fileName = basename($file['name']);
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "Only JPG, JPEG, PNG, GIF, and WebP files are allowed.";
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = "Image must be less than 2MB.";
    }
    
    // MIME type validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_mimes)) {
        $errors[] = "Invalid file type. Please upload a valid image.";
    }
    
    return $errors;
}

function handleImageUpload($file, $targetDir, $oldImagePath = null) {
    // Delete old image if it exists and is not a default image
    if ($oldImagePath && file_exists($oldImagePath) && !str_contains($oldImagePath, 'default')) {
        unlink($oldImagePath);
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Generate safe filename
    $fileName = basename($file['name']);
    $safeFileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $fileName);
    $targetFile = $targetDir . $safeFileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        // Verify the uploaded file is actually an image
        if (getimagesize($targetFile)) {
            return $targetFile;
        } else {
            unlink($targetFile); // Delete if not a valid image
            return false;
        }
    }
    
    return false;
}

function getUploadError($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File is too large.";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded.";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing temporary folder.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk.";
        case UPLOAD_ERR_EXTENSION:
            return "File upload stopped by extension.";
        default:
            return "Unknown upload error.";
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin</title>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .sub-label {
            font-weight: normal;
            font-size: 0.9em;
            color: #666;
            margin-bottom: 8px;
            display: block;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="file"],
        select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }
        
        input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .categories-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .categories-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .category-checkbox-item {
            flex: 1 1 200px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .checkbox-label:hover {
            background: #f8f9fa;
        }
        
        .checkbox-text {
            font-weight: normal;
        }
        
        .current-categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        
        .category-tag {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        
        .current-image {
            margin-bottom: 10px;
        }
        
        .current-image img {
            max-width: 150px;
            height: auto;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .image-info {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
        }
        
        .help-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
            display: block;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-cancel:hover {
            background: #545b62;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .categories-checkboxes {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <i class="section-icon fa fa-suitcase fa-5x" aria-hidden="true"></i>
    <h2>Edit Product</h2>

    <!-- Display session messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-message"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-message"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Display form validation errors -->
    <?php if (!empty($form_errors)): ?>
        <div class="error-message">
            <ul>
                <?php foreach ($form_errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="name">Product Name: <span class="required">*</span></label>
            <input type="text" name="name" id="name" 
                   value="<?php echo htmlspecialchars($_POST['name'] ?? $product['Name']); ?>" 
                   required maxlength="255">
            <small class="help-text">Required, max 255 characters</small>
        </div>

        <div class="form-group">
            <label for="price">Price (R): <span class="required">*</span></label>
            <input type="number" step="0.01" min="0" max="100000" name="price" id="price" 
                   value="<?php echo htmlspecialchars($_POST['price'] ?? $product['Price']); ?>" required>
            <small class="help-text">Must be 0 or greater</small>
        </div>

        <div class="form-group">
            <label>Categories: <span class="required">*</span></label>
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
                                                <?php echo in_array($cat, ($_POST['categories'] ?? $currentCategories)) ? 'checked' : ''; ?>>
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
                           value="<?php echo htmlspecialchars($_POST['new_category'] ?? ''); ?>" maxlength="100">
                    <small class="help-text">You can add multiple categories by checking existing ones and/or entering a new one. Max 100 characters per category.</small>
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
            <label for="stock">Stock: <span class="required">*</span></label>
            <input type="number" min="0" max="100000" name="stock" id="stock" 
                   value="<?php echo htmlspecialchars($_POST['stock'] ?? $product['Stock']); ?>" required>
            <small class="help-text">Must be 0 or greater</small>
        </div>

        <div class="form-group">
            <label>Current Image:</label>
            <?php if (!empty($product['ImgUrl'])): ?>
                <div class="current-image">
                    <img src="<?php echo htmlspecialchars($product['ImgUrl']); ?>" 
                         alt="<?php echo htmlspecialchars($product['Name']); ?>">
                    <div class="image-info">Current image</div>
                </div>
            <?php else: ?>
                <span>No image uploaded.</span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="product_image">Upload New Image (optional):</label>
            <input type="file" name="product_image" id="product_image" 
                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <small class="help-text">Leave empty to keep current image. Max size: 2MB. Allowed formats: JPG, JPEG, PNG, GIF, WebP</small>
        </div>

<div class="form-actions">
    <button type="submit" class="btn-primary">Update Product</button>
    <a href="<?php
        if (isset($_SESSION['Role']) && $_SESSION['Role'] === 'barber') {
            echo 'barber_dashboard.php';
        } else {
            echo 'admin_dashboard.php?view=products';
        }
    ?>" class="btn-cancel">Cancel</a>
</div>
    </form>
</div>
<?php include 'footer.php'; ?>
