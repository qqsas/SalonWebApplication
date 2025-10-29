<?php
session_start();
if (!isset($_SESSION['UserID']) || (strtolower($_SESSION['Role'] ?? '') !== 'barber')) {
    header("Location: Login.php");
    exit();
}

include 'db.php';

$message = '';
$success = false;
$barberID = null;

// Ensure we have a BarberID in session (some flows may store only UserID)
if (!empty($_SESSION['BarberID'])) {
    $barberID = (int) $_SESSION['BarberID'];
} else {
    // Try to look up BarberID from the Barber table using the logged-in UserID
    $userID = (int) $_SESSION['UserID'];
    $stmt = $conn->prepare("SELECT BarberID FROM Barber WHERE UserID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $barberID = (int) $row['BarberID'];
            $_SESSION['BarberID'] = $barberID;
        } else {
            $message = "Could not find your barber profile. Please contact admin.";
        }
        $stmt->close();
    } else {
        $message = "Database error (looking up barber): " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $barberID) {
    $serviceID = isset($_POST['ServicesID']) ? (int) $_POST['ServicesID'] : 0;

    if ($serviceID <= 0) {
        $message = "Please select a valid service.";
    } else {
        // Check if a row exists for this barber & service (regardless of IsDeleted)
        $stmt = $conn->prepare("SELECT IsDeleted FROM BarberServices WHERE BarberID = ? AND ServicesID = ? LIMIT 1");
        if (!$stmt) {
            $message = "Database error (prepare select): " . $conn->error;
        } else {
            $stmt->bind_param("ii", $barberID, $serviceID);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                // Row exists
                if ((int)$row['IsDeleted'] === 1) {
                    // Restore the soft-deleted row
                    $stmt->close();
                    $upd = $conn->prepare("UPDATE BarberServices SET IsDeleted = 0 WHERE BarberID = ? AND ServicesID = ?");
                    if ($upd) {
                        $upd->bind_param("ii", $barberID, $serviceID);
                        if ($upd->execute()) {
                            $message = "Service restored to your offerings.";
                            $success = true;
                        } else {
                            $message = "Failed to restore service: " . $upd->error;
                        }
                        $upd->close();
                    } else {
                        $message = "Database error (prepare update): " . $conn->error;
                    }
                } else {
                    $message = "Service already exists in your offerings.";
                    $stmt->close();
                }
            } else {
                // No row exists -> insert a new one
                $stmt->close();
                $ins = $conn->prepare("INSERT INTO BarberServices (BarberID, ServicesID) VALUES (?, ?)");
                if ($ins) {
                    $ins->bind_param("ii", $barberID, $serviceID);
                    if ($ins->execute()) {
                        $message = "Service added successfully.";
                        $success = true;
                    } else {
                        $message = "Error adding service: " . $ins->error;
                    }
                    $ins->close();
                } else {
                    $message = "Database error (prepare insert): " . $conn->error;
                }
            }
        }
    }
}

// Get available services
$services = $conn->query("SELECT * FROM Services WHERE IsDeleted = 0 ORDER BY Name");
if (!$services) {
    // only set message if we don't already have a message
    if (!$message) $message = "Failed to load services: " . $conn->error;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Service</title>
    <link rel="stylesheet" href="addedit.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1>Add Service to My Offerings</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="ServicesID">Select Service:</label>
                <select name="ServicesID" id="ServicesID" required>
                    <option value="">-- Choose a Service --</option>
                    <?php if ($services): while ($service = $services->fetch_assoc()): ?>
                        <option value="<?php echo (int)$service['ServicesID']; ?>">
                            <?php echo htmlspecialchars($service['Name']); ?> - $<?php echo htmlspecialchars($service['Price']); ?> (<?php echo htmlspecialchars($service['Time']); ?> mins)
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            
            <button type="submit">Add Service</button>
            <a href="barber_dashboard.php?view=services" class="button">Cancel</a>
        </form>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>

