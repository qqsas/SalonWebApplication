<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch recommended products
$stmt = $conn->prepare("SELECT p.*, c.category_name FROM products p
                        LEFT JOIN categories c ON p.category_id = c.category_id
                        WHERE p.is_available = 1 AND p.is_deleted = 0
                        ORDER BY p.created_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
$recommended_products = $result->fetch_all(MYSQLI_ASSOC);

// Fetch categories
$stmt = $conn->prepare("SELECT * FROM categories");
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);

// Fetch the user's role
$user_role = isset($_SESSION['user_id']) ? $_SESSION['role'] : null;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage</title>
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet"  media="(max-width: 768px)">
</head>
<body>

<!-- Search and Categories -->
<div class="container">
    <div class="flex-container">
        <div class="search-section">
            <h2>Search Products</h2>
            <form method="GET" action="search_results.php" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Search for products..." required>
                <button type="submit" class="search-button">Search</button>
            </form>
        </div>
        <div class="category-section">
            <h3>Categories</h3>
            <ul class="category-list">
                <?php foreach ($categories as $category): ?>
                    <li class="category-item">
                        <a href="category.php?id=<?= $category['category_id'] ?>">
                            <?= htmlspecialchars($category['category_name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Recommended Listings -->
<div class="container">
    <h2>Recommended Products</h2>
    <div class="product-grid">
        <?php foreach ($recommended_products as $product): ?>
            <div class="product-card">
                <?php $product_img = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'default.png'; ?>
                <img src="<?= $product_img ?>" class="product-image" alt="<?= htmlspecialchars($product['name']) ?>">

                <div class="product-info">
                    <h5 class="product-title"><?= htmlspecialchars($product['name']) ?></h5>
                    <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                    <?php if (!is_null($product['original_price']) && $product['original_price'] > $product['price']): ?>
                        <p class="product-price">
                            <strong>
                                <span class="price-original">R<?= number_format($product['original_price'], 2) ?></span>
                                <span class="price-discounted">Now R<?= number_format($product['price'], 2) ?></span>
                            </strong>
                        </p>
                    <?php else: ?>
                        <p class="product-price"><strong>Price: R<?= number_format($product['price'], 2) ?></strong></p>
                    <?php endif; ?>

                    <a href="product_details.php?id=<?= $product['product_id'] ?>" class="btn">View Product</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
