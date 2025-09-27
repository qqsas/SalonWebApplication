<?php
session_start();
include 'db.php';
include 'mail.php'; // Include your mail setup

// --- Access Control: Only admin or logged-in user can edit ---
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$appointment_id = $_GET['id'] ?? null;
if (!$appointment_id) {
    echo "Invalid appointment ID.";
    exit();
}

// --- Fetch appointment details ---
$stmt = $conn->prepare("SELECT * FROM Appointment WHERE AppointmentID=?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    echo "Appointment not found.";
    exit();
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $forName   = trim($_POST['forName']);
    $forAge    = intval($_POST['forAge']);
    $serviceId = intval($_POST['service_id']);
    $barberId  = intval($_POST['barber_id']);
    $time      = $_POST['time'];
    $duration  = intval($_POST['duration']);
    $status    = trim($_POST['status']);
    $cost      = floatval($_POST['cost']);

    if ($forName && $forAge && $serviceId && $barberId && $time && $duration && $status) {
        // Fetch service name
        $stmt = $conn->prepare("SELECT Name FROM Services WHERE ServicesID=?");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $service = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $serviceType = $service ? $service['Name'] : 'Custom';

        $stmt = $conn->prepare("UPDATE Appointment 
            SET ForName=?, ForAge=?, Type=?, BarberID=?, Time=?, Duration=?, Status=?, Cost=? 
            WHERE AppointmentID=?");
        $stmt->bind_param("sssisidsi", $forName, $forAge, $serviceType, $barberId, $time, $duration, $status, $cost, $appointment_id);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Appointment updated successfully.</p>";

            // --- Fetch user and barber emails for notifications ---
            $stmt = $conn->prepare("
                SELECT u.Name AS UserName, u.Email AS UserEmail, b.Name AS BarberName, bu.Email AS BarberEmail
                FROM Appointment a
                JOIN User u ON a.UserID = u.UserID
                JOIN Barber b ON a.BarberID = b.BarberID
                JOIN User bu ON b.UserID = bu.UserID
                WHERE a.AppointmentID = ?
            ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $emails = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($emails) {
                $mail = getMailer(); // PHPMailer object

                try {
                    // Add recipients
                    $mail->addAddress($emails['UserEmail'], $emails['UserName']);
                    $mail->addAddress($emails['BarberEmail'], $emails['BarberName']);

                    $mail->isHTML(true);
                    $mail->Subject = "Appointment Updated: {$status}";
                    $mail->Body = "
                        <h2>Appointment Update</h2>
                        <p>Dear Customer/Barber,</p>
                        <p>The appointment for <strong>{$forName}</strong> with <strong>{$emails['BarberName']}</strong> 
                        for the service <strong>{$serviceType}</strong> scheduled on <strong>" . date('F j, Y \a\t g:i A', strtotime($time)) . "</strong> 
                        has been updated to status: <strong>{$status}</strong>.</p>
                        <p>Duration: {$duration} minutes<br>Cost: R" . number_format($cost, 2) . "</p>
                        <p>Thank you!</p>
                    ";
                    $mail->AltBody = "The appointment for {$forName} with {$emails['BarberName']} for {$serviceType} on " . date('F j, Y \a\t g:i A', strtotime($time)) . " has been updated. Status: {$status}.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mail Error: {$mail->ErrorInfo}");
                }
            }
        } else {
            echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>All required fields must be filled.</p>";
    }
}

// --- Fetch barbers ---
$barbers = $conn->query("SELECT BarberID, Name FROM Barber");

// --- Fetch services ---
$services = $conn->query("SELECT ServicesID, Name, Price, Time FROM Services WHERE IsDeleted=0");

include 'header.php';
?>

<div class="container">
    <h2>Edit Appointment</h2>

    <form method="post">
        <div>
            <label for="forName">For Name:</label><br>
            <input type="text" name="forName" id="forName" value="<?= htmlspecialchars($appointment['ForName']) ?>" required>
        </div>

        <div>
            <label for="forAge">For Age:</label><br>
            <input type="number" name="forAge" id="forAge" value="<?= htmlspecialchars($appointment['ForAge']) ?>" required>
        </div>

        <div>
            <label for="service_id">Service:</label><br>
            <select name="service_id" id="service_id" required>
                <?php while ($service = $services->fetch_assoc()) { ?>
                    <option value="<?= $service['ServicesID'] ?>" <?= ($service['Name'] == $appointment['Type']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($service['Name']) ?> - R<?= $service['Price'] ?> (<?= $service['Time'] ?> min)
                    </option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label for="barber_id">Barber:</label><br>
            <select name="barber_id" id="barber_id" required>
                <?php while ($barber = $barbers->fetch_assoc()) { ?>
                    <option value="<?= $barber['BarberID'] ?>" <?= ($barber['BarberID'] == $appointment['BarberID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($barber['Name']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label for="time">Time:</label><br>
            <input type="datetime-local" name="time" id="time" value="<?= date('Y-m-d\TH:i', strtotime($appointment['Time'])) ?>" required>
        </div>

        <div>
            <label for="duration">Duration (minutes):</label><br>
            <input type="number" name="duration" id="duration" value="<?= htmlspecialchars($appointment['Duration']) ?>" required>
        </div>

        <div>
            <label for="cost">Cost:</label><br>
            <input type="number" step="0.01" name="cost" id="cost" value="<?= htmlspecialchars($appointment['Cost']) ?>" required>
        </div>

        <div>
            <label for="status">Status:</label><br>
            <select name="status" id="status" required>
                <option value="Pending"   <?= ($appointment['Status']=="Pending") ? "selected" : "" ?>>Pending</option>
                <option value="Confirmed" <?= ($appointment['Status']=="Confirmed") ? "selected" : "" ?>>Confirmed</option>
                <option value="Completed" <?= ($appointment['Status']=="Completed") ? "selected" : "" ?>>Completed</option>
                <option value="Cancelled" <?= ($appointment['Status']=="Cancelled") ? "selected" : "" ?>>Cancelled</option>
            </select>
        </div>

        <br>
        <button type="submit">Update Appointment</button>
    </form>
</div>

<?php include 'footer.php'; ?>

