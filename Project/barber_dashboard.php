<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: login.php");
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
$searchParam = urlencode($search);
$searchLike = $search ? "%" . strtolower($search) . "%" : "%";

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
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

function displayPagination($totalPages, $page, $view, $searchParam) {
    if ($totalPages <= 1) return;
    
    echo "<div class='pagination'>";
    if ($page > 1) {
        echo "<a href='?view=$view&search=$searchParam&page=".($page-1)."'>Previous</a> ";
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) {
            echo "<strong>$i</strong> ";
        } else {
            echo "<a href='?view=$view&search=$searchParam&page=$i'>$i</a> ";
        }
    }
    if ($page < $totalPages) {
        echo "<a href='?view=$view&search=$searchParam&page=".($page+1)."'>Next</a>";
    }
    echo "</div>";
}
?>

<h1>Barber Dashboard</h1>

<nav>
    <a href="?view=overview">Overview</a> |
    <a href="?view=appointments">My Appointments</a> |
    <a href="?view=services">My Services</a> |
    <a href="?view=workinghours">Working Hours</a> |
    <a href="?view=profile">My Profile</a> |
    <a href="?view=reviews">My Reviews</a>
</nav>

<form method="GET">
    <input type="hidden" name="view" value="<?php echo escape($view); ?>">
    <input type="text" name="search" placeholder="Search..." value="<?php echo escape($search); ?>">
    <button type="submit">Search</button>
    <?php if ($search): ?>
        <a href="?view=<?php echo escape($view); ?>">Clear Search</a>
    <?php endif; ?>
</form>

<?php
if (isset($_GET['message'])) {
    $messageType = isset($_GET['success']) && $_GET['success'] ? 'success' : 'error';
    $message = escape($_GET['message']);
    echo "<div class='message {$messageType}'>{$message}</div>";
}

switch($view) {
    case 'appointments':
        $where = "a.BarberID = ? AND (LOWER(u.Name) LIKE ? OR LOWER(a.ForName) LIKE ? OR LOWER(a.Type) LIKE ?)";
        $params = [$barberID, $searchLike, $searchLike, $searchLike];
        $types = "isss";
        $totalRecords = getRecordCount($conn, 'Appointment a 
                                LEFT JOIN User u ON a.UserID = u.UserID', 
                                $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo "<h2>My Appointments (Total: $totalRecords)</h2>";
        
        $stmt = $conn->prepare("SELECT a.*, u.Name AS UserName, u.Number AS UserPhone 
                                FROM Appointment a 
                                LEFT JOIN User u ON a.UserID = u.UserID 
                                WHERE $where ORDER BY a.Time DESC LIMIT ? OFFSET ?");
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
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Client</th><th>Phone</th><th>For Name</th><th>Age</th><th>Service</th><th>Date & Time</th><th>Duration</th><th>Status</th><th>Cost</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            $appointmentTime = new DateTime($row['Time']);
            $formattedTime = $appointmentTime->format('M j, Y g:i A');
            
            echo "<tr>
                    <td>".escape($row['AppointmentID'])."</td>
                    <td>".escape($row['UserName'])."</td>
                    <td><a href='tel:".escape($row['UserPhone'])."'>".escape($row['UserPhone'])."</a></td>
                    <td>".escape($row['ForName'])."</td>
                    <td>".escape($row['ForAge'])."</td>
                    <td>".escape($row['Type'])."</td>
                    <td>".escape($formattedTime)."</td>
                    <td>".escape($row['Duration'])." minutes</td>
                    <td>
                        <form method='POST' action='update_appointment_status_b.php' style='margin:0;'>
                            <input type='hidden' name='AppointmentID' value='".escape($row['AppointmentID'])."'>
                            <input type='hidden' name='redirect' value='barber_dashboard.php?view=appointments&search=$searchParam&page=$page'>
                            <select name='Status' onchange='this.form.submit()'>
                                <option value='scheduled' ".($row['Status']=='scheduled'?'selected':'').">Scheduled</option>
                                <option value='confirmed' ".($row['Status']=='confirmed'?'selected':'').">Confirmed</option>
                                <option value='in progress' ".($row['Status']=='in progress'?'selected':'').">In Progress</option>
                                <option value='completed' ".($row['Status']=='completed'?'selected':'').">Completed</option>
                                <option value='cancelled' ".($row['Status']=='cancelled'?'selected':'').">Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td>$".escape($row['Cost'])."</td>
                    <td>
                        <a href='view_appointment_b.php?id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&page=$page'>View</a> |
                        <a href='tel:".escape($row['UserPhone'])."'>Call</a>
                    </td>
                  </tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'appointments', $searchParam);
        break;

    case 'services':
        $where = "bs.BarberID = ? AND (LOWER(s.Name) LIKE ? OR LOWER(s.Description) LIKE ?) AND bs.IsDeleted = 0";
        $params = [$barberID, $searchLike, $searchLike];
        $types = "iss";
        $totalRecords = getRecordCount($conn, 'BarberServices bs 
                                LEFT JOIN Services s ON bs.ServicesID = s.ServicesID', 
                                $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo '<a href="add_barber_service.php?barberID='.$barberID.'&view=services&search='.$searchParam.'">+ Add Service</a><br><br>';
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
            echo "<p>No services found. <a href='add_barber_service.php?barberID=$barberID'>Add your first service</a></p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>Service</th><th>Description</th><th>Price</th><th>Duration</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            $descPreview = strlen($row['Description']) > 50 ? substr($row['Description'], 0, 50) . '...' : $row['Description'];
            echo "<tr>
                    <td>".escape($row['Name'])."</td>
                    <td title='".escape($row['Description'])."'>".escape($descPreview)."</td>
                    <td>$".escape($row['Price'])."</td>
                    <td>".escape($row['Time'])." minutes</td>
                    <td>
                        <a href='edit_barber_service.php?id=".escape($row['BarberServiceID'])."&view=services&search=$searchParam&page=$page'>Edit</a> | 
                        <a href='remove_barber_service.php?id=".escape($row['BarberServiceID'])."&view=services&search=$searchParam&page=$page' onclick='return confirm(\"Remove this service from your offerings?\")'>Remove</a>
                    </td>
                  </tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'services', $searchParam);
        break;

    case 'workinghours':
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
        
        echo "<form method='POST' action='update_working_hours.php'>";
        echo "<input type='hidden' name='BarberID' value='$barberID'>";
        echo "<input type='hidden' name='redirect' value='barber_dashboard.php?view=workinghours'>";
        
        echo "<table border='1'>
                <tr>
                    <th>Day</th><th>Start Time</th><th>End Time</th><th>Working?</th>
                </tr>";
        
        foreach ($days as $dayNum => $dayName) {
            $hour = $workingHours[$dayNum] ?? null;
            $isWorking = $hour ? 'checked' : '';
            $startTime = $hour ? $hour['StartTime'] : '09:00';
            $endTime = $hour ? $hour['EndTime'] : '17:00';
            
            echo "<tr>
                    <td>$dayName</td>
                    <td><input type='time' name='startTime[$dayNum]' value='$startTime' $isWorking></td>
                    <td><input type='time' name='endTime[$dayNum]' value='$endTime' $isWorking></td>
                    <td><input type='checkbox' name='workingDays[]' value='$dayNum' $isWorking></td>
                  </tr>";
        }
        
        echo "</table>";
        echo "<br><button type='submit'>Save Working Hours</button>";
        echo "</form>";
        break;

    case 'profile':
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
        
        echo "<form method='POST' action='update_barber_profile.php'>";
        echo "<input type='hidden' name='BarberID' value='$barberID'>";
        echo "<input type='hidden' name='redirect' value='barber_dashboard.php?view=profile'>";
        
        echo "<table border='0' cellpadding='5'>";
        echo "<tr><td><strong>Name:</strong></td><td><input type='text' name='Name' value='".escape($barber['Name'])."' required></td></tr>";
        echo "<tr><td><strong>Email:</strong></td><td>".escape($barber['Email'])."</td></tr>";
        echo "<tr><td><strong>Phone:</strong></td><td>".escape($barber['Number'])."</td></tr>";
        echo "<tr><td><strong>Bio:</strong></td><td><textarea name='Bio' rows='4' cols='50'>".escape($barber['Bio'])."</textarea></td></tr>";
        echo "</table>";
        
        echo "<br><button type='submit'>Update Profile</button>";
        echo "</form>";
        break;

    case 'reviews':
        $where = "a.BarberID = ? AND (LOWER(u.Name) LIKE ? OR LOWER(r.Comment) LIKE ?)";
        $params = [$barberID, $searchLike, $searchLike];
        $types = "iss";
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
                                WHERE $where AND r.ReviewID IS NOT NULL 
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
        
        echo "<table border='1'>
                <tr>
                    <th>Client</th><th>Service</th><th>Rating</th><th>Comment</th><th>Date</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            $stars = str_repeat('★', $row['Rating']) . str_repeat('☆', 5 - $row['Rating']);
            $commentPreview = strlen($row['Comment']) > 100 ? substr($row['Comment'], 0, 100) . '...' : $row['Comment'];
            
            echo "<tr>
                    <td>".escape($row['UserName'])."</td>
                    <td>".escape($row['ServiceType'])."</td>
                    <td title='".$row['Rating']."/5'>".$stars."</td>
                    <td title='".escape($row['Comment'])."'>".escape($commentPreview)."</td>
                    <td>".escape($row['CreatedAt'])."</td>
                  </tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'reviews', $searchParam);
        break;

    default:
        // Overview - Show statistics for the barber
        echo "<h2>Overview</h2>";
        
        // Today's appointments
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
        
        // Monthly revenue
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $stmt = $conn->prepare("SELECT SUM(Cost) as total FROM Appointment 
                               WHERE BarberID = ? AND Status = 'completed' 
                               AND DATE(Time) BETWEEN ? AND ?");
        $stmt->bind_param("iss", $barberID, $monthStart, $monthEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthRevenue = $result->fetch_assoc()['total'] ?? 0;
        
        // Average rating
        $stmt = $conn->prepare("SELECT AVG(r.Rating) as avg_rating FROM Appointment a 
                               LEFT JOIN Reviews r ON a.ReviewID = r.ReviewID 
                               WHERE a.BarberID = ? AND r.Rating IS NOT NULL");
        $stmt->bind_param("i", $barberID);
        $stmt->execute();
        $result = $stmt->get_result();
        $avgRating = $result->fetch_assoc()['avg_rating'] ?? 0;
        
        echo "<div style='display: flex; gap: 20px; flex-wrap: wrap;'>";
        echo "<div style='border: 1px solid #ccc; padding: 20px; border-radius: 5px; text-align: center;'>";
        echo "<h3>Today's Appointments</h3>";
        echo "<p style='font-size: 24px; font-weight: bold;'>$todayAppointments</p>";
        echo "</div>";
        
        echo "<div style='border: 1px solid #ccc; padding: 20px; border-radius: 5px; text-align: center;'>";
        echo "<h3>This Week</h3>";
        echo "<p style='font-size: 24px; font-weight: bold;'>$weekAppointments</p>";
        echo "</div>";
        
        echo "<div style='border: 1px solid #ccc; padding: 20px; border-radius: 5px; text-align: center;'>";
        echo "<h3>Monthly Revenue</h3>";
        echo "<p style='font-size: 24px; font-weight: bold;'>$".number_format($monthRevenue, 2)."</p>";
        echo "</div>";
        
        echo "<div style='border: 1px solid #ccc; padding: 20px; border-radius: 5px; text-align: center;'>";
        echo "<h3>Average Rating</h3>";
        echo "<p style='font-size: 24px; font-weight: bold;'>".number_format($avgRating, 1)."/5</p>";
        echo "</div>";
        echo "</div>";
        
        // Upcoming appointments
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
            echo "<table border='1'>
                    <tr><th>Client</th><th>Service</th><th>Time</th><th>Status</th></tr>";
            while ($row = $result->fetch_assoc()) {
                $time = new DateTime($row['Time']);
                echo "<tr>
                        <td>".escape($row['UserName'])."</td>
                        <td>".escape($row['Type'])."</td>
                        <td>".$time->format('M j, g:i A')."</td>
                        <td>".escape($row['Status'])."</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No upcoming appointments.</p>";
        }
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

include 'footer.php';
?>
