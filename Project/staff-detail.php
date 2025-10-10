<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_GET['BarberID']) || !is_numeric($_GET['BarberID'])) {
    echo "Barber not found.";
    exit();
}

$BarberID = (int)$_GET['BarberID'];

// Fetch barber details
$barberStmt = $conn->prepare("SELECT a.BarberID, a.Name, a.Bio, u.Email, u.Number 
                              FROM Barber a
                              JOIN User u ON a.UserID = u.UserID
                              WHERE a.BarberID = ?");
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
        .service-card { border:1px solid #ddd; padding:10px 15px; border-radius:8px; margin:10px; }
        .service-buttons { margin-top:10px; }
    </style>
</head>
<body>

<div class="staff-details-container">
    <h1><?= htmlspecialchars($barber['Name']) ?></h1>
    <p><strong>Email:</strong> <?= htmlspecialchars($barber['Email']) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($barber['Number']) ?></p>
    <p><strong>Bio:</strong><br><?= nl2br(htmlspecialchars($barber['Bio'])) ?></p>

    <h2>Services offered by <?= htmlspecialchars($barber['Name']) ?></h2>
    <?php if(empty($services)): ?>
        <p>No services assigned to this barber yet.</p>
    <?php else: ?>
        <div class="services-grid" style="display:flex; flex-wrap:wrap; gap:15px;">
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

</body>
</html>

