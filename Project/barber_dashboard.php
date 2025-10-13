<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'header.php';

// Get barber ID from session
$barberID = $_SESSION['BarberID'] ?? null;
if (!$barberID) {
    // If BarberID is not in session, get it from Barber table using UserID
    $stmt = $conn->prepare("SELECT BarberID FROM Barber WHERE UserID = ? AND IsDeleted = 0");
    $stmt->bind_param("i", $_SESSION['UserID']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $barberData = $result->fetch_assoc();
        $barberID = $barberData['BarberID'];
        $_SESSION['BarberID'] = $barberID;
    } else {
        die("Barber profile not found.");
    }
}

$view = $_GET['view'] ?? 'overview';
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$status = $_GET['status'] ?? '';
$date = $_GET['date'] ?? '';
$category = $_GET['category'] ?? '';
$rating = $_GET['rating'] ?? '';

$searchParam = urlencode($search);
$searchLike = $search ? "%" . strtolower($search) . "%" : "%";

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Function to convert USD to ZAR (Rands)
function usdToZar($usdAmount) {
    // You can use a fixed exchange rate or fetch from an API
    // For now, using a fixed rate of 18.5 (approximate)
    $exchangeRate = 18.5;
    return number_format($usdAmount * $exchangeRate, 2);
}

// Function to format currency in Rands
function formatCurrency($amount) {
    return 'R ' . usdToZar($amount);
}

function getRecordCount($conn, $table, $where = '', $params = [], $types = '') {
    $sql = "SELECT COUNT(*) as total FROM $table";
    if ($where) $sql .= " WHERE $where";
    
    $stmt = $conn->prepare($sql);
    if ($where && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'];
}

function displayPagination($totalPages, $page, $view, $searchParam, $filter = '', $status = '', $date = '', $category = '', $rating = '') {
    if ($totalPages <= 1) return;
    
    $filterParams = "";
    if ($filter) $filterParams .= "&filter=$filter";
    if ($status) $filterParams .= "&status=$status";
    if ($date) $filterParams .= "&date=$date";
    if ($category) $filterParams .= "&category=$category";
    if ($rating) $filterParams .= "&rating=$rating";
    
    echo "<div class='pagination'>";
    if ($page > 1) {
        echo "<a href='?view=$view&search=$searchParam{$filterParams}&page=".($page-1)."'>Previous</a> ";
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) {
            echo "<strong>$i</strong> ";
        } else {
            echo "<a href='?view=$view&search=$searchParam{$filterParams}&page=$i'>$i</a> ";
        }
    }
    if ($page < $totalPages) {
        echo "<a href='?view=$view&search=$searchParam{$filterParams}&page=".($page+1)."'>Next</a>";
    }
    echo "</div>";
}

$features = [];
$stmt = $conn->prepare("SELECT FeatureName, IsEnabled FROM Features WHERE FeatureName IN ('allow services','allow products')");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $features[$row['FeatureName']] = (bool)$row['IsEnabled'];
}
?>

<div class="barber-dashboard">
    <link rel="stylesheet" href="barberstyle.css">
    <link rel="stylesheet" href="adminstyle.css">

    <div class="dashboard-header">
        <div class="barber-welcome">
            <h1>Barber Dashboard</h1>
            <?php
            // Get barber name for welcome message
            $stmt = $conn->prepare("SELECT Name FROM Barber WHERE BarberID = ?");
            $stmt->bind_param("i", $barberID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $barber = $result->fetch_assoc();
                echo "<p>Welcome back, <span class='barber-name'>" . escape($barber['Name']) . "</span>!</p>";
            }
            ?>
        </div>
    </div>

    <div class="main-content">
        <!-- Improved Navigation -->
        <div class="dashboard-nav">
            <ul class="dashboard-nav-links">
                <li><a class="dashboard-nav-link <?php echo $view === 'overview' ? 'active' : ''; ?>" href="?view=overview">Overview</a></li>
                <li><a class="dashboard-nav-link <?php echo $view === 'appointments' ? 'active' : ''; ?>" href="?view=appointments">My Appointments</a></li>
                <li><a class="dashboard-nav-link <?php echo $view === 'services' ? 'active' : ''; ?>" href="?view=services">My Services</a></li>
                <li><a class="dashboard-nav-link <?php echo $view === 'workinghours' ? 'active' : ''; ?>" href="?view=workinghours">Working Hours</a></li>
                <li><a class="dashboard-nav-link <?php echo $view === 'profile' ? 'active' : ''; ?>" href="?view=profile">My Profile</a></li>
                <li><a class="dashboard-nav-link <?php echo $view === 'reviews' ? 'active' : ''; ?>" href="?view=reviews">My Reviews</a></li>
                <?php if ($features['allow products'] ?? false): ?>
                    <li><a class="dashboard-nav-link <?php echo $view === 'products' ? 'active' : ''; ?>" href="?view=products">My Products</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Improved Search and Filter Form -->
        <div class="search-filter-container">
            <form method="GET" class="search-filter-form">
                <input type="hidden" name="view" value="<?php echo escape($view); ?>">
                
                <div class="search-section">
                    <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo escape($search); ?>">
                    <button type="submit" class="search-button">Search</button>
                    <?php if ($search || $filter || $status || $date || $category || $rating): ?>
                        <a href="?view=<?php echo escape($view); ?>" class="clear-search">Clear All</a>
                    <?php endif; ?>
                </div>

                <?php if ($view === 'appointments'): ?>
                <div class="filter-section">
                    <select name="status" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="in progress" <?php echo $status === 'in progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    
                    <select name="filter" class="filter-select">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Past</option>
                    </select>
                    
                    <input type="date" name="date" class="filter-select" value="<?php echo escape($date); ?>" placeholder="Specific Date">
                </div>
                <?php elseif ($view === 'reviews'): ?>
                <div class="filter-section">
                    <select name="rating" class="filter-select">
                        <option value="">All Ratings</option>
                        <option value="5" <?php echo $rating === '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating === '4' ? 'selected' : ''; ?>>4+ Stars</option>
                        <option value="3" <?php echo $rating === '3' ? 'selected' : ''; ?>>3+ Stars</option>
                        <option value="2" <?php echo $rating === '2' ? 'selected' : ''; ?>>2+ Stars</option>
                        <option value="1" <?php echo $rating === '1' ? 'selected' : ''; ?>>1+ Stars</option>
                    </select>
                    
                    <select name="filter" class="filter-select">
                        <option value="">All Time</option>
                        <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="year" <?php echo $filter === 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </div>
                <?php elseif ($view === 'products'): ?>
                <div class="filter-section">
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php
                        $catStmt = $conn->prepare("SELECT DISTINCT Category FROM Products WHERE IsDeleted = 0 ORDER BY Category");
                        $catStmt->execute();
                        $catResult = $catStmt->get_result();
                        while ($cat = $catResult->fetch_assoc()) {
                            $selected = $category === $cat['Category'] ? 'selected' : '';
                            echo "<option value='".escape($cat['Category'])."' $selected>".escape($cat['Category'])."</option>";
                        }
                        ?>
                    </select>
                    
                    <select name="filter" class="filter-select">
                        <option value="">All Stock</option>
                        <option value="in_stock" <?php echo $filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>Low Stock (< 10)</option>
                        <option value="out_of_stock" <?php echo $filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php
        if (isset($_GET['message'])) {
            $messageType = isset($_GET['success']) && $_GET['success'] ? 'success' : 'error';
            $message = escape($_GET['message']);
            echo "<div class='message {$messageType}'>{$message}</div>";
        }

        switch($view) {
            case 'appointments':
                // Build WHERE clause for appointments
                $where = "a.BarberID = ? AND (LOWER(u.Name) LIKE ? OR LOWER(a.ForName) LIKE ? OR LOWER(a.Type) LIKE ?)";
                $params = [$barberID, $searchLike, $searchLike, $searchLike];
                $types = "isss";
                
                // Add status filter
                if ($status) {
                    $where .= " AND a.Status = ?";
                    $params[] = $status;
                    $types .= "s";
                }
                
                // Add date filters
                if ($filter === 'today') {
                    $where .= " AND DATE(a.Time) = CURDATE()";
                } elseif ($filter === 'week') {
                    $where .= " AND YEARWEEK(a.Time, 1) = YEARWEEK(CURDATE(), 1)";
                } elseif ($filter === 'month') {
                    $where .= " AND YEAR(a.Time) = YEAR(CURDATE()) AND MONTH(a.Time) = MONTH(CURDATE())";
                } elseif ($filter === 'upcoming') {
                    $where .= " AND a.Time >= NOW()";
                } elseif ($filter === 'past') {
                    $where .= " AND a.Time < NOW()";
                }
                
                // Add specific date filter
                if ($date) {
                    $where .= " AND DATE(a.Time) = ?";
                    $params[] = $date;
                    $types .= "s";
                }
                
                $totalRecords = getRecordCount($conn, 'Appointment a 
                                        LEFT JOIN User u ON a.UserID = u.UserID', 
                                        $where, $params, $types);
                $totalPages = ceil($totalRecords / $limit);
                
                echo "<h2>My Appointments (Total: $totalRecords)</h2>";
                
                $orderBy = "a.Time " . ($filter === 'past' ? "DESC" : "ASC");
                $stmt = $conn->prepare("SELECT a.*, u.Name AS UserName, u.Number AS UserPhone 
                                        FROM Appointment a 
                                        LEFT JOIN User u ON a.UserID = u.UserID 
                                        WHERE $where ORDER BY $orderBy LIMIT ? OFFSET ?");
                $params[] = $limit;
                $params[] = $offset;
                $types .= "ii";
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    echo "<p>No appointments found.</p>";
                    break;
                }
                
                echo "<div class='table-container'>";
                echo "<table class='data-table'>
                        <tr>
                            <th>ID</th><th>Client</th><th>Phone</th><th>For Name</th><th>Age</th><th>Service</th><th>Date & Time</th><th>Duration</th><th>Status</th><th>Cost</th><th>Actions</th>
                        </tr>";
                while ($row = $result->fetch_assoc()) {
                    $appointmentTime = new DateTime($row['Time']);
                    $formattedTime = $appointmentTime->format('M j, Y g:i A');
                    $statusClass = 'status-' . $row['Status'];
                    
                    echo "<tr>
                            <td>".escape($row['AppointmentID'])."</td>
                            <td>
                                <div class='client-info'>
                                    <div class='client-avatar'>".strtoupper(substr(escape($row['UserName']), 0, 1))."</div>
                                    <div>
                                        <div class='client-name'>".escape($row['UserName'])."</div>
                                    </div>
                                </div>
                            </td>
                            <td><a href='tel:".escape($row['UserPhone'])."' class='client-phone'>".escape($row['UserPhone'])."</a></td>
                            <td>".escape($row['ForName'])."</td>
                            <td>".escape($row['ForAge'])."</td>
                            <td>".escape($row['Type'])."</td>
                            <td>".escape($formattedTime)."</td>
                            <td>".escape($row['Duration'])." minutes</td>
                            <td>
                                <form method='POST' action='update_appointment_status_b.php' class='inline-form'>
                                    <input type='hidden' name='AppointmentID' value='".escape($row['AppointmentID'])."'>
                                    <input type='hidden' name='redirect' value='barber_dashboard.php?view=appointments&search=$searchParam&filter=$filter&status=$status&date=$date&page=$page'>
                                    <select name='Status' onchange='this.form.submit()'>
                                        <option value='scheduled' ".($row['Status']=='scheduled'?'selected':'').">Scheduled</option>
                                        <option value='confirmed' ".($row['Status']=='confirmed'?'selected':'').">Confirmed</option>
                                        <option value='in progress' ".($row['Status']=='in progress'?'selected':'').">In Progress</option>
                                        <option value='completed' ".($row['Status']=='completed'?'selected':'').">Completed</option>
                                        <option value='cancelled' ".($row['Status']=='cancelled'?'selected':'').">Cancelled</option>
                                    </select>
                                </form>
                            </td>
                            <td class='barber-highlight'>".formatCurrency($row['Cost'])."</td>
                            <td>
                                <div class='action-buttons'>
                                    <a href='view_appointment_b.php?id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&filter=$filter&status=$status&date=$date&page=$page' class='btn btn-sm btn-primary'>View</a>
                                    <a href='tel:".escape($row['UserPhone'])."' class='btn btn-sm btn-success'>Call</a>
                                </div>
                            </td>
                          </tr>";
                }
                echo "</table>";
                echo "</div>";
                displayPagination($totalPages, $page, 'appointments', $searchParam, $filter, $status, $date);
                break;

            case 'services':
                $where = "bs.BarberID = ? AND (LOWER(s.Name) LIKE ? OR LOWER(s.Description) LIKE ?) AND bs.IsDeleted = 0";
                $params = [$barberID, $searchLike, $searchLike];
                $types = "iss";
                $totalRecords = getRecordCount($conn, 'BarberServices bs 
                                        LEFT JOIN Services s ON bs.ServicesID = s.ServicesID', 
                                        $where, $params, $types);
                $totalPages = ceil($totalRecords / $limit);
                
                // Only show "Add Service" if allowed
                if (!empty($features['allow services'])) {
                    echo '<a href="add_barber_service.php?barberID='.$barberID.'&view=services&search='.$searchParam.'" class="add-btn">
                            <span>+</span> Add Service
                          </a>';
                }

                echo "<h2>My Services (Total: $totalRecords)</h2>";
                
                $stmt = $conn->prepare("SELECT bs.*, s.Name, s.Description, s.Price, s.Time 
                                        FROM BarberServices bs 
                                        LEFT JOIN Services s ON bs.ServicesID = s.ServicesID 
                                        WHERE $where ORDER BY s.Name LIMIT ? OFFSET ?");
                $params[] = $limit;
                $params[] = $offset;
                $types .= "ii";
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    echo "<p>No services found.";
                    if (!empty($features['allow services'])) {
                        echo " <a href='add_barber_service.php?barberID=$barberID'>Add your first service</a>";
                    }
                    echo "</p>";
                    break;
                }
                
                echo "<div class='table-container'>";
                echo "<table class='data-table'>
                        <tr>
                            <th>Service</th><th>Description</th><th>Price</th><th>Duration</th><th>Actions</th>
                        </tr>";
                while ($row = $result->fetch_assoc()) {
                    $descPreview = strlen($row['Description']) > 50 ? substr($row['Description'], 0, 50) . '...' : $row['Description'];

                    echo "<tr>
                            <td class='barber-highlight'>".escape($row['Name'])."</td>
                            <td title='".escape($row['Description'])."'>".escape($descPreview)."</td>
                            <td class='barber-highlight'>".formatCurrency($row['Price'])."</td>
                            <td>".escape($row['Time'])." minutes</td>
                            <td>
                                <div class='action-buttons'>";
                    
                    // Show buttons only if allow services is true
                    if (!empty($features['allow services'])) {
                        echo "<a href='edit_barber_service.php?id=".escape($row['BarberServiceID'])."&view=services&search=$searchParam&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
                        echo "<a href='remove_barber_service.php?id=".escape($row['BarberServiceID'])."&view=services&search=$searchParam&page=$page' onclick='return confirm(\"Remove this service from your offerings?\")' class='btn btn-sm btn-danger'>Remove</a>";
                    } else {
                        echo "<span class='text-light'>-</span>";
                    }

                    echo "  </div>
                            </td>
                          </tr>";
                }
                echo "</table>";
                echo "</div>";
                displayPagination($totalPages, $page, 'services', $searchParam);
                break;

            case 'workinghours':
                echo "<div class='availability-settings'>";
                echo "<h2>My Working Hours</h2>";
                
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
                
                echo "<form method='POST' action='update_working_hours.php' class='availability-form'>";
                echo "<input type='hidden' name='BarberID' value='$barberID'>";
                echo "<input type='hidden' name='redirect' value='barber_dashboard.php?view=workinghours'>";
                
                echo "<div class='availability-grid'>";
                
                foreach ($days as $dayNum => $dayName) {
                    $hour = $workingHours[$dayNum] ?? null;
                    $isWorking = $hour ? 'checked' : '';
                    $startTime = $hour ? $hour['StartTime'] : '09:00';
                    $endTime = $hour ? $hour['EndTime'] : '17:00';
                    
                    echo "<div class='availability-day'>
                            <div class='day-name'>$dayName</div>
                            <div class='time-inputs'>
                                <input type='time' name='startTime[$dayNum]' value='$startTime' class='form-control' $isWorking>
                                <span>to</span>
                                <input type='time' name='endTime[$dayNum]' value='$endTime' class='form-control' $isWorking>
                            </div>
                            <div class='working-toggle'>
                                <input type='checkbox' name='workingDays[]' value='$dayNum' id='day$dayNum' $isWorking>
                                <label for='day$dayNum'>Working</label>
                            </div>
                          </div>";
                }
                
                echo "</div>";
                echo "<br><button type='submit' class='btn btn-primary'>Save Working Hours</button>";
                echo "</form>";
                echo "</div>";

                // Fetch current unavailability with date filter
                $unavailabilityWhere = "BarberID = ?";
                $unavailabilityParams = [$barberID];
                $unavailabilityTypes = "i";
                
                if ($date) {
                    $unavailabilityWhere .= " AND Date = ?";
                    $unavailabilityParams[] = $date;
                    $unavailabilityTypes .= "s";
                }
                
                $stmt = $conn->prepare("SELECT * FROM BarberUnavailability WHERE $unavailabilityWhere ORDER BY Date DESC, StartTime");
                $stmt->bind_param($unavailabilityTypes, ...$unavailabilityParams);
                $stmt->execute();
                $result = $stmt->get_result();
                $unavailability = $result->fetch_all(MYSQLI_ASSOC);

                echo "<h3>Mark Unavailability</h3>";
                echo "<form method='POST' action='update_unavailability.php' class='unavailability-form'>";
                echo "<input type='hidden' name='BarberID' value='$barberID'>";
                echo "<input type='hidden' name='redirect' value='barber_dashboard.php?view=workinghours'>";

                echo "<div class='unavailability-inputs'>
                        <label>Date:</label>
                        <input type='date' name='Date' required>
                        <label>Start Time (optional):</label>
                        <input type='time' name='StartTime'>
                        <label>End Time (optional):</label>
                        <input type='time' name='EndTime'>
                        <label>Reason (optional):</label>
                        <input type='text' name='Reason' maxlength='255'>
                        <button type='submit' class='btn btn-warning'>Add Unavailability</button>
                      </div>";

                echo "</form>";

                if ($unavailability) {
                    echo "<h4>Existing Unavailability" . ($date ? " for " . escape($date) : "") . "</h4>";
                    echo "<table class='data-table'>
                            <tr><th>Date</th><th>Start</th><th>End</th><th>Reason</th><th>Actions</th></tr>";
                    foreach ($unavailability as $u) {
                        $start = $u['StartTime'] ?? '-';
                        $end = $u['EndTime'] ?? '-';
                        $reason = htmlspecialchars($u['Reason']);
                        echo "<tr>
                                <td>{$u['Date']}</td>
                                <td>{$start}</td>
                                <td>{$end}</td>
                                <td>{$reason}</td>
                                <td>
                                    <form method='POST' action='delete_unavailability.php' style='display:inline'>
                                        <input type='hidden' name='UnavailabilityID' value='{$u['UnavailabilityID']}'>
                                        <input type='hidden' name='redirect' value='barber_dashboard.php?view=workinghours'>
                                        <button type='submit' onclick='return confirm(\"Remove this unavailability?\")' class='btn btn-sm btn-danger'>Remove</button>
                                    </form>
                                </td>
                              </tr>";
                    }
                    echo "</table>";
                }
                break;

            case 'profile':
                echo "<div class='profile-management'>";
                echo "<h2>My Profile</h2>";
                
                // Get barber profile
                $stmt = $conn->prepare("SELECT b.*, u.Email, u.Number FROM Barber b 
                                        LEFT JOIN User u ON b.UserID = u.UserID 
                                        WHERE b.BarberID = ?");
                $stmt->bind_param("i", $barberID);
                $stmt->execute();
                $result = $stmt->get_result();
                $barber = $result->fetch_assoc();
                
                if (!$barber) {
                    echo "<p>Profile not found.</p>";
                    break;
                }
                
                echo "<form method='POST' action='update_barber_profile.php' class='profile-form' enctype='multipart/form-data'>";
                echo "<input type='hidden' name='BarberID' value='$barberID'>";
                echo "<input type='hidden' name='redirect' value='barber_dashboard.php?view=profile'>";
                
                // Display current image if exists
                $currentImg = !empty($barber['ImgUrl']) ? $barber['ImgUrl'] : 'Img/default-staff.jpg';

                echo "<div class='form-group'>
                        <label class='form-label'>Profile Image:</label>
                        <div class='current-image'>
                            <img src='".escape($currentImg)."' alt='Profile Image' style='max-width:150px; max-height:150px; border-radius:8px;'>
                        </div>
                        <input type='file' name='ImgFile' accept='image/*' class='form-control'>
                        <small>Upload a new image to replace the current one.</small>
                      </div>";

                echo "<div class='form-group'>
                        <label class='form-label'>Name:</label>
                        <input type='text' name='Name' class='form-control' value='".escape($barber['Name'])."' required>
                      </div>";
                
                echo "<div class='form-group'>
                        <label class='form-label'>Bio:</label>
                        <textarea name='Bio' class='form-control' rows='4' placeholder='Tell clients about your experience and specialties...'>".escape($barber['Bio'])."</textarea>
                      </div>";
                
                echo "<button type='submit' class='btn btn-primary'>Update Profile</button>";
                echo "</form>";
                echo "</div>";
                break;

            case 'reviews':
                // Build WHERE clause for reviews
                $where = "a.BarberID = ? AND (LOWER(u.Name) LIKE ? OR LOWER(r.Comment) LIKE ?) AND r.ReviewID IS NOT NULL";
                $params = [$barberID, $searchLike, $searchLike];
                $types = "iss";
                
                // Add rating filter
                if ($rating) {
                    $where .= " AND r.Rating >= ?";
                    $params[] = (int)$rating;
                    $types .= "i";
                }
                
                // Add time filter
                if ($filter === 'week') {
                    $where .= " AND r.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                } elseif ($filter === 'month') {
                    $where .= " AND r.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                } elseif ($filter === 'year') {
                    $where .= " AND r.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                }
                
                $totalRecords = getRecordCount($conn, 'Appointment a 
                                        LEFT JOIN Reviews r ON a.ReviewID = r.ReviewID
                                        LEFT JOIN User u ON a.UserID = u.UserID', 
                                        $where, $params, $types);
                $totalPages = ceil($totalRecords / $limit);
                
                echo "<h2>My Reviews (Total: $totalRecords)</h2>";
                
                $stmt = $conn->prepare("SELECT r.*, u.Name AS UserName, a.AppointmentID, a.Type AS ServiceType 
                                        FROM Appointment a 
                                        LEFT JOIN Reviews r ON a.ReviewID = r.ReviewID
                                        LEFT JOIN User u ON a.UserID = u.UserID 
                                        WHERE $where 
                                        ORDER BY r.CreatedAt DESC LIMIT ? OFFSET ?");
                $params[] = $limit;
                $params[] = $offset;
                $types .= "ii";
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    echo "<p>No reviews found.</p>";
                    break;
                }
                
                echo "<div class='table-container'>";
                echo "<table class='data-table'>
                        <tr>
                            <th>Client</th><th>Service</th><th>Rating</th><th>Comment</th><th>Date</th>
                        </tr>";
                while ($row = $result->fetch_assoc()) {
                    $stars = str_repeat('★', $row['Rating']) . str_repeat('☆', 5 - $row['Rating']);
                    $commentPreview = strlen($row['Comment']) > 100 ? substr($row['Comment'], 0, 100) . '...' : $row['Comment'];
                    $ratingColor = $row['Rating'] >= 4 ? 'barber-highlight' : ($row['Rating'] >= 3 ? 'text-light' : 'text-warning');
                    
                    echo "<tr>
                            <td>
                                <div class='client-info'>
                                    <div class='client-avatar'>".strtoupper(substr(escape($row['UserName']), 0, 1))."</div>
                                    <div class='client-name'>".escape($row['UserName'])."</div>
                                </div>
                            </td>
                            <td>".escape($row['ServiceType'])."</td>
                            <td class='$ratingColor' title='".$row['Rating']."/5'>".$stars."</td>
                            <td title='".escape($row['Comment'])."'>".escape($commentPreview)."</td>
                            <td>".escape($row['CreatedAt'])."</td>
                          </tr>";
                }
                echo "</table>";
                echo "</div>";
                displayPagination($totalPages, $page, 'reviews', $searchParam, $filter, '', '', '', $rating);
                break;

            case 'products':
                if (!($features['allow products'] ?? false)) {
                    echo "<div class='message warning'>You are not allowed to manage products.</div>";
                    break;
                }

                // Build WHERE clause for products
                $where = "(LOWER(p.Name) LIKE ? OR LOWER(p.Category) LIKE ?) AND p.IsDeleted = 0";
                $params = [$searchLike, $searchLike];
                $types = "ss";

                // Add category filter
                if ($category) {
                    $where .= " AND p.Category = ?";
                    $params[] = $category;
                    $types .= "s";
                }

                // Add stock filter
                if ($filter === 'in_stock') {
                    $where .= " AND p.Stock > 0";
                } elseif ($filter === 'low_stock') {
                    $where .= " AND p.Stock > 0 AND p.Stock < 10";
                } elseif ($filter === 'out_of_stock') {
                    $where .= " AND p.Stock = 0";
                }

                // Count total products for pagination
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Products p WHERE $where");
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $totalRecords = $stmt->get_result()->fetch_assoc()['total'];
                $totalPages = ceil($totalRecords / $limit);

                echo '<a href="add_product.php?view=products&search='.$searchParam.'&category='.urlencode($category).'&filter='.$filter.'" class="add-btn">
                        <span>+</span> Add Product
                      </a>';
                echo "<h2>All Products (Total: $totalRecords)</h2>";

                // Fetch products
                $stmt = $conn->prepare("SELECT * FROM Products p WHERE $where ORDER BY p.Name LIMIT ? OFFSET ?");
                $params[] = $limit;
                $params[] = $offset;
                $types .= "ii";
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    echo "<p>No products found.</p>";
                    break;
                }

                echo "<div class='table-container'>";
                echo "<table class='data-table'>
                        <tr>
                            <th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th>
                        </tr>";
                while ($row = $result->fetch_assoc()) {
                    $stockClass = $row['Stock'] == 0 ? 'out-of-stock' : ($row['Stock'] < 10 ? 'low-stock' : 'in-stock');
                    echo "<tr>
                            <td class='barber-highlight'>".escape($row['Name'])."</td>
                            <td>".escape($row['Category'])."</td>
                            <td class='barber-highlight'>".formatCurrency($row['Price'])."</td>
                            <td class='$stockClass'>".escape($row['Stock'])."</td>
                            <td>
                                <div class='action-buttons'>
                                    <a href='edit_product.php?id=".escape($row['ProductID'])."&view=products&search=$searchParam&category=".urlencode($category)."&filter=$filter&page=$page' class='btn btn-sm btn-primary'>Edit</a>
                                </div>
                            </td>
                          </tr>";
                }
                echo "</table>";
                echo "</div>";

                displayPagination($totalPages, $page, 'products', $searchParam, $filter, '', '', $category);
                break;

            default:
                // Overview - Show statistics for the barber
                echo "<h2>Dashboard Overview</h2>";
                
                // Today's appointments - make this clickable
                $today = date('Y-m-d');
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Appointment 
                                       WHERE BarberID = ? AND DATE(Time) = ? AND Status IN ('scheduled', 'confirmed')");
                $stmt->bind_param("is", $barberID, $today);
                $stmt->execute();
                $result = $stmt->get_result();
                $todayAppointments = $result->fetch_assoc()['count'];
                
                // Weekly appointments
                $weekStart = date('Y-m-d', strtotime('monday this week'));
                $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Appointment 
                                       WHERE BarberID = ? AND DATE(Time) BETWEEN ? AND ?");
                $stmt->bind_param("iss", $barberID, $weekStart, $weekEnd);
                $stmt->execute();
                $result = $stmt->get_result();
                $weekAppointments = $result->fetch_assoc()['count'];
                
                
                // Average rating
                $stmt = $conn->prepare("SELECT AVG(r.Rating) as avg_rating FROM Appointment a 
                                       LEFT JOIN Reviews r ON a.ReviewID = r.ReviewID 
                                       WHERE a.BarberID = ? AND r.Rating IS NOT NULL");
                $stmt->bind_param("i", $barberID);
                $stmt->execute();
                $result = $stmt->get_result();
                $avgRating = $result->fetch_assoc()['avg_rating'] ?? 0;
                
                
                echo "<div class='barber-stats-grid'>";
                // Make Today's Appointments clickable
                echo "<a href='?view=appointments&filter=today' class='barber-stat-card-link'>
                        <div class='barber-stat-card'>
                            <div class='barber-stat-value'>$todayAppointments</div>
                            <div class='barber-stat-label'>Today's Appointments</div>
                        </div>
                      </a>";
                
                echo "<div class='barber-stat-card'>
                        <div class='barber-stat-value'>$weekAppointments</div>
                        <div class='barber-stat-label'>This Week</div>
                      </div>";
                
                
                echo "<div class='barber-stat-card'>
                        <div class='barber-stat-value'>".number_format($avgRating, 1)."</div>
                        <div class='barber-stat-label'>Average Rating</div>
                      </div>";
                echo "</div>";
                
                // Upcoming appointments
                echo "<div class='today-appointments'>";
                echo "<h3>Upcoming Appointments</h3>";
                $stmt = $conn->prepare("SELECT a.*, u.Name AS UserName FROM Appointment a 
                                       LEFT JOIN User u ON a.UserID = u.UserID 
                                       WHERE a.BarberID = ? AND a.Time >= NOW() 
                                       AND a.Status IN ('scheduled', 'confirmed')
                                       ORDER BY a.Time ASC LIMIT 5");
                $stmt->bind_param("i", $barberID);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<div class='appointment-list'>";
                    while ($row = $result->fetch_assoc()) {
                        $time = new DateTime($row['Time']);
                        $statusClass = 'status-' . str_replace(' ', '-', $row['Status']);
                        echo "<a href='view_appointment_b.php?id=".escape($row['AppointmentID'])."' class='appointment-item-link'>
                                <div class='appointment-item {$row['Status']}'>
                                    <div class='appointment-time'>".$time->format('g:i A')."</div>
                                    <div class='appointment-client'>".escape($row['UserName'])."</div>
                                    <div class='appointment-service'>".escape($row['Type'])."</div>
                                    <div class='appointment-status $statusClass'>".escape($row['Status'])."</div>
                                </div>
                              </a>";
                    }
                    echo "</div>";
                } else {
                    echo "<p>No upcoming appointments.</p>";
                }
                echo "</div>";
                break;
        }

        echo "
        <script>
        function confirmAction(message) {
            return confirm(message || 'Are you sure?');
        }

        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => msg.style.display = 'none');
        }, 5000);
        </script>
        ";
        ?>

    </div>
</div>

<?php
include 'footer.php';
?>
