<?php
session_start();
// if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
//     header("Location: Login.php");
//     exit();
// }

include 'db.php';

$barberID = $_POST['BarberID'] ?? null;
$redirect = $_POST['redirect'] ?? 'admin_dashboard.php?view=working_hours';
$workingDays = $_POST['workingDays'] ?? [];
$startTimes = $_POST['startTime'] ?? [];
$endTimes = $_POST['endTime'] ?? [];

if (!$barberID) {
    header("Location: $redirect&message=Barber ID required&success=0");
    exit();
}

try {
    // First, verify the barber exists and is not deleted
    $checkStmt = $conn->prepare("SELECT BarberID FROM Barber WHERE BarberID = ? AND IsDeleted = 0");
    $checkStmt->bind_param("i", $barberID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: $redirect&message=Barber not found or has been deleted&success=0");
        exit();
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Delete existing working hours for this barber
    $deleteStmt = $conn->prepare("DELETE FROM BarberWorkingHours WHERE BarberID = ?");
    $deleteStmt->bind_param("i", $barberID);
    $deleteStmt->execute();
    
    // Insert new working hours for selected days
    if (!empty($workingDays) && is_array($workingDays)) {
        $insertStmt = $conn->prepare("INSERT INTO BarberWorkingHours (BarberID, DayOfWeek, StartTime, EndTime) VALUES (?, ?, ?, ?)");
        
        foreach ($workingDays as $day) {
            $startTime = $startTimes[$day] ?? '09:00:00';
            $endTime = $endTimes[$day] ?? '17:00:00';
            
            // Ensure times have seconds
            if (strlen($startTime) === 5) $startTime .= ':00';
            if (strlen($endTime) === 5) $endTime .= ':00';
            
            // Validate time order
            if ($startTime >= $endTime) {
                $conn->rollback();
                header("Location: $redirect&message=End time must be after start time for " . getDayName($day) . "&success=0");
                exit();
            }
            
            // Validate day number
            if ($day < 1 || $day > 7) {
                $conn->rollback();
                header("Location: $redirect&message=Invalid day number&success=0");
                exit();
            }
            
            $insertStmt->bind_param("iiss", $barberID, $day, $startTime, $endTime);
            if (!$insertStmt->execute()) {
                $conn->rollback();
                header("Location: $redirect&message=Error saving working hours for " . getDayName($day) . "&success=0");
                exit();
            }
        }
        $insertStmt->close();
    }
    
    $conn->commit();
    header("Location: $redirect&message=Working hours updated successfully&success=1");
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    header("Location: $redirect&message=Error updating working hours: " . urlencode($e->getMessage()) . "&success=0");
}

exit();

// Helper function to get day name for error messages
function getDayName($dayNumber) {
    $days = [
        1 => 'Monday',
        2 => 'Tuesday', 
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];
    return $days[$dayNumber] ?? 'Unknown day';
}
?>
