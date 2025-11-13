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
    /* --- Scoped Modern Gallery (does not affect other pages) --- */
    body.gallery-page {
        background-color: var(--white);
        font-family: 'Poppins', 'Segoe UI', sans-serif;
        color: var(--text-dark, #1f1f1f);
        margin: 0;
        padding: 0;
    }

    .gallery-container {
        max-width: 1400px;
        margin: 5rem auto;
        padding: 0 1.5rem 4rem;
    }

    .gallery-title {
        text-align: center;
        font-size: clamp(2rem, 4vw, 2.8rem);
        font-weight: 700;
        margin-bottom: 3rem;
        color: var(--black);
        letter-spacing: 0.5px;
        position: relative;
    }

    .gallery-title::after {
        content: '';
        display: block;
        width: 100px;
        height: 4px;
        background: var(--primary-color);
        margin: 0.8rem auto 0;
        border-radius: 3px;
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2rem;
        justify-items: center;
    }

    .gallery-card {
        background: var(--gray-light);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
        position: relative;
        width: 100%;
        max-width: 500px;
        animation: fadeInUp 0.5s ease both;
    }

    .gallery-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--primary-color);
    }

    .gallery-card img {
        width: 100%;
        height: 320px;
        object-fit: cover;
        display: block;
        transition: transform 0.4s ease, filter 0.3s ease;
    }

    .gallery-card:hover img {
        transform: scale(1.05);
        filter: brightness(1.05);
    }

    .gallery-card-content {
        padding: 1.2rem;
        text-align: center;
    }

    .gallery-card-content h3 {
        font-size: 1.2rem;
        margin-bottom: 0.4rem;
        color: var(--primary-color);
        font-weight: 600;
    }

    .gallery-card-content p {
        font-size: 0.95rem;
        color: var(--primary-color);
        line-height: 1.6;
        margin: 0 auto;
        opacity: 0.9;
    }

    .gallery-card:hover .gallery-card-content h3 {
        color: var(--text-dark);
    }

    /* Lightbox */
    .lightbox {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.85);
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .lightbox.active {
        display: flex;
    }

    .lightbox img {
        max-width: 90%;
        max-height: 90vh;
        border-radius: 12px;
        box-shadow: 0 0 40px rgba(0,0,0,0.3);
    }

    .lightbox::after {
        content: "✖";
        position: absolute;
        top: 30px;
        right: 50px;
        font-size: 2rem;
        color: #fff;
        cursor: pointer;
    }

    .no-gallery {
        text-align: center;
        color: #7f8c8d;
        font-size: 1.2rem;
        margin-top: 4rem;
        padding: 3rem 1.5rem;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 6px 14px rgba(0,0,0,0.05);
    }

    .no-gallery::before {
        content: '📸';
        display: block;
        font-size: 3rem;
        opacity: 0.7;
        margin-bottom: 0.8rem;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .gallery-card img { height: 260px; }
    }
    </style>
</head>
<body class="gallery-page">
    <div class="gallery-container">
        <h1 class="gallery-title">Our Previous Works</h1>

        <?php if (!empty($galleryItems)): ?>
            <div class="gallery-grid">
                <?php foreach ($galleryItems as $item): ?>
                    <div class="gallery-card">
                        <img src="<?php echo htmlspecialchars($item['ImageUrl']); ?>" 
                             alt="<?php echo htmlspecialchars($item['Title'] ?? 'Gallery Image'); ?>">
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

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        document.body.appendChild(lightbox);

        document.querySelectorAll('.gallery-card img').forEach(img => {
            img.addEventListener('click', () => {
                lightbox.innerHTML = `<img src="${img.src}" alt="${img.alt}">`;
                lightbox.classList.add('active');
            });
        });

        lightbox.addEventListener('click', () => lightbox.classList.remove('active'));
    });
    </script>

</body>
</html>

<?php include 'footer.php'; ?>
