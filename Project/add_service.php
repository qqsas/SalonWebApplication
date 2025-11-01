<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'header.php';

$errors = [];
$success = '';

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

// Debug: Uncomment the line below to see what categories are being extracted
// echo "<pre>Extracted Categories: "; print_r($allCategories); echo "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $description = trim($_POST['Description']);
    $priceType = $_POST['PriceType'] ?? 'fixed';
    $price = trim($_POST['Price'] ?? '');
    $minPrice = trim($_POST['MinPrice'] ?? '');
    $maxPrice = trim($_POST['MaxPrice'] ?? '');
    $time = trim($_POST['Time']);
    $imageName = null;
    
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
    if (empty($name)) $errors[] = "Service name is required.";
    if (empty($categories)) $errors[] = "At least one category is required.";
    if (empty($time) || $time <= 0) $errors[] = "Service time is required and must be greater than 0.";
    
    // Price validation based on price type
    if ($priceType === 'fixed') {
        if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a valid positive number.";
        // Set min and max price to null for fixed pricing
        $minPrice = null;
        $maxPrice = null;
    } else { // range pricing
        if (!is_numeric($minPrice) || $minPrice < 0) $errors[] = "Minimum price must be a valid positive number.";
        if (!is_numeric($maxPrice) || $maxPrice < 0) $errors[] = "Maximum price must be a valid positive number.";
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
            $imageName = uniqid() . "." . strtolower($ext);
            $targetDir = __DIR__ . "/Img/";
            
            // Create directory if it doesn't exist
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $targetFile = $targetDir . $imageName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $errors[] = "Failed to upload image.";
                $imageName = null;
            } else {
                // Add "Img/" to the stored URL
                $imageName = "Img/" . $imageName;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Services (Name, Category, Description, Price, PriceType, MinPrice, MaxPrice, Time, ImgUrl) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Bind parameters based on price type
        if ($priceType === 'fixed') {
            $stmt->bind_param("sssdssiss", $name, $categoryJson, $description, $price, $priceType, $minPrice, $maxPrice, $time, $imageName);
        } else {
            $stmt->bind_param("sssssdsis", $name, $categoryJson, $description, $price, $priceType, $minPrice, $maxPrice, $time, $imageName);
        }
        
        if ($stmt->execute()) {
            $success = "Service added successfully!";
            // Clear form
            $name = $description = $price = $minPrice = $maxPrice = $time = '';
            $priceType = 'fixed';
            $categories = [];
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Add New Service</h2>

<link href="addedit.css" rel="stylesheet">
<?php
if (!empty($errors)) {
    echo "<div class='error-message'><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul></div>";
}

if ($success) {
    echo "<div class='success-message'>" . htmlspecialchars($success) . "</div>";
}
?>

<form method="POST" action="" enctype="multipart/form-data">
    <div class="form-group">
        <label for="Name">Service Name:</label>
        <input type="text" name="Name" id="Name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
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
                                            <?php echo (isset($categories) && in_array($cat, $categories)) ? 'checked' : ''; ?>>
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
        </div>
    </div>

    <div class="form-group">
        <label for="Description">Description:</label>
        <textarea name="Description" id="Description" rows="4"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
    </div>

    <div class="form-group">
        <label for="PriceType">Price Type:</label>
        <select name="PriceType" id="PriceType" onchange="togglePriceFields()" required>
            <option value="fixed" <?php echo ($priceType ?? 'fixed') === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
            <option value="range" <?php echo ($priceType ?? 'fixed') === 'range' ? 'selected' : ''; ?>>Price Range</option>
        </select>
    </div>

    <div class="form-group" id="fixedPriceField" style="display: <?php echo ($priceType ?? 'fixed') === 'fixed' ? 'block' : 'none'; ?>;">
        <label for="Price">Price (R):</label>
        <input type="number" step="0.01" min="0" name="Price" id="Price" 
               value="<?php echo htmlspecialchars($price ?? ''); ?>" 
               <?php echo ($priceType ?? 'fixed') === 'fixed' ? 'required' : ''; ?>>
    </div>

    <div class="form-group" id="rangePriceFields" style="display: <?php echo ($priceType ?? 'fixed') === 'range' ? 'block' : 'none'; ?>;">
        <div class="price-range-container">
            <div class="price-range-item">
                <label for="MinPrice">Minimum Price (R):</label>
                <input type="number" step="0.01" min="0" name="MinPrice" id="MinPrice" 
                       value="<?php echo htmlspecialchars($minPrice ?? ''); ?>" 
                       <?php echo ($priceType ?? 'fixed') === 'range' ? 'required' : ''; ?>>
            </div>
            <div class="price-range-item">
                <label for="MaxPrice">Maximum Price (R):</label>
                <input type="number" step="0.01" min="0" name="MaxPrice" id="MaxPrice" 
                       value="<?php echo htmlspecialchars($maxPrice ?? ''); ?>" 
                       <?php echo ($priceType ?? 'fixed') === 'range' ? 'required' : ''; ?>>
            </div>
        </div>
        <small class="help-text">Maximum price must be greater than minimum price.</small>
    </div>

    <div class="form-group">
        <label for="Time">Duration (minutes):</label>
        <input type="number" min="1" name="Time" id="Time" value="<?php echo htmlspecialchars($time ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label for="image">Service Image:</label>
        <input type="file" name="image" id="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
        <small class="help-text">Allowed formats: JPG, JPEG, PNG, GIF, WebP</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Add Service</button>
        <a href="admin_dashboard.php?view=services" class="btn-cancel">Cancel</a>
    </div>
</form>

<script>
function togglePriceFields() {
    const priceType = document.getElementById('PriceType').value;
    const fixedPriceField = document.getElementById('fixedPriceField');
    const rangePriceFields = document.getElementById('rangePriceFields');
    const priceInput = document.getElementById('Price');
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
    } else {
        fixedPriceField.style.display = 'none';
        rangePriceFields.style.display = 'block';
        priceInput.required = false;
        minPriceInput.required = true;
        maxPriceInput.required = true;
        // Clear fixed price when switching to range
        priceInput.value = '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePriceFields();
});
</script>

<?php include 'footer.php'; ?>
