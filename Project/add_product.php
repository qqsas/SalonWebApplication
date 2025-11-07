<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Validate session and permissions
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

// Check user role and permissions
$allowed = false;
if ($_SESSION['Role'] === 'admin') {
    $allowed = true;
} elseif ($_SESSION['Role'] === 'barber') {
    // Check if barbers are allowed to add products
    include 'db.php';
    $featureCheck = $conn->prepare("SELECT IsEnabled FROM Features WHERE FeatureName = 'allow products'");
    if ($featureCheck && $featureCheck->execute()) {
        $result = $featureCheck->get_result();
        if ($row = $result->fetch_assoc()) {
            $allowed = (bool)$row['IsEnabled'];
        }
    }
    $featureCheck->close();
    $conn->close();
}

if (!$allowed) {
    header("Location: unauthorized.php");
    exit();
}

include 'db.php';
include 'header.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$name = $price = $stock = "";
$categories = [];
$newCategory = "";

// Configuration
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$uploadDir = "Img/products/";
$maxNameLength = 100;
$maxCategoryLength = 50;
$maxCategories = 10;

// Get all unique categories from existing products
$allCategories = [];

try {
    // First, try to extract categories from JSON arrays
    $jsonCategoryQuery = $conn->prepare("
        SELECT Category 
        FROM Products 
        WHERE Category IS NOT NULL 
        AND Category != 'null' 
        AND Category LIKE '[%'
        AND IsDeleted = 0
    ");
    
    if ($jsonCategoryQuery && $jsonCategoryQuery->execute()) {
        $jsonResult = $jsonCategoryQuery->get_result();
        while ($row = $jsonResult->fetch_assoc()) {
            if (!empty($row['Category'])) {
                try {
                    // Decode the JSON array
                    $catArray = json_decode($row['Category'], true);
                    if (is_array($catArray)) {
                        foreach ($catArray as $category) {
                            if (!empty($category) && is_string($category)) {
                                $cleanCat = trim($category);
                                if (strlen($cleanCat) <= $maxCategoryLength) {
                                    $allCategories[] = $cleanCat;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    // If JSON decoding fails, skip this entry
                    continue;
                }
            }
        }
        $jsonCategoryQuery->close();
    }

    // Also get legacy single categories
    $legacyCategoryQuery = $conn->prepare("
        SELECT Category 
        FROM Products 
        WHERE Category IS NOT NULL 
        AND Category != '' 
        AND Category NOT LIKE '[%'
        AND IsDeleted = 0
    ");
    
    if ($legacyCategoryQuery && $legacyCategoryQuery->execute()) {
        $legacyResult = $legacyCategoryQuery->get_result();
        while ($row = $legacyResult->fetch_assoc()) {
            if (!empty($row['Category'])) {
                $category = trim($row['Category']);
                // Clean up any escaped quotes or JSON artifacts
                $category = str_replace(['\"', '"', '[', ']'], '', $category);
                $category = trim($category);
                if (!empty($category) && strlen($category) <= $maxCategoryLength) {
                    $allCategories[] = $category;
                }
            }
        }
        $legacyCategoryQuery->close();
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

} catch (Exception $e) {
    error_log("Category loading error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Security token validation failed. Please try again.";
    } else {
        // Validate and sanitize inputs
        $name = trim($_POST['Name'] ?? '');
        $price = trim($_POST['Price'] ?? '');
        $stock = trim($_POST['Stock'] ?? '');
        $imgUrl = null;
        
        // Handle multiple categories
        $categories = [];
        if (isset($_POST['categories']) && is_array($_POST['categories'])) {
            $categories = array_map('trim', $_POST['categories']);
            $categories = array_filter($categories); // Remove empty values
            
            // Validate each category
            foreach ($categories as $category) {
                if (strlen($category) > $maxCategoryLength) {
                    $errors[] = "Category names must be less than {$maxCategoryLength} characters.";
                    break;
                }
                if (!preg_match('/^[a-zA-Z0-9\s\-_&]+$/', $category)) {
                    $errors[] = "Category names can only contain letters, numbers, spaces, hyphens, underscores, and ampersands.";
                    break;
                }
            }
            
            // Check category limit
            if (count($categories) > $maxCategories) {
                $errors[] = "Maximum {$maxCategories} categories allowed per product.";
            }
        }
        
        // Handle new category input
        $newCategory = trim($_POST['new_category'] ?? '');
        if (!empty($newCategory)) {
            if (strlen($newCategory) > $maxCategoryLength) {
                $errors[] = "New category must be less than {$maxCategoryLength} characters.";
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-_&]+$/', $newCategory)) {
                $errors[] = "New category can only contain letters, numbers, spaces, hyphens, underscores, and ampersands.";
            } else {
                $categories[] = $newCategory;
            }
        }
        
        // Remove duplicates and empty values
        $categories = array_unique(array_filter($categories));
        
        // Name validation
        if (empty($name)) {
            $errors[] = "Product name is required.";
        } elseif (strlen($name) > $maxNameLength) {
            $errors[] = "Product name must be less than {$maxNameLength} characters.";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-_.,!@#$%^&*()]+$/', $name)) {
            $errors[] = "Product name contains invalid characters.";
        }
        
        // Check for duplicate product name
        if (empty($errors)) {
            $checkStmt = $conn->prepare("SELECT ProductID FROM Products WHERE Name = ? AND IsDeleted = 0");
            if ($checkStmt) {
                $checkStmt->bind_param("s", $name);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $errors[] = "A product with this name already exists.";
                }
                $checkStmt->close();
            }
        }

        // Price validation
        if (empty($price)) {
            $errors[] = "Price is required.";
        } elseif (!is_numeric($price) || $price < 0) {
            $errors[] = "Price must be a valid positive number.";
        } elseif ($price > 1000000) {
            $errors[] = "Price cannot exceed R 1,000,000.";
        } else {
            $price = floatval($price);
            if ($price < 0.01) {
                $errors[] = "Price must be at least R 0.01.";
            }
        }

        // Stock validation
        if (!is_numeric($stock) || $stock < 0) {
            $errors[] = "Stock must be a valid non-negative number.";
        } elseif ($stock > 1000000) {
            $errors[] = "Stock cannot exceed 1,000,000 units.";
        } else {
            $stock = intval($stock);
        }

        // Categories validation
        if (empty($categories)) {
            $errors[] = "At least one category is required.";
        }

        // Handle image upload
        if (isset($_FILES['ImgFile']) && $_FILES['ImgFile']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['ImgFile']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                    UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
                    UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
                    UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                    UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
                ];
                $errors[] = $uploadErrors[$_FILES['ImgFile']['error']] ?? "Unknown upload error.";
            } else {
                $file = $_FILES['ImgFile'];
                $fileTmp = $file['tmp_name'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Check file size
                if ($fileSize > $maxFileSize) {
                    $errors[] = "File size must be less than " . ($maxFileSize / 1024 / 1024) . "MB.";
                }

                // Check file extension
                if (!in_array($fileExt, $allowedExtensions)) {
                    $errors[] = "Only " . implode(', ', $allowedExtensions) . " files are allowed.";
                }

                // Check MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $fileTmp);
                finfo_close($finfo);
                
                if (!in_array($mime, $allowedMimeTypes)) {
                    $errors[] = "Invalid file type. Please upload a valid image file.";
                }

                // Check if file is actually an image
                if (!getimagesize($fileTmp)) {
                    $errors[] = "Uploaded file is not a valid image.";
                }

                // Check for potential malicious content
                if ($fileExt === 'php' || preg_match('/\.(php|phtml|php3|php4|php5|phar|html|htm)/i', $fileName)) {
                    $errors[] = "File type not allowed for security reasons.";
                }
            }
        }

        // Process file upload if no errors
        if (empty($errors)) {
            // Create upload directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    $errors[] = "Failed to create upload directory.";
                }
            }

            if (empty($errors) && isset($_FILES['ImgFile']) && $_FILES['ImgFile']['error'] === UPLOAD_ERR_OK) {
                // Generate secure filename
                $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                $baseName = substr($baseName, 0, 100); // Limit filename length
                
                // Generate unique filename
                $newFileName = $baseName . '_' . uniqid() . '.' . $fileExt;
                $targetFile = $uploadDir . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($fileTmp, $targetFile)) {
                    // Verify the moved file is actually an image
                    if (getimagesize($targetFile)) {
                        $imgUrl = $targetFile;
                        
                        // Set proper permissions
                        chmod($targetFile, 0644);
                    } else {
                        $errors[] = "Uploaded file is not a valid image.";
                        unlink($targetFile); // Remove invalid file
                    }
                } else {
                    $errors[] = "Failed to save uploaded file. Please try again.";
                }
            }
        }

        // Insert into database if no errors
        if (empty($errors)) {
            // Convert to JSON for storage
            $categoryJson = !empty($categories) ? json_encode(array_values($categories)) : null;
            
            $stmt = $conn->prepare("INSERT INTO Products (Name, Price, Category, Stock, ImgUrl) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sdsss", $name, $price, $categoryJson, $stock, $imgUrl);
                if ($stmt->execute()) {
                    $success = "Product added successfully!";
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    // Clear form
                    $name = $price = $stock = '';
                    $categories = [];
                    $newCategory = '';
                } else {
                    $errors[] = "Database error: " . $stmt->error;
                    // Clean up uploaded file if database insert failed
                    if (isset($imgUrl) && file_exists($imgUrl)) {
                        unlink($imgUrl);
                    }
                }
                $stmt->close();
            } else {
                $errors[] = "Database preparation error: " . $conn->error;
                // Clean up uploaded file
                if (isset($imgUrl) && file_exists($imgUrl)) {
                    unlink($imgUrl);
                }
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - Admin</title>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .error-message { 
            background: #ffebee; 
            color: #c62828; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #c62828;
            margin-bottom: 15px;
        }
        .success-message { 
            background: #e8f5e8; 
            color: #2e7d32; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #2e7d32;
            margin-bottom: 15px;
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
        input[type="text"], input[type="number"], input[type="file"], select, textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .categories-container {
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 4px;
            background: #fafafa;
        }
        .categories-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        .category-checkbox-item {
            margin-bottom: 8px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal;
        }
        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        .checkbox-text {
            flex: 1;
        }
        .new-category {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        .help-text {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
            display: block;
        }
        .form-actions {
            margin-top: 30px;
            text-align: center;
        }
        .btn-primary, .btn-cancel {
            padding: 12px 24px;
            margin: 0 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background: #45a049;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        .btn-cancel:hover {
            background: #5a6268;
        }
        .no-categories {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .char-count {
            font-size: 0.8em;
            color: #666;
            text-align: right;
            margin-top: 5px;
        }
        .file-preview {
            margin-top: 10px;
            text-align: center;
        }
        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        .required {
            color: #c62828;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Product</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <strong>Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-group">
                <label for="Name">Product Name <span class="required">*</span></label>
                <input type="text" name="Name" id="Name" value="<?= htmlspecialchars($name) ?>" 
                       maxlength="<?= $maxNameLength ?>" 
                       pattern="[a-zA-Z0-9\s\-_.,!@#$%^&*()]+"
                       title="Letters, numbers, spaces, and basic punctuation only"
                       required>
                <div class="char-count">
                    <span id="nameCount">0</span>/<?= $maxNameLength ?> characters
                </div>
            </div>

            <div class="form-group">
                <label for="Price">Price (R) <span class="required">*</span></label>
                <input type="number" step="0.01" min="0.01" max="1000000" name="Price" id="Price" 
                       value="<?= htmlspecialchars($price) ?>" required>
                <div class="help-text">Minimum: R 0.01, Maximum: R 1,000,000</div>
            </div>

            <div class="form-group">
                <label>Categories <span class="required">*</span></label>
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
                                                    <?= (in_array($cat, $categories)) ? 'checked' : ''; ?>>
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
                        <input type="text" name="new_category" id="new_category" 
                               placeholder="Enter new category name" 
                               maxlength="<?= $maxCategoryLength ?>"
                               pattern="[a-zA-Z0-9\s\-_&]+"
                               title="Letters, numbers, spaces, hyphens, underscores, and ampersands only"
                               value="<?= htmlspecialchars($newCategory) ?>">
                        <div class="char-count">
                            <span id="categoryCount">0</span>/<?= $maxCategoryLength ?> characters
                        </div>
                        <small class="help-text">You can select existing categories and/or add a new one. Maximum <?= $maxCategories ?> categories total.</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="Stock">Stock <span class="required">*</span></label>
                <input type="number" min="0" max="1000000" name="Stock" id="Stock" 
                       value="<?= htmlspecialchars($stock) ?>" required>
                <div class="help-text">Maximum: 1,000,000 units</div>
            </div>

            <div class="form-group">
                <label for="ImgFile">Upload Product Image</label>
                <input type="file" name="ImgFile" id="ImgFile" 
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <div class="help-text">
                    Allowed formats: <?= implode(', ', $allowedExtensions) ?><br>
                    Maximum file size: <?= ($maxFileSize / 1024 / 1024) ?>MB
                </div>
                <div id="filePreview" class="file-preview" style="display: none;">
                    <img id="previewImage" src="#" alt="Preview">
                </div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('Name');
            const categoryInput = document.getElementById('new_category');
            const nameCount = document.getElementById('nameCount');
            const categoryCount = document.getElementById('categoryCount');
            const fileInput = document.getElementById('ImgFile');
            const filePreview = document.getElementById('filePreview');
            const previewImage = document.getElementById('previewImage');
            const form = document.getElementById('productForm');
            const submitBtn = document.getElementById('submitBtn');
            const checkboxes = document.querySelectorAll('input[name="categories[]"]');

            // Character count for name
            nameInput.addEventListener('input', function() {
                nameCount.textContent = this.value.length;
            });
            nameCount.textContent = nameInput.value.length;

            // Character count for new category
            categoryInput.addEventListener('input', function() {
                categoryCount.textContent = this.value.length;
            });
            categoryCount.textContent = categoryInput.value.length;

            // File preview
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        filePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    filePreview.style.display = 'none';
                }
            });

            // File size validation
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                const maxSize = <?= $maxFileSize ?>;
                if (file && file.size > maxSize) {
                    alert('File size must be less than <?= ($maxFileSize / 1024 / 1024) ?>MB');
                    this.value = '';
                    filePreview.style.display = 'none';
                }
            });

            // Category limit validation
            function updateCategoryCount() {
                const checkedCount = document.querySelectorAll('input[name="categories[]"]:checked').length;
                const newCategory = categoryInput.value.trim();
                const totalCategories = checkedCount + (newCategory ? 1 : 0);
                
                if (totalCategories > <?= $maxCategories ?>) {
                    alert('Maximum <?= $maxCategories ?> categories allowed. Please unselect some categories or remove the new category.');
                    return false;
                }
                return true;
            }

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateCategoryCount);
            });

            categoryInput.addEventListener('input', updateCategoryCount);

            // Form submission handling
            form.addEventListener('submit', function(e) {
                if (!updateCategoryCount()) {
                    e.preventDefault();
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Adding Product...';
            });

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>
