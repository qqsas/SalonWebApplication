<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barberID = $_POST['BarberID'] ?? null;
    $workingDays = $_POST['workingDays'] ?? [];
    $startTimes = $_POST['startTime'] ?? [];
    $endTimes = $_POST['endTime'] ?? [];
    $redirect = $_POST['redirect'] ?? 'barber_dashboard.php?view=workinghours';
    
    if ($barberID && $barberID == $_SESSION['BarberID']) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // First, remove all existing working hours for this barber
            $stmt = $conn->prepare("DELETE FROM BarberWorkingHours WHERE BarberID = ?");
            $stmt->bind_param("i", $barberID);
            $stmt->execute();
            
            // Insert new working hours for selected days
            foreach ($workingDays as $day) {
                $day = (int)$day;
                $startTime = $startTimes[$day] ?? '09:00';
                $endTime = $endTimes[$day] ?? '17:00';
                
                $stmt = $conn->prepare("INSERT INTO BarberWorkingHours (BarberID, DayOfWeek, StartTime, EndTime) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $barberID, $day, $startTime, $endTime);
                $stmt->execute();
            }
            
            $conn->commit();
            header("Location: $redirect&message=Working hours updated successfully&success=1");
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: $redirect&message=Error updating working hours&success=0");
        }
    } else {
        header("Location: $redirect&message=Invalid barber ID&success=0");
    }
} else {
    header("Location: barber_dashboard.php");
}
exit();
?>
