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

// Verify the service belongs to the logged-in barber
$stmt = $conn->prepare("SELECT bs.*, s.Name, s.Description, s.Price, s.Time, s.ServicesID
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name'] ?? '');
    $description = trim($_POST['Description'] ?? '');
    $price = floatval($_POST['Price'] ?? 0);
    $time = intval($_POST['Time'] ?? 0);

    if ($name && $description && $price > 0 && $time > 0) {
        // Update the Services table directly
        $stmt = $conn->prepare("UPDATE Services 
                                SET Name = ?, Description = ?, Price = ?, Time = ?
                                WHERE ServicesID = ?");
        $stmt->bind_param("ssdii", $name, $description, $price, $time, $service['ServicesID']);

        if ($stmt->execute()) {
            $message = "Service details updated successfully.";
            $success = true;

            // Refresh the updated service details
            $stmt = $conn->prepare("SELECT bs.*, s.Name, s.Description, s.Price, s.Time, s.ServicesID
                                    FROM BarberServices bs
                                    LEFT JOIN Services s ON bs.ServicesID = s.ServicesID
                                    WHERE bs.BarberServiceID = ?");
            $stmt->bind_param("i", $barberServiceID);
            $stmt->execute();
            $result = $stmt->get_result();
            $service = $result->fetch_assoc();
        } else {
            $message = "Error updating service details.";
        }
    } else {
        $message = "Please fill in all fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Service</title>
    <link rel="stylesheet" href="addedit.css">
    <style>
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        textarea { resize: vertical; }
        button, .button { padding: 10px 15px; border: none; border-radius: 4px; background: #007BFF; color: white; cursor: pointer; text-decoration: none; }
        button:hover, .button:hover { background: #0056b3; }
        .message.success { color: green; margin-bottom: 15px; }
        .message.error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <h1>Edit Service Details</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="Name">Service Name:</label>
            <input type="text" name="Name" id="Name" value="<?= htmlspecialchars($service['Name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="Description">Description:</label>
            <textarea name="Description" id="Description" rows="4" required><?= htmlspecialchars($service['Description']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="Price">Price (in Rands):</label>
            <input type="number" step="0.01" name="Price" id="Price" value="<?= htmlspecialchars($service['Price']) ?>" required>
        </div>

        <div class="form-group">
            <label for="Time">Duration (minutes):</label>
            <input type="number" name="Time" id="Time" value="<?= htmlspecialchars($service['Time']) ?>" required>
        </div>

        <button type="submit">Save Changes</button>
        <a href="barber_dashboard.php?view=services" class="button">Cancel</a>
    </form>
</div>

<?php include 'footer.php'; ?>
</body>
</html>

