<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

$service_id = $_GET['id'] ?? null;
if (!$service_id) {
    echo "Invalid service ID.";
    exit();
}

// Get all unique categories from existing services - FIXED QUERY
$allCategories = [];

// First, try to extract categories from JSON arrays
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
                // Decode the JSON array
                $categories = json_decode($row['Category'], true);
                if (is_array($categories)) {
                    foreach ($categories as $category) {
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
    FROM Services 
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $priceType = $_POST['PriceType'] ?? 'fixed';
    $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
    $minPrice = !empty($_POST['MinPrice']) ? floatval($_POST['MinPrice']) : null;
    $maxPrice = !empty($_POST['MaxPrice']) ? floatval($_POST['MaxPrice']) : null;
    $time = intval($_POST['time']);
    $newImage = null;
    
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

    // Validate inputs
    $errors = [];
    if (empty($name)) $errors[] = "Service name is required.";
    if (empty($categories)) $errors[] = "At least one category is required.";
    if ($time <= 0) $errors[] = "Service time must be greater than 0.";
    
    // Price validation based on price type
    if ($priceType === 'fixed') {
        if ($price === null || $price < 0) $errors[] = "Price must be a valid positive number.";
        // Set min and max price to null for fixed pricing
        $minPrice = null;
        $maxPrice = null;
    } else { // range pricing
        if ($minPrice === null || $minPrice < 0) $errors[] = "Minimum price must be a valid positive number.";
        if ($maxPrice === null || $maxPrice < 0) $errors[] = "Maximum price must be a valid positive number.";
        if ($minPrice >= $maxPrice) $errors[] = "Maximum price must be greater than minimum price.";
        // Set fixed price to null for range pricing
        $price = null;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['image']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Only JPG, JPEG, PNG, GIF, and WebP images are allowed.";
        } else {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $newImage = uniqid() . "." . strtolower($ext);
            $targetDir = __DIR__ . "/Img/";
            $targetFile = $targetDir . $newImage;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $stmt = $conn->prepare("SELECT ImgUrl FROM Services WHERE ServicesID=?");
                $stmt->bind_param("i", $service_id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($old && !empty($old['ImgUrl'])) {
                    $oldFile = $targetDir . $old['ImgUrl'];
                    if (file_exists($oldFile)) unlink($oldFile);
                }
            } else {
                $errors[] = "Failed to upload new image.";
                $newImage = null;
            }
        }
    }

    if (empty($errors)) {
        if ($newImage) {
            $stmt = $conn->prepare("UPDATE Services SET Name=?, Category=?, Description=?, Price=?, PriceType=?, MinPrice=?, MaxPrice=?, Time=?, ImgUrl=? WHERE ServicesID=?");
            $stmt->bind_param("sssssdsisi", $name, $categoryJson, $description, $price, $priceType, $minPrice, $maxPrice, $time, $newImage, $service_id);
        } else {
            $stmt = $conn->prepare("UPDATE Services SET Name=?, Category=?, Description=?, Price=?, PriceType=?, MinPrice=?, MaxPrice=?, Time=? WHERE ServicesID=?");
            $stmt->bind_param("sssssdsii", $name, $categoryJson, $description, $price, $priceType, $minPrice, $maxPrice, $time, $service_id);
        }

        if ($stmt->execute()) {
            $successMessage = "Service updated successfully.";
        } else {
            $errors[] = "Error updating service: " . $conn->error;
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM Services WHERE ServicesID=?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

if (!$service) {
    echo "Service not found.";
    exit();
}

// Parse existing categories from JSON
$currentCategories = [];
if (!empty($service['Category'])) {
    // If it's a JSON string, decode it
    if (is_string($service['Category']) && $service['Category'][0] === '[') {
        $currentCategories = json_decode($service['Category'], true) ?: [];
    } 
    // If it's already an array, use it directly
    elseif (is_array($service['Category'])) {
        $currentCategories = $service['Category'];
    }
    // If it's a single string (legacy data), wrap it in an array
    elseif (is_string($service['Category'])) {
        $currentCategories = [$service['Category']];
    }
}

// Clean current categories
$currentCategories = array_map(function($cat) {
    return trim(str_replace(['\"', '"', '[', ']', '\\'], '', $cat));
}, $currentCategories);
$currentCategories = array_filter($currentCategories);

// Set default price type if not set (for existing services)
$priceType = $service['PriceType'] ?? 'fixed';
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Service</h2>
    <link href="addedit.css" rel="stylesheet">
    
    <?php
    if (!empty($errors)) {
        echo "<div class='error-message'><ul>";
        foreach ($errors as $error) echo "<li>" . htmlspecialchars($error) . "</li>";
        echo "</ul></div>";
    }
    
    if (isset($successMessage)) {
        echo "<div class='success-message'>" . htmlspecialchars($successMessage) . "</div>";
    }
    ?>
    
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Service Name:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($service['Name']); ?>" required>
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
            <label for="description">Description:</label>
            <textarea name="description" id="description" rows="4"><?php echo htmlspecialchars($service['Description']); ?></textarea>
        </div>

        <div class="form-group">
            <label for="PriceType">Price Type:</label>
            <select name="PriceType" id="PriceType" onchange="togglePriceFields()" required>
                <option value="fixed" <?php echo $priceType === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                <option value="range" <?php echo $priceType === 'range' ? 'selected' : ''; ?>>Price Range</option>
            </select>
        </div>

        <div class="form-group" id="fixedPriceField" style="display: <?php echo $priceType === 'fixed' ? 'block' : 'none'; ?>;">
            <label for="price">Price (R):</label>
            <input type="number" step="0.01" min="0" name="price" id="price" 
                   value="<?php echo htmlspecialchars($service['Price'] ?? ''); ?>" 
                   <?php echo $priceType === 'fixed' ? 'required' : ''; ?>>
        </div>

        <div class="form-group" id="rangePriceFields" style="display: <?php echo $priceType === 'range' ? 'block' : 'none'; ?>;">
            <div class="price-range-container">
                <div class="price-range-item">
                    <label for="MinPrice">Minimum Price (R):</label>
                    <input type="number" step="0.01" min="0" name="MinPrice" id="MinPrice" 
                           value="<?php echo htmlspecialchars($service['MinPrice'] ?? ''); ?>" 
                           <?php echo $priceType === 'range' ? 'required' : ''; ?>>
                </div>
                <div class="price-range-item">
                    <label for="MaxPrice">Maximum Price (R):</label>
                    <input type="number" step="0.01" min="0" name="MaxPrice" id="MaxPrice" 
                           value="<?php echo htmlspecialchars($service['MaxPrice'] ?? ''); ?>" 
                           <?php echo $priceType === 'range' ? 'required' : ''; ?>>
                </div>
            </div>
            <small class="help-text">Maximum price must be greater than minimum price.</small>
        </div>
        
        <div class="form-group">
            <label for="time">Duration (minutes):</label>
            <input type="number" min="1" name="time" id="time" value="<?php echo htmlspecialchars($service['Time']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="image">Service Image:</label>
            <?php if (!empty($service['ImgUrl'])): ?>
                <div class="current-image">
                    <img src="<?php echo htmlspecialchars($service['ImgUrl']); ?>" width="120" alt="Service Image">
                    <div class="image-info">Current image</div>
                </div>
            <?php endif; ?>
            <input type="file" name="image" id="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
            <small class="help-text">Leave empty to keep current image. Allowed formats: JPG, JPEG, PNG, GIF, WebP</small>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">Update Service</button>
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
    } else {
        fixedPriceField.style.display = 'none';
        rangePriceFields.style.display = 'block';
        priceInput.required = false;
        minPriceInput.required = true;
        maxPriceInput.required = true;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePriceFields();
});
</script>

<?php include 'footer.php'; ?>
