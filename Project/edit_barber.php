<?php
session_start();
include 'db.php';

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

<?php include 'header.php'; ?>
<div class="container" style="max-width:700px; margin:auto; padding:20px;">
    <h2>Edit Barber</h2>

    <link href="addedit.css" rel="stylesheet">
    <form method="post" enctype="multipart/form-data" style="background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);">
        <div style="margin-bottom:15px;">
            <label for="name"><strong>Name:</strong></label><br>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($barber['Name']); ?>" required style="width:100%; padding:8px;">
        </div>

        <div style="margin-bottom:15px;">
            <label for="bio"><strong>Bio:</strong></label><br>
            <textarea name="bio" id="bio" rows="3" style="width:100%; padding:8px;"><?php echo htmlspecialchars($barber['Bio']); ?></textarea>
        </div>

        <div style="margin-bottom:15px;">
            <label for="user_id"><strong>Linked User:</strong></label><br>
            <select name="user_id" id="user_id" required style="width:100%; padding:8px;">
                <?php while ($user = $users->fetch_assoc()) { ?>
                    <option value="<?php echo $user['UserID']; ?>" <?php echo ($user['UserID'] == $barber['UserID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['Name'] . " (" . $user['Email'] . ")"); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div style="margin-bottom:15px;">
            <label for="ImgFile"><strong>Profile Image:</strong></label><br>
            <?php if (!empty($barber['ImgUrl'])): ?>
                <img src="<?php echo htmlspecialchars($barber['ImgUrl']); ?>" alt="Current Image" style="max-width:120px; border-radius:8px; display:block; margin-bottom:8px;">
            <?php endif; ?>
            <input type="file" name="ImgFile" id="ImgFile" accept=".jpg,.jpeg,.png,.gif">
        </div>

        <div style="margin-bottom:15px;">
            <h3 style="margin-bottom:8px;">Assigned Services</h3>
            <p style="color:#666; margin-bottom:10px;">Select or deselect services below to assign or remove them for this barber.</p>

            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <?php while ($s = $services_list->fetch_assoc()) { ?>
                    <label style="display:flex; align-items:center; gap:6px; background:#f9f9f9; padding:6px 10px; border-radius:6px; border:1px solid #ddd; flex:1 1 45%;">
                        <input type="checkbox" name="services[]" value="<?php echo $s['ServicesID']; ?>"
                            <?php echo in_array($s['ServicesID'], $current_services) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($s['Name']); ?>
                        <span style="font-size:0.85em; color:#888;">(R<?php echo number_format($s['Price'], 2); ?> / <?php echo htmlspecialchars($s['Time']); ?> mins)</span>
                    </label>
                <?php } ?>
            </div>
        </div>

        <button type="submit" style="background:#007bff; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer;">
            Update Barber
        </button>
    </form>
</div>
<?php include 'footer.php'; ?>
