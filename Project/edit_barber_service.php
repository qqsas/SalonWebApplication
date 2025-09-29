<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

$barberID = $_SESSION['BarberID'];
$barberServiceID = $_GET['id'] ?? null;
$message = '';
$success = false;

if (!$barberServiceID) {
    header("Location: barber_dashboard.php?view=services");
    exit();
}

// Verify this service belongs to the barber
$stmt = $conn->prepare("SELECT bs.*, s.Name, s.Description, s.Price, s.Time 
                       FROM BarberServices bs 
                       LEFT JOIN Services s ON bs.ServicesID = s.ServicesID 
                       WHERE bs.BarberServiceID = ? AND bs.BarberID = ? AND bs.IsDeleted = 0");
$stmt->bind_param("ii", $barberServiceID, $barberID);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();

if (!$service) {
    header("Location: barber_dashboard.php?view=services&message=Service not found&success=0");
    exit();
}

// Get available services for dropdown
$services = $conn->query("SELECT * FROM Services WHERE IsDeleted = 0 ORDER BY Name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newServiceID = $_POST['ServicesID'] ?? null;
    
    if ($newServiceID) {
        $stmt = $conn->prepare("UPDATE BarberServices SET ServicesID = ? WHERE BarberServiceID = ? AND BarberID = ?");
        $stmt->bind_param("iii", $newServiceID, $barberServiceID, $barberID);
        
        if ($stmt->execute()) {
            $message = "Service updated successfully";
            $success = true;
            // Refresh service data
            $stmt = $conn->prepare("SELECT bs.*, s.Name, s.Description, s.Price, s.Time 
                                   FROM BarberServices bs 
                                   LEFT JOIN Services s ON bs.ServicesID = s.ServicesID 
                                   WHERE bs.BarberServiceID = ?");
            $stmt->bind_param("i", $barberServiceID);
            $stmt->execute();
            $result = $stmt->get_result();
            $service = $result->fetch_assoc();
        } else {
            $message = "Error updating service";
        }
    } else {
        $message = "Please select a service";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Service</title>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1>Edit Service</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="ServicesID">Service:</label>
                <select name="ServicesID" id="ServicesID" required>
                    <option value="">-- Choose a Service --</option>
                    <?php while ($s = $services->fetch_assoc()): ?>
                        <option value="<?php echo $s['ServicesID']; ?>" <?php echo $s['ServicesID'] == $service['ServicesID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['Name']); ?> - $<?php echo $s['Price']; ?> (<?php echo $s['Time']; ?> mins)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="current-info">
                <h3>Current Service Details:</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($service['Name']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($service['Description']); ?></p>
                <p><strong>Price:</strong> $<?php echo $service['Price']; ?></p>
                <p><strong>Duration:</strong> <?php echo $service['Time']; ?> minutes</p>
            </div>
            
            <button type="submit">Update Service</button>
            <a href="barber_dashboard.php?view=services" class="button">Cancel</a>
        </form>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
