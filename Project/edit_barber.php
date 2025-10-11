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

        if ($_FILES['ImgFile']['size'] > 5*1024*1024) {
            echo "<p style='color:red;'>File too large (max 5MB).</p>";
            exit();
        }

        if (!move_uploaded_file($_FILES['ImgFile']['tmp_name'], $targetFile)) {
            echo "<p style='color:red;'>Failed to upload image.</p>";
            exit();
        }

        $imgPath = $targetFile;
    }

    // --- Build SQL dynamically ---
    if ($imgPath) {
        $stmt = $conn->prepare("UPDATE Barber SET Name=?, Bio=?, UserID=?, ImgUrl=? WHERE BarberID=?");
        $stmt->bind_param("ssisi", $name, $bio, $user_id, $imgPath, $barber_id);
    } else {
        $stmt = $conn->prepare("UPDATE Barber SET Name=?, Bio=?, UserID=? WHERE BarberID=?");
        $stmt->bind_param("ssii", $name, $bio, $user_id, $barber_id);
    }

    if ($stmt->execute()) {
        echo "<p style='color:green;'>Barber updated successfully.</p>";
    } else {
        echo "<p style='color:red;'>Error updating barber: " . $conn->error . "</p>";
    }
    $stmt->close();
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

// --- Fetch available users to link ---
$users = $conn->query("SELECT UserID, Name, Email FROM User WHERE Role='barber' AND IsDeleted=0");
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Barber</h2>

    <form method="post" enctype="multipart/form-data">
        <div>
            <label for="name">Name:</label><br>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($barber['Name']); ?>" required>
        </div>

        <div>
            <label for="bio">Bio:</label><br>
            <textarea name="bio" id="bio"><?php echo htmlspecialchars($barber['Bio']); ?></textarea>
        </div>

        <div>
            <label for="user_id">Linked User:</label><br>
            <select name="user_id" id="user_id" required>
                <?php while ($user = $users->fetch_assoc()) { ?>
                    <option value="<?php echo $user['UserID']; ?>" 
                        <?php echo ($user['UserID'] == $barber['UserID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['Name'] . " (" . $user['Email'] . ")"); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label for="ImgFile">Profile Image (optional):</label><br>
            <?php if (!empty($barber['ImgUrl'])): ?>
                <img src="<?php echo htmlspecialchars($barber['ImgUrl']); ?>" alt="Current Image" style="max-width:150px; display:block; margin-bottom:5px;">
            <?php endif; ?>
            <input type="file" name="ImgFile" id="ImgFile" accept=".jpg,.jpeg,.png,.gif">
        </div>

        <br>
        <button type="submit">Update Barber</button>
    </form>
</div>
<?php include 'footer.php'; ?>

