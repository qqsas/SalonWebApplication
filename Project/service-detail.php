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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($service['Name']) ?> - Service Details</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .barber-card { border:1px solid #ddd; padding:10px 15px; border-radius:8px; margin:10px; }
        .barber-buttons { margin-top:10px; }
        .barber-buttons a { margin-right:10px; display:inline-block; }
    </style>
</head>
<body>

<div class="service-details-container">
    <h1><?= htmlspecialchars($service['Name']) ?></h1>
    <p><strong>Price:</strong> R<?= number_format($service['Price'],2) ?></p>
    <p><strong>Duration:</strong> <?= htmlspecialchars($service['Time']) ?> mins</p>
    <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($service['Description'])) ?></p>

    <h2>Barbers who can perform this service</h2>
    <?php if(empty($barbers)): ?>
        <p>No barbers available for this service currently.</p>
    <?php else: ?>
        <div class="barbers-grid" style="display:flex; flex-wrap:wrap; gap:15px;">
            <?php foreach($barbers as $barber): ?>
                <div class="barber-card">
                    <h3><?= htmlspecialchars($barber['Name']) ?></h3>
                    <p><?= nl2br(htmlspecialchars($barber['Bio'])) ?></p>
                    <div class="barber-buttons">
                        <a href="make_appointment.php?ServicesID=<?= $ServicesID ?>&BarberID=<?= $barber['BarberID'] ?>" class="btn">Make Appointment</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

