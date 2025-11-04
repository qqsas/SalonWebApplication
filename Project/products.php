<?php
session_start();
include 'db.php';
include 'header.php';

// Handle input
$searchTerm = $_GET['search'] ?? '';
$sortOption = $_GET['sort'] ?? 'newest';
$selectedCategories = $_GET['category'] ?? []; // array now

if (!is_array($selectedCategories)) {
    $selectedCategories = [$selectedCategories];
}

// Fetch all unique categories from Products table (handling JSON arrays)
$allCategories = [];

// First, extract categories from JSON arrays
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
                $categories = json_decode($row['Category'], true);
                if (is_array($categories)) {
                    foreach ($categories as $category) {
                        if (!empty($category) && is_string($category)) {
                            $allCategories[] = trim($category);
                        }
                    }
                }
            } catch (Exception $e) {
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
            $category = str_replace(['\"', '"', '[', ']'], '', $category);
            if (!empty($category)) {
                $allCategories[] = $category;
            }
        }
    }
}

// Clean and deduplicate categories
$allCategories = array_map(function($cat) {
    return trim(str_replace(['\"', '"', '[', ']', '\\'], '', $cat));
}, $allCategories);
$allCategories = array_filter($allCategories);
$allCategories = array_unique($allCategories);
sort($allCategories);

// Base query
$query = "SELECT ProductID, Name, Price, Category, Stock, ImgUrl, CreatedAt 
          FROM Products
          WHERE Stock > 0";

$params = [];
$types = "";

// Search filter
if (!empty($searchTerm)) {
    $query .= " AND (Name LIKE ? OR Category LIKE ?)";
    $searchWildcard = "%{$searchTerm}%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= "ss";
}

// Category filter - handle multiple selected categories
if (!empty($selectedCategories)) {
    $query .= " AND (";
    $categoryConditions = [];
    foreach ($selectedCategories as $cat) {
        $categoryConditions[] = "(Category LIKE ? OR Category LIKE ?)";
        $jsonPattern = '%"' . $cat . '"%';
        $legacyPattern = '%' . $cat . '%';
        $params[] = $jsonPattern;
        $params[] = $legacyPattern;
        $types .= "ss";
    }
    $query .= implode(" OR ", $categoryConditions) . ")";
}

// Sort logic
switch ($sortOption) {
    case 'price_asc':
        $query .= " ORDER BY Price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY Price DESC";
        break;
    case 'name_asc':
        $query .= " ORDER BY Name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY Name DESC";
        break;
    default:
        $query .= " ORDER BY CreatedAt DESC"; // newest first
        break;
}

$stmt = $conn->prepare($query);

// Bind parameters if needed
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Process products to parse categories for display
foreach ($products as &$product) {
    $categories = [];
    if (!empty($product['Category'])) {
        if (is_string($product['Category']) && $product['Category'][0] === '[') {
            $categories = json_decode($product['Category'], true) ?: [];
        } elseif (is_array($product['Category'])) {
            $categories = $product['Category'];
        } elseif (is_string($product['Category'])) {
            $categories = [$product['Category']];
        }
    }
    
    $categories = array_map(function($cat) {
        return trim(str_replace(['\"', '"', '[', ']', '\\'], '', $cat));
    }, $categories);
    $categories = array_filter($categories);
    
    $product['ParsedCategories'] = $categories;
    $product['CategoriesDisplay'] = !empty($categories) ? implode(', ', $categories) : 'Uncategorized';
}
unset($product); // Break reference
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Products - Kumar Kailey's Hair & Beauty</title>
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .checkbox-group { display:flex; flex-wrap:wrap; gap:10px; margin:10px 0; }
        .checkbox-group label { display:flex; align-items:center; gap:5px; }
    </style>
</head>
<body>

<h1 class="product-title">All Products</h1>

<!-- Search and Filter Section -->
<div class="filter-container">
    <form method="get" class="filter-form" id="productFilterForm">
        <input type="text" name="search" id="productSearch" class="filter-search"
               placeholder="Search products or categories..." value="<?= htmlspecialchars($searchTerm) ?>">

        <select name="sort" id="productSort" class="filter-sort">
            <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc" <?= $sortOption === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_desc" <?= $sortOption === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="name_asc" <?= $sortOption === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
            <option value="name_desc" <?= $sortOption === 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
        </select>

        <!-- Checkboxes for multiple categories -->
        <div class="checkbox-group">
            <?php foreach ($allCategories as $cat): ?>
                <label>
                    <input type="checkbox" name="category[]" value="<?= htmlspecialchars($cat) ?>"
                        <?= in_array($cat, $selectedCategories) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="filter-button" id="applyButton">Apply</button>
    </form>
</div>

<!-- Product Listing Section -->
<div class="product-section">
    <?php if (empty($products)): ?>
        <p class="no-products-message">No products found.</p>
    <?php else: ?>
        <div class="product-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
                <div class="product-card"
                     data-name="<?= htmlspecialchars(strtolower($product['Name'])) ?>"
                     data-price="<?= $product['Price'] ?>"
                     data-categories="<?= htmlspecialchars(strtolower(implode(',', $product['ParsedCategories']))) ?>">
                    <?php 
                    $product_img = !empty($product['ImgUrl']) ? "" . htmlspecialchars($product['ImgUrl']) : 'Img/default-product.jpg';
                    ?>
                    <img src="<?= $product_img ?>" 
                         alt="<?= htmlspecialchars($product['Name']) ?>" 
                         class="product-image"
                         onerror="this.src='Img/default-product.jpg'">
                    <div class="product-details">
                        <h2 class="product-name"><?= htmlspecialchars($product['Name']) ?></h2>
                        <p class="product-price">R<?= number_format($product['Price'], 2) ?></p>
                        
                        <div class="product-category">
                            <div class="categories-tags">
                                <?php foreach ($product['ParsedCategories'] as $category): ?>
                                    <span class="category-tag"><?= htmlspecialchars($category) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="product-stock">
                            <?php
                            $stock = $product['Stock'];
                            if ($stock > 10) {
                                echo '<span class="stock-status in-stock">In Stock (' . $stock . ')</span>';
                            } elseif ($stock > 0) {
                                echo '<span class="stock-status low-stock">Low Stock (' . $stock . ')</span>';
                            } else {
                                echo '<span class="stock-status out-of-stock">Out of Stock</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="product-buttons">
                            <a href="product_details.php?id=<?= $product['ProductID'] ?>" class="btn">View Product</a>
                            <?php if ($product['Stock'] > 0): ?>
                            <form method="POST" action="add_to_cart.php" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $product['ProductID'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn">Add to Cart</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="no-results">No products found matching your filters.</div>
    <?php endif; ?>
</div>

<script>
// ===== CLIENT-SIDE FILTERING AND SORTING =====
const searchInput = document.getElementById('productSearch');
const sortSelect = document.getElementById('productSort');
const productsGrid = document.getElementById('productsGrid');
const productCards = Array.from(productsGrid ? productsGrid.children : []);
const noResults = document.querySelector('.no-results');
const categoryCheckboxes = document.querySelectorAll('.checkbox-group input[type=checkbox]');

function getSelectedCategories() {
    return Array.from(categoryCheckboxes)
                .filter(chk => chk.checked)
                .map(chk => chk.value.toLowerCase());
}

// FILTER FUNCTION
function filterProducts() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedCats = getSelectedCategories();
    let hasMatches = false;

    productCards.forEach(card => {
        const name = card.dataset.name;
        const categories = card.dataset.categories;

        const matchSearch = name.includes(searchTerm) || categories.includes(searchTerm);
        const matchCategory = selectedCats.length === 0 || selectedCats.some(cat => categories.includes(cat));

        const match = matchSearch && matchCategory;
        card.style.display = match ? '' : 'none';
        if (match) hasMatches = true;
    });

    noResults.classList.toggle('show', !hasMatches);
}

// SORT FUNCTION
function sortProducts() {
    const sortValue = sortSelect.value;
    let sortedCards = [...productCards].filter(card => card.style.display !== 'none');

    sortedCards.sort((a,b) => {
        const priceA = parseFloat(a.dataset.price);
        const priceB = parseFloat(b.dataset.price);
        const nameA = a.dataset.name;
        const nameB = b.dataset.name;

        switch(sortValue) {
            case 'price_asc': return priceA - priceB;
            case 'price_desc': return priceB - priceA;
            case 'name_asc': return nameA.localeCompare(nameB);
            case 'name_desc': return nameB.localeCompare(nameA);
            case 'newest':
            default: return 0;
        }
    });

    sortedCards.forEach(card => productsGrid.appendChild(card));
}

// EVENT LISTENERS
searchInput.addEventListener('input', () => { filterProducts(); sortProducts(); });
sortSelect.addEventListener('change', sortProducts);
categoryCheckboxes.forEach(chk => chk.addEventListener('change', () => { filterProducts(); sortProducts(); }));

const applyButton = document.getElementById('applyButton');
applyButton.addEventListener('click', () => { filterProducts(); sortProducts(); });

document.addEventListener('DOMContentLoaded', function() {
    filterProducts();
    sortProducts();
});
</script>

<?php
include 'footer.php';
?>
</body>
</html>

