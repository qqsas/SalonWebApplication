<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

$barberID = $_SESSION['BarberID'];
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceID = $_POST['ServicesID'] ?? null;
    
    if ($serviceID) {
        // Check if service already exists for this barber
        $stmt = $conn->prepare("SELECT * FROM BarberServices WHERE BarberID = ? AND ServicesID = ? AND IsDeleted = 0");
        $stmt->bind_param("ii", $barberID, $serviceID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO BarberServices (BarberID, ServicesID) VALUES (?, ?)");
            $stmt->bind_param("ii", $barberID, $serviceID);
            
            if ($stmt->execute()) {
                $message = "Service added successfully";
                $success = true;
            } else {
                $message = "Error adding service";
            }
        } else {
            $message = "Service already exists in your offerings";
        }
    } else {
        $message = "Please select a service";
    }
}

// Get available services
$services = $conn->query("SELECT * FROM Services WHERE IsDeleted = 0 ORDER BY Name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Service</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1>Add Service to My Offerings</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="ServicesID">Select Service:</label>
                <select name="ServicesID" id="ServicesID" required>
                    <option value="">-- Choose a Service --</option>
                    <?php while ($service = $services->fetch_assoc()): ?>
                        <option value="<?php echo $service['ServicesID']; ?>">
                            <?php echo htmlspecialchars($service['Name']); ?> - $<?php echo $service['Price']; ?> (<?php echo $service['Time']; ?> mins)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit">Add Service</button>
            <a href="barber_dashboard.php?view=services" class="button">Cancel</a>
        </form>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
