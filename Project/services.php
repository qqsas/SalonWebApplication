<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch all services
$serviceQuery = "SELECT ServicesID, Name, Description, Price, Time FROM Services ORDER BY Name ASC";
$serviceResult = $conn->query($serviceQuery);
$services = $serviceResult ? $serviceResult->fetch_all(MYSQLI_ASSOC) : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Services - Kumar Kailey Hair & Beauty</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    @media (min-width:769px) {
        .menu-toggle { display: none !important; }
    }
    .service-card { border: 1px solid #ddd; padding: 15px; margin: 10px; border-radius: 8px; }
    .service-buttons { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
    </style>
</head>
<body>

<div class="services-container">
    <h1>Our Services</h1>

    <?php if(empty($services)): ?>
        <p>No services available at the moment.</p>
    <?php else: ?>
        <div class="services-grid">
            <?php foreach($services as $service): ?>
                <div class="service-card">
                    <h2><?= htmlspecialchars($service['Name']) ?></h2>
                    <p><?= nl2br(htmlspecialchars($service['Description'])) ?></p>
                    <p><strong>Price:</strong> R<?= number_format($service['Price'],2) ?></p>
                    <p><strong>Duration:</strong> <?= htmlspecialchars($service['Time']) ?> mins</p>

                    <div class="service-buttons">
                        <?php
                        // Fetch barbers who can perform this service
                        $barberStmt = $conn->prepare("
                            SELECT a.BarberID, a.Name 
                            FROM Admin a
                            JOIN BarberServices bs ON a.BarberID = bs.BarberID
                            WHERE bs.ServicesID = ?
                            ORDER BY a.Name ASC
                        ");
                        $barberStmt->bind_param("i", $service['ServicesID']);
                        $barberStmt->execute();
                        $barberResult = $barberStmt->get_result();
                        $barbers = $barberResult ? $barberResult->fetch_all(MYSQLI_ASSOC) : [];

                        if(!empty($barbers)):
                            foreach($barbers as $barber): ?>
                                <a href="make_appointment.php?ServicesID=<?= $service['ServicesID'] ?>&BarberID=<?= $barber['BarberID'] ?>" class="btn">
                                    Book with <?= htmlspecialchars($barber['Name']) ?>
                                </a>
                            <?php endforeach;
                        else: ?>
                            <p>No barbers available for this service.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

