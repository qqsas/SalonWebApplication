<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = (int)$_GET['id'];

// Fetch product details
$stmt = $conn->prepare("SELECT ProductID, Name, Price, Category, Stock, ImgUrl, CreatedAt 
                        FROM Products 
                        WHERE ProductID = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header('Location: index.php');
    exit;
}

// Fetch related products (same category, excluding current product)
$related_stmt = $conn->prepare("SELECT ProductID, Name, Price, ImgUrl 
                                FROM Products 
                                WHERE Category = ? AND ProductID != ? 
                                LIMIT 4");
$related_stmt->bind_param("si", $product['Category'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['Name']) ?> - Product Details</title>
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width: 768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @media (min-width: 769px) {
            .menu-toggle { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Product Details -->
<div class="item-detail-page">
    <div class="item-detail__container">
        <div class="item-detail__media">
            <?php $product_img = !empty($product['ImgUrl']) ? htmlspecialchars($product['ImgUrl']) : 'default.png'; ?>
            <img src="<?= $product_img ?>" class="item-detail__primary-image" alt="<?= htmlspecialchars($product['Name']) ?>">
        </div>
        <div class="item-detail__content">
            <h2 class="item-detail__title"><?= htmlspecialchars($product['Name']) ?></h2>
            <p class="item-detail__meta"><strong>Category:</strong> <?= htmlspecialchars($product['Category']) ?></p>
            <p class="item-detail__price"><strong>Price:</strong> R<?= number_format($product['Price'], 2) ?></p>
            <p class="item-detail__stock"><strong>In Stock:</strong> <?= htmlspecialchars($product['Stock']) ?></p>
            
            <form action="add_to_cart.php" method="POST" class="item-detail__action-form">
                <input type="hidden" name="product_id" value="<?= $product['ProductID']; ?>">
                
                <div class="item-detail__form-control">
                    <label for="quantity" class="item-detail__form-label"><strong>Quantity:</strong></label>
                    <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= $product['Stock'] ?>" class="item-detail__quantity-selector" required>
                </div>
                
                <button type="submit" class="item-detail__action-btn">Add to Cart</button>
            </form>
        </div>
    </div>
</div>

<?php if ($related_result && $related_result->num_rows > 0): ?>
<div class="similar-items-section">
    <h4 class="similar-items__heading">More from this category</h4>
    <div class="similar-items__grid">
        <?php while ($related = $related_result->fetch_assoc()): ?>
            <div class="similar-item-card">
                <?php $related_img = !empty($related['ImgUrl']) ? htmlspecialchars($related['ImgUrl']) : 'default.png'; ?>
                <img src="<?= $related_img ?>" class="similar-item__image" alt="<?= htmlspecialchars($related['Name']) ?>">
                <div class="similar-item__details">
                    <h5 class="similar-item__title"><?= htmlspecialchars($related['Name']) ?></h5>
                    <p class="similar-item__price">R<?= number_format($related['Price'], 2) ?></p>
                    <a href="product_details.php?id=<?= $related['ProductID'] ?>" class="similar-item__link">View Details</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>
