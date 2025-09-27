<?php
session_start();
include 'db.php';
include 'header.php';

// Redirect if not logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$UserID = $_SESSION['UserID'];
$AppointmentID = $_GET['AppointmentID'] ?? null;

if (!$AppointmentID) {
    die("Invalid request. No AppointmentID provided.");
}

// Check if appointment belongs to this user and is completed
$sql = "
    SELECT a.AppointmentID, a.UserID, a.Status, a.ReviewID, s.ServicesID
    FROM Appointment a
    LEFT JOIN BarberServices bs ON a.BarberID = bs.BarberID
    LEFT JOIN Services s ON bs.ServicesID = s.ServicesID
    WHERE a.AppointmentID = ? AND a.UserID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $AppointmentID, $UserID);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    die("Appointment not found or not yours.");
}
if (strtolower($appt['Status']) !== 'completed') {
    die("You can only review completed appointments.");
}
if (!empty($appt['ReviewID'])) {
    die("Review already exists for this appointment.");
}

$success = $error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? "");

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5.";
    } else {
        // Insert review
        $sql = "INSERT INTO Reviews (UserID, ProductID, Rating, Comment, Status) VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiis", $UserID, $appt['ServicesID'], $rating, $comment);
        
        if ($stmt->execute()) {
            $newReviewID = $stmt->insert_id;

            // Link to appointment
            $update = $conn->prepare("UPDATE Appointment SET ReviewID = ? WHERE AppointmentID = ?");
            $update->bind_param("ii", $newReviewID, $AppointmentID);
            $update->execute();

            $success = "Review submitted successfully! Awaiting approval.";
        } else {
            $error = "Error submitting review. Please try again.";
        }
    }
}
?>

<div class="container">
    <h1>Write a Review</h1>

    <?php if ($success): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
        <p><a href="view_appointments.php">Back to Appointments</a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <label for="rating">Rating (1–5):</label><br>
            <select name="rating" id="rating" required>
                <option value="">Select...</option>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                <?php endfor; ?>
            </select>
            <br><br>

            <label for="comment">Comment:</label><br>
            <textarea name="comment" id="comment" rows="4" cols="50"></textarea>
            <br><br>

            <button type="submit">Submit Review</button>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

