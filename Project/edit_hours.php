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
                        <button type='submit' class='btn'>Save Working Hours</button>
                        <a href='admin_dashboard.php?view=working_hours&search=<?php echo $searchParam; ?>&page=<?php echo $page; ?>' class='btn btn-cancel'>Cancel</a>
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
                            <button type='submit' class='btn btn-warning'>Add Unavailability</button>
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
        .admin-dashboard {
            min-height: 100vh;
            background-color: var(--gray-light);
            padding: 2rem 0;
        }

        .dashboard-header {
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .admin-welcome h1 {
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .admin-welcome p {
            color: var(--text-medium);
            margin: 0.25rem 0;
        }

        .barber-name {
            color: var(--primary-color);
            font-weight: 600;
        }

        .back-navigation {
            display: flex;
            align-items: center;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .message {
            padding: 1rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 1.5rem;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .availability-settings {
            background: var(--background-white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .availability-settings h2 {
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .working-hours-section {
            margin-bottom: 3rem;
        }

        .availability-form {
            margin-top: 1.5rem;
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .availability-day {
            background: var(--gray-light);
            padding: 1.25rem;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--gray-medium);
            transition: all 0.2s ease;
        }

        .availability-day:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(84, 88, 133, 0.1);
        }

        .day-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .time-inputs {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .time-input {
            flex: 1;
            padding: 0.6rem;
            border: 1px solid var(--gray-medium);
            border-radius: var(--border-radius-sm);
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .time-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(84, 88, 133, 0.1);
        }

        .time-input:disabled {
            background: var(--gray-light);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .time-separator {
            color: var(--text-medium);
            font-weight: 500;
        }

        .working-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .working-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .working-label {
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            user-select: none;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-light);
        }

        .save-hours-btn {
            flex: 1;
        }

        .unavailability-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid var(--gray-light);
        }

        .section-title {
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }

        .section-subtitle {
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .unavailability-form {
            background: var(--gray-light);
            padding: 1.5rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 2rem;
        }

        .unavailability-inputs {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .input-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .input-group.full-width {
            grid-column: 1 / -1;
        }

        .input-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .input-field {
            padding: 0.75rem;
            border: 1px solid var(--gray-medium);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(84, 88, 133, 0.1);
        }

        .input-field:required {
            border-left: 3px solid #dc3545;
        }

        .required {
            color: #dc3545;
            font-weight: bold;
        }

        .time-options-info {
            background-color: #e7f3ff;
            border-left: 4px solid var(--accent-color);
            padding: 1rem 1.25rem;
            margin: 1rem 0;
            border-radius: var(--border-radius-sm);
        }

        .time-options-info p {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .time-options-info ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.5rem;
        }

        .time-options-info li {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            color: var(--text-medium);
        }

        .add-unavailability-btn {
            width: 100%;
        }

        .existing-unavailability {
            margin-top: 2rem;
        }

        .no-unavailability {
            text-align: center;
            padding: 2rem;
            color: var(--text-light);
            font-style: italic;
            background: var(--gray-light);
            border-radius: var(--border-radius-sm);
        }

        .unavailability-table {
            margin-top: 1rem;
        }

        .unavailability-item {
            transition: background-color 0.2s ease;
        }

        .unavailability-item:hover {
            background-color: var(--gray-light);
        }

        .remove-unavailability-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .availability-grid {
                grid-template-columns: 1fr;
            }

            .input-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .time-inputs {
                flex-direction: column;
                align-items: stretch;
            }

            .time-separator {
                display: none;
            }
        }
        </style>

        <?php } // End if barber is not deleted ?>
    </div>
</div>

<?php include 'footer.php'; ?>
