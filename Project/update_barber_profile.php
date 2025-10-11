<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barberID = $_POST['BarberID'] ?? null;
    $name = trim($_POST['Name'] ?? '');
    $bio = trim($_POST['Bio'] ?? '');
    $redirect = $_POST['redirect'] ?? 'barber_dashboard.php?view=profile';

    if (!$barberID || $barberID != $_SESSION['BarberID'] || !$name) {
        header("Location: $redirect&message=Invalid data&success=0");
        exit();
    }

    // Handle image upload if provided
    $imgPath = null;
    if (!empty($_FILES['ImgFile']['name'])) {
        $targetDir = "Img/";
        $fileName = basename($_FILES['ImgFile']['name']);
        $targetFile = $targetDir . time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $fileName);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($imageFileType, $allowedTypes)) {
            header("Location: $redirect&message=Only JPG, JPEG, PNG & GIF allowed&success=0");
            exit();
        }

        if ($_FILES['ImgFile']['size'] > 5*1024*1024) {
            header("Location: $redirect&message=File too large (max 5MB)&success=0");
            exit();
        }

        if (move_uploaded_file($_FILES['ImgFile']['tmp_name'], $targetFile)) {
            $imgPath = $targetFile;
        } else {
            header("Location: $redirect&message=Failed to upload image&success=0");
            exit();
        }
    }

    // Build SQL dynamically
    if ($imgPath) {
        $stmt = $conn->prepare("UPDATE Barber SET Name = ?, Bio = ?, ImgUrl = ? WHERE BarberID = ?");
        $stmt->bind_param("sssi", $name, $bio, $imgPath, $barberID);
    } else {
        $stmt = $conn->prepare("UPDATE Barber SET Name = ?, Bio = ? WHERE BarberID = ?");
        $stmt->bind_param("ssi", $name, $bio, $barberID);
    }

    if ($stmt->execute()) {
        header("Location: $redirect&message=Profile updated successfully&success=1");
    } else {
        header("Location: $redirect&message=Error updating profile&success=0");
    }
} else {
    header("Location: barber_dashboard.php");
}
exit();
?>

