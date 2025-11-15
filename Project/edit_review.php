<?php
session_start();
include 'db.php';

// --- Access Control: only admin ---
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$review_id = $_GET['id'] ?? null;
if (!$review_id) {
    echo "Invalid review ID.";
    exit();
}

// --- Fetch review details ---
$stmt = $conn->prepare("SELECT * FROM Reviews WHERE ReviewID=?");
$stmt->bind_param("i", $review_id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$review) {
    echo "Review not found.";
    exit();
}

// Optional: Restrict editing to admin only
if ($_SESSION['Role'] !== 'admin') {
    echo "You do not have permission to change review status.";
    exit();
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? 'pending';

    // Validate status
    $allowedStatuses = ['pending', 'cancelled', 'approved'];
    if (!in_array($status, $allowedStatuses)) {
        echo "<p style='color:red;'>Invalid status value.</p>";
    } else {
        $stmt = $conn->prepare("UPDATE Reviews SET Status=? WHERE ReviewID=?");
        $stmt->bind_param("si", $status, $review_id);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Review status updated successfully.</p>";
            // Refresh the review data
            $stmt->close();
            $stmt = $conn->prepare("SELECT * FROM Reviews WHERE ReviewID=?");
            $stmt->bind_param("i", $review_id);
            $stmt->execute();
            $review = $stmt->get_result()->fetch_assoc();
        } else {
            echo "<p style='color:red;'>Error updating status: " . $conn->error . "</p>";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Review Status - Admin</title>
    <link href="addedit.css" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    <!-- Return Button -->
    <a href="admin_dashboard.php" class="btn">← Back to Admin Dashboard</a>
    
    <h2>Edit Review Status</h2>

    <div class="form-container">
        <!-- Review Details -->
        <div class="review-details">
            <h3>Review Details</h3>
            <p><strong>Review ID:</strong> <?= htmlspecialchars($review['ReviewID']) ?></p>
            <p><strong>User ID:</strong> <?= htmlspecialchars($review['UserID']) ?></p>
            <p><strong>Rating:</strong> <?= htmlspecialchars($review['Rating']) ?>/5</p>
            <p><strong>Comment:</strong> <?= htmlspecialchars($review['Comment'] ?? 'No comment') ?></p>
            <p><strong>Current Status:</strong> <span style="color: 
                <?= $review['Status'] === 'approved' ? 'green' : 
                   ($review['Status'] === 'pending' ? 'orange' : 'red') ?>">
                <?= ucfirst(htmlspecialchars($review['Status'])) ?>
            </span></p>
            <p><strong>Created:</strong> <?= htmlspecialchars($review['CreatedAt']) ?></p>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if (isset($stmt) && $stmt->execute()): ?>
                <div class="message success">Review status updated successfully.</div>
            <?php elseif (isset($conn->error)): ?>
                <div class="message error">Error updating status: <?php echo $conn->error; ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" required>
                    <option value="pending" <?php if ($review['Status'] === 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="approved" <?php if ($review['Status'] === 'approved') echo 'selected'; ?>>Approved</option>
                    <option value="cancelled" <?php if ($review['Status'] === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
            </div>

            <div class="button-group">
                <button type="submit" class="btn">Update Status</button>
                <a href="admin_dashboard.php" class="btn btn-cancel" style="text-align: center;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
