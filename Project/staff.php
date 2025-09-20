<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch all barbers
$barberQuery = "SELECT a.BarberID, a.Name, a.Bio, u.Email, u.Number 
                FROM Admin a
                JOIN User u ON a.UserID = u.UserID
                ORDER BY a.Name ASC";
$barberResult = $conn->query($barberQuery);
$barbers = $barberResult ? $barberResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Staff - Kumar Kailey Hair & Beauty</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .staff-grid { display:flex; flex-wrap:wrap; gap:15px; }
        .staff-card { border:1px solid #ddd; padding:15px; border-radius:8px; flex:1 1 250px; }
        .staff-card h2 { margin-top:0; }
        .staff-card p { margin:5px 0; }
        .btn { display:inline-block; padding:6px 12px; background:#007BFF; color:white; text-decoration:none; border-radius:4px; }
    </style>
</head>
<body>

<div class="staff-container">
    <h1>Meet Our Barbers</h1>

    <?php if(empty($barbers)): ?>
        <p>No barbers available at the moment.</p>
    <?php else: ?>
        <div class="staff-grid">
            <?php foreach($barbers as $barber): ?>
                <div class="staff-card">
                    <h2><?= htmlspecialchars($barber['Name']) ?></h2>
                    <p><strong>Email:</strong> <?= htmlspecialchars($barber['Email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($barber['Number']) ?></p>
                    <p><strong>Bio:</strong> <?= htmlspecialchars(substr($barber['Bio'],0,100)) ?>...</p>
                    <a href="staff-detail.php?BarberID=<?= $barber['BarberID'] ?>" class="btn">View Profile</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

