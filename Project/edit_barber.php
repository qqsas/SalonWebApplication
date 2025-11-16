<?php
session_start();
include 'db.php';

$pageRoleRequirement = 'none';

// --- Only allow admin access ---
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

$barber_id = $_GET['id'] ?? null;
if (!$barber_id) {
    echo "Invalid barber ID.";
    exit();
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $bio = trim($_POST['bio']);
    $user_id = intval($_POST['user_id']);
    $services = $_POST['services'] ?? [];

    if (!$name || !$user_id) {
        echo "<p style='color:red;'>Name and UserID are required.</p>";
        exit();
    }

    // --- Handle image upload ---
    $imgPath = null;
    if (!empty($_FILES['ImgFile']['name'])) {
        $targetDir = "Img/";
        $fileName = basename($_FILES['ImgFile']['name']);
        $targetFile = $targetDir . time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $fileName);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($imageFileType, $allowedTypes)) {
            echo "<p style='color:red;'>Only JPG, JPEG, PNG & GIF allowed.</p>";
            exit();
        }

        if ($_FILES['ImgFile']['size'] > 5 * 1024 * 1024) {
            echo "<p style='color:red;'>File too large (max 5MB).</p>";
            exit();
        }

        if (!move_uploaded_file($_FILES['ImgFile']['tmp_name'], $targetFile)) {
            echo "<p style='color:red;'>Failed to upload image.</p>";
            exit();
        }

        $imgPath = $targetFile;
    }

    $conn->begin_transaction();
    try {
        // --- Update barber info ---
        if ($imgPath) {
            $stmt = $conn->prepare("UPDATE Barber SET Name=?, Bio=?, UserID=?, ImgUrl=? WHERE BarberID=?");
            $stmt->bind_param("ssisi", $name, $bio, $user_id, $imgPath, $barber_id);
        } else {
            $stmt = $conn->prepare("UPDATE Barber SET Name=?, Bio=?, UserID=? WHERE BarberID=?");
            $stmt->bind_param("ssii", $name, $bio, $user_id, $barber_id);
        }
        $stmt->execute();
        $stmt->close();

        // --- Fetch current barber services (including deleted ones) ---
        $existing_services = [];
        $res = $conn->query("SELECT ServicesID, IsDeleted FROM BarberServices WHERE BarberID = $barber_id");
        while ($row = $res->fetch_assoc()) {
            $existing_services[$row['ServicesID']] = $row['IsDeleted'];
        }

        // --- Handle service updates properly ---
        // 1. Loop through all services that exist
        foreach ($existing_services as $service_id => $is_deleted) {
            if (in_array($service_id, $services)) {
                // If checked again and was deleted, restore it
                if ($is_deleted == 1) {
                    $conn->query("UPDATE BarberServices SET IsDeleted=0 WHERE BarberID=$barber_id AND ServicesID=$service_id");
                }
            } else {
                // If unchecked, mark as deleted
                if ($is_deleted == 0) {
                    $conn->query("UPDATE BarberServices SET IsDeleted=1 WHERE BarberID=$barber_id AND ServicesID=$service_id");
                }
            }
        }

        // 2. Add new services that were never assigned before
        $stmt = $conn->prepare("INSERT INTO BarberServices (BarberID, ServicesID, CreatedAt, IsDeleted) VALUES (?, ?, NOW(), 0)");
        foreach ($services as $service_id) {
            if (!array_key_exists($service_id, $existing_services)) {
                $stmt->bind_param("ii", $barber_id, $service_id);
                $stmt->execute();
            }
        }
        $stmt->close();

        $conn->commit();
        echo "<p style='color:green;'>Barber and services updated successfully.</p>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<p style='color:red;'>Error updating barber: " . $e->getMessage() . "</p>";
    }
}

// --- Fetch barber details ---
$stmt = $conn->prepare("SELECT * FROM Barber WHERE BarberID=?");
$stmt->bind_param("i", $barber_id);
$stmt->execute();
$result = $stmt->get_result();
$barber = $result->fetch_assoc();
$stmt->close();

if (!$barber) {
    echo "Barber not found.";
    exit();
}

// --- Fetch available users ---
$users = $conn->query("SELECT UserID, Name, Email FROM User WHERE Role='barber' AND IsDeleted=0");

// --- Fetch all services ---
$services_list = $conn->query("SELECT ServicesID, Name, Price, Time FROM Services WHERE IsDeleted=0");

// --- Fetch assigned services ---
$current_services = [];
$res = $conn->query("SELECT ServicesID FROM BarberServices WHERE BarberID=$barber_id AND IsDeleted=0");
while ($row = $res->fetch_assoc()) {
    $current_services[] = $row['ServicesID'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Barber - Admin</title>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .container {
            max-width: 700px;
            margin: auto;
            padding: 20px;
        }
        
        .form-container {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        button[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        
        .message {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }
        
        input[type="text"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .services-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .service-checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f9f9f9;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            flex: 1 1 45%;
        }
        
        .current-image {
            max-width: 120px;
            border-radius: 8px;
            display: block;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Edit Barber</h2>

    <div class="form-container">
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($barber['Name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="bio">Bio:</label>
                <textarea name="bio" id="bio" rows="3"><?php echo htmlspecialchars($barber['Bio']); ?></textarea>
            </div>


            <div class="form-group">
                <label for="ImgFile">Profile Image:</label>
                <?php if (!empty($barber['ImgUrl'])): ?>
                    <img src="<?php echo htmlspecialchars($barber['ImgUrl']); ?>" alt="Current Image" class="current-image">
                <?php endif; ?>
                <input type="file" name="ImgFile" id="ImgFile" accept=".jpg,.jpeg,.png,.gif">
            </div>

            <div class="form-group">
                <h3 style="margin-bottom:8px;">Assigned Services</h3>
                <p style="color:#666; margin-bottom:10px;">Select or deselect services below to assign or remove them for this barber.</p>

                <div class="services-grid">
                    <?php 
                    // Reset the pointer for services_list
                    $services_list->data_seek(0);
                    while ($s = $services_list->fetch_assoc()) { ?>
                        <label class="service-checkbox">
                            <input type="checkbox" name="services[]" value="<?php echo $s['ServicesID']; ?>"
                                <?php echo in_array($s['ServicesID'], $current_services) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($s['Name']); ?>
                            <span style="font-size:0.85em; color:#888;">(R<?php echo number_format($s['Price'], 2); ?> / <?php echo htmlspecialchars($s['Time']); ?> mins)</span>
                        </label>
                    <?php } ?>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn">Update Barber</button>
                <a href="admin_dashboard.php" class="btn btn-cancel" style="text-align: center;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
