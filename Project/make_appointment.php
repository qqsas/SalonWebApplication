<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$UserID = $_SESSION['UserID'];

if (!isset($_GET['ServicesID']) || !isset($_GET['BarberID'])) {
    echo "Service or barber not selected.";
    exit();
}

$ServicesID = (int)$_GET['ServicesID'];
$BarberID = (int)$_GET['BarberID'];

// Verify barber offers this service
$checkServiceStmt = $conn->prepare("SELECT 1 FROM BarberServices WHERE BarberID = ? AND ServicesID = ?");
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
if (!$service) exit("Service not found.");

// Fetch barber info
$barberStmt = $conn->prepare("SELECT Name FROM Barber WHERE BarberID = ?");
$barberStmt->bind_param("i", $BarberID);
$barberStmt->execute();
$barberResult = $barberStmt->get_result();
$barber = $barberResult->fetch_assoc();
if (!$barber) exit("Barber not found.");

// Week offset (0 = this week, 1 = next week, etc.)
$week_offset = isset($_GET['week']) ? max(0, (int)$_GET['week']) : 0;

// Dates to display (7 days starting from week_offset)
$dates = [];
$startDate = strtotime("+$week_offset week Monday");
for ($i = 0; $i < 7; $i++) {
    $dates[] = date('Y-m-d', strtotime("+$i day", $startDate));
}

// Fetch appointments for each date
$appointments = [];
foreach ($dates as $date) {
    $appointments[$date] = [];
    $stmt = $conn->prepare("
        SELECT Time AS StartTime, Duration 
        FROM Appointment 
        WHERE BarberID = ? AND DATE(Time) = ? AND Status NOT IN ('Cancelled')
    ");
    $stmt->bind_param("is", $BarberID, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $start = strtotime($row['StartTime']);
        $duration_seconds = (int)$row['Duration'];
        if ($duration_seconds < 480) {
            $duration_seconds = $duration_seconds * 60; // Convert minutes to seconds
        }
        $end = $start + $duration_seconds + (15*60);
        $appointments[$date][] = [
            'start' => $start, 
            'end' => $end
        ];
    }
}

// Fetch barber working hours
$working_hours = [];
for ($d = 0; $d < 7; $d++) {
    $dow = date('w', strtotime($dates[$d]));
    $stmt = $conn->prepare("SELECT StartTime, EndTime FROM BarberWorkingHours WHERE BarberID = ? AND DayOfWeek = ?");
    $stmt->bind_param("ii", $BarberID, $dow);
    $stmt->execute();
    $res = $stmt->get_result();
    $working_hours[$dates[$d]] = [];
    while ($row = $res->fetch_assoc()) {
        $working_hours[$dates[$d]][] = [
            'start' => strtotime($dates[$d] . ' ' . $row['StartTime']),
            'end'   => strtotime($dates[$d] . ' ' . $row['EndTime'])
        ];
    }
}

// Fetch barber unavailability
$unavailable = [];
foreach ($dates as $date) {
    $stmt = $conn->prepare("SELECT StartTime, EndTime FROM BarberUnavailability WHERE BarberID = ? AND Date = ?");
    $stmt->bind_param("is", $BarberID, $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $unavailable[$date] = [];
    while ($row = $res->fetch_assoc()) {
        $start = !empty($row['StartTime']) ? strtotime($date . ' ' . $row['StartTime']) : strtotime($date . ' 00:00');
        $end   = !empty($row['EndTime']) ? strtotime($date . ' ' . $row['EndTime']) : strtotime($date . ' 23:59:59');
        $unavailable[$date][] = [
            'start' => $start, 
            'end' => $end
        ];
    }
}

// Slot settings
$slot_interval = 15; // minutes
$slot_duration = $service['Time'] * 60; // seconds
$slots = [];
$day_start_hour = 8; // 08:00
$day_end_hour   = 20; // 20:00
for ($hour = $day_start_hour; $hour < $day_end_hour; $hour++) {
    for ($minute = 0; $minute < 60; $minute += $slot_interval) {
        $slots[] = $hour * 3600 + $minute * 60;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment - <?= htmlspecialchars($service['Name']) ?></title>
    <link href="styles.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1,h2,h3,h4 { margin-bottom: 10px; }
        .calendar { border-collapse: collapse; width: 100%; max-width: 900px; margin-top: 20px; }
        .calendar th, .calendar td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        .calendar th { background-color: #f0f0f0; }
        .time-column { background-color: #fafafa; font-weight: bold; width: 80px; }
        .available { background-color: #e8f5e8; cursor: pointer; }
        .unavailable { background-color: #f5e8e8; color: #888; cursor: not-allowed; }
        .booked { background-color: #d3d3d3; color: #666; cursor: not-allowed; }
        .selected { background-color: #007bff; color: #fff; }
    </style>
    <script>
        let selectedSlot = null;
        function selectSlot(elem, datetime) {
            if(elem.classList.contains('unavailable') || elem.classList.contains('booked')) return;
            if(selectedSlot) selectedSlot.classList.remove('selected');
            elem.classList.add('selected');
            selectedSlot = elem;
            document.getElementById('selected_time').value = datetime;
        }
    </script>
</head>
<body>
<h1>Book Appointment</h1>
<h2>Service: <?= htmlspecialchars($service['Name']) ?> (<?= $service['Time'] ?> mins)</h2>
<h3>Barber: <?= htmlspecialchars($barber['Name']) ?></h3>

<div class="week-nav">
<?php 
$startDateDisplay = date('M j, Y', strtotime($dates[0]));
$endDateDisplay = date('M j, Y', strtotime(end($dates)));
?>
<p>Showing appointments from <strong><?= $startDateDisplay ?></strong> to <strong><?= $endDateDisplay ?></strong></p>
<a href="?ServicesID=<?= $ServicesID ?>&BarberID=<?= $BarberID ?>&week=<?= max(0, $week_offset-1) ?>">Previous Week</a>
<span>Week <?= $week_offset+1 ?></span>
<a href="?ServicesID=<?= $ServicesID ?>&BarberID=<?= $BarberID ?>&week=<?= $week_offset+1 ?>">Next Week</a>
</div>

<form action="confirm_appointment.php" method="POST">
    <input type="hidden" name="ServicesID" value="<?= $ServicesID ?>">
    <input type="hidden" name="BarberID" value="<?= $BarberID ?>">
    <input type="hidden" name="UserID" value="<?= $UserID ?>">
    <input type="hidden" name="selected_time" id="selected_time">

    <table class="calendar">
        <tr>
            <th class="time-column">Time</th>
            <?php foreach ($dates as $date): ?>
                <th><?= date('D, M j', strtotime($date)) ?></th>
            <?php endforeach; ?>
        </tr>

        <?php foreach ($slots as $offset): 
            $timeLabel = gmdate('H:i', $offset);
        ?>
            <tr>
                <td class="time-column"><?= $timeLabel ?></td>

                <?php foreach ($dates as $date): 
                    $slot_start = strtotime($date) + $offset;
                    $slot_end   = $slot_start + $slot_duration;

                    $class = 'available';

                    // Check working hours
                    $in_working = false;
                    foreach ($working_hours[$date] as $wh) {
                        if ($slot_start >= $wh['start'] && $slot_end <= $wh['end']) {
                            $in_working = true;
                            break;
                        }
                    }
                    if (!$in_working) {
                        $class = 'unavailable';
                    }

                    // Check booked appointments
                    if ($class === 'available') {
                        foreach ($appointments[$date] as $app) {
                            $overlap = ($slot_start >= $app['start'] && $slot_start < $app['end']) ||
                                      ($slot_end > $app['start'] && $slot_end <= $app['end']) ||
                                      ($slot_start <= $app['start'] && $slot_end >= $app['end']);
                            if ($overlap) {
                                $class = 'booked';
                                break;
                            }
                        }
                    }

                    // Check unavailability
                    if ($class === 'available') {
                        foreach ($unavailable[$date] as $ua) {
                            $overlap = ($slot_start >= $ua['start'] && $slot_start < $ua['end']) ||
                                      ($slot_end > $ua['start'] && $slot_end <= $ua['end']) ||
                                      ($slot_start <= $ua['start'] && $slot_end >= $ua['end']);
                            if ($overlap) {
                                $class = 'unavailable';
                                break;
                            }
                        }
                    }

                    // Past times
                    if ($class === 'available' && $slot_start < time()) {
                        $class = 'unavailable';
                    }
                ?>
                    <td class="<?= $class ?>" 
                        onclick="selectSlot(this,'<?= date('Y-m-d H:i:s', $slot_start) ?>')">
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>

    <button type="submit" style="margin-top:20px; padding:10px 20px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer;">
        Confirm Appointment
    </button>
</form>

</body>
</html>

