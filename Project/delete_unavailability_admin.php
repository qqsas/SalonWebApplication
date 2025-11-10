<?php
session_start();
// if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
//     header("Location: Login.php");
//     exit();
// }

include 'db.php';

$unavailabilityID = $_POST['UnavailabilityID'] ?? null;
$redirect = $_POST['redirect'] ?? 'admin_dashboard.php?view=working_hours';

if (!$unavailabilityID) {
    header("Location: $redirect&message=Unavailability ID required&success=0");
    exit();
}

try {
    $deleteStmt = $conn->prepare("DELETE FROM BarberUnavailability WHERE UnavailabilityID = ?");
    $deleteStmt->bind_param("i", $unavailabilityID);
    
    if ($deleteStmt->execute()) {
        header("Location: $redirect&message=Unavailability removed successfully&success=1");
    } else {
        header("Location: $redirect&message=Error removing unavailability&success=0");
    }
    
} catch (Exception $e) {
    header("Location: $redirect&message=Error: " . urlencode($e->getMessage()) . "&success=0");
}

exit();
?>
