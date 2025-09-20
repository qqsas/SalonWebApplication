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
$barberStmt = $conn->prepare("SELECT Name FROM Admin WHERE BarberID = ?");
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

// Debug: Show selected date and current time
$debug_info = "Selected Date: $selected_date<br>";
$debug_info .= "Current Server Time: " . date('Y-m-d H:i:s') . "<br>";

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

$debug_info .= "Booked slots found: " . count($booked_slots) . "<br>";

// Define working hours (9:00–17:00)
$work_start_hour = 9;
$work_end_hour = 17;
$slot_interval = 15; // minutes between possible slot starts

$slot_duration = $service['Time'] * 60; // service duration in seconds
$selected_day_start = strtotime($selected_date);

// Debug: Show working hours and service duration
$debug_info .= "Service Duration: {$service['Time']} minutes<br>";
$debug_info .= "Working Hours: $work_start_hour:00 - $work_end_hour:00<br>";

// Generate available slots for the selected day
$available_slots = [];
$total_slots_considered = 0;
$slots_rejected = 0;

for ($hour = $work_start_hour; $hour < $work_end_hour; $hour++) {
    for ($minute = 0; $minute < 60; $minute += $slot_interval) {
        $slot_start = $selected_day_start + ($hour * 3600) + ($minute * 60);
        $slot_end = $slot_start + $slot_duration;
        $total_slots_considered++;

        // Skip if slot is in the past (for current day)
        $is_past = false;
        if ($selected_date == date('Y-m-d') && $slot_start < time()) {
            $is_past = true;
            $slots_rejected++;
            continue;
        }

        $conflict = false;
        $conflict_reason = "";
        
        // Check for conflicts with booked appointments
        foreach ($booked_slots as $b) {
            if ($slot_start < $b[1] && $slot_end > $b[0]) {
                $conflict = true;
                $conflict_reason = "Overlaps with existing appointment";
                break;
            }
        }

        // Ensure slot fits within working hours
        $slot_end_time = $slot_start + $slot_duration;
        $work_end_timestamp = $selected_day_start + ($work_end_hour * 3600);
        
        if ($slot_end_time > $work_end_timestamp) {
            $conflict = true;
            $conflict_reason = "Extends beyond working hours";
        }

        if (!$conflict && !$is_past) {
            $available_slots[] = $slot_start;
        } else if (!$is_past) {
            $slots_rejected++;
            $debug_info .= "Slot rejected: " . date('H:i', $slot_start) . " - Reason: $conflict_reason<br>";
        }
    }
}

$debug_info .= "Total slots considered: $total_slots_considered<br>";
$debug_info .= "Slots rejected: $slots_rejected<br>";
$debug_info .= "Available slots found: " . count($available_slots) . "<br>";
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

<?php if(empty($available_slots)): ?>
    <p>No available slots on <?= date('F j, Y', strtotime($selected_date)) ?>. Please select another day.</p>
    
    <h4>Possible reasons:</h4>
    <ul>
        <li>The barber might be fully booked on this day</li>
        <li>The service duration might be too long for the remaining time slots</li>
        <li>There might be no working hours defined for this barber</li>
        <li>All available slots might be in the past (if viewing today)</li>
    </ul>
    
    <p>Try selecting a different date using the navigation above.</p>
<?php else: ?>
    <form action="confirm_appointment.php" method="POST">
        <input type="hidden" name="ServicesID" value="<?= $ServicesID ?>">
        <input type="hidden" name="BarberID" value="<?= $BarberID ?>">
        <input type="hidden" name="UserID" value="<?= $UserID ?>">
        <input type="hidden" name="selected_time" id="selected_time">

        <h3>Available Time Slots:</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin: 20px 0;">
            <?php foreach($available_slots as $slot): ?>
                <?php $slot_display = date("H:i", $slot); ?>
                <div style="padding: 12px; text-align: center; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; background-color: #e8f5e8;"
                     onclick="selectSlot(this, '<?= date("Y-m-d H:i:s", $slot) ?>')">
                    <?= $slot_display ?>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" style="margin-top:20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Confirm Appointment
        </button>
    </form>
<?php endif; ?>

</body>
</html>
