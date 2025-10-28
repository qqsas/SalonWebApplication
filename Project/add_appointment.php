<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // Include your mail setup
include 'header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID   = $_POST['UserID'];
    $barberID = $_POST['BarberID'];
    $forName  = trim($_POST['ForName']);
    $forAge   = intval($_POST['ForAge']);
    $type     = trim($_POST['Type']);
    $time     = $_POST['Time'];
    $duration = intval($_POST['Duration']);
    $cost     = floatval($_POST['Cost']);
    $status   = $_POST['Status'] ?? 'Scheduled';

    // Basic validation
    if (empty($forName)) $errors[] = "ForName is required.";
    if ($forAge <= 0) $errors[] = "Age must be a positive number.";
    if (empty($type)) $errors[] = "Type is required.";
    if (empty($time)) $errors[] = "Time is required.";
    if ($duration <= 0) $errors[] = "Duration must be positive.";
    if ($cost < 0) $errors[] = "Cost must be positive.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Appointment (UserID, BarberID, ForName, ForAge, Type, Time, Duration, Cost, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissisdds", $userID, $barberID, $forName, $forAge, $type, $time, $duration, $cost, $status);

        if ($stmt->execute()) {
            $success = "Appointment added successfully!";
            $appointment_id = $conn->insert_id;

            // --- Fetch user and barber info for email ---
            $stmt2 = $conn->prepare("
                SELECT u.Name AS UserName, u.Email AS UserEmail, b.Name AS BarberName, bu.Email AS BarberEmail
                FROM Appointment a
                JOIN User u ON a.UserID = u.UserID
                JOIN Barber b ON a.BarberID = b.BarberID
                JOIN User bu ON b.UserID = bu.UserID
                WHERE a.AppointmentID = ?
            ");
            $stmt2->bind_param("i", $appointment_id);
            $stmt2->execute();
            $emails = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            if ($emails) {
                $mail = getMailer(); // PHPMailer object

                try {
                    $mail->addAddress($emails['UserEmail'], $emails['UserName']);
                    $mail->addAddress($emails['BarberEmail'], $emails['BarberName']);

                    $mail->isHTML(true);
                    $mail->Subject = "New Appointment Scheduled";
                    $mail->Body = "
                        <h2>New Appointment Scheduled</h2>
                        <p>Dear Customer/Barber,</p>
                        <p>An appointment has been scheduled for <strong>{$forName}</strong> with <strong>{$emails['BarberName']}</strong> 
                        for the service <strong>{$type}</strong>.</p>
                        <p><strong>Date & Time:</strong> " . date('F j, Y \a\t g:i A', strtotime($time)) . "</p>
                        <p><strong>Duration:</strong> {$duration} minutes<br>
                        <strong>Cost:</strong> R" . number_format($cost, 2) . "<br>
                        <strong>Status:</strong> {$status}</p>
                        <p>Thank you for choosing our services!</p>
                    ";
                    $mail->AltBody = "Appointment for {$forName} with {$emails['BarberName']} for {$type} on " . date('F j, Y \a\t g:i A', strtotime($time)) . ". Status: {$status}.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mail Error: {$mail->ErrorInfo}");
                }
            }

        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch users and barbers
$users   = $conn->query("SELECT UserID, Name FROM User WHERE IsDeleted=0");
$barbers = $conn->query("SELECT BarberID, Name FROM Barber WHERE IsDeleted=0");
?>

<h2>Add New Appointment</h2>
    <link href="addedit.css" rel="stylesheet">

<?php if ($errors): ?>
    <div style="color:red;"><ul>
    <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
    </ul></div>
<?php endif; ?>

<?php if ($success): ?>
    <div style="color:green;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST">
    <label>User:</label><br>
    <select name="UserID" required>
        <?php while($u = $users->fetch_assoc()): ?>
            <option value="<?= $u['UserID'] ?>"><?= htmlspecialchars($u['Name']) ?></option>
        <?php endwhile; ?>
    </select><br><br>

    <label>Barber:</label><br>
    <select name="BarberID" required>
        <?php while($b = $barbers->fetch_assoc()): ?>
            <option value="<?= $b['BarberID'] ?>"><?= htmlspecialchars($b['Name']) ?></option>
        <?php endwhile; ?>
    </select><br><br>

    <label>For Name:</label><br>
    <input type="text" name="ForName" required><br><br>

    <label>For Age:</label><br>
    <input type="number" name="ForAge" min="1" required><br><br>

    <label>Type:</label><br>
    <input type="text" name="Type" required><br><br>

    <label>Time:</label><br>
    <input type="datetime-local" name="Time" required><br><br>

    <label>Duration (minutes):</label><br>
    <input type="number" name="Duration" min="1" required><br><br>

    <label>Cost:</label><br>
    <input type="number" step="0.01" name="Cost" min="0" required><br><br>

    <label>Status:</label><br>
    <select name="Status">
        <option value="Scheduled">Scheduled</option>
        <option value="Completed">Completed</option>
        <option value="Cancelled">Cancelled</option>
    </select><br><br>

    <button type="submit">Add Appointment</button>
</form>

