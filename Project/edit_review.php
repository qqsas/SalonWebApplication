<?php
session_start();
include 'db.php';

// --- Access Control: only admin or review owner ---
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

// Optional: Restrict editing to admin or the original user
if ($_SESSION['Role'] !== 'admin' && $_SESSION['UserID'] != $review['UserID']) {
    echo "You do not have permission to edit this review.";
    exit();
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("UPDATE Reviews SET Rating=?, Comment=? WHERE ReviewID=?");
        $stmt->bind_param("isi", $rating, $comment, $review_id);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Review updated successfully.</p>";
        } else {
            echo "<p style='color:red;'>Error updating review: " . $conn->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>Rating must be between 1 and 5.</p>";
    }
}
?>

<?php include 'header.php'; ?>
<div class="container">
    <h2>Edit Review</h2>

    <form method="post">
        <div>
            <label for="rating">Rating (1-5):</label><br>
            <input type="number" name="rating" id="rating" min="1" max="5" value="<?php echo htmlspecialchars($review['Rating']); ?>" required>
        </div>

        <div>
            <label for="comment">Comment:</label><br>
            <textarea name="comment" id="comment"><?php echo htmlspecialchars($review['Comment']); ?></textarea>
        </div>

        <br>
        <button type="submit">Update Review</button>
    </form>
</div>
<?php include 'footer.php'; ?>

