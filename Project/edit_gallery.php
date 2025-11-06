<?php
session_start();
if (!isset($_SESSION['UserID']) ||
    !(
        $_SESSION['Role'] === 'admin' ||
        ($_SESSION['Role'] === 'barber' && empty($features['allow gallery']))
    )
) {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'header.php';

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?view=gallery");
    exit();
}

$id = intval($_GET['id']);
$errors = [];
$success = '';

// Fetch existing gallery item
$stmt = $conn->prepare("SELECT * FROM Gallery WHERE GalleryID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$gallery = $result->fetch_assoc();

if (!$gallery) {
    echo "<p>Gallery item not found.</p>";
    include 'footer.php';
    exit();
}

$title = $gallery['Title'];
$description = $gallery['Description'];
$imgUrl = $gallery['ImageUrl'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['Title']);
    $description = trim($_POST['Description']);

    // Handle new image upload
    if (isset($_FILES['ImgFile']) && $_FILES['ImgFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['ImgFile']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['ImgFile']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG, or GIF files are allowed.";
        } else {
            $targetDir = "Img/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            // Find next available number
            $files = scandir($targetDir);
            $maxNum = 0;
            foreach ($files as $file) {
                if (preg_match('/^(\d+)\.\w+$/', $file, $matches)) {
                    $num = intval($matches[1]);
                    if ($num > $maxNum) $maxNum = $num;
                }
            }
            $nextNum = $maxNum + 1;
            $fileName = $nextNum . "." . $fileExt;
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                // Delete old image file
                if (file_exists($gallery['ImageUrl'])) unlink($gallery['ImageUrl']);
                $imgUrl = $targetFile;
            } else {
                $errors[] = "Failed to upload new image.";
            }
        }
    }

    // Update record if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE Gallery SET Title=?, Description=?, ImageUrl=? WHERE GalleryID=?");
        $stmt->bind_param("sssi", $title, $description, $imgUrl, $id);
        if ($stmt->execute()) {
            $success = "Gallery item updated successfully!";
            header("Refresh: 1; URL=admin_dashboard.php?view=gallery");
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Edit Gallery Item</h2>

<?php if (!empty($errors)): ?>
    <div style="color:red;">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div style="color:green;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <label>Title:</label><br>
    <link href="addedit.css" rel="stylesheet">
    <input type="text" name="Title" value="<?= htmlspecialchars($title) ?>" required><br><br>

    <label>Description:</label><br>
    <textarea name="Description" rows="4"><?= htmlspecialchars($description) ?></textarea><br><br>

    <label>Current Image:</label><br>
    <img src="<?= htmlspecialchars($imgUrl) ?>" style="width:150px;height:150px;object-fit:cover;border-radius:10px;"><br><br>

    <label>Replace Image (optional):</label><br>
    <input type="file" name="ImgFile" accept="image/*"><br><br>

    <button type="submit">Save Changes</button>
    <a class="btn" href="admin_dashboard.php?view=gallery">Cancel</a>
</form>

<?php include 'footer.php'; ?>

