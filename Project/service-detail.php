<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_GET['ServicesID']) || !is_numeric($_GET['ServicesID'])) {
    echo "Service not found.";
    exit();
}

$ServicesID = (int)$_GET['ServicesID'];

// Fetch service details
$serviceStmt = $conn->prepare("SELECT * FROM Services WHERE ServicesID = ?");
$serviceStmt->bind_param("i", $ServicesID);
$serviceStmt->execute();
$serviceResult = $serviceStmt->get_result();
$service = $serviceResult->fetch_assoc();
if (!$service) {
    echo "Service not found.";
    exit();
}

// Fetch barbers who can perform this service
$barbersStmt = $conn->prepare("
    SELECT a.BarberID, a.Name, a.Bio 
    FROM Admin a
    JOIN BarberServices bs ON a.BarberID = bs.BarberID
    WHERE bs.ServicesID = ?
    ORDER BY a.Name ASC
");
$barbersStmt->bind_param("i", $ServicesID);
$barbersStmt->execute();
$barbersResult = $barbersStmt->get_result();
$barbers = $barbersResult->fetch_all(MYSQLI_ASSOC);

// Handle price display based on price type
$priceDisplay = '';
$priceType = $service['PriceType'] ?? 'fixed';

if ($priceType === 'range') {
    $minPrice = $service['MinPrice'] ?? 0;
    $maxPrice = $service['MaxPrice'] ?? 0;
    $priceDisplay = "R" . number_format($minPrice, 2) . " - R" . number_format($maxPrice, 2);
} else {
    // Fixed price
    $price = $service['Price'] ?? 0;
    $priceDisplay = "R" . number_format($price, 2);
}

// Parse categories for display
$categories = [];
if (!empty($service['Category'])) {
    // If it's a JSON string, decode it
    if (is_string($service['Category']) && $service['Category'][0] === '[') {
        $categories = json_decode($service['Category'], true) ?: [];
    } 
    // If it's already an array, use it directly
    elseif (is_array($service['Category'])) {
        $categories = $service['Category'];
    }
    // If it's a single string (legacy data), wrap it in an array
    elseif (is_string($service['Category'])) {
        $categories = [$service['Category']];
    }
}

// Clean categories for display
$categories = array_map(function($cat) {
    return trim(str_replace(['\"', '"', '[', ']', '\\'], '', $cat));
}, $categories);
$categories = array_filter($categories);
$categoriesDisplay = !empty($categories) ? implode(', ', $categories) : 'No categories';

// Handle service image
$serviceImage = !empty($service['ImgUrl']) ? $service['ImgUrl'] : 'default-service.jpg';
$imagePath = "" . $serviceImage;

// Check if image file exists, otherwise use default
if (!empty($service['ImgUrl']) && file_exists($imagePath)) {
    $serviceImageSrc = $imagePath;
} else {
    $serviceImageSrc = "Img/default-service.jpg"; // Make sure you have a default image
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($service['Name']) ?> - Service Details</title>
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .service-details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .service-header {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
            align-items: start;
        }
        .service-image-container {
            position: relative;
        }
        .service-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .service-info {
            flex: 1;
        }
        .service-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .meta-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .meta-item strong {
            display: block;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .price-display {
            font-size: 1.5em;
            font-weight: bold;
            color: #28a745;
        }
        .barbers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .barber-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .barber-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .barber-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .barber-bio {
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .barber-buttons {
            margin-top: 15px;
        }
        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #0056b3;
        }
        .categories-display {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .price-type-badge {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 10px;
            text-transform: capitalize;
        }
        .service-description {
            line-height: 1.6;
            color: #555;
        }
        .no-image {
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .service-header {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .service-image {
                height: 200px;
                max-width: 300px;
                margin: 0 auto;
            }
            .service-meta {
                grid-template-columns: 1fr;
            }
            .barbers-grid {
                grid-template-columns: 1fr;
            }
            .service-header {
                padding: 20px;
            }
        }
        @media (max-width: 480px) {
            .service-image {
                height: 180px;
            }
        }
    </style>
</head>
<body>

<div class="service-details-container">
    <div class="service-header">
        <div class="service-image-container">
            <img src="<?= htmlspecialchars($serviceImageSrc) ?>" 
                 alt="<?= htmlspecialchars($service['Name']) ?>" 
                 class="service-image"
                 onerror="this.src='Img/default-service.jpg'">
        </div>
        <div class="service-info">
            <h1><?= htmlspecialchars($service['Name']) ?></h1>
            <div class="categories-display">
                <strong>Categories:</strong> <?= htmlspecialchars($categoriesDisplay) ?>
            </div>
            <p class="service-description"><?= nl2br(htmlspecialchars($service['Description'])) ?></p>
        </div>
    </div>

    <div class="service-meta">
        <div class="meta-item">
            <strong>Price</strong>
            <span class="price-display"><?= $priceDisplay ?></span>
            <span class="price-type-badge"><?= htmlspecialchars($priceType) ?> pricing</span>
        </div>
        <div class="meta-item">
            <strong>Duration</strong>
            <span><?= htmlspecialchars($service['Time']) ?> minutes</span>
        </div>
        <div class="meta-item">
            <strong>Service Type</strong>
            <span><?= htmlspecialchars(ucfirst($priceType)) ?> Pricing</span>
        </div>
    </div>

    <h2>Available Barbers</h2>
    <?php if(empty($barbers)): ?>
        <div class="no-barbers" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
            <p style="font-size: 1.1em; color: #666; margin: 0;">No barbers available for this service currently.</p>
            <p style="color: #999; margin: 10px 0 0 0;">Please check back later or contact us for availability.</p>
        </div>
    <?php else: ?>
        <div class="barbers-grid">
            <?php foreach($barbers as $barber): ?>
                <div class="barber-card">
                    <h3><?= htmlspecialchars($barber['Name']) ?></h3>
                    <div class="barber-bio">
                        <?= nl2br(htmlspecialchars($barber['Bio'])) ?>
                    </div>
                    <div class="barber-buttons">
                        <a href="make_appointment.php?ServicesID=<?= $ServicesID ?>&BarberID=<?= $barber['BarberID'] ?>" class="btn">
                            Book with <?= htmlspecialchars(explode(' ', $barber['Name'])[0]) ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
