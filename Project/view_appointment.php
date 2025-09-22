<?php
session_start();
include 'db.php';
include 'header.php';

// Redirect if not logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
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
        b.Name AS BarberName,
        s.Name AS ServiceName
    FROM Appointment a
    JOIN Admin b ON a.BarberID = b.BarberID
    LEFT JOIN BarberServices bs ON b.BarberID = bs.BarberID
    LEFT JOIN Services s ON bs.ServicesID = s.ServicesID
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
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['AppointmentID']); ?></td>
                    <td><?php echo htmlspecialchars($row['ForName']) . " (" . htmlspecialchars($row['ForAge']) . ")"; ?></td>
                    <td><?php echo htmlspecialchars($row['BarberName']); ?></td>
                    <td><?php echo htmlspecialchars($row['ServiceName'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['Type']); ?></td>
                    <td><?php echo date("d M Y H:i", strtotime($row['Time'])); ?></td>
                    <td><?php echo htmlspecialchars($row['Duration']); ?></td>
                    <td><?php echo htmlspecialchars($row['Status']); ?></td>
                    <td><?php echo "R" . number_format($row['Cost'], 2); ?></td>
                    <td><?php echo date("d M Y H:i", strtotime($row['CreatedAt'])); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>You have no appointments booked yet.</p>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

