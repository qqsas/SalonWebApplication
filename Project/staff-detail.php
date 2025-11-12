<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_GET['BarberID']) || !is_numeric($_GET['BarberID'])) {
    echo "Barber not found.";
    exit();
}

$BarberID = (int)$_GET['BarberID'];

// Fetch barber details (added ImgUrl)
$barberStmt = $conn->prepare("
    SELECT a.BarberID, a.Name, a.Bio, a.ImgUrl, u.Email, u.Number 
    FROM Barber a
    JOIN User u ON a.UserID = u.UserID
    WHERE a.BarberID = ?
");
$barberStmt->bind_param("i", $BarberID);
$barberStmt->execute();
$barberResult = $barberStmt->get_result();
$barber = $barberResult->fetch_assoc();

if (!$barber) {
    echo "Barber not found.";
    exit();
}

// Fetch services this barber can perform
$servicesStmt = $conn->prepare("
    SELECT s.ServicesID, s.Name, s.Description, s.Price, s.Time
    FROM Services s
    JOIN BarberServices bs ON s.ServicesID = bs.ServicesID
    WHERE bs.BarberID = ?
    ORDER BY s.Name ASC
");
$servicesStmt->bind_param("i", $BarberID);
$servicesStmt->execute();
$servicesResult = $servicesStmt->get_result();
$services = $servicesResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($barber['Name']) ?> - Staff Details</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f5f5f5; /* Change this to your desired page background color */
            display: flex;
            flex-direction: column;
        }

        .page-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .staff-details-container {
            animation: fadeInUp 0.8s ease-out;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: var(--card-shadow);
            margin: 10px auto;
            flex: 1;
            max-width: 1400px;
            overflow: hidden;
            padding: 15px;
            position: relative;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            align-items: start;
            min-height: 0;
        }

        .staff-details-container h1 {
            color: var(--text-dark);
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 10px;
            position: relative;
            text-align: center;
        }

        .staff-details-container h1::after {
            background: var(--primary-color);
            border-radius: 2px;
            content: '';
            display: block;
            height: 3px;
            margin: 8px auto;
            width: 60px;
        }

        .staff-details-container h2 {
            color: var(--text-dark);
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0 0 12px 0;
            position: relative;
            text-align: left;
        }

        .staff-details-container h2::after {
            background: var(--secondary-color);
            border-radius: 2px;
            content: '';
            display: block;
            height: 2px;
            margin: 6px 0 0 0;
            width: 50px;
        }

        .barber-sidebar {
            grid-column: 1;
            display: flex;
            flex-direction: column;
        }

        .barber-image {
            margin-bottom: 10px;
            position: relative;
            text-align: center;
        }

        .staff-details-container .barber-image img {
            background: var(--gray-light);
            border-radius: 50%;
            border: 3px solid var(--accent-color);
            box-shadow: var(--card-shadow);
            height: 120px;
            object-fit: cover;
            transition: all var(--transition-normal);
            width: 120px;
        }

        .barber-info {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .barber-info p {
            margin-bottom: 8px;
            line-height: 1.5;
            font-size: 0.9rem;
        }

        .services-section {
            margin-top: 0;
            grid-column: 2;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .services-section h2 {
            margin-bottom: 12px;
            position: sticky;
            top: 0;
            background: var(--white);
            padding-bottom: 8px;
            z-index: 1;
        }

        .staff-details-container .services-section p {
            font-size: 0.9rem;
            color: var(--gray-medium);
        }

        .staff-details-container .services-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            margin-top: 0;
            transition: all 0.4s ease;
        }

        .staff-details-container .service-card {
            animation: cardEntrance 0.6s ease-out;
            animation: fadeInUp 0.6s ease-out;
            background: var(--white);
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            height: fit-content;
            overflow: hidden;
            padding: 12px;
            position: relative;
            transition: all var(--transition-normal);
        }

        .staff-details-container .service-card h3 {
            color: var(--text-dark);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .staff-details-container .service-card p {
            color: #444;
            flex-grow: 1;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .staff-details-container .service-buttons {
            margin-top: 8px;
        }

        .staff-details-container .service-buttons .btn {
            font-size: 0.8rem;
            padding: 6px 12px;
            width: 100%;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .staff-details-container {
                grid-template-columns: 300px 1fr;
                gap: 15px;
                padding: 12px;
            }
            
            .staff-details-container .barber-image img {
                width: 100px;
                height: 100px;
            }
            
            .staff-details-container h1 {
                font-size: 1.3rem;
            }
        }

        footer {
            margin-top: auto !important;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .staff-details-container {
                grid-template-columns: 1fr;
                max-height: calc(100vh - 80px);
                padding: 10px;
            }
            
            .barber-sidebar {
                grid-column: 1;
                text-align: center;
            }
            
            .services-section {
                grid-column: 1;
                max-height: calc(100vh - 400px);
            }
            
            .staff-details-container .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
<div class="staff-details-container">
    <div class="barber-sidebar">
        <div class="barber-image">
            <?php if (!empty($barber['ImgUrl']) && file_exists($barber['ImgUrl'])): ?>
                <img src="<?= htmlspecialchars($barber['ImgUrl']); ?>" alt="<?= htmlspecialchars($barber['Name']); ?>">
            <?php else: ?>
                <img src="Img/default_barber.png" alt="Default Barber Image">
            <?php endif; ?>
        </div>

        <h1><?= htmlspecialchars($barber['Name']) ?></h1>
        
        <div class="barber-info">
            <p><strong>Email:</strong> <?= htmlspecialchars($barber['Email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($barber['Number']) ?></p>
            <p><strong>Bio:</strong><br><?= nl2br(htmlspecialchars($barber['Bio'])) ?></p>
        </div>
    </div>

    <div class="services-section">
        <h2>Services Offered</h2>
        <?php if(empty($services)): ?>
            <p>No services assigned to this barber yet.</p>
        <?php else: ?>
            <div class="services-grid">
                <?php foreach($services as $service): ?>
                    <div class="service-card">
                        <h3><?= htmlspecialchars($service['Name']) ?></h3>
                        <p><?= nl2br(htmlspecialchars($service['Description'])) ?></p>
                        <p><strong>Price:</strong> R<?= number_format($service['Price'],2) ?></p>
                        <p><strong>Duration:</strong> <?= htmlspecialchars($service['Time']) ?> mins</p>
                        <div class="service-buttons">
                            <a href="make_appointment.php?ServicesID=<?= $service['ServicesID'] ?>&BarberID=<?= $barber['BarberID'] ?>" class="btn">
                                Make Appointment
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include 'footer.php'; ?>

