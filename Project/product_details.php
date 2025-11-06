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

// Parse product categories
$productCategories = [];
if (!empty($product['Category'])) {
    // If it's a JSON string, decode it
    if (is_string($product['Category']) && $product['Category'][0] === '[') {
        $productCategories = json_decode($product['Category'], true) ?: [];
    } 
    // If it's already an array, use it directly
    elseif (is_array($product['Category'])) {
        $productCategories = $product['Category'];
    }
    // If it's a single string (legacy data), wrap it in an array
    elseif (is_string($product['Category'])) {
        $productCategories = [$product['Category']];
    }
}

// Clean categories for display
$productCategories = array_map(function($cat) {
    return trim(str_replace(['\"', '"', '[', ']', '\\'], '', $cat));
}, $productCategories);
$productCategories = array_filter($productCategories);
$categoriesDisplay = !empty($productCategories) ? implode(', ', $productCategories) : 'Uncategorized';

// Fetch approved reviews for this product
$reviews_stmt = $conn->prepare("
    SELECT r.*, u.Name as UserName 
    FROM Reviews r 
    JOIN User u ON r.UserID = u.UserID 
    WHERE r.ProductID = ? AND r.Status = 'approved'
    ORDER BY r.CreatedAt DESC
");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$approved_reviews = [];

while ($review = $reviews_result->fetch_assoc()) {
    $approved_reviews[] = $review;
}
$reviews_stmt->close();

// Calculate average rating
$average_rating = 0;
$total_ratings = count($approved_reviews);
if ($total_ratings > 0) {
    $total_score = 0;
    foreach ($approved_reviews as $review) {
        $total_score += $review['Rating'];
    }
    $average_rating = round($total_score / $total_ratings, 1);
}

// Fetch related products (products that share at least one category)
$related_products = [];
if (!empty($productCategories)) {
    // Create a placeholder string for the prepared statement
    $placeholders = str_repeat('?,', count($productCategories) - 1) . '?';
    
    $related_stmt = $conn->prepare("
        SELECT DISTINCT p.ProductID, p.Name, p.Price, p.ImgUrl, p.Category 
        FROM Products p 
        WHERE p.ProductID != ? 
        AND (
            p.Category LIKE '%\"' || ? || '\"%' 
            OR p.Category LIKE '%' || ? || '%'
        )
        LIMIT 4
    ");
    
    // We need to bind the product_id and then each category twice (for JSON and legacy matching)
    $types = "i" . str_repeat("s", count($productCategories) * 2);
    $params = [$product_id];
    
    foreach ($productCategories as $category) {
        $params[] = $category; // For JSON matching
        $params[] = $category; // For legacy matching
    }
    
    $related_stmt->bind_param($types, ...$params);
    $related_stmt->execute();
    $related_result = $related_stmt->get_result();
    
    while ($related = $related_result->fetch_assoc()) {
        $related_products[] = $related;
    }
    $related_stmt->close();
}

// Alternative approach: if no related products found by category, get random products
if (empty($related_products)) {
    $fallback_stmt = $conn->prepare("
        SELECT ProductID, Name, Price, ImgUrl 
        FROM Products 
        WHERE ProductID != ? 
        ORDER BY RAND() 
        LIMIT 4
    ");
    $fallback_stmt->bind_param("i", $product_id);
    $fallback_stmt->execute();
    $fallback_result = $fallback_stmt->get_result();
    
    while ($related = $fallback_result->fetch_assoc()) {
        $related_products[] = $related;
    }
    $fallback_stmt->close();
}
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
        .reviews-section {
            margin: 40px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .average-rating {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .rating-stars {
            display: flex;
            gap: 2px;
        }
        
        .star {
            color: #ddd;
            font-size: 1.5em;
        }
        
        .star.filled {
            color: #ffc107;
        }
        
        .total-reviews {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .review-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reviewer-name {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .review-date {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .review-rating {
            display: flex;
            gap: 2px;
        }
        
        .review-rating .star {
            font-size: 1.1em;
        }
        
        .review-content {
            line-height: 1.6;
            color: #495057;
        }
        
        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        
        .review-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        @media (max-width: 768px) {
            .reviews-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .rating-summary {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .review-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<!-- Product Details -->
<div class="item-detail-page">
    <div class="item-detail__container">
        <div class="item-detail__media">
            <?php 
            $product_img = !empty($product['ImgUrl']) ? "" . htmlspecialchars($product['ImgUrl']) : 'Img/default-product.jpg';
            ?>
            <img src="<?= $product_img ?>" 
                 class="item-detail__primary-image" 
                 alt="<?= htmlspecialchars($product['Name']) ?>"
                 onerror="this.src='Img/default-product.jpg'">
        </div>
        <div class="item-detail__content">
            <h2 class="item-detail__title"><?= htmlspecialchars($product['Name']) ?></h2>
            
            <!-- Display average rating if available -->
            <?php if ($total_ratings > 0): ?>
            <div class="item-detail__rating" style="margin-bottom: 15px;">
                <div class="rating-stars">
                    <?php
                    $full_stars = floor($average_rating);
                    $has_half_star = ($average_rating - $full_stars) >= 0.5;
                    
                    for ($i = 1; $i <= 5; $i++): 
                        if ($i <= $full_stars): ?>
                            <span class="star filled">★</span>
                        <?php elseif ($i == $full_stars + 1 && $has_half_star): ?>
                            <span class="star filled">★</span>
                        <?php else: ?>
                            <span class="star">★</span>
                        <?php endif;
                    endfor; 
                    ?>
                </div>
                <span style="margin-left: 8px; color: #6c757d;">
                    <?= $average_rating ?> (<?= $total_ratings ?> review<?= $total_ratings !== 1 ? 's' : '' ?>)
                </span>
            </div>
            <?php endif; ?>
            
            <div class="item-detail__meta">
                <strong>Categories:</strong>
                <div class="categories-tags">
                    <?php if (!empty($productCategories)): ?>
                        <?php foreach ($productCategories as $category): ?>
                            <span class="category-tag"><?= htmlspecialchars($category) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="category-tag">Uncategorized</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <p class="item-detail__price"><strong>Price:</strong> R<?= number_format($product['Price'], 2) ?></p>
            
            <div class="item-detail__stock">
                <strong>Stock Status:</strong>
                <?php
                $stock = $product['Stock'];
                if ($stock > 10) {
                    echo '<span class="stock-status in-stock">In Stock (' . $stock . ' available)</span>';
                } elseif ($stock > 0) {
                    echo '<span class="stock-status low-stock">Low Stock (' . $stock . ' left)</span>';
                } else {
                    echo '<span class="stock-status out-of-stock">Out of Stock</span>';
                }
                ?>
            </div>
            
            <?php if ($product['Stock'] > 0): ?>
            <form action="add_to_cart.php" method="POST" class="item-detail__action-form">
                <input type="hidden" name="product_id" value="<?= $product['ProductID']; ?>">
                
                <div class="item-detail__form-control">
                    <label for="quantity" class="item-detail__form-label"><strong>Quantity:</strong></label>
                    <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= $product['Stock'] ?>" class="item-detail__quantity-selector" required>
                </div>
                
                <button type="submit" class="item-detail__action-btn">Add to Cart</button>
            </form>
            <?php else: ?>
            <div class="out-of-stock-message">
                <p style="color: #dc3545; font-weight: bold;">This product is currently out of stock.</p>
                <p style="color: #6c757d;">Please check back later or contact us for availability.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reviews Section -->
<div class="reviews-section">
    <div class="reviews-header">
        <h3>Customer Reviews</h3>
        <?php if ($total_ratings > 0): ?>
        <div class="rating-summary">
            <div class="average-rating"><?= $average_rating ?></div>
            <div>
                <div class="rating-stars">
                    <?php
                    $full_stars = floor($average_rating);
                    $has_half_star = ($average_rating - $full_stars) >= 0.5;
                    
                    for ($i = 1; $i <= 5; $i++): 
                        if ($i <= $full_stars): ?>
                            <span class="star filled">★</span>
                        <?php elseif ($i == $full_stars + 1 && $has_half_star): ?>
                            <span class="star filled">★</span>
                        <?php else: ?>
                            <span class="star">★</span>
                        <?php endif;
                    endfor; 
                    ?>
                </div>
                <div class="total-reviews">Based on <?= $total_ratings ?> review<?= $total_ratings !== 1 ? 's' : '' ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($approved_reviews)): ?>
        <div class="reviews-list">
            <?php foreach ($approved_reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <span class="reviewer-name"><?= htmlspecialchars($review['UserName']) ?></span>
                            <span class="review-date"><?= date('F j, Y', strtotime($review['CreatedAt'])) ?></span>
                        </div>
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= $review['Rating'] ? 'filled' : '' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($review['Title'])): ?>
                        <div class="review-title"><?= htmlspecialchars($review['Title']) ?></div>
                    <?php endif; ?>
                    
                    <div class="review-content">
                        <?= nl2br(htmlspecialchars($review['Comment'] ?? 'No comment provided')) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-reviews">
            <p>No reviews yet for this product.</p>
            <p>Be the first to share your experience!</p>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($related_products)): ?>
<div class="similar-items-section">
    <h4 class="similar-items__heading">You Might Also Like</h4>
    <div class="similar-items__grid">
        <?php foreach ($related_products as $related): ?>
            <div class="similar-item-card">
                <?php 
                $related_img = !empty($related['ImgUrl']) ? "" . htmlspecialchars($related['ImgUrl']) : 'Img/default-product.jpg';
                ?>
                <img src="<?= $related_img ?>" 
                     class="similar-item__image" 
                     alt="<?= htmlspecialchars($related['Name']) ?>"
                     onerror="this.src='Img/default-product.jpg'">
                <div class="similar-item__details">
                    <h5 class="similar-item__title"><?= htmlspecialchars($related['Name']) ?></h5>
                    <p class="similar-item__price">R<?= number_format($related['Price'], 2) ?></p>
                    <a href="product_details.php?id=<?= $related['ProductID'] ?>" class="similar-item__link">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>
