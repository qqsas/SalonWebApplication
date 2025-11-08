<?php 
session_start(); 
include 'db.php'; 
include 'header.php'; 

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$UserID = $_SESSION['UserID'];
$AppointmentID = isset($_GET['AppointmentID']) ? intval($_GET['AppointmentID']) : null;
$ProductID = isset($_GET['ProductID']) ? intval($_GET['ProductID']) : null;

if (!$AppointmentID && !$ProductID) {
    echo "<div class='alert alert-danger'>Invalid request. No AppointmentID or ProductID provided.</div>";
    include 'footer.php';
    exit();
}

$success = $error = "";
$reviewTarget = "";

// Fetch appointment details
if ($AppointmentID) {
    $stmt = $conn->prepare("SELECT AppointmentID, UserID, Status, ReviewID, BarberID, Date FROM Appointment WHERE AppointmentID = ? AND UserID = ?");
    if ($stmt === false) {
        $error = "Unable to prepare appointment lookup.";
    } else {
        $stmt->bind_param("ii", $AppointmentID, $UserID);
        $stmt->execute();
        $appt = $stmt->get_result()->fetch_assoc();
    
        if (!$appt) {
            $error = "Appointment not found or not yours.";
        } elseif (strtolower($appt['Status']) !== 'completed') {
            $error = "You can only review completed appointments.";
        } elseif (!empty($appt['ReviewID'])) {
            $error = "Review already exists for this appointment.";
        } else {
            $barberID = $appt['BarberID'];
            $barberQuery = $conn->prepare("SELECT Name FROM User WHERE UserID = ?");
            if ($barberQuery === false) {
                $error = "Unable to load barber details.";
            } else {
                $barberQuery->bind_param("i", $barberID);
                $barberQuery->execute();
                $barber = $barberQuery->get_result()->fetch_assoc();
                if ($barber) {
                    $reviewTarget = "Appointment with " . htmlspecialchars($barber['Name']) . " on " . htmlspecialchars($appt['Date']);
                }
            }
        }
    }
}

// Fetch product details
if ($ProductID) {
    $stmt = $conn->prepare("SELECT Name, ImgUrl FROM Products WHERE ProductID = ?");
    if ($stmt === false) {
        $error = "Unable to prepare product lookup.";
    } else {
        $stmt->bind_param("i", $ProductID);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if (!$product) {
            $error = "Product not found.";
        } else {
            $reviewTarget = "Product: " . htmlspecialchars($product['Name']);
        }
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? "");

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5.";
    } elseif (strlen($comment) > 500) {
        $error = "Comment too long. Max 500 characters.";
    } elseif (empty($error)) {
        if ($AppointmentID) {
            $stmt = $conn->prepare("INSERT INTO Reviews (UserID, ProductID, AppointmentID, Rating, Comment, Status, CreatedAt) VALUES (?, NULL, ?, ?, ?, 'pending', NOW())");
            if ($stmt === false) {
                $error = "Unable to prepare appointment review insert.";
            } else {
                $stmt->bind_param("iiis", $UserID, $AppointmentID, $rating, $comment);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO Reviews (UserID, ProductID, AppointmentID, Rating, Comment, Status, CreatedAt) VALUES (?, ?, NULL, ?, ?, 'pending', NOW())");
            if ($stmt === false) {
                $error = "Unable to prepare product review insert.";
            } else {
                $stmt->bind_param("iiis", $UserID, $ProductID, $rating, $comment);
            }
        }

        if (empty($error) && $stmt && $stmt->execute()) {
            $newReviewID = $stmt->insert_id;
            if ($AppointmentID) {
                $update = $conn->prepare("UPDATE Appointment SET ReviewID = ? WHERE AppointmentID = ?");
                if ($update) {
                    $update->bind_param("ii", $newReviewID, $AppointmentID);
                    $update->execute();
                }
            }

            $success = "Review submitted successfully and awaiting admin approval.";
            $error = "";
        } else {
            $error = "Error submitting review.";
        }
    }
}
?>

<div class="container" style="max-width: 700px; margin-top: 40px;">
    <div class="card shadow p-4">
        <h2 class="text-center mb-3">Write a Review</h2>

        <?php if ($reviewTarget): ?>
            <p class="text-muted text-center"><?= $reviewTarget ?></p>
            <hr>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= $AppointmentID ? 'appointments.php' : 'products.php' ?>" class="btn btn-secondary">Back</a>
                <a href="index.php" class="btn btn-primary">Home</a>
            </div>
        <?php else: ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="rating" class="form-label">Rating</label>
                <select name="rating" id="rating" class="form-select" required>
                    <option value="">Select rating...</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="comment" class="form-label">Comment</label>
                <textarea name="comment" id="comment" class="form-control" rows="4" maxlength="500" placeholder="Write your review here..."></textarea>
                <small class="text-muted">Max 500 characters</small>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= $AppointmentID ? 'appointments.php' : 'products.php' ?>" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Submit Review</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
