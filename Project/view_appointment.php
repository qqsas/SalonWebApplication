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

// Fetch appointments for the logged-in customer
$sql = "
    SELECT 
        a.AppointmentID,
        a.ForName,
        a.ForAge,
        a.Type,
        a.Time,
        a.Duration,
        a.Status,
        a.Cost,
        a.CreatedAt,
        a.ReviewID,
        b.Name AS BarberName,
        s.Name AS ServiceName
    FROM Appointment a
    JOIN Barber b ON a.BarberID = b.BarberID
    LEFT JOIN Services s ON a.Type = s.Name
    WHERE a.UserID = ? AND (a.IsDeleted IS NULL OR a.IsDeleted = 0)
    ORDER BY a.Time DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $UserID);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container">
    <h1>My Appointments</h1>

    <?php if ($result->num_rows > 0): ?>
        <table border="1" cellpadding="10" cellspacing="0">
            <tr>
                <th>Appointment ID</th>
                <th>Booked For</th>
                <th>Barber</th>
                <th>Service</th>
                <th>Type</th>
                <th>Time</th>
                <th>Duration (mins)</th>
                <th>Status</th>
                <th>Cost</th>
                <th>Created At</th>
                <th>Actions</th>
                <th>Review</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['AppointmentID']); ?></td>
                    <td><?= htmlspecialchars($row['ForName']) . " (" . htmlspecialchars($row['ForAge']) . ")"; ?></td>
                    <td><?= htmlspecialchars($row['BarberName']); ?></td>
                    <td><?= htmlspecialchars($row['ServiceName'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['Type']); ?></td>
                    <td><?= date("d M Y H:i", strtotime($row['Time'])); ?></td>
                    <td><?= htmlspecialchars($row['Duration']); ?></td>
                    <td><?= htmlspecialchars($row['Status']); ?></td>
                    <td><?= "R" . number_format($row['Cost'], 2); ?></td>
                    <td><?= date("d M Y H:i", strtotime($row['CreatedAt'])); ?></td>
                    <td>
                        <?php
                        $appointmentDate = date('Y-m-d', strtotime($row['Time']));
                        $today = date('Y-m-d');
                        if ($appointmentDate > $today && strtolower($row['Status']) !== 'cancelled'): ?>
                            <a href="cancel_appointment.php?AppointmentID=<?= $row['AppointmentID']; ?>" class="btn" 
                               onclick="return confirm('Are you sure you want to cancel this appointment?');">
                               Cancel
                            </a>
                        <?php else: ?>
                            <span style="color: gray;">Cannot cancel</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        if (strtolower($row['Status']) === 'completed') {
                            if (!empty($row['ReviewID'])) {
                                $reviewCheck = $conn->prepare("SELECT Rating, Comment, CreatedAt, Status FROM Reviews WHERE ReviewID = ?");
                                $reviewCheck->bind_param("i", $row['ReviewID']);
                                $reviewCheck->execute();
                                $reviewRes = $reviewCheck->get_result();

                                if ($reviewRes->num_rows > 0) {
                                    $rev = $reviewRes->fetch_assoc();
                                    echo "<strong>Rated:</strong> " . htmlspecialchars($rev['Rating']) . "/5<br>";
                                    echo "<em>" . nl2br(htmlspecialchars($rev['Comment'])) . "</em><br>";
                                    echo "<small>" . date("d M Y H:i", strtotime($rev['CreatedAt'])) . "</small><br>";
                                    echo "<span>Status: " . htmlspecialchars($rev['Status']) . "</span>";
                                }
                            } else {
                                echo '<a href="make_review.php?AppointmentID=' . $row['AppointmentID'] . '" class="btn">Add Review</a>';
                            }
                        } else {
                            echo "<span style='color: gray;'>Available after completion</span>";
                        }
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>You have no appointments booked yet.</p>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

