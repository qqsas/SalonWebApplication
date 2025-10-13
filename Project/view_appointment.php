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
  <link rel="stylesheet" href="styles.css">
    <h1>My Appointments</h1>

    <?php if ($result->num_rows > 0): ?>
        <!-- Sort Dropdown -->
        <label for="sortColumn">Sort by:</label>
        <select id="sortColumn">
            <option value="0">Appointment ID</option>
            <option value="1">Booked For</option>
            <option value="2">Barber</option>
            <option value="3">Service</option>
            <option value="4">Type</option>
            <option value="5">Time</option>
            <option value="6">Duration</option>
            <option value="7">Status</option>
            <option value="8">Cost</option>
            <option value="9">Created At</option>
        </select>

        <table id="appointmentsTable" border="1" cellpadding="10" cellspacing="0">
            <thead>
            <tr>
                <th data-type="number">Appointment ID</th>
                <th data-type="string">Booked For</th>
                <th data-type="string">Barber</th>
                <th data-type="string">Service</th>
                <th data-type="string">Type</th>
                <th data-type="date">Time</th>
                <th data-type="number">Duration (mins)</th>
                <th data-type="string">Status</th>
                <th data-type="number">Cost</th>
                <th data-type="date">Created At</th>
                <th>Actions</th>
                <th>Review</th>
            </tr>
            </thead>
<tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td data-label="Appointment ID"><?= htmlspecialchars($row['AppointmentID']); ?></td>
            <td data-label="Booked For"><?= htmlspecialchars($row['ForName']) . " (" . htmlspecialchars($row['ForAge']) . ")"; ?></td>
            <td data-label="Barber"><?= htmlspecialchars($row['BarberName']); ?></td>
            <td data-label="Service"><?= htmlspecialchars($row['ServiceName'] ?? 'N/A'); ?></td>
            <td data-label="Type"><?= htmlspecialchars($row['Type']); ?></td>
            <td data-label="Time"><?= date("d M Y H:i", strtotime($row['Time'])); ?></td>
            <td data-label="Duration"><?= htmlspecialchars($row['Duration']); ?> mins</td>
            <td data-label="Status"><?= htmlspecialchars($row['Status']); ?></td>
            <td data-label="Cost"><?= "R" . number_format($row['Cost'], 2); ?></td>
            <td data-label="Created At"><?= date("d M Y H:i", strtotime($row['CreatedAt'])); ?></td>
            <td data-label="Actions">
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
            <td data-label="Review">
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
</tbody>

        </table>

        <!-- JS Sorting -->
        <script>
            const table = document.getElementById('appointmentsTable');
            const select = document.getElementById('sortColumn');
            let asc = true; // ascending order toggle

            select.addEventListener('change', function() {
                const tbody = table.tBodies[0];
                const rows = Array.from(tbody.rows);
                const colIndex = parseInt(this.value);
                const type = table.tHead.rows[0].cells[colIndex].dataset.type;

                rows.sort((a, b) => {
                    let valA = a.cells[colIndex].innerText.trim();
                    let valB = b.cells[colIndex].innerText.trim();

                    if (type === 'number') {
                        valA = parseFloat(valA.replace(/[^\d.-]/g,''));
                        valB = parseFloat(valB.replace(/[^\d.-]/g,''));
                        return asc ? valA - valB : valB - valA;
                    } else if (type === 'date') {
                        return asc ? new Date(valA) - new Date(valB) : new Date(valB) - new Date(valA);
                    } else {
                        return asc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }
                });

                rows.forEach(row => tbody.appendChild(row));
                asc = !asc; // toggle order for next sort
            });
        </script>
    <?php else: ?>
        <p>You have no appointments booked yet.</p>
    <?php endif; ?>
</div>



