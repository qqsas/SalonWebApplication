<?php
session_start();
include 'db.php';

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

$service_id = $_GET['id'] ?? null;
if (!$service_id) {
    echo "Invalid service ID.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $time = intval($_POST['time']);
    $newImage = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $newImage = uniqid() . "." . strtolower($ext);
        $targetDir = __DIR__ . "/Img/";
        $targetFile = $targetDir . $newImage;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $stmt = $conn->prepare("SELECT ImgUrl FROM Services WHERE ServicesID=?");
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($old && !empty($old['ImgUrl'])) {
                $oldFile = $targetDir . $old['ImgUrl'];
                if (file_exists($oldFile)) unlink($oldFile);
            }
        } else {
            echo "<p style='color:red;'>Failed to upload new image.</p>";
            $newImage = null;
        }
    }

    if ($name && $price >= 0 && $time > 0) {
        if ($newImage) {
            $stmt = $conn->prepare("UPDATE Services SET Name=?, Description=?, Price=?, Time=?, ImgUrl=? WHERE ServicesID=?");
            $stmt->bind_param("ssdisi", $name, $description, $price, $time, $newImage, $service_id);
        } else {
            $stmt = $conn->prepare("UPDATE Services SET Name=?, Description=?, Price=?, Time=? WHERE ServicesID=?");
            $stmt->bind_param("ssdii", $name, $description, $price, $time, $service_id);
        }

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Service updated successfully.</p>";
        } else {
            echo "<p style='color:red;'>Error updating service: " . $conn->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>Please provide valid values.</p>";
    }
}

$stmt = $conn->prepare("SELECT * FROM Services WHERE ServicesID=?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

if (!$service) {
    echo "Service not found.";
    exit();
}
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Service</h2>
    <form method="post" enctype="multipart/form-data">
        <div>
            <label for="name">Service Name:</label><br>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($service['Name']); ?>" required>
        </div>
        <div>
            <label for="description">Description:</label><br>
            <textarea name="description" id="description"><?php echo htmlspecialchars($service['Description']); ?></textarea>
        </div>
        <div>
            <label for="price">Price (R):</label><br>
            <input type="number" step="0.01" name="price" id="price" value="<?php echo htmlspecialchars($service['Price']); ?>" required>
        </div>
        <div>
            <label for="time">Duration (minutes):</label><br>
            <input type="number" name="time" id="time" value="<?php echo htmlspecialchars($service['Time']); ?>" required>
        </div>
        <div>
            <label for="image">Service Image:</label><br>
            <?php if (!empty($service['ImgUrl'])): ?>
                <img src="Img/<?php echo htmlspecialchars($service['ImgUrl']); ?>" width="120" alt="Service Image"><br>
            <?php endif; ?>
            <input type="file" name="image" id="image" accept="image/*">
        </div>
        <br>
        <button type="submit">Update Service</button>
    </form>
</div>
<?php include 'footer.php'; ?>
