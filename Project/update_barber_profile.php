<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barberID = $_POST['BarberID'] ?? null;
    $name = trim($_POST['Name'] ?? '');
    $bio = trim($_POST['Bio'] ?? '');
    $redirect = $_POST['redirect'] ?? 'barber_dashboard.php?view=profile';
    
    if ($barberID && $barberID == $_SESSION['BarberID'] && $name) {
        $stmt = $conn->prepare("UPDATE Barber SET Name = ?, Bio = ? WHERE BarberID = ?");
        $stmt->bind_param("ssi", $name, $bio, $barberID);
        
        if ($stmt->execute()) {
            header("Location: $redirect&message=Profile updated successfully&success=1");
        } else {
            header("Location: $redirect&message=Error updating profile&success=0");
        }
    } else {
        header("Location: $redirect&message=Invalid data&success=0");
    }
} else {
    header("Location: barber_dashboard.php");
}
exit();
?>
