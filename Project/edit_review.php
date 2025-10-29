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

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Review Status</h2>
    <link href="addedit.css" rel="stylesheet">

    <form method="post">
        <div>
            <label for="status">Status:</label><br>
            <select name="status" id="status" required>
                <option value="pending" <?php if ($review['Status'] === 'pending') echo 'selected'; ?>>Pending</option>
                <option value="approved" <?php if ($review['Status'] === 'approved') echo 'selected'; ?>>Approved</option>
                <option value="cancelled" <?php if ($review['Status'] === 'cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
        </div>

        <br>
        <button type="submit">Update Status</button>
    </form>
</div>
<?php include 'footer.php'; ?>

