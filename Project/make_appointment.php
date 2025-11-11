<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$UserID = $_SESSION['UserID'];

// Fetch user's profile name
$userStmt = $conn->prepare("SELECT Name FROM User WHERE UserID = ?");
$userStmt->bind_param("i", $UserID);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$profileName = $user ? $user['Name'] : '';

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

// Week offset (0 = current week starting from today, 1 = next week, etc.)
$week_offset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

// Dates to display (7 days starting from today + week offset)
$dates = [];
$startDate = strtotime("+$week_offset week"); // Start from today
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
        WHERE BarberID = ? AND DATE(Time) = ? AND Status NOT IN ('Cancelled') AND Status NOT IN ('Pending')
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

// Calculate display dates for the header
$startDateDisplay = date('M j, Y', strtotime($dates[0]));
$endDateDisplay = date('M j, Y', strtotime(end($dates)));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment - <?= htmlspecialchars($service['Name']) ?></title>
    <link href="styles2.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="Acontainer">
        <!-- Header Section -->
        <div class="booking-header">
            <h1>Book Your Appointment</h1>
            <h2><?= htmlspecialchars($service['Name']) ?> (<?= $service['Time'] ?> minutes)</h2>
            <h3>with <?= htmlspecialchars($barber['Name']) ?></h3>
            <p>Price: R<?= number_format($service['Price'], 2) ?></p>
        </div>

        <!-- Week Navigation -->
        <div class="week-nav">
            <?php if ($week_offset > 0): ?>
                <a href="?ServicesID=<?= $ServicesID ?>&BarberID=<?= $BarberID ?>&week=<?= $week_offset-1 ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                    Previous Week
                </a>
            <?php else: ?>
                <span></span> <!-- Empty space for layout -->
            <?php endif; ?>
            
            <p>Showing appointments from <strong><?= $startDateDisplay ?></strong> to <strong><?= $endDateDisplay ?></strong></p>
            
            <span>
                <?php if ($week_offset == 0): ?>
                    This Week
                <?php else: ?>
                    Week <?= $week_offset+1 ?>
                <?php endif; ?>
            </span>
            
            <a href="?ServicesID=<?= $ServicesID ?>&BarberID=<?= $BarberID ?>&week=<?= $week_offset+1 ?>">
                Next Week
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                </svg>
            </a>
        </div>

        <!-- Calendar Legend -->
        <div class="calendar-legend">
            <div class="legend-item">
                <div class="legend-color legend-available"></div>
                <span>Available</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-booked"></div>
                <span>Booked</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-unavailable"></div>
                <span>Unavailable</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-selected"></div>
                <span>Selected</span>
            </div>
        </div>

        <form action="confirm_appointment.php" method="POST" class="booking-form" id="bookingForm">
            <input type="hidden" name="ServicesID" value="<?= $ServicesID ?>">
            <input type="hidden" name="BarberID" value="<?= $BarberID ?>">
            <input type="hidden" name="UserID" value="<?= $UserID ?>">
            <input type="hidden" name="selected_time" id="selected_time">

            <!-- Appointment Name Field -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="appointment_name" class="form-label">Appointment For *</label>
                <input type="text" name="appointment_name" id="appointment_name" class="form-input" 
                       placeholder="Enter name for this appointment" 
                       value="<?= htmlspecialchars($profileName) ?>"
                       maxlength="100" required>
                <small class="form-text">Leave blank to use your profile name: <?= htmlspecialchars($profileName) ?></small>
            </div>

            <div class="calendar-container">
                <table class="calendar">
                    <tr>
                        <th class="time-column">Time</th>
                        <?php foreach ($dates as $date): 
                            $isToday = $date == date('Y-m-d');
                            $dayClass = $isToday ? 'today' : '';
                        ?>
                            <th class="<?= $dayClass ?>">
                                <?= date('D, M j', strtotime($date)) ?>
                                <?php if ($isToday): ?>
                                    <br><small>(Today)</small>
                                <?php endif; ?>
                            </th>
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
                                    data-datetime="<?= date('Y-m-d H:i:s', $slot_start) ?>"
                                    tabindex="<?= $class === 'available' ? '0' : '-1' ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="booking-actions">
                <button type="submit" id="submitButton" disabled>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    Confirm Appointment
                </button>
            </div>
        </form>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Appointment</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to book this appointment?</p>
                <div class="appointment-details">
                    <p><strong>Service:</strong> <?= htmlspecialchars($service['Name']) ?></p>
                    <p><strong>Barber:</strong> <?= htmlspecialchars($barber['Name']) ?></p>
                    <p><strong>For:</strong> <span id="confirmAppointmentName"></span></p>
                    <p><strong>Date & Time:</strong> <span id="confirmDateTime"></span></p>
                    <p><strong>Duration:</strong> <?= $service['Time'] ?> minutes</p>
                    <p><strong>Price:</strong> R<?= number_format($service['Price'], 2) ?></p>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" id="confirmBooking" class="btn-confirm">Yes, Book Appointment</button>
                <button type="button" id="cancelBooking" class="btn-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Global function for slot selection (needed for inline onclick)
        let selectedSlot = null;
        const selectedTimeInput = document.getElementById('selected_time');
        const submitButton = document.getElementById('submitButton');
        const bookingForm = document.getElementById('bookingForm');
        const confirmationModal = document.getElementById('confirmationModal');
        const confirmDateTime = document.getElementById('confirmDateTime');
        const confirmAppointmentName = document.getElementById('confirmAppointmentName');
        const confirmBookingBtn = document.getElementById('confirmBooking');
        const cancelBookingBtn = document.getElementById('cancelBooking');
        const closeModal = document.querySelector('.close');
        const appointmentNameInput = document.getElementById('appointment_name');
        const profileName = "<?= addslashes($profileName) ?>";
        
        function selectSlot(elem, datetime) {
            if(elem.classList.contains('unavailable') || elem.classList.contains('booked')) return;
            
            // Remove previous selection
            if(selectedSlot) {
                selectedSlot.classList.remove('selected');
            }
            
            // Add new selection
            elem.classList.add('selected');
            selectedSlot = elem;
            selectedTimeInput.value = datetime;
            
            // Update display
            const date = new Date(datetime);
            let selectedTimeDisplay = document.querySelector('.selected-time-display');
            if (!selectedTimeDisplay) {
                selectedTimeDisplay = document.createElement('div');
                selectedTimeDisplay.className = 'selected-time-display';
                document.querySelector('.booking-form').insertBefore(selectedTimeDisplay, document.querySelector('.booking-actions'));
            }
            
            selectedTimeDisplay.innerHTML = `
                <strong>Selected Time:</strong> 
                ${date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })} 
                at ${date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
            `;
            selectedTimeDisplay.classList.add('show');
            
            // Enable submit button
            submitButton.disabled = false;
        }

        // Handle appointment name auto-fill
        function handleAppointmentName() {
            if (appointmentNameInput.value.trim() === '') {
                appointmentNameInput.value = profileName;
            }
        }

        // Enhanced booking functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectedTimeDisplay = document.createElement('div');
            
            // Create selected time display
            selectedTimeDisplay.className = 'selected-time-display';
            document.querySelector('.booking-form').insertBefore(selectedTimeDisplay, document.querySelector('.booking-actions'));
            
            // Set up appointment name handling
            appointmentNameInput.addEventListener('blur', handleAppointmentName);
            appointmentNameInput.addEventListener('input', function() {
                // If user clears the field and then types something, don't auto-fill
                if (this.value.trim() === '' && this.dataset.userCleared !== 'true') {
                    this.dataset.userCleared = 'true';
                }
            });
            
            // Auto-fill on page load if empty
            if (appointmentNameInput.value.trim() === '') {
                appointmentNameInput.value = profileName;
            }
            
            // Add click event listeners to all available slots
            document.querySelectorAll('.calendar td.available').forEach(slot => {
                slot.addEventListener('click', function() {
                    const datetime = this.getAttribute('data-datetime');
                    selectSlot(this, datetime);
                });
            });
            
            // Add tooltips to slots
            document.querySelectorAll('.calendar td').forEach(slot => {
                const time = slot.parentElement.querySelector('.time-column').textContent;
                const dateHeader = slot.cellIndex > 0 ? 
                    document.querySelectorAll('.calendar th')[slot.cellIndex].textContent : '';
                
                if(slot.classList.contains('available')) {
                    slot.setAttribute('data-tooltip', `Click to select ${dateHeader} at ${time}`);
                } else if(slot.classList.contains('booked')) {
                    slot.setAttribute('data-tooltip', 'This slot is already booked');
                } else if(slot.classList.contains('unavailable')) {
                    slot.setAttribute('data-tooltip', 'This slot is unavailable');
                }
            });
            
            // Keyboard navigation for accessibility
            document.addEventListener('keydown', function(e) {
                if(!selectedSlot) return;
                
                const currentRow = selectedSlot.parentElement;
                const currentCell = selectedSlot.cellIndex;
                let newSlot = null;
                
                switch(e.key) {
                    case 'ArrowUp':
                        newSlot = currentRow.previousElementSibling?.cells[currentCell];
                        break;
                    case 'ArrowDown':
                        newSlot = currentRow.nextElementSibling?.cells[currentCell];
                        break;
                    case 'ArrowLeft':
                        newSlot = currentRow.cells[currentCell - 1];
                        break;
                    case 'ArrowRight':
                        newSlot = currentRow.cells[currentCell + 1];
                        break;
                    case 'Enter':
                    case ' ':
                        if(selectedSlot.classList.contains('available')) {
                            const datetime = selectedSlot.getAttribute('data-datetime');
                            if(datetime) selectSlot(selectedSlot, datetime);
                        }
                        break;
                }
                
                if(newSlot && newSlot.classList.contains('available')) {
                    e.preventDefault();
                    const datetime = newSlot.getAttribute('data-datetime');
                    if(datetime) selectSlot(newSlot, datetime);
                }
            });
            
            // Form submission with confirmation
            bookingForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if(!selectedTimeInput.value) {
                    alert('Please select a time slot for your appointment.');
                    return false;
                }
                
                // Auto-fill appointment name if empty
                handleAppointmentName();
                
                // Validate appointment name
                if (!appointmentNameInput.value.trim()) {
                    alert('Please enter a name for the appointment.');
                    appointmentNameInput.focus();
                    return false;
                }
                
                // Show confirmation modal
                const selectedDate = new Date(selectedTimeInput.value);
                confirmDateTime.textContent = 
                    `${selectedDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })} at ${selectedDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`;
                
                confirmAppointmentName.textContent = appointmentNameInput.value;
                
                confirmationModal.style.display = 'block';
            });
            
            // Confirm booking
            confirmBookingBtn.addEventListener('click', function() {
                // Show loading state
                submitButton.innerHTML = 'Booking...';
                submitButton.disabled = true;
                
                // Close modal
                confirmationModal.style.display = 'none';
                
                // Submit the form
                bookingForm.submit();
            });
            
            // Cancel booking
            cancelBookingBtn.addEventListener('click', function() {
                confirmationModal.style.display = 'none';
            });
            
            // Close modal when clicking X
            closeModal.addEventListener('click', function() {
                confirmationModal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === confirmationModal) {
                    confirmationModal.style.display = 'none';
                }
            });
        });
    </script>

</body>
</html>
