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

$errors = [];
$success = '';
$title = $description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['Title']);
    $description = trim($_POST['Description']);
    $imgUrl = null;

    if (empty($title)) $errors[] = "Title is required.";
    if (empty($_FILES['ImgFile']['name'])) $errors[] = "Please upload an image.";

    // Handle image upload
    if (isset($_FILES['ImgFile']) && $_FILES['ImgFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['ImgFile']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['ImgFile']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG, or GIF files are allowed.";
        } else {
            $targetDir = "Img/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            // Find next available number for filename
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
                $imgUrl = $targetFile;
            } else {
                $errors[] = "Failed to move uploaded file.";
            }
        }
    }

    // Insert into database if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Gallery (Title, Description, ImageUrl) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $description, $imgUrl);
        if ($stmt->execute()) {
            $success = "Gallery item added successfully!";
            $title = $description = '';
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<h2>Add Gallery Item</h2>

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

<form method="POST" action="" enctype="multipart/form-data">
    <label>Title:</label><br>
    <input type="text" name="Title" value="<?= htmlspecialchars($title) ?>" required><br><br>

    <label>Description:</label><br>
    <textarea name="Description" rows="4"><?= htmlspecialchars($description) ?></textarea><br><br>

    <label>Upload Image:</label><br>
    <input type="file" name="ImgFile" accept="image/*" required><br><br>

    <button type="submit">Add Gallery Item</button>
    <a href="admin_dashboard.php?view=gallery">Cancel</a>
</form>

<?php include 'footer.php'; ?>

