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

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>

<div class="admin-dashboard">
    <div class="dashboard-header">
        <div class="admin-welcome">
            <h1>Edit Gallery Item</h1>
            <p>Update gallery item details and image</p>
        </div>
        
        <div class="back-navigation">
            <a href="admin_dashboard.php?view=gallery" class="btn btn-secondary">
                ← Back to Gallery
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="form-container">
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?= escape($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message"><?= escape($success) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <div class="form-group">
                        <label for="Title">Title: <span class="required">*</span></label>
                        <input type="text" name="Title" id="Title" value="<?= escape($title) ?>" required maxlength="255">
                        <small class="help-text">Required, max 255 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="Description">Description:</label>
                        <textarea name="Description" id="Description" rows="4" maxlength="1000"><?= escape($description) ?></textarea>
                        <small class="help-text">Optional, max 1000 characters</small>
                    </div>

                    <div class="form-group">
                        <label>Current Image:</label>
                        <?php if (!empty($imgUrl)): ?>
                            <div class="current-image">
                                <img src="<?= escape($imgUrl) ?>" alt="Current gallery image">
                                <div class="image-info">Current image</div>
                            </div>
                        <?php else: ?>
                            <p class="no-image">No image currently set</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="ImgFile">Replace Image (optional):</label>
                        <input type="file" name="ImgFile" id="ImgFile" accept="image/jpeg,image/jpg,image/png,image/gif">
                        <small class="help-text">Leave empty to keep current image. Allowed formats: JPG, JPEG, PNG, GIF</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">Save Changes</button>
                        <a href="admin_dashboard.php?view=gallery" class="btn btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.admin-dashboard {
    min-height: 100vh;
    background-color: var(--gray-light);
    padding: 2rem 0;
}

.dashboard-header {
    max-width: 1200px;
    margin: 0 auto 2rem;
    padding: 0 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-welcome h1 {
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    font-size: 2rem;
}

.admin-welcome p {
    color: var(--text-medium);
    margin: 0;
}

.back-navigation {
    display: flex;
    align-items: center;
}

.container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.form-container {
    background: var(--background-white);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    margin-bottom: 1.5rem;
    border: 1px solid #f5c6cb;
}

.error-message ul {
    margin: 0.5rem 0 0 0;
    padding-left: 1.5rem;
}

.error-message li {
    margin-bottom: 0.25rem;
}

.success-message {
    background: #d4edda;
    color: #155724;
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    margin-bottom: 1.5rem;
    border: 1px solid #c3e6cb;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-dark);
}

.required {
    color: #dc3545;
    font-weight: bold;
}

.help-text {
    display: block;
    font-size: 0.85rem;
    color: var(--text-light);
    margin-top: 0.25rem;
}

input[type="text"],
textarea,
input[type="file"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--gray-medium);
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input[type="text"]:focus,
textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(84, 88, 133, 0.1);
}

textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.current-image {
    margin-bottom: 1rem;
}

.current-image img {
    max-width: 300px;
    width: 100%;
    height: auto;
    border-radius: var(--border-radius-sm);
    border: 2px solid var(--gray-light);
    box-shadow: var(--card-shadow);
}

.image-info {
    font-size: 0.85rem;
    color: var(--text-light);
    margin-top: 0.5rem;
}

.no-image {
    color: var(--text-light);
    font-style: italic;
    padding: 1rem;
    background: var(--gray-light);
    border-radius: var(--border-radius-sm);
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--gray-light);
}

.form-actions .btn {
    flex: 1;
    text-align: center;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-container {
        padding: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .current-image img {
        max-width: 100%;
    }
}
</style>

<?php include 'footer.php'; ?>

