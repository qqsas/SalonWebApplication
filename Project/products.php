<?php
session_start();
include 'db.php';
include 'header.php';

// Handle input
$searchTerm = $_GET['search'] ?? '';
$sortOption = $_GET['sort'] ?? 'newest';
$selectedCategory = $_GET['category'] ?? '';

// Fetch all categories for the dropdown
$categoryQuery = "SELECT category_id, category_name FROM categories";
$categoryResult = $conn->query($categoryQuery);
$categories = $categoryResult->fetch_all(MYSQLI_ASSOC);

// Build base query
$query = "SELECT p.*, c.category_name FROM products p
          LEFT JOIN categories c ON p.category_id = c.category_id
          WHERE p.is_available = 1 AND p.is_deleted = 0";

$params = [];
$types = "";

// Search filter
if (!empty($searchTerm)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchWildcard = "%{$searchTerm}%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= "ss";
}

// Category filter
if (!empty($selectedCategory)) {
    $query .= " AND p.category_id = ?";
    $params[] = $selectedCategory;
    $types .= "i";
}

// Sort logic
switch ($sortOption) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $query .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.name DESC";
        break;
    default:
        $query .= " ORDER BY p.created_at DESC"; // newest
        break;
}

$stmt = $conn->prepare($query);

// Bind parameters if needed
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
@media (min-width: 769px) {
      .menu-toggle {
        display: none !important;
      }
    }
        </style>
    <meta charset="UTF-8">
    <title>All Products - E-Commerce Platform</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet"  media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<!-- Search and Filter Section -->
<div class="filter-container">
    <form method="get" class="filter-form">
        <input type="text" name="search" class="filter-search" placeholder="Search products..." value="<?= htmlspecialchars($searchTerm) ?>">

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
                <option value="<?= $cat['category_id'] ?>" <?= $selectedCategory == $cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name']) ?>
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
                    <?php $product_img = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'default.png'; ?>
                    <img src="<?= $product_img ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">

                    <div class="product-details">
                        <h2 class="product-name"><?= htmlspecialchars($product['name']) ?></h2>
                        <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>

                        <?php if (!is_null($product['original_price']) && $product['original_price'] > $product['price']): ?>
                            <p class="product-price">
                                <span class="original-price">R<?= number_format($product['original_price'], 2) ?></span>
                                <span class="price-discounted">Now R<?= number_format($product['price'], 2) ?></span>
                            </p>
                        <?php else: ?>
                            <p class="product-price">Price: R<?= number_format($product['price'], 2) ?></p>
                        <?php endif; ?>

                        <p class="product-category">Category: <?= htmlspecialchars($product['category_name']) ?></p>
                        <a href="product_details.php?id=<?= $product['product_id'] ?>" class="btn">View Product</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
