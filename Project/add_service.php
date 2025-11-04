<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Validate session and role
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
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

// Configuration
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$uploadDir = "Img/services/";
$maxNameLength = 100;
$maxDescriptionLength = 65535; // TEXT field in MySQL
$maxCategoryLength = 255; // longtext field but we'll limit for practical purposes
$maxCategories = 10;
$maxTime = 1440; // 24 hours in minutes
$minTime = 1; // 1 minute
$maxPrice = 1000000; // R 1,000,000

// Initialize form variables
$name = $description = $price = $minPrice = $maxPrice = $time = '';
$priceType = 'fixed';
$categories = [];
$newCategory = '';

// Get all unique categories from existing services
$allCategories = [];

try {
    // First, try to extract categories from JSON arrays
    $jsonCategoryQuery = $conn->prepare("
        SELECT Category 
        FROM Services 
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
        FROM Services 
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Security token validation failed. Please try again.";
    } else {
        // Validate and sanitize inputs
        $name = trim($_POST['Name'] ?? '');
        $description = trim($_POST['Description'] ?? '');
        $priceType = $_POST['PriceType'] ?? 'fixed';
        $price = trim($_POST['Price'] ?? '');
        $minPrice = trim($_POST['MinPrice'] ?? '');
        $maxPrice = trim($_POST['MaxPrice'] ?? '');
        $time = trim($_POST['Time'] ?? '');
        $imageName = null;
        
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
                $errors[] = "Maximum {$maxCategories} categories allowed per service.";
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
            $errors[] = "Service name is required.";
        } elseif (strlen($name) > $maxNameLength) {
            $errors[] = "Service name must be less than {$maxNameLength} characters.";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-_.,!@#$%^&*()]+$/', $name)) {
            $errors[] = "Service name contains invalid characters.";
        }
        
        // Check for duplicate service name
        if (empty($errors)) {
            $checkStmt = $conn->prepare("SELECT ServicesID FROM Services WHERE Name = ? AND IsDeleted = 0");
            if ($checkStmt) {
                $checkStmt->bind_param("s", $name);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $errors[] = "A service with this name already exists.";
                }
                $checkStmt->close();
            }
        }

        // Description validation
        if (!empty($description) && strlen($description) > $maxDescriptionLength) {
            $errors[] = "Description must be less than {$maxDescriptionLength} characters.";
        }

        // Price type validation
        if (!in_array($priceType, ['fixed', 'range'])) {
            $errors[] = "Invalid price type selected.";
        }

        // Price validation based on price type
        if ($priceType === 'fixed') {
            if (empty($price)) {
                $errors[] = "Price is required for fixed pricing.";
            } elseif (!is_numeric($price) || $price < 0) {
                $errors[] = "Price must be a valid positive number.";
            } elseif ($price > $maxPrice) {
                $errors[] = "Price cannot exceed R " . number_format($maxPrice, 2) . ".";
            } else {
                $price = floatval($price);
                if ($price < 0.01) {
                    $errors[] = "Price must be at least R 0.01.";
                }
            }
            // Set min and max price to null for fixed pricing
            $minPrice = null;
            $maxPrice = null;
        } else { // range pricing
            if (empty($minPrice)) {
                $errors[] = "Minimum price is required for range pricing.";
            } elseif (!is_numeric($minPrice) || $minPrice < 0) {
                $errors[] = "Minimum price must be a valid positive number.";
            } elseif ($minPrice > $maxPrice) {
                $errors[] = "Minimum price cannot exceed maximum price.";
            } else {
                $minPrice = floatval($minPrice);
                if ($minPrice < 0.01) {
                    $errors[] = "Minimum price must be at least R 0.01.";
                }
            }
            
            if (empty($maxPrice)) {
                $errors[] = "Maximum price is required for range pricing.";
            } elseif (!is_numeric($maxPrice) || $maxPrice < 0) {
                $errors[] = "Maximum price must be a valid positive number.";
            } elseif ($maxPrice > $maxPrice) {
                $errors[] = "Maximum price cannot exceed R " . number_format($maxPrice, 2) . ".";
            } else {
                $maxPrice = floatval($maxPrice);
            }
            
            if (empty($errors) && $minPrice >= $maxPrice) {
                $errors[] = "Maximum price must be greater than minimum price.";
            }
            // Set fixed price to null for range pricing
            $price = null;
        }

        // Time validation
        if (empty($time)) {
            $errors[] = "Service duration is required.";
        } elseif (!is_numeric($time) || $time < $minTime) {
            $errors[] = "Service duration must be at least {$minTime} minute.";
        } elseif ($time > $maxTime) {
            $errors[] = "Service duration cannot exceed {$maxTime} minutes.";
        } else {
            $time = intval($time);
        }

        // Categories validation
        if (empty($categories)) {
            $errors[] = "At least one category is required.";
        }

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                    UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
                    UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
                    UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                    UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
                ];
                $errors[] = $uploadErrors[$_FILES['image']['error']] ?? "Unknown upload error.";
            } else {
                $file = $_FILES['image'];
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

            if (empty($errors) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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
                        $imageName = "Img/services/" . $newFileName;
                        
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
            
            $stmt = $conn->prepare("INSERT INTO Services (Name, Category, Description, Price, PriceType, MinPrice, MaxPrice, Time, ImgUrl) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                // Handle null values for price fields
                $priceValue = $priceType === 'fixed' ? $price : null;
                $minPriceValue = $priceType === 'range' ? $minPrice : null;
                $maxPriceValue = $priceType === 'range' ? $maxPrice : null;
                
                $stmt->bind_param("sssdssdis", $name, $categoryJson, $description, $priceValue, $priceType, $minPriceValue, $maxPriceValue, $time, $imageName);
                
                if ($stmt->execute()) {
                    $success = "Service added successfully!";
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    // Clear form
                    $name = $description = $price = $minPrice = $maxPrice = $time = '';
                    $priceType = 'fixed';
                    $categories = [];
                    $newCategory = '';
                } else {
                    $errors[] = "Database error: " . $stmt->error;
                    // Clean up uploaded file if database insert failed
                    if (isset($imageName) && file_exists(str_replace('Img/services/', $uploadDir, $imageName))) {
                        unlink(str_replace('Img/services/', $uploadDir, $imageName));
                    }
                }
                $stmt->close();
            } else {
                $errors[] = "Database preparation error: " . $conn->error;
                // Clean up uploaded file
                if (isset($imageName) && file_exists(str_replace('Img/services/', $uploadDir, $imageName))) {
                    unlink(str_replace('Img/services/', $uploadDir, $imageName));
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
    <title>Add New Service - Admin</title>
    <link href="addedit.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h2>Add New Service</h2>

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

        <form method="POST" action="" enctype="multipart/form-data" id="serviceForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-group">
                <label for="Name">Service Name <span class="required">*</span></label>
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
                <label for="Description">Description:</label>
                <textarea name="Description" id="Description" rows="4" 
                          maxlength="<?= $maxDescriptionLength ?>"><?= htmlspecialchars($description) ?></textarea>
                <div class="char-count">
                    <span id="descCount">0</span>/<?= $maxDescriptionLength ?> characters
                </div>
            </div>

            <div class="form-group">
                <label for="PriceType">Price Type <span class="required">*</span></label>
                <select name="PriceType" id="PriceType" required>
                    <option value="fixed" <?= $priceType === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                    <option value="range" <?= $priceType === 'range' ? 'selected' : ''; ?>>Price Range</option>
                </select>
            </div>

<div class="form-group" id="fixedPriceField" style="display: <?= $priceType === 'fixed' ? 'block' : 'none'; ?>;">
    <label for="Price">Price (R) <span class="required">*</span></label>
    <input type="number" step="0.01" min="0.01" max="<?= is_numeric($maxPrice) ? $maxPrice : 1000000 ?>" name="Price" id="Price" 
           value="<?= htmlspecialchars($price) ?>" 
           <?= $priceType === 'fixed' ? 'required' : ''; ?>>
    <div class="help-text">
        Minimum: R 0.01, Maximum: R <?= number_format(is_numeric($maxPrice) ? $maxPrice : 1000000, 2) ?>
    </div>
</div>

<div class="form-group" id="rangePriceFields" style="display: <?= $priceType === 'range' ? 'block' : 'none'; ?>;">
    <div class="price-range-container">
        <div class="price-range-item">
            <label for="MinPrice">Minimum Price (R) <span class="required">*</span></label>
            <input type="number" step="0.01" min="0.01" max="<?= is_numeric($maxPrice) ? $maxPrice : 1000000 ?>" name="MinPrice" id="MinPrice" 
                   value="<?= htmlspecialchars($minPrice) ?>" 
                   <?= $priceType === 'range' ? 'required' : ''; ?>>
        </div>
        <div class="price-range-item">
            <label for="MaxPrice">Maximum Price (R) <span class="required">*</span></label>
            <input type="number" step="0.01" min="0.01" max="<?= is_numeric($maxPrice) ? $maxPrice : 1000000 ?>" name="MaxPrice" id="MaxPrice" 
                   value="<?= htmlspecialchars($maxPrice) ?>" 
                   <?= $priceType === 'range' ? 'required' : ''; ?>>
        </div>
    </div>
    <small class="help-text">
        Maximum price must be greater than minimum price. Maximum allowed: R <?= number_format(is_numeric($maxPrice) ? $maxPrice : 1000000, 2) ?>
    </small>
</div>

<div class="form-group">
    <label for="Time">Duration (minutes) <span class="required">*</span></label>
    <input type="number" min="<?= $minTime ?>" max="<?= $maxTime ?>" name="Time" id="Time" 
           value="<?= htmlspecialchars($time) ?>" required>
    <div class="help-text">Minimum: <?= $minTime ?> minute, Maximum: <?= $maxTime ?> minutes (24 hours)</div>
</div>

<div class="form-group">
    <label for="image">Service Image</label>
    <input type="file" name="image" id="image" 
           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
    <div class="help-text">
        Allowed formats: <?= implode(', ', $allowedExtensions) ?><br>
        Maximum file size: <?= ($maxFileSize / 1024 / 1024) ?>MB
    </div>
    <div id="filePreview" class="file-preview" style="display: none;">
        <img id="previewImage" src="#" alt="Preview">
    </div>
</div>

<div class="form-actions" style="margin-top: 40px; text-align: center;">
    <button type="submit" class="btn-primary" id="submitBtn" style="display: inline-block;">
        Add Service
    </button>
    <a href="admin_dashboard.php?view=services" class="btn-cancel" style="display: inline-block;">
        Cancel
    </a>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('Name');
            const descInput = document.getElementById('Description');
            const categoryInput = document.getElementById('new_category');
            const nameCount = document.getElementById('nameCount');
            const descCount = document.getElementById('descCount');
            const categoryCount = document.getElementById('categoryCount');
            const fileInput = document.getElementById('image');
            const filePreview = document.getElementById('filePreview');
            const previewImage = document.getElementById('previewImage');
            const priceTypeSelect = document.getElementById('PriceType');

            // Character counters
            function updateCount(input, counter) {
                counter.textContent = input.value.length;
            }

            [nameInput, descInput, categoryInput].forEach((input) => {
                const counter = document.getElementById(input.id === 'Name' ? 'nameCount' : 
                                                        input.id === 'Description' ? 'descCount' : 'categoryCount');
                input.addEventListener('input', () => updateCount(input, counter));
                updateCount(input, counter);
            });

            // File preview
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        previewImage.src = e.target.result;
                        filePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    filePreview.style.display = 'none';
                }
            });

            // Toggle price fields
            function togglePriceFields() {
                const fixedField = document.getElementById('fixedPriceField');
                const rangeFields = document.getElementById('rangePriceFields');
                const priceInput = document.getElementById('Price');
                const minPriceInput = document.getElementById('MinPrice');
                const maxPriceInput = document.getElementById('MaxPrice');

                if (priceTypeSelect.value === 'fixed') {
                    fixedField.style.display = 'block';
                    rangeFields.style.display = 'none';
                    priceInput.required = true;
                    minPriceInput.required = false;
                    maxPriceInput.required = false;
                } else {
                    fixedField.style.display = 'none';
                    rangeFields.style.display = 'block';
                    priceInput.required = false;
                    minPriceInput.required = true;
                    maxPriceInput.required = true;
                }
            }

            togglePriceFields();
            priceTypeSelect.addEventListener('change', togglePriceFields);
        });
    </script>
</body>
</html>

