<?php
session_start();
// if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
//     header("Location: Login.php");
//     exit();
// }

include 'db.php';

$barberID = $_POST['BarberID'] ?? null;
$date = $_POST['Date'] ?? null;
$startTime = $_POST['StartTime'] ?: null;
$endTime = $_POST['EndTime'] ?: null;
$reason = $_POST['Reason'] ?: null;
$redirect = $_POST['redirect'] ?? 'admin_dashboard.php?view=working_hours';

// Validate required field (only date is required)
if (!$barberID || !$date) {
    header("Location: $redirect&message=Date is required&success=0");
    exit();
}

try {
    // Handle different time scenarios
    if ($startTime && !$endTime) {
        // If only start time is provided, set end time to end of day (23:59:59)
        $endTime = '23:59:59';
        
        // Ensure start time has seconds
        if (strlen($startTime) === 5) $startTime .= ':00';
        
    } elseif (!$startTime && $endTime) {
        // If only end time is provided, set start time to start of day (00:00:00)
        $startTime = '00:00:00';
        
        // Ensure end time has seconds
        if (strlen($endTime) === 5) $endTime .= ':00';
        
    } elseif ($startTime && $endTime) {
        // If both times are provided, validate time order
        if ($startTime >= $endTime) {
            header("Location: $redirect&message=End time must be after start time&success=0");
            exit();
        }
        
        // Ensure times have seconds
        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5) $endTime .= ':00';
        
    } else {
        // If no times are provided, set all day unavailability (00:00:00 to 23:59:59)
        $startTime = '00:00:00';
        $endTime = '23:59:59';
    }
    
    // Check for overlapping unavailability
    $checkStmt = $conn->prepare("SELECT * FROM BarberUnavailability WHERE BarberID = ? AND Date = ? AND ((StartTime <= ? AND EndTime >= ?) OR (StartTime <= ? AND EndTime >= ?))");
    $checkStmt->bind_param("isssss", $barberID, $date, $endTime, $startTime, $startTime, $endTime);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        header("Location: $redirect&message=Unavailability period overlaps with existing unavailability&success=0");
        exit();
    }
    
    // Insert new unavailability
    $insertStmt = $conn->prepare("INSERT INTO BarberUnavailability (BarberID, Date, StartTime, EndTime, Reason) VALUES (?, ?, ?, ?, ?)");
    $insertStmt->bind_param("issss", $barberID, $date, $startTime, $endTime, $reason);
    
    if ($insertStmt->execute()) {
        header("Location: $redirect&message=Unavailability added successfully&success=1");
    } else {
        header("Location: $redirect&message=Error adding unavailability&success=0");
    }
    
} catch (Exception $e) {
    header("Location: $redirect&message=Error: " . urlencode($e->getMessage()) . "&success=0");
}

exit();
?>
