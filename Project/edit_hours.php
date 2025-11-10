<?php
session_start();
// if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
//     header("Location: Login.php");
//     exit();
// }

include 'db.php';
include 'header.php';

// Get barber ID from URL parameter
$barberID = $_GET['barber_id'] ?? null;
if (!$barberID) {
    die("Barber ID not specified.");
}

// Get barber details
$stmt = $conn->prepare("SELECT b.*, u.Name AS OwnerName FROM Barber b LEFT JOIN User u ON b.UserID = u.UserID WHERE b.BarberID = ?");
$stmt->bind_param("i", $barberID);
$stmt->execute();
$result = $stmt->get_result();
$barber = $result->fetch_assoc();

if (!$barber) {
    die("Barber not found.");
}

// Get redirect parameters
$view = $_GET['view'] ?? 'working_hours';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$searchParam = urlencode($search);

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>

<div class="admin-dashboard">
    <div class="dashboard-header">
        <div class="admin-welcome">
            <h1>Edit Working Hours</h1>
            <p>Managing working hours for <span class="barber-name"><?php echo escape($barber['Name']); ?></span></p>
            <?php if ($barber['OwnerName']): ?>
                <p>Owner: <?php echo escape($barber['OwnerName']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="back-navigation">
            <a href="admin_dashboard.php?view=working_hours&search=<?php echo $searchParam; ?>&page=<?php echo $page; ?>" class="btn btn-secondary">
                ← Back to Working Hours
            </a>
        </div>
    </div>

    <div class="main-content">
        <?php
        if (isset($_GET['message'])) {
            $messageType = isset($_GET['success']) && $_GET['success'] ? 'success' : 'error';
            $message = escape($_GET['message']);
            echo "<div class='message {$messageType}'>{$message}</div>";
        }

        if ($barber['IsDeleted']) {
            echo "<div class='message error'>This barber has been deleted and cannot be edited.</div>";
        } else {
        ?>
        
        <div class="availability-settings">
            <h2>Working Hours for <?php echo escape($barber['Name']); ?></h2>
            
            <!-- Working Hours Form -->
            <div class="working-hours-section">
                <?php
                // Get current working hours
                $stmt = $conn->prepare("SELECT * FROM BarberWorkingHours WHERE BarberID = ? ORDER BY DayOfWeek");
                $stmt->bind_param("i", $barberID);
                $stmt->execute();
                $result = $stmt->get_result();
                $workingHours = [];
                while ($row = $result->fetch_assoc()) {
                    $workingHours[$row['DayOfWeek']] = $row;
                }
                
                $days = [
                    1 => 'Monday',
                    2 => 'Tuesday', 
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                    7 => 'Sunday'
                ];
                ?>
                
                <form method='POST' action='update_working_hours_admin.php' class='availability-form'>
                    <input type='hidden' name='BarberID' value='<?php echo $barberID; ?>'>
                    <input type='hidden' name='redirect' value='admin_dashboard.php?view=working_hours&search=<?php echo $searchParam; ?>&page=<?php echo $page; ?>'>
                    
                    <div class='availability-grid'>
                        <?php foreach ($days as $dayNum => $dayName): ?>
                            <?php
                            $hour = $workingHours[$dayNum] ?? null;
                            $isWorking = $hour ? 'checked' : '';
                            $startTime = $hour ? substr($hour['StartTime'], 0, 5) : '09:00';
                            $endTime = $hour ? substr($hour['EndTime'], 0, 5) : '17:00';
                            ?>
                            
                            <div class='availability-day'>
                                <div class='day-name'><?php echo $dayName; ?></div>
                                <div class='time-inputs'>
                                    <input type='time' name='startTime[<?php echo $dayNum; ?>]' value='<?php echo $startTime; ?>' class='form-control time-input' step='3600' <?php echo $isWorking; ?>>
                                    <span class='time-separator'>to</span>
                                    <input type='time' name='endTime[<?php echo $dayNum; ?>]' value='<?php echo $endTime; ?>' class='form-control time-input' step='3600' <?php echo $isWorking; ?>>
                                </div>
                                <div class='working-toggle'>
                                    <input type='checkbox' name='workingDays[]' value='<?php echo $dayNum; ?>' id='day<?php echo $dayNum; ?>' class='working-checkbox' <?php echo $isWorking; ?>>
                                    <label for='day<?php echo $dayNum; ?>' class='working-label'>Working</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class='form-actions'>
                        <button type='submit' class='btn btn-primary save-hours-btn'>Save Working Hours</button>
                        <a href='admin_dashboard.php?view=working_hours&search=<?php echo $searchParam; ?>&page=<?php echo $page; ?>' class='btn btn-secondary'>Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Unavailability Management -->
            <div class='unavailability-section'>
                <h3 class='section-title'>Manage Unavailability</h3>
                
                <!-- Add Unavailability Form -->
                <form method='POST' action='update_unavailability_admin.php' class='unavailability-form' id='unavailabilityForm'>
                    <input type='hidden' name='BarberID' value='<?php echo $barberID; ?>'>
                    <input type='hidden' name='redirect' value='edit_hours.php?barber_id=<?php echo $barberID; ?>&view=<?php echo $view; ?>&search=<?php echo $searchParam; ?>&page=<?php echo $page; ?>'>

                    <div class='unavailability-inputs'>
                        <div class='input-row'>
                            <div class='input-group'>
                                <label class='input-label'>Date <span class="required">*</span></label>
                                <input type='date' name='Date' class='input-field date-input' required>
                            </div>
                            
                            <div class='input-group'>
                                <label class='input-label'>Start Time (optional)</label>
                                <input type='time' name='StartTime' class='input-field time-input' step='3600' id='startTimeInput'>
                            </div>
                            
                            <div class='input-group'>
                                <label class='input-label'>End Time (optional)</label>
                                <input type='time' name='EndTime' class='input-field time-input' step='3600' id='endTimeInput'>
                            </div>
                        </div>
                        
                        <div class='input-group full-width'>
                            <label class='input-label'>Reason (optional)</label>
                            <input type='text' name='Reason' class='input-field text-input' maxlength='255' placeholder='Enter reason for unavailability'>
                        </div>

                        <div class='time-options-info'>
                            <p><strong>Note:</strong> 
                                <ul>
                                    <li>No times = Unavailable all day</li>
                                    <li>Start time only = Unavailable from start time to end of day</li>
                                    <li>End time only = Unavailable from start of day to end time</li>
                                    <li>Both times = Unavailable for specific time range</li>
                                </ul>
                            </p>
                        </div>
                        
                        <div class='form-actions'>
                            <button type='submit' class='btn btn-warning add-unavailability-btn'>Add Unavailability</button>
                        </div>
                    </div>
                </form>

                <!-- Existing Unavailability -->
                <?php
                // Fetch current unavailability
                $stmt = $conn->prepare("SELECT * FROM BarberUnavailability WHERE BarberID = ? ORDER BY Date DESC, StartTime");
                $stmt->bind_param("i", $barberID);
                $stmt->execute();
                $result = $stmt->get_result();
                $unavailability = $result->fetch_all(MYSQLI_ASSOC);
                ?>

                <?php if ($unavailability): ?>
                    <div class='existing-unavailability'>
                        <h4 class='section-subtitle'>Existing Unavailability</h4>
                        <div class='table-container'>
                            <table class='data-table unavailability-table'>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unavailability as $u): ?>
                                        <?php
                                        $start = $u['StartTime'] ? substr($u['StartTime'], 0, 5) : '00:00';
                                        $end = $u['EndTime'] ? substr($u['EndTime'], 0, 5) : '23:59';
                                        $reason = htmlspecialchars($u['Reason']);
                                        
                                        // Determine display text based on actual times
                                        $startDisplay = ($start === '00:00' && $end === '23:59') ? 'All Day' : $start;
                                        $endDisplay = ($start === '00:00' && $end === '23:59') ? 'All Day' : $end;
                                        ?>
                                        <tr class='unavailability-item'>
                                            <td class='unavailability-date'><?php echo $u['Date']; ?></td>
                                            <td class='unavailability-start'><?php echo $startDisplay; ?></td>
                                            <td class='unavailability-end'><?php echo $endDisplay; ?></td>
                                            <td class='unavailability-reason'><?php echo $reason; ?></td>
                                            <td class='unavailability-actions'>
                                                <form method='POST' action='delete_unavailability_admin.php' class='inline-form'>
                                                    <input type='hidden' name='UnavailabilityID' value='<?php echo $u['UnavailabilityID']; ?>'>
                                                    <input type='hidden' name='redirect' value='edit_hours.php?barber_id=<?php echo $barberID; ?>&view=<?php echo $view; ?>&search=<?php echo $searchParam; ?>&page=<?php echo $page; ?>'>
                                                    <button type='submit' onclick='return confirm(\"Remove this unavailability?\")' class='btn btn-sm btn-danger remove-unavailability-btn'>Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class='no-unavailability'>
                        <p>No unavailability periods set.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- JavaScript for time handling -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Format time inputs to show only hours and minutes
            const timeInputs = document.querySelectorAll('input[type="time"]');
            timeInputs.forEach(input => {
                // Remove seconds from existing values
                if (input.value && input.value.length > 5) {
                    input.value = input.value.substring(0, 5);
                }
                
                // Ensure step attribute is set to 3600 seconds (1 hour)
                if (!input.getAttribute('step')) {
                    input.setAttribute('step', '3600');
                }
            });
            
            // Handle working hours form submission
            const workingHoursForm = document.querySelector('.availability-form');
            if (workingHoursForm) {
                workingHoursForm.addEventListener('submit', function(e) {
                    const timeInputs = this.querySelectorAll('input[type="time"]');
                    timeInputs.forEach(input => {
                        if (input.value) {
                            // Ensure time is in HH:MM format
                            if (input.value.length === 5 && input.value.includes(':')) {
                                // Add :00 seconds if not present
                                input.value = input.value + ':00';
                            }
                        }
                    });
                });
            }
            
            // Handle unavailability form submission - REMOVED VALIDATION FOR SINGLE TIMES
            const unavailabilityForm = document.getElementById('unavailabilityForm');
            if (unavailabilityForm) {
                unavailabilityForm.addEventListener('submit', function(e) {
                    const startTimeInput = document.getElementById('startTimeInput');
                    const endTimeInput = document.getElementById('endTimeInput');
                    
                    // Validate time order only if both times are provided
                    if (startTimeInput.value && endTimeInput.value) {
                        if (startTimeInput.value >= endTimeInput.value) {
                            alert('End time must be after start time');
                            e.preventDefault();
                            return;
                        }
                        
                        // Add seconds to start time
                        if (startTimeInput.value.length === 5 && startTimeInput.value.includes(':')) {
                            startTimeInput.value = startTimeInput.value + ':00';
                        }
                        
                        // Add seconds to end time
                        if (endTimeInput.value.length === 5 && endTimeInput.value.includes(':')) {
                            endTimeInput.value = endTimeInput.value + ':00';
                        }
                    } else if (startTimeInput.value || endTimeInput.value) {
                        // If only one time is provided, add seconds if needed
                        if (startTimeInput.value && startTimeInput.value.length === 5 && startTimeInput.value.includes(':')) {
                            startTimeInput.value = startTimeInput.value + ':00';
                        }
                        if (endTimeInput.value && endTimeInput.value.length === 5 && endTimeInput.value.includes(':')) {
                            endTimeInput.value = endTimeInput.value + ':00';
                        }
                    }
                    // If no times provided, that's fine - it will be all-day unavailability
                });
            }
            
            // Real-time validation for unavailability times - ONLY WHEN BOTH ARE PROVIDED
            const startTimeInput = document.getElementById('startTimeInput');
            const endTimeInput = document.getElementById('endTimeInput');
            
            if (startTimeInput && endTimeInput) {
                endTimeInput.addEventListener('change', function() {
                    // Only validate if both times are provided
                    if (startTimeInput.value && endTimeInput.value) {
                        const start = startTimeInput.value;
                        const end = endTimeInput.value;
                        
                        if (start >= end) {
                            alert('End time must be after start time');
                            endTimeInput.value = '';
                            endTimeInput.focus();
                        }
                    }
                });
                
                startTimeInput.addEventListener('change', function() {
                    // Only validate if both times are provided
                    if (startTimeInput.value && endTimeInput.value) {
                        const start = startTimeInput.value;
                        const end = endTimeInput.value;
                        
                        if (start >= end) {
                            alert('End time must be after start time');
                            endTimeInput.value = '';
                            endTimeInput.focus();
                        }
                    }
                });
            }

            // Handle working checkbox toggling
            const workingCheckboxes = document.querySelectorAll('.working-checkbox');
            workingCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const dayElement = this.closest('.availability-day');
                    const timeInputs = dayElement.querySelectorAll('.time-input');
                    
                    if (this.checked) {
                        timeInputs.forEach(input => input.removeAttribute('disabled'));
                    } else {
                        timeInputs.forEach(input => input.setAttribute('disabled', 'disabled'));
                    }
                });
                
                // Trigger change event on page load
                checkbox.dispatchEvent(new Event('change'));
            });
        });
        </script>

        <style>
        .required {
            color: #dc3545;
            font-weight: bold;
        }

        .input-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .input-field:required {
            border-left: 3px solid #dc3545;
        }
        
        .time-options-info {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .time-options-info p {
            margin: 0;
            font-size: 0.9em;
            color: #495057;
        }
        
        .time-options-info ul {
            margin: 5px 0 0 0;
            padding-left: 20px;
        }
        
        .time-options-info li {
            font-size: 0.85em;
            margin-bottom: 3px;
        }
        </style>

        <?php } // End if barber is not deleted ?>
    </div>
</div>

<?php include 'footer.php'; ?>
