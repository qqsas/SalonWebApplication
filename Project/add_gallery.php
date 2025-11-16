<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Validate session and permissions
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

// Check user role and permissions
$allowed = false;
if ($_SESSION['Role'] === 'admin') {
    $allowed = true;
} elseif ($_SESSION['Role'] === 'barber') {
    // Check if barbers are allowed to use gallery feature
    include 'db.php';
    $featureCheck = $conn->prepare("SELECT IsEnabled FROM Features WHERE FeatureName = 'allow gallery'");
    if ($featureCheck && $featureCheck->execute()) {
        $result = $featureCheck->get_result();
        if ($row = $result->fetch_assoc()) {
            $allowed = (bool)$row['IsEnabled'];
        }
    }
    $featureCheck->close();
    $conn->close();
}

if (!$allowed) {
    header("Location: unauthorized.php");
    exit();
}

include 'db.php';
include 'header.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$title = $description = '';

// Configuration
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$uploadDir = "Img/gallery/";
$maxTitleLength = 150;
$maxDescriptionLength = 1000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $errors[] = "Security token validation failed. Please try again.";
    } else {
        // Validate and sanitize inputs
        $title = trim($_POST['Title'] ?? '');
        $description = trim($_POST['Description'] ?? '');
        $imgUrl = null;

        // Title validation
        if (empty($title)) {
            $errors[] = "Title is required.";
        } elseif (strlen($title) > $maxTitleLength) {
            $errors[] = "Title must be less than " . $maxTitleLength . " characters.";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-_,\.!?\']+$/i', $title)) {
            $errors[] = "Title contains invalid characters. Only letters, numbers, spaces, and basic punctuation are allowed.";
        }

        // Description validation
        if (!empty($description) && strlen($description) > $maxDescriptionLength) {
            $errors[] = "Description must be less than " . $maxDescriptionLength . " characters.";
        }

        // File upload validation
        if (!isset($_FILES['ImgFile']) || $_FILES['ImgFile']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Please select an image to upload.";
        } elseif ($_FILES['ImgFile']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
                UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
            ];
            $errors[] = $uploadErrors[$_FILES['ImgFile']['error']] ?? "Unknown upload error.";
        } else {
            // File validation
            $file = $_FILES['ImgFile'];
            $fileTmp = $file['tmp_name'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Check file size
            if ($fileSize > $maxFileSize) {
                $errors[] = "File size must be less than " . ($maxFileSize / 1024 / 1024) . "MB.";
            }

            // Check file extension
            if (!in_array($fileExt, $allowedExtensions)) {
                $errors[] = "Only " . implode(', ', $allowedExtensions) . " files are allowed.";
            }

            // Check MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $fileTmp);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowedMimeTypes)) {
                $errors[] = "Invalid file type. Please upload a valid image file.";
            }

            // Check if file is actually an image
            if (!getimagesize($fileTmp)) {
                $errors[] = "Uploaded file is not a valid image.";
            }

            // Check for potential malicious content
            if ($fileExt === 'php' || preg_match('/\.(php|phtml|php3|php4|php5|phar|html|htm)/i', $fileName)) {
                $errors[] = "File type not allowed for security reasons.";
            }
        }

        // Handle file upload if no errors
        if (empty($errors)) {
            // Create upload directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    $errors[] = "Failed to create upload directory.";
                }
            }

            if (empty($errors)) {
                // Generate secure filename
                $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                $baseName = substr($baseName, 0, 100); // Limit filename length
                
                // Find next available filename
                $counter = 1;
                $newFileName = $baseName . '.' . $fileExt;
                $targetFile = $uploadDir . $newFileName;
                
                while (file_exists($targetFile)) {
                    $newFileName = $baseName . '_' . $counter . '.' . $fileExt;
                    $targetFile = $uploadDir . $newFileName;
                    $counter++;
                    if ($counter > 1000) {
                        $errors[] = "Too many files with similar names. Please rename your file.";
                        break;
                    }
                }

                if (empty($errors)) {
                    // Move uploaded file
                    if (move_uploaded_file($fileTmp, $targetFile)) {
                        // Verify the moved file is actually an image
                        if (getimagesize($targetFile)) {
                            $imgUrl = $targetFile;
                            
                            // Set proper permissions
                            chmod($targetFile, 0644);
                        } else {
                            $errors[] = "Uploaded file is not a valid image.";
                            unlink($targetFile); // Remove invalid file
                        }
                    } else {
                        $errors[] = "Failed to save uploaded file. Please try again.";
                    }
                }
            }
        }

        // Insert into database if no errors
        if (empty($errors) && $imgUrl) {
            $stmt = $conn->prepare("INSERT INTO Gallery (Title, Description, ImageUrl) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $title, $description, $imgUrl);
                if ($stmt->execute()) {
                    $success = "Gallery item added successfully!";
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    // Clear form
                    $title = $description = '';
                } else {
                    $errors[] = "Database error: " . $stmt->error;
                    // Clean up uploaded file if database insert failed
                    if (file_exists($imgUrl)) {
                        unlink($imgUrl);
                    }
                }
                $stmt->close();
            } else {
                $errors[] = "Database preparation error: " . $conn->error;
                // Clean up uploaded file
                if (isset($imgUrl) && file_exists($imgUrl)) {
                    unlink($imgUrl);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Gallery Item - Admin</title>
    <link href="addedit.css" rel="stylesheet">
    <style>
        .error { 
            background: #ffebee; 
            color: #c62828; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #c62828;
            margin-bottom: 15px;
        }
        .success { 
            background: #e8f5e8; 
            color: #2e7d32; 
            padding: 12px; 
            border-radius: 4px; 
            border-left: 4px solid #2e7d32;
            margin-bottom: 15px;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        input[type="text"], textarea, input[type="file"] { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        textarea { 
            resize: vertical; 
            min-height: 100px; 
        }
        button, .btn { 
            padding: 10px 20px; 
            margin: 5px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
        }
        button { 
            background: #4CAF50; 
            color: white; 
        }
        button:hover { 
            background: #45a049; 
        }
        .btn { 
            background: #6c757d; 
            color: white; 
        }
        .btn:hover { 
            background: #5a6268; 
        }
        .char-count {
            font-size: 0.8em;
            color: #666;
            text-align: right;
            margin-top: 5px;
        }
        .file-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .required {
            color: #c62828;
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="section-icon fa-regular fa-image fa-5x"></i>
        <h2>Add Gallery Item</h2>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" id="galleryForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-group">
                <label for="Title">Title <span class="required">*</span></label>
                <input type="text" name="Title" id="Title" value="<?= htmlspecialchars($title) ?>" 
                       maxlength="<?= $maxTitleLength ?>" 
                       pattern="[a-zA-Z0-9\s\-_,\.!?']+" 
                       title="Only letters, numbers, spaces, and basic punctuation are allowed"
                       required>
                <div class="char-count">
                    <span id="titleCount">0</span>/<?= $maxTitleLength ?> characters
                </div>
            </div>

            <div class="form-group">
                <label for="Description">Description</label>
                <textarea name="Description" id="Description" rows="4" 
                          maxlength="<?= $maxDescriptionLength ?>"><?= htmlspecialchars($description) ?></textarea>
                <div class="char-count">
                    <span id="descCount">0</span>/<?= $maxDescriptionLength ?> characters
                </div>
            </div>

            <div class="form-group">
                <label for="ImgFile">Upload Image <span class="required">*</span></label>
                <input type="file" name="ImgFile" id="ImgFile" accept="image/*" required>
                <div class="file-info">
                    Allowed formats: <?= implode(', ', $allowedExtensions) ?><br>
                    Maximum file size: <?= ($maxFileSize / 1024 / 1024) ?>MB
                </div>
                <div id="filePreview" style="margin-top: 10px; display: none;">
                    <img id="previewImage" src="#" alt="Preview" style="max-width: 200px; max-height: 200px;">
                </div>
            </div>

            <div class="form-group">
                <button type="submit" id="submitBtn">Add Gallery Item</button>
                <a class="btn" href="admin_dashboard.php?view=gallery">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const titleInput = document.getElementById('Title');
            const descInput = document.getElementById('Description');
            const titleCount = document.getElementById('titleCount');
            const descCount = document.getElementById('descCount');
            const fileInput = document.getElementById('ImgFile');
            const filePreview = document.getElementById('filePreview');
            const previewImage = document.getElementById('previewImage');
            const form = document.getElementById('galleryForm');
            const submitBtn = document.getElementById('submitBtn');

            // Character count for title
            titleInput.addEventListener('input', function() {
                titleCount.textContent = this.value.length;
            });
            titleCount.textContent = titleInput.value.length;

            // Character count for description
            descInput.addEventListener('input', function() {
                descCount.textContent = this.value.length;
            });
            descCount.textContent = descInput.value.length;

            // File preview
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        filePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    filePreview.style.display = 'none';
                }
            });

            // File size validation
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                const maxSize = <?= $maxFileSize ?>;
                if (file && file.size > maxSize) {
                    alert('File size must be less than <?= ($maxFileSize / 1024 / 1024) ?>MB');
                    this.value = '';
                    filePreview.style.display = 'none';
                }
            });

            // Form submission handling
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
            });

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>
