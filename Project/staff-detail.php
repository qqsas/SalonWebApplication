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
    <!-- <link href="styles2.css" rel="stylesheet"> -->
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .staff-details-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
        }
        .barber-image {
            text-align: center;
            margin-bottom: 20px;
        }
        .barber-image img {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd;
        }
        .service-card {
            border: 1px solid #ddd;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px;
            background-color: #fafafa;
            flex: 1 1 250px;
        }
        .services-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: flex-start;
        }
        .btn {
            display: inline-block;
            background-color: #007BFF;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 6px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

<div class="staff-details-container">
    <div class="barber-image">
        <?php if (!empty($barber['ImgUrl']) && file_exists($barber['ImgUrl'])): ?>
            <img src="<?= htmlspecialchars($barber['ImgUrl']); ?>" alt="<?= htmlspecialchars($barber['Name']); ?>">
        <?php else: ?>
            <img src="Img/default_barber.png" alt="Default Barber Image">
        <?php endif; ?>
    </div>

    <h1><?= htmlspecialchars($barber['Name']) ?></h1>
    <p><strong>Email:</strong> <?= htmlspecialchars($barber['Email']) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($barber['Number']) ?></p>
    <p><strong>Bio:</strong><br><?= nl2br(htmlspecialchars($barber['Bio'])) ?></p>

    <h2>Services offered by <?= htmlspecialchars($barber['Name']) ?></h2>
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

</body>
</html>

