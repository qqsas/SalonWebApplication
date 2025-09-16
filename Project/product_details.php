<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: homepage.php');
    exit;
}

$product_id = (int)$_GET['id'];

// Fetch product details
$stmt = $conn->prepare("SELECT p.*, c.category_name FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.category_id 
                        WHERE p.product_id = ? AND p.is_available = 1 AND p.is_deleted = 0");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

$related_stmt = $conn->prepare("SELECT product_id, name, price, image_path FROM products 
                                WHERE category_id = ? AND is_available = 1 AND is_deleted = 0 AND product_id != ?
                                LIMIT 4");




if (!$product) {
    header('Location: homepage.php');
    exit;
}

// Fetch related products (same category, excluding current product)
$related_stmt = $conn->prepare("SELECT product_id, name, price, image_path FROM products 
                                WHERE category_id = ? AND is_available = 1 AND is_deleted = 0 AND product_id != ?
                                LIMIT 4");
$related_stmt->bind_param("ii", $product['category_id'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();

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
    <title><?= htmlspecialchars($product['name']) ?> - Product Details</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet"  media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<!-- Product Details -->
<div class="product-page">
    <div class="product-details">
        <div class="image-section">
            <?php $product_img = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'default.png'; ?>
            <img src="<?= $product_img ?>" class="product-main-image" alt="<?= htmlspecialchars($product['name']) ?>">
        </div>
        <div class="product-info-section">
            <h2 class="product-name"><?= htmlspecialchars($product['name']) ?></h2>
            <p><strong>Category:</strong> <?= htmlspecialchars($product['category_name']) ?></p>
            <p><strong>Price:</strong> R<?= number_format($product['price'], 2) ?></p>
            <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            
            <form action="add_to_cart.php" method="POST" class="add-to-cart-form">
                <input type="hidden" name="product_id" value="<?= $product['product_id']; ?>">
                
                <div class="form-group">
                    <label for="quantity"><strong>Quantity:</strong></label>
                    <input type="number" name="quantity" id="quantity" value="1" min="1" class="quantity-input" required>
                </div>
                
                <button type="submit" class="btn">Add to Cart</button>
            </form>
        </div>
    </div>
</div>

<?php if ($related_result->num_rows > 0): ?>
<div class="related-products-section">
    <h4 class="related-products-title">More from this category</h4>
    <div class="related-products-list">
        <?php while ($related = $related_result->fetch_assoc()): ?>
            <div class="related-product-card">
                <?php 
                $related_img = !empty($related['image_path']) ? htmlspecialchars($related['image_path']) : 'default.png'; 
                ?>
                <img src="<?= $related_img ?>" class="related-product-image" alt="<?= htmlspecialchars($related['name']) ?>">
                <div class="related-product-info">
                    <h5 class="related-product-name"><?= htmlspecialchars($related['name']) ?></h5>
                    <p class="related-product-price">R<?= number_format($related['price'], 2) ?></p>
                    <a href="product_details.php?id=<?= $related['product_id'] ?>" class="btn">View</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>

