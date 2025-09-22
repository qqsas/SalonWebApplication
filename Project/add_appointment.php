<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';
include 'header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = $_POST['UserID'];
    $barberID = $_POST['BarberID'];
    $forName = $_POST['ForName'];
    $forAge = $_POST['ForAge'];
    $type = $_POST['Type'];
    $time = $_POST['Time'];
    $duration = $_POST['Duration'];
    $cost = $_POST['Cost'];
    $status = $_POST['Status'] ?? 'Scheduled';

    // Basic validation
    if (empty($forName)) $errors[] = "ForName is required.";
    if (!is_numeric($forAge) || $forAge <= 0) $errors[] = "Age must be a positive number.";
    if (empty($type)) $errors[] = "Type is required.";
    if (empty($time)) $errors[] = "Time is required.";
    if (!is_numeric($duration) || $duration <= 0) $errors[] = "Duration must be positive.";
    if (!is_numeric($cost) || $cost < 0) $errors[] = "Cost must be a positive number.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Appointment (UserID, BarberID, ForName, ForAge, Type, Time, Duration, Cost, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissisdds", $userID, $barberID, $forName, $forAge, $type, $time, $duration, $cost, $status);
        if ($stmt->execute()) {
            $success = "Appointment added successfully!";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch users and barbers
$users = $conn->query("SELECT UserID, Name FROM User WHERE IsDeleted=0");
$barbers = $conn->query("SELECT BarberID, Name FROM Barber WHERE IsDeleted=0");
?>

<h2>Add New Appointment</h2>

<?php
if ($errors) {
    echo "<div style='color:red;'><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul></div>";
}
if ($success) {
    echo "<div style='color:green;'>" . htmlspecialchars($success) . "</div>";
}
?>

<form method="POST">
    <label>User:</label><br>
    <select name="UserID" required>
        <?php while($u = $users->fetch_assoc()) {
            echo "<option value='{$u['UserID']}'>{$u['Name']}</option>";
        } ?>
    </select><br><br>

    <label>Barber:</label><br>
    <select name="BarberID" required>
        <?php while($b = $barbers->fetch_assoc()) {
            echo "<option value='{$b['BarberID']}'>{$b['Name']}</option>";
        } ?>
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

