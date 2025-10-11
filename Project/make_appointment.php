<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$UserID = $_SESSION['UserID'];

// Get ServicesID and BarberID from GET
if (!isset($_GET['ServicesID']) || !isset($_GET['BarberID'])) {
    echo "Service or barber not selected.";
    exit();
}

$ServicesID = (int)$_GET['ServicesID'];
$BarberID = (int)$_GET['BarberID'];

// Verify barber offers this service
$checkServiceStmt = $conn->prepare("
    SELECT 1 FROM BarberServices WHERE BarberID = ? AND ServicesID = ?
");
$checkServiceStmt->bind_param("ii", $BarberID, $ServicesID);
$checkServiceStmt->execute();
$checkResult = $checkServiceStmt->get_result();
if ($checkResult->num_rows === 0) {
    echo "Selected barber does not provide this service.";
    exit();
}

// Fetch service info
$serviceStmt = $conn->prepare("SELECT Name, Price, Time FROM Services WHERE ServicesID = ?");
$serviceStmt->bind_param("i", $ServicesID);
$serviceStmt->execute();
$serviceResult = $serviceStmt->get_result();
$service = $serviceResult->fetch_assoc();
if (!$service) {
    echo "Service not found.";
    exit();
}

// Fetch barber info
$barberStmt = $conn->prepare("SELECT Name FROM Barber WHERE BarberID = ?");
$barberStmt->bind_param("i", $BarberID);
$barberStmt->execute();
$barberResult = $barberStmt->get_result();
$barber = $barberResult->fetch_assoc();
if (!$barber) {
    echo "Barber not found.";
    exit();
}

// Get selected date or default to today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_timestamp = strtotime($selected_date);
$prev_day = date('Y-m-d', strtotime('-1 day', $current_timestamp));
$next_day = date('Y-m-d', strtotime('+1 day', $current_timestamp));

// Get booked appointments for the barber on selected date
$appointmentStmt = $conn->prepare("
    SELECT Time, Duration 
    FROM Appointment 
    WHERE BarberID = ? AND DATE(Time) = ? AND Status != 'Cancelled'
");
$appointmentStmt->bind_param("is", $BarberID, $selected_date);
$appointmentStmt->execute();
$appointmentResult = $appointmentStmt->get_result();
$booked_slots = [];
while ($row = $appointmentResult->fetch_assoc()) {
    $start_time = strtotime($row['Time']);
    $duration = $row['Duration'] * 60; // convert mins to seconds
    $booked_slots[] = [$start_time, $start_time + $duration];
}

// Get barber unavailability
$unavailable_slots = [];
$full_day_unavailable = false;

$unavailableStmt = $conn->prepare("
    SELECT Date, StartTime, EndTime 
    FROM BarberUnavailability 
    WHERE BarberID = ? AND Date = ?
");
$unavailableStmt->bind_param("is", $BarberID, $selected_date);
$unavailableStmt->execute();
$unavailResult = $unavailableStmt->get_result();

while ($row = $unavailResult->fetch_assoc()) {
    // If both StartTime and EndTime are NULL, the whole day is unavailable
    if (empty($row['StartTime']) && empty($row['EndTime'])) {
        $full_day_unavailable = true;
    } else {
        // If only one is missing, fill with day bounds
        $start_ts = !empty($row['StartTime']) ? strtotime($selected_date . ' ' . $row['StartTime']) : strtotime($selected_date . ' 00:00');
        $end_ts = !empty($row['EndTime']) ? strtotime($selected_date . ' ' . $row['EndTime']) : strtotime($selected_date . ' 23:59:59');

        $unavailable_slots[] = [$start_ts, $end_ts];
    }
}

// Determine day of week (0=Sunday, 1=Monday, ..., 6=Saturday)
$dayOfWeek = date('w', strtotime($selected_date));

// Check if barber works on this day
$workStmt = $conn->prepare("
    SELECT StartTime, EndTime 
    FROM BarberWorkingHours 
    WHERE BarberID = ? AND DayOfWeek = ?
");
$workStmt->bind_param("ii", $BarberID, $dayOfWeek);
$workStmt->execute();
$workResult = $workStmt->get_result();

$working_hours = [];
$day_off = false;
if ($workResult->num_rows === 0) {
    $day_off = true; // No working hours listed for this day
} else {
    while ($row = $workResult->fetch_assoc()) {
        $working_hours[] = [
            'start' => strtotime($selected_date . ' ' . $row['StartTime']),
            'end' => strtotime($selected_date . ' ' . $row['EndTime'])
        ];
    }
}

// Slot settings
$slot_interval = 15; // minutes
$slot_duration = $service['Time'] * 60; // service duration in seconds
$selected_day_start = strtotime($selected_date);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment - <?= htmlspecialchars($service['Name']) ?></title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        let selectedSlot = null;
        function selectSlot(elem, timestamp) {
            if(elem.classList.contains('unavailable')) return;
            if(selectedSlot) selectedSlot.classList.remove('selected');
            elem.classList.add('selected');
            selectedSlot = elem;
            document.getElementById('selected_time').value = timestamp;
        }
    </script>
    <style>
        .slot { padding: 12px; text-align: center; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; }
        .available { background-color: #e8f5e8; }
        .unavailable { background-color: #f5e8e8; color: #888; cursor: not-allowed; }
        .selected { background-color: #007bff; color: #fff; }
    </style>
</head>
<body>

<h1>Book Appointment</h1>
<h2>Service: <?= htmlspecialchars($service['Name']) ?> (<?= $service['Time'] ?> mins)</h2>
<h3>Barber: <?= htmlspecialchars($barber['Name']) ?></h3>

<!-- Date Navigation -->
<div style="margin: 20px 0; display: flex; justify-content: center; align-items: center;">
    <a href="?ServicesID=<?= $ServicesID ?>&BarberID=<?= $BarberID ?>&date=<?= $prev_day ?>" 
       style="text-decoration: none; padding: 8px 16px; background: #f0f0f0; border-radius: 4px; margin-right: 15px;">
        &lt; Previous Day
    </a>
    
    <strong style="font-size: 18px;"><?= date('F j, Y', strtotime($selected_date)) ?></strong>
    
    <a href="?ServicesID=<?= $ServicesID ?>&BarberID=<?= $BarberID ?>&date=<?= $next_day ?>" 
       style="text-decoration: none; padding: 8px 16px; background: #f0f0f0; border-radius: 4px; margin-left: 15px;">
        Next Day &gt;
    </a>
</div>

<?php if($full_day_unavailable || $day_off): ?>
    <p>The barber is unavailable for the entire day. Please select another date.</p>
<?php else: ?>
    <form action="confirm_appointment.php" method="POST">
        <input type="hidden" name="ServicesID" value="<?= $ServicesID ?>">
        <input type="hidden" name="BarberID" value="<?= $BarberID ?>">
        <input type="hidden" name="UserID" value="<?= $UserID ?>">
        <input type="hidden" name="selected_time" id="selected_time">

        <h3>Available Time Slots:</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin: 20px 0;">
            <?php
            for ($hour = 0; $hour < 24; $hour++) {
                for ($minute = 0; $minute < 60; $minute += $slot_interval) {
                    $slot_start = $selected_day_start + ($hour * 3600) + ($minute * 60);
                    $slot_end = $slot_start + $slot_duration;
                    $slot_display = date("H:i", $slot_start);

                    $class = 'available';

                    // Past slots
                    if ($slot_start < time() && $selected_date == date('Y-m-d')) { $class = 'unavailable'; }

                    // Booked appointments
                    foreach ($booked_slots as $b) {
                        if ($slot_start < $b[1] && $slot_end > $b[0]) { $class = 'unavailable'; break; }
                    }

                    // Partial unavailability
                    foreach ($unavailable_slots as $u) {
                        if ($slot_start < $u[1] && $slot_end > $u[0]) { $class = 'unavailable'; break; }
                    }

                    // Not within working hours
                    $in_working_hours = false;
                    foreach ($working_hours as $wh) {
                        if ($slot_start >= $wh['start'] && $slot_end <= $wh['end']) {
                            $in_working_hours = true;
                            break;
                        }
                    }
                    if (!$in_working_hours) $class = 'unavailable';

                    echo "<div class='slot $class' onclick='selectSlot(this,\"".date("Y-m-d H:i:s",$slot_start)."\")'>$slot_display</div>";
                }
            }
            ?>
        </div>

        <button type="submit" style="margin-top:20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Confirm Appointment
        </button>
    </form>
<?php endif; ?>

</body>
</html>

