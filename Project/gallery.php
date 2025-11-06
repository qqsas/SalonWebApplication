<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch gallery images
$stmt = $conn->prepare("SELECT * FROM Gallery WHERE IsDeleted = 0 ORDER BY CreatedAt DESC");
$stmt->execute();
$result = $stmt->get_result();
$galleryItems = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - Previous Works</title>
    
    <style>
        body {
            background-color: #f9f9f9;
            font-family: 'Poppins', sans-serif;
        }
        .gallery-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        .gallery-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 20px;
            color: #333;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .gallery-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .gallery-card img {
            width: 100%;
            height: 240px;
            object-fit: cover;
        }
        .gallery-card-content {
            padding: 15px;
        }
        .gallery-card-content h3 {
            font-size: 1.2rem;
            color: #222;
            margin-bottom: 8px;
        }
        .gallery-card-content p {
            font-size: 0.95rem;
            color: #666;
        }
        .no-gallery {
            text-align: center;
            color: #777;
            font-size: 1.1rem;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="gallery-container">
        <h1 class="gallery-title">Our Previous Works</h1>

        <?php if (count($galleryItems) > 0): ?>
            <div class="gallery-grid">
                <?php foreach ($galleryItems as $item): ?>
                    <div class="gallery-card">
                        <img src="<?php echo htmlspecialchars($item['ImageUrl']); ?>" alt="<?php echo htmlspecialchars($item['Title'] ?? 'Gallery Image'); ?>">
                        <div class="gallery-card-content">
                            <h3><?php echo htmlspecialchars($item['Title'] ?? 'Untitled'); ?></h3>
                            <p><?php echo htmlspecialchars($item['Description'] ?? ''); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-gallery">No gallery images found yet. Please check back soon!</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php include 'footer.php'; ?>

