<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

$barberID = $_SESSION['BarberID'];
$appointmentID = $_GET['id'] ?? null;

if (!$appointmentID) {
    header("Location: barber_dashboard.php?view=appointments");
    exit();
}

// Get appointment details
$stmt = $conn->prepare("SELECT a.*, u.Name AS UserName, u.Email, u.Number, b.Name AS BarberName 
                       FROM Appointment a 
                       LEFT JOIN User u ON a.UserID = u.UserID 
                       LEFT JOIN Barber b ON a.BarberID = b.BarberID 
                       WHERE a.AppointmentID = ? AND a.BarberID = ?");
$stmt->bind_param("ii", $appointmentID, $barberID);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    header("Location: barber_dashboard.php?view=appointments&message=Appointment not found&success=0");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appointment Details</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1>Appointment Details</h1>
        
        <div class="appointment-details">
            <h2>Client Information</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($appointment['UserName']); ?></p>
            <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($appointment['Email']); ?>"><?php echo htmlspecialchars($appointment['Email']); ?></a></p>
            <p><strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($appointment['Number']); ?>"><?php echo htmlspecialchars($appointment['Number']); ?></a></p>
            
            <h2>Appointment Information</h2>
            <p><strong>Service For:</strong> <?php echo htmlspecialchars($appointment['ForName']); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($appointment['ForAge']); ?></p>
            <p><strong>Service Type:</strong> <?php echo htmlspecialchars($appointment['Type']); ?></p>
            <p><strong>Date & Time:</strong> <?php echo date('F j, Y g:i A', strtotime($appointment['Time'])); ?></p>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars($appointment['Duration']); ?> minutes</p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($appointment['Status']); ?></p>
            <p><strong>Cost:</strong> $<?php echo htmlspecialchars($appointment['Cost']); ?></p>
            <p><strong>Created:</strong> <?php echo date('F j, Y g:i A', strtotime($appointment['CreatedAt'])); ?></p>
        </div>
        
        <div class="actions">
            <a href="barber_dashboard.php?view=appointments" class="button">Back to Appointments</a>
            <a href="tel:<?php echo htmlspecialchars($appointment['Number']); ?>" class="button">Call Client</a>
            <a href="mailto:<?php echo htmlspecialchars($appointment['Email']); ?>" class="button">Email Client</a>
            
            <form method="POST" action="update_appointment_status.php" style="display: inline;">
                <input type="hidden" name="AppointmentID" value="<?php echo $appointmentID; ?>">
                <input type="hidden" name="redirect" value="view_appointment.php?id=<?php echo $appointmentID; ?>">
                <select name="Status" onchange="this.form.submit()">
                    <option value="scheduled" <?php echo $appointment['Status']=='scheduled'?'selected':''; ?>>Scheduled</option>
                    <option value="confirmed" <?php echo $appointment['Status']=='confirmed'?'selected':''; ?>>Confirmed</option>
                    <option value="in progress" <?php echo $appointment['Status']=='in progress'?'selected':''; ?>>In Progress</option>
                    <option value="completed" <?php echo $appointment['Status']=='completed'?'selected':''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $appointment['Status']=='cancelled'?'selected':''; ?>>Cancelled</option>
                </select>
            </form>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
