<?php
session_start();
include 'db.php';

// --- Access Control: Only admin or logged-in user can edit ---
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$appointment_id = $_GET['id'] ?? null;
if (!$appointment_id) {
    echo "Invalid appointment ID.";
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
        } else {
            echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>All required fields must be filled.</p>";
    }
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

// --- Fetch barbers ---
$barbers = $conn->query("SELECT BarberID, Name FROM Barber");

// --- Fetch services ---
$services = $conn->query("SELECT ServicesID, Name, Price, Time FROM Services WHERE IsDeleted=0");
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Appointment</h2>

    <form method="post">
        <div>
            <label for="forName">For Name:</label><br>
            <input type="text" name="forName" id="forName" value="<?php echo htmlspecialchars($appointment['ForName']); ?>" required>
        </div>

        <div>
            <label for="forAge">For Age:</label><br>
            <input type="number" name="forAge" id="forAge" value="<?php echo htmlspecialchars($appointment['ForAge']); ?>" required>
        </div>

        <div>
            <label for="service_id">Service:</label><br>
            <select name="service_id" id="service_id" required>
                <?php while ($service = $services->fetch_assoc()) { ?>
                    <option value="<?php echo $service['ServicesID']; ?>" 
                        <?php echo ($service['Name'] == $appointment['Type']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($service['Name']) . " - R" . $service['Price'] . " (" . $service['Time'] . " min)"; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label for="barber_id">Barber:</label><br>
            <select name="barber_id" id="barber_id" required>
                <?php while ($barber = $barbers->fetch_assoc()) { ?>
                    <option value="<?php echo $barber['BarberID']; ?>" 
                        <?php echo ($barber['BarberID'] == $appointment['BarberID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($barber['Name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label for="time">Time:</label><br>
            <input type="datetime-local" name="time" id="time" value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['Time'])); ?>" required>
        </div>

        <div>
            <label for="duration">Duration (minutes):</label><br>
            <input type="number" name="duration" id="duration" value="<?php echo htmlspecialchars($appointment['Duration']); ?>" required>
        </div>

        <div>
            <label for="cost">Cost:</label><br>
            <input type="number" step="0.01" name="cost" id="cost" value="<?php echo htmlspecialchars($appointment['Cost']); ?>" required>
        </div>

        <div>
            <label for="status">Status:</label><br>
            <select name="status" id="status" required>
                <option value="Pending"   <?php if ($appointment['Status']=="Pending") echo "selected"; ?>>Pending</option>
                <option value="Confirmed" <?php if ($appointment['Status']=="Confirmed") echo "selected"; ?>>Confirmed</option>
                <option value="Completed" <?php if ($appointment['Status']=="Completed") echo "selected"; ?>>Completed</option>
                <option value="Cancelled" <?php if ($appointment['Status']=="Cancelled") echo "selected"; ?>>Cancelled</option>
            </select>
        </div>

        <br>
        <button type="submit">Update Appointment</button>
    </form>
</div>
<?php include 'footer.php'; ?>

