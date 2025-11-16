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

// Get unique data for filters
$statuses = [];
$barbers = [];
$services = [];
while ($row = $result->fetch_assoc()) {
    $statuses[$row['Status']] = $row['Status'];
    $barbers[$row['BarberName']] = $row['BarberName'];
    $services[$row['ServiceName'] ?? $row['Type']] = $row['ServiceName'] ?? $row['Type'];
}
$result->data_seek(0); // Reset result pointer
?>

<div class="Acontainer">
  
    <h1>My Appointments</h1>

    <?php if ($result->num_rows > 0): ?>
        <!-- Enhanced Sort and Filter Controls -->
        <div class="orders-container">
            <div class="orders-controls">

                <!-- Sort Dropdown -->
                <label for="sortColumn">Sort by:</label>
                <select id="sortColumn">
                    <option value="0">Appointment ID</option>
                    <option value="1">Booked For</option>
                    <option value="2">Barber</option>
                    <option value="3">Service</option>
                    <option value="4">Type</option>
                    <option value="5">Time (Newest First)</option>
                    <option value="5-asc">Time (Oldest First)</option>
                    <option value="6">Duration (Longest First)</option>
                    <option value="6-asc">Duration (Shortest First)</option>
                    <option value="7">Status A-Z</option>
                    <option value="7-desc">Status Z-A</option>
                    <option value="8">Cost (High to Low)</option>
                    <option value="8-asc">Cost (Low to High)</option>
                    <option value="9">Created At (Newest First)</option>
                    <option value="9-asc">Created At (Oldest First)</option>
                </select>

                <!-- Status Filter -->
                <label for="statusFilter">Status:</label>
                <select id="statusFilter">
                    <option value="all">All Statuses</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Barber Filter -->
                <label for="barberFilter">Barber:</label>
                <select id="barberFilter">
                    <option value="all">All Barbers</option>
                    <?php foreach ($barbers as $barber): ?>
                        <option value="<?= htmlspecialchars($barber) ?>"><?= htmlspecialchars($barber) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Service Filter -->
                <label for="serviceFilter">Service:</label>
                <select id="serviceFilter">
                    <option value="all">All Services</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= htmlspecialchars($service) ?>"><?= htmlspecialchars($service) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Date Range Filters -->
                <label for="dateFrom">From Date:</label>
                <input type="date" id="dateFrom">

                <label for="dateTo">To Date:</label>
                <input type="date" id="dateTo">

                <!-- Cost Range Filters -->
                <label for="minCost">Min Cost:</label>
                <input type="number" id="minCost" placeholder="R 0" min="0" step="0.01" style="width: 80px;">

                <label for="maxCost">Max Cost:</label>
                <input type="number" id="maxCost" placeholder="R 1000" min="0" step="0.01" style="width: 80px;">

                <!-- Quick Action Buttons -->
                <button type="button" id="applyFilters" class="btn">Apply Filters</button>
                <button type="button" id="resetFilters" class="btn">Reset</button>
            </div>

            <!-- Quick Filter Buttons -->
            <div class="orders-controls" style="margin-top: 10px;">
                <strong>Quick Filters:</strong>
                <button type="button" class="btn quick-filter" data-status="all">Show All</button>
                <button type="button" class="btn quick-filter" data-status="Confirmed">Confirmed</button>
                <button type="button" class="btn quick-filter" data-status="Completed">Completed</button>
                <button type="button" class="btn quick-filter" data-status="Pending">Pending</button>
                <button type="button" class="btn quick-filter" data-status="Cancelled">Cancelled</button>
                <button type="button" class="btn quick-filter" data-time="upcoming">Upcoming</button>
                <button type="button" class="btn quick-filter" data-time="past">Past</button>
            </div>
        </div>

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
                    <tr data-status="<?= htmlspecialchars($row['Status']) ?>" 
                        data-barber="<?= htmlspecialchars($row['BarberName']) ?>"
                        data-service="<?= htmlspecialchars($row['ServiceName'] ?? $row['Type']) ?>"
                        data-time="<?= strtotime($row['Time']) ?>"
                        data-cost="<?= $row['Cost'] ?>"
                        data-duration="<?= $row['Duration'] ?>">
                        <td data-label="Appointment ID"><?= htmlspecialchars($row['AppointmentID']); ?></td>
                        <td data-label="Booked For"><?= htmlspecialchars($row['ForName']); ?></td>
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

        <!-- Enhanced JS for Filtering and Sorting -->
        <script>
            // Get filter elements
            const statusFilter = document.getElementById('statusFilter');
            const barberFilter = document.getElementById('barberFilter');
            const serviceFilter = document.getElementById('serviceFilter');
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            const minCost = document.getElementById('minCost');
            const maxCost = document.getElementById('maxCost');
            const applyFilters = document.getElementById('applyFilters');
            const resetFilters = document.getElementById('resetFilters');
            const quickFilters = document.querySelectorAll('.quick-filter');
            const tableRows = document.querySelectorAll('#appointmentsTable tbody tr');

            function filterAppointments() {
                const selectedStatus = statusFilter.value;
                const selectedBarber = barberFilter.value;
                const selectedService = serviceFilter.value;
                const dateFromValue = dateFrom.value ? new Date(dateFrom.value).getTime() : null;
                const dateToValue = dateTo.value ? new Date(dateTo.value).getTime() + 86400000 : null; // Add 1 day
                const minCostValue = minCost.value ? parseFloat(minCost.value) : null;
                const maxCostValue = maxCost.value ? parseFloat(maxCost.value) : null;

                tableRows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    const rowBarber = row.getAttribute('data-barber');
                    const rowService = row.getAttribute('data-service');
                    const rowTime = parseInt(row.getAttribute('data-time')) * 1000; // Convert to milliseconds
                    const rowCost = parseFloat(row.getAttribute('data-cost'));
                    
                    let show = true;

                    // Status filter
                    if (selectedStatus !== 'all' && rowStatus !== selectedStatus) {
                        show = false;
                    }

                    // Barber filter
                    if (selectedBarber !== 'all' && rowBarber !== selectedBarber) {
                        show = false;
                    }

                    // Service filter
                    if (selectedService !== 'all' && rowService !== selectedService) {
                        show = false;
                    }

                    // Date range filter
                    if (dateFromValue && rowTime < dateFromValue) {
                        show = false;
                    }
                    if (dateToValue && rowTime > dateToValue) {
                        show = false;
                    }

                    // Cost range filter
                    if (minCostValue && rowCost < minCostValue) {
                        show = false;
                    }
                    if (maxCostValue && rowCost > maxCostValue) {
                        show = false;
                    }

                    row.style.display = show ? '' : 'none';
                });
            }

            function resetAllFilters() {
                statusFilter.value = 'all';
                barberFilter.value = 'all';
                serviceFilter.value = 'all';
                dateFrom.value = '';
                dateTo.value = '';
                minCost.value = '';
                maxCost.value = '';
                filterAppointments();
            }

            // Quick filter functionality
            quickFilters.forEach(button => {
                button.addEventListener('click', function() {
                    const status = this.getAttribute('data-status');
                    const timeFilter = this.getAttribute('data-time');
                    
                    if (status) {
                        statusFilter.value = status;
                    }
                    
                    if (timeFilter === 'upcoming') {
                        const today = new Date().getTime();
                        tableRows.forEach(row => {
                            const rowTime = parseInt(row.getAttribute('data-time')) * 1000;
                            row.style.display = rowTime > today ? '' : 'none';
                        });
                    } else if (timeFilter === 'past') {
                        const today = new Date().getTime();
                        tableRows.forEach(row => {
                            const rowTime = parseInt(row.getAttribute('data-time')) * 1000;
                            row.style.display = rowTime <= today ? '' : 'none';
                        });
                    } else {
                        filterAppointments();
                    }
                });
            });

            // Event listeners
            applyFilters.addEventListener('click', filterAppointments);
            resetFilters.addEventListener('click', resetAllFilters);

            // Enhanced Sorting Functionality
            const table = document.getElementById('appointmentsTable');
            const sortSelect = document.getElementById('sortColumn');

            sortSelect.addEventListener('change', function() {
                const tbody = table.tBodies[0];
                const visibleRows = Array.from(tbody.rows).filter(row => row.style.display !== 'none');
                const sortValue = this.value;
                const colIndex = parseInt(sortValue.split('-')[0]);
                const isAscending = !sortValue.includes('-desc') && !sortValue.includes('-asc') ? false : !sortValue.includes('-desc');
                const type = table.tHead.rows[0].cells[colIndex].dataset.type;

                visibleRows.sort((a, b) => {
                    let valA, valB;

                    // Use data attributes for specific sorts
                    if (sortValue === '8' || sortValue === '8-asc') {
                        valA = parseFloat(a.getAttribute('data-cost'));
                        valB = parseFloat(b.getAttribute('data-cost'));
                    } else if (sortValue === '6' || sortValue === '6-asc') {
                        valA = parseInt(a.getAttribute('data-duration'));
                        valB = parseInt(b.getAttribute('data-duration'));
                    } else if (sortValue === '5' || sortValue === '5-asc') {
                        valA = parseInt(a.getAttribute('data-time'));
                        valB = parseInt(b.getAttribute('data-time'));
                    } else if (sortValue === '9' || sortValue === '9-asc') {
                        valA = new Date(a.cells[colIndex].innerText.trim()).getTime();
                        valB = new Date(b.cells[colIndex].innerText.trim()).getTime();
                    } else {
                        valA = a.cells[colIndex].innerText.trim();
                        valB = b.cells[colIndex].innerText.trim();
                    }

                    if (type === 'number') {
                        valA = typeof valA === 'string' ? parseFloat(valA.replace(/[^\d.-]/g,'')) : valA;
                        valB = typeof valB === 'string' ? parseFloat(valB.replace(/[^\d.-]/g,'')) : valB;
                        return isAscending ? valA - valB : valB - valA;
                    } else if (type === 'date') {
                        valA = typeof valA === 'string' ? new Date(valA).getTime() : valA;
                        valB = typeof valB === 'string' ? new Date(valB).getTime() : valB;
                        return isAscending ? valA - valB : valB - valA;
                    } else {
                        return isAscending ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }
                });

                // Reorder only visible rows while maintaining hidden rows in their original positions
                const allRows = Array.from(tbody.rows);
                const hiddenRows = allRows.filter(row => row.style.display === 'none');
                
                // Clear tbody
                while (tbody.firstChild) {
                    tbody.removeChild(tbody.firstChild);
                }

                // Append sorted visible rows
                visibleRows.forEach(row => tbody.appendChild(row));
                // Append hidden rows at the end
                hiddenRows.forEach(row => tbody.appendChild(row));
            });

            // Initialize with current date restrictions
            const today = new Date().toISOString().split('T')[0];
            dateFrom.max = today;
            dateTo.max = today;
        </script>
    <?php else: ?>
        <p>You have no appointments booked yet.</p>
    <?php endif; ?>
</div>

<?php
include 'footer.php';
?>