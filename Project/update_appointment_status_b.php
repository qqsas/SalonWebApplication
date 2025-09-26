<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentID = $_POST['AppointmentID'] ?? null;
    $status = $_POST['Status'] ?? '';
    $redirect = $_POST['redirect'] ?? 'barber_dashboard.php?view=appointments';
    
    if ($appointmentID && $status) {
        // Verify the appointment belongs to this barber
        $stmt = $conn->prepare("SELECT BarberID FROM Appointment WHERE AppointmentID = ?");
        $stmt->bind_param("i", $appointmentID);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        
        if ($appointment && $appointment['BarberID'] == $_SESSION['BarberID']) {
            $stmt = $conn->prepare("UPDATE Appointment SET Status = ? WHERE AppointmentID = ?");
            $stmt->bind_param("si", $status, $appointmentID);
            
            if ($stmt->execute()) {
                header("Location: $redirect&message=Status updated successfully&success=1");
            } else {
                header("Location: $redirect&message=Error updating status&success=0");
            }
        } else {
            header("Location: $redirect&message=Appointment not found&success=0");
        }
    } else {
        header("Location: $redirect&message=Invalid data&success=0");
    }
} else {
    header("Location: barber_dashboard.php");
}
exit();
?>
