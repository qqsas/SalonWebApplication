<?php
include 'db.php';

$now = date('Y-m-d H:i:s');

$stmt = $conn->prepare("
    UPDATE Appointment 
    SET Status = 'Completed' 
    WHERE Status != 'Cancelled' AND Status != 'Completed' AND Time < ?
");
$stmt->bind_param("s", $now);
$stmt->execute();

echo "Appointments updated.\n";

