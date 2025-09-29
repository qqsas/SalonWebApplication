<?php
session_start();
include 'db.php';
include 'header.php';

// Handle input
$searchTerm = $_GET['search'] ?? '';
$sortOption = $_GET['sort'] ?? 'newest';
$selectedCategory = $_GET['category'] ?? '';

// Fetch distinct categories from Products table
$categoryQuery = "SELECT DISTINCT Category FROM Products";
$categoryResult = $conn->query($categoryQuery);
$categories = $categoryResult ? $categoryResult->fetch_all(MYSQLI_ASSOC) : [];

// Base query
$query = "SELECT ProductID, Name, Price, Category, Stock, ImgUrl, CreatedAt 
          FROM Products
          WHERE Stock > 0";

$params = [];
$types = "";

// Search filter
if (!empty($searchTerm)) {
    $query .= " AND Name LIKE ?";
    $searchWildcard = "%{$searchTerm}%";
    $params[] = $searchWildcard;
    $types .= "s";
}

// Category filter
if (!empty($selectedCategory)) {
    $query .= " AND Category = ?";
    $params[] = $selectedCategory;
    $types .= "s";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Products - Kumar Kailey’s Hair & Beauty</title>
    <link href="mobile.css" rel="stylesheet" media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<!-- Search and Filter Section -->
<div class="filter-container">
    <form method="get" class="filter-form">
        <input type="text" name="search" class="filter-search" placeholder="Search products..." 
               value="<?= htmlspecialchars($searchTerm) ?>">

        <select name="sort" class="filter-sort">
            <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc" <?= $sortOption === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_desc" <?= $sortOption === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="name_asc" <?= $sortOption === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
            <option value="name_desc" <?= $sortOption === 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
        </select>

        <select name="category" class="filter-category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['Category']) ?>" 
                        <?= $selectedCategory == $cat['Category'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['Category']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="filter-button">Apply</button>
    </form>
</div>

<!-- Product Listing Section -->
<div class="product-section">
    <h1 class="product-title">All Products</h1>

    <?php if (empty($products)): ?>
        <p class="no-products-message">No products found.</p>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <?php $product_img = !empty($product['ImgUrl']) ? htmlspecialchars($product['ImgUrl']) : 'default.png'; ?>
                    <img src="<?= $product_img ?>" alt="<?= htmlspecialchars($product['Name']) ?>" class="product-image">

                    <div class="product-details">
                        <h2 class="product-name"><?= htmlspecialchars($product['Name']) ?></h2>
                        <p class="product-price">Price: R<?= number_format($product['Price'], 2) ?></p>
                        <p class="product-category">Category: <?= htmlspecialchars($product['Category']) ?></p>
                        <p class="product-stock">In Stock: <?= htmlspecialchars($product['Stock']) ?></p>
                        
                        <div class="product-buttons" style="display:flex; gap:10px; flex-wrap:wrap;">
                            <!-- View Product Button -->
                            <a href="product_details.php?id=<?= $product['ProductID'] ?>" class="btn">View Product</a>
                            
                            <!-- Add to Cart Button -->
                            <form method="POST" action="add_to_cart.php" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $product['ProductID'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn">Add to Cart</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

