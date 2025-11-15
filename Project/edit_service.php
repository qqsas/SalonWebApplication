<?php
session_start();
include 'db.php';

// --- Access Control ---
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

// --- Validate and sanitize service ID ---
$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$service_id || $service_id <= 0) {
    $_SESSION['error'] = "Invalid service ID.";
    header("Location: admin_dashboard.php?view=services");
    exit();
}

// --- Get all unique categories from existing services ---
$allCategories = [];

// Fetch categories from JSON arrays
$jsonCategoryQuery = $conn->query("
    SELECT Category 
    FROM Services 
    WHERE Category IS NOT NULL 
    AND Category != 'null' 
    AND Category LIKE '[%'
");

if ($jsonCategoryQuery) {
    while ($row = $jsonCategoryQuery->fetch_assoc()) {
        if (!empty($row['Category'])) {
            try {
                $categories = json_decode($row['Category'], true);
                if (is_array($categories)) {
                    foreach ($categories as $category) {
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
    FROM Services 
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

// --- Initialize variables ---
$form_errors = [];

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Sanitize and validate input data ---
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
    $priceType = filter_input(INPUT_POST, 'PriceType', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
    $minPrice = filter_input(INPUT_POST, 'MinPrice', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
    $maxPrice = filter_input(INPUT_POST, 'MaxPrice', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
    $time = filter_input(INPUT_POST, 'time', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    
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
        $form_errors[] = "Service name is required and must be less than 255 characters.";
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
    
    if (strlen($description) > 1000) {
        $form_errors[] = "Description must be less than 1000 characters.";
    }
    
    if (!$time) {
        $form_errors[] = "Service time must be a valid number greater than 0.";
    }
    
    if ($time > 480) { // 8 hours max
        $form_errors[] = "Service time cannot exceed 480 minutes (8 hours).";
    }
    
    // Price validation based on price type
    $allowed_price_types = ['fixed', 'range'];
    if (!in_array($priceType, $allowed_price_types)) {
        $form_errors[] = "Please select a valid price type.";
    }
    
    if ($priceType === 'fixed') {
        if ($price === false || $price < 0) {
            $form_errors[] = "Price must be a valid non-negative number.";
        }
        
        if ($price > 100000) {
            $form_errors[] = "Price seems unreasonably high. Please verify the amount.";
        }
        
        // Set min and max price to null for fixed pricing
        $minPrice = null;
        $maxPrice = null;
    } else { // range pricing
        if ($minPrice === false || $minPrice < 0) {
            $form_errors[] = "Minimum price must be a valid non-negative number.";
        }
        
        if ($maxPrice === false || $maxPrice < 0) {
            $form_errors[] = "Maximum price must be a valid non-negative number.";
        }
        
        if ($minPrice > 100000 || $maxPrice > 100000) {
            $form_errors[] = "Price range seems unreasonably high. Please verify the amounts.";
        }
        
        if ($minPrice !== false && $maxPrice !== false && $minPrice >= $maxPrice) {
            $form_errors[] = "Maximum price must be greater than minimum price.";
        }
        
        // Set fixed price to null for range pricing
        $price = null;
    }

    // --- Handle image upload with validation ---
    $newImage = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_errors = validateImageUpload($_FILES['image']);
        
        if (!empty($upload_errors)) {
            $form_errors = array_merge($form_errors, $upload_errors);
        } else {
            $newImage = handleImageUpload($_FILES['image'], 'Img/', $service['ImgUrl'] ?? null);
            if (!$newImage) {
                $form_errors[] = "Failed to upload image. Please try again.";
            }
        }
    } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $form_errors[] = "File upload error: " . getUploadError($_FILES['image']['error']);
    }

    // --- Update database if no errors ---
    if (empty($form_errors)) {
        $conn->begin_transaction();
        
        try {
            if ($newImage) {
                $stmt = $conn->prepare("UPDATE Services SET Name=?, Category=?, Description=?, Price=?, PriceType=?, MinPrice=?, MaxPrice=?, Time=?, ImgUrl=? WHERE ServicesID=?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssssdsisi", $name, $categoryJson, $description, $price, $priceType, $minPrice, $maxPrice, $time, $newImage, $service_id);
            } else {
                $stmt = $conn->prepare("UPDATE Services SET Name=?, Category=?, Description=?, Price=?, PriceType=?, MinPrice=?, MaxPrice=?, Time=? WHERE ServicesID=?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssssdsii", $name, $categoryJson, $description, $price, $priceType, $minPrice, $maxPrice, $time, $service_id);
            }

            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['success'] = "Service updated successfully.";
                
                // Redirect to prevent form resubmission
                header("Location: edit_service.php?id=" . $service_id);
                exit();
                
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Service update error: " . $e->getMessage());
            $form_errors[] = "Error updating service: " . $e->getMessage();
        }
    }
}

// --- Fetch service details with prepared statement ---
$stmt = $conn->prepare("SELECT * FROM Services WHERE ServicesID = ?");
if (!$stmt) {
    error_log("Service fetch prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: admin_dashboard.php?view=services");
    exit();
}

$stmt->bind_param("i", $service_id);
if (!$stmt->execute()) {
    error_log("Service fetch execute failed: " . $stmt->error);
    $_SESSION['error'] = "Failed to fetch service details.";
    header("Location: admin_dashboard.php?view=services");
    exit();
}

$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

if (!$service) {
    $_SESSION['error'] = "Service not found.";
    header("Location: admin_dashboard.php?view=services");
    exit();
}

// --- Parse existing categories from JSON ---
$currentCategories = [];
if (!empty($service['Category'])) {
    if (is_string($service['Category']) && $service['Category'][0] === '[') {
        $currentCategories = json_decode($service['Category'], true) ?: [];
    } elseif (is_array($service['Category'])) {
        $currentCategories = $service['Category'];
    } elseif (is_string($service['Category'])) {
        $currentCategories = [$service['Category']];
    }
}

// Clean current categories
$currentCategories = array_map('trimCategory', $currentCategories);
$currentCategories = array_filter($currentCategories);

// Set default price type if not set
$priceType = $service['PriceType'] ?? 'fixed';

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
    <title>Edit Service - Admin</title>
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
        select,
        textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
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
        
        .price-range-container {
            display: flex;
            gap: 15px;
        }
        
        .price-range-item {
            flex: 1;
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
            
            .price-range-container {
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
    <i class="section-icon fa fa-scissors fa-5x" aria-hidden="true"></i>
    <h2>Edit Service</h2>

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
            <label for="name">Service Name: <span class="required">*</span></label>
            <input type="text" name="name" id="name" 
                   value="<?php echo htmlspecialchars($_POST['name'] ?? $service['Name']); ?>" 
                   required maxlength="255">
            <small class="help-text">Required, max 255 characters</small>
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
            <label for="description">Description:</label>
            <textarea name="description" id="description" rows="4" maxlength="1000"><?php echo htmlspecialchars($_POST['description'] ?? $service['Description']); ?></textarea>
            <small class="help-text">Max 1000 characters</small>
        </div>

        <div class="form-group">
            <label for="PriceType">Price Type: <span class="required">*</span></label>
            <select name="PriceType" id="PriceType" onchange="togglePriceFields()" required>
                <option value="">Select price type</option>
                <option value="fixed" <?php echo ($_POST['PriceType'] ?? $priceType) === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                <option value="range" <?php echo ($_POST['PriceType'] ?? $priceType) === 'range' ? 'selected' : ''; ?>>Price Range</option>
            </select>
        </div>

        <div class="form-group" id="fixedPriceField" style="display: none;">
            <label for="price">Price (R): <span class="required">*</span></label>
            <input type="number" step="0.01" min="0" max="100000" name="price" id="price" 
                   value="<?php echo htmlspecialchars($_POST['price'] ?? $service['Price'] ?? ''); ?>">
            <small class="help-text">Must be 0 or greater</small>
        </div>

        <div class="form-group" id="rangePriceFields" style="display: none;">
            <div class="price-range-container">
                <div class="price-range-item">
                    <label for="MinPrice">Minimum Price (R): <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0" max="100000" name="MinPrice" id="MinPrice" 
                           value="<?php echo htmlspecialchars($_POST['MinPrice'] ?? $service['MinPrice'] ?? ''); ?>">
                </div>
                <div class="price-range-item">
                    <label for="MaxPrice">Maximum Price (R): <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0" max="100000" name="MaxPrice" id="MaxPrice" 
                           value="<?php echo htmlspecialchars($_POST['MaxPrice'] ?? $service['MaxPrice'] ?? ''); ?>">
                </div>
            </div>
            <small class="help-text">Maximum price must be greater than minimum price. Both must be 0 or greater.</small>
        </div>
        
        <div class="form-group">
            <label for="time">Duration (minutes): <span class="required">*</span></label>
            <input type="number" min="1" max="480" name="time" id="time" 
                   value="<?php echo htmlspecialchars($_POST['time'] ?? $service['Time']); ?>" required>
            <small class="help-text">Must be between 1 and 480 minutes (8 hours)</small>
        </div>
        
        <div class="form-group">
            <label for="image">Service Image:</label>
            <?php if (!empty($service['ImgUrl'])): ?>
                <div class="current-image">
                    <img src="<?php echo htmlspecialchars($service['ImgUrl']); ?>" alt="Service Image">
                    <div class="image-info">Current image</div>
                </div>
            <?php endif; ?>
            <input type="file" name="image" id="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <small class="help-text">Leave empty to keep current image. Max size: 2MB. Allowed formats: JPG, JPEG, PNG, GIF, WebP</small>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Service</button>
            <a href="admin_dashboard.php?view=services" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

<script>
function togglePriceFields() {
    const priceType = document.getElementById('PriceType').value;
    const fixedPriceField = document.getElementById('fixedPriceField');
    const rangePriceFields = document.getElementById('rangePriceFields');
    const priceInput = document.getElementById('price');
    const minPriceInput = document.getElementById('MinPrice');
    const maxPriceInput = document.getElementById('MaxPrice');
    
    if (priceType === 'fixed') {
        fixedPriceField.style.display = 'block';
        rangePriceFields.style.display = 'none';
        priceInput.required = true;
        minPriceInput.required = false;
        maxPriceInput.required = false;
        // Clear range values when switching to fixed
        minPriceInput.value = '';
        maxPriceInput.value = '';
    } else if (priceType === 'range') {
        fixedPriceField.style.display = 'none';
        rangePriceFields.style.display = 'block';
        priceInput.required = false;
        minPriceInput.required = true;
        maxPriceInput.required = true;
        // Clear fixed price when switching to range
        priceInput.value = '';
    } else {
        fixedPriceField.style.display = 'none';
        rangePriceFields.style.display = 'none';
        priceInput.required = false;
        minPriceInput.required = false;
        maxPriceInput.required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePriceFields();
    
    // Add real-time validation for price range
    const minPriceInput = document.getElementById('MinPrice');
    const maxPriceInput = document.getElementById('MaxPrice');
    
    if (minPriceInput && maxPriceInput) {
        maxPriceInput.addEventListener('input', function() {
            const minPrice = parseFloat(minPriceInput.value);
            const maxPrice = parseFloat(maxPriceInput.value);
            
            if (minPrice && maxPrice && maxPrice <= minPrice) {
                maxPriceInput.setCustomValidity('Maximum price must be greater than minimum price');
            } else {
                maxPriceInput.setCustomValidity('');
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
