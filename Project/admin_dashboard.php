<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'header.php';

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

<h1>Admin Dashboard</h1>

<nav>
    <a href="?view=overview">Overview</a> |
    <a href="?view=users">Users</a> |
    <a href="?view=barbers">Barbers</a> |
    <a href="?view=appointments">Appointments</a> |
    <a href="?view=orders">Orders</a> |
    <a href="?view=products">Products</a> |
    <a href="?view=services">Services</a> |
    <a href="?view=reviews">Reviews</a> |
    <a href="?view=contacts">Contacts</a>
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
    case 'users':
        $where = "(LOWER(Name) LIKE ? OR LOWER(Email) LIKE ?)";
        $params = [$searchLike, $searchLike];
        $types = "ss";
        $totalRecords = getRecordCount($conn, 'User', $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo '<a href="add_user.php?view=users&search='.$searchParam.'">+ Add Client</a><br><br>';
        $stmt = $conn->prepare("SELECT * FROM User WHERE $where ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Users (Total: $totalRecords)</h2>";
        
        if ($result->num_rows === 0) {
            echo "<p>No users found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th><th>Number</th><th>Role</th><th>CreatedAt</th><th>Status</th><th>Actions</th>
                </tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['UserID'])."</td>
                    <td>".escape($row['Name'])."</td>
                    <td>".escape($row['Email'])."</td>
                    <td>".escape($row['Number'])."</td>
                    <td>
                        <form method='POST' action='update_user_role.php' style='display:inline;'>
                            <input type='hidden' name='UserID' value='".escape($row['UserID'])."'>
                            <input type='hidden' name='redirect' value='admin_dashboard.php?view=users&search=$searchParam&page=$page'>
                            <select name='Role' onchange='this.form.submit()'>
                                <option value='client' ".($row['Role']=='client'?'selected':'').">Client</option>
                                <option value='admin' ".($row['Role']=='admin'?'selected':'').">Admin</option>
                                <option value='barber' ".($row['Role']=='barber'?'selected':'').">Barber</option>
                            </select>
                        </form>
                    </td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=User&id=".escape($row['UserID'])."&view=users&search=$searchParam&page=$page'>Restore</a>";
            } else {
                echo "<a href='edit_user.php?UserID=".escape($row['UserID'])."&view=users&search=$searchParam&page=$page'>Edit</a> | 
                      <a href='soft_delete.php?table=User&id=".escape($row['UserID'])."&view=users&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'users', $searchParam);
        break;

    case 'barbers':
        $where = "LOWER(Barber.Name) LIKE ?";
        $params = [$searchLike];
        $types = "s";
        $totalRecords = getRecordCount($conn, 'Barber', $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo '<a href="add_barber.php?view=barbers&search='.$searchParam.'">+ Add Barber</a><br><br>';
        $stmt = $conn->prepare("SELECT Barber.*, User.Name AS OwnerName, User.Email FROM Barber 
                                LEFT JOIN User ON Barber.UserID = User.UserID 
                                WHERE $where ORDER BY Barber.CreatedAt DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Barbers (Total: $totalRecords)</h2>";
        
        if ($result->num_rows === 0) {
            echo "<p>No barbers found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Name</th><th>Owner</th><th>Email</th><th>Bio</th><th>CreatedAt</th><th>Status</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            $bioPreview = strlen($row['Bio']) > 50 ? substr($row['Bio'], 0, 50) . '...' : $row['Bio'];
            echo "<tr>
                    <td>".escape($row['BarberID'])."</td>
                    <td>".escape($row['Name'])."</td>
                    <td>".escape($row['OwnerName'])."</td>
                    <td>".escape($row['Email'])."</td>
                    <td title='".escape($row['Bio'])."'>".escape($bioPreview)."</td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Barber&id=".escape($row['BarberID'])."&view=barbers&search=$searchParam&page=$page'>Restore</a>";
            } else {
                echo "<a href='edit_barber.php?id=".escape($row['BarberID'])."&view=barbers&search=$searchParam&page=$page'>Edit</a> | 
                      <a href='soft_delete.php?table=Barber&id=".escape($row['BarberID'])."&view=barbers&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'barbers', $searchParam);
        break;

    case 'appointments':
        $where = "(LOWER(u.Name) LIKE ? OR LOWER(b.Name) LIKE ? OR LOWER(a.ForName) LIKE ?)";
        $params = [$searchLike, $searchLike, $searchLike];
        $types = "sss";
        $totalRecords = getRecordCount($conn, 'Appointment a 
                                LEFT JOIN User u ON a.UserID = u.UserID 
                                LEFT JOIN Barber b ON a.BarberID = b.BarberID', 
                                $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo '<a href="add_appointment.php?view=appointments&search='.$searchParam.'">+ Add Appointment</a><br><br>';
        $stmt = $conn->prepare("SELECT a.*, u.Name AS UserName, b.Name AS BarberName FROM Appointment a 
                                LEFT JOIN User u ON a.UserID = u.UserID 
                                LEFT JOIN Barber b ON a.BarberID = b.BarberID
                                WHERE $where ORDER BY a.Time DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Appointments (Total: $totalRecords)</h2>";
        
        if ($result->num_rows === 0) {
            echo "<p>No appointments found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>User</th><th>Barber</th><th>ForName</th><th>ForAge</th><th>Type</th><th>Time</th><th>Duration</th><th>Status</th><th>Cost</th><th>Deleted?</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['AppointmentID'])."</td>
                    <td>".escape($row['UserName'])."</td>
                    <td>".escape($row['BarberName'])."</td>
                    <td>".escape($row['ForName'])."</td>
                    <td>".escape($row['ForAge'])."</td>
                    <td>".escape($row['Type'])."</td>
                    <td>".escape($row['Time'])."</td>
                    <td>".escape($row['Duration'])." minutes</td>
                    <td>
                        <form method='POST' action='update_appointment_status.php' style='margin:0;'>
                            <input type='hidden' name='AppointmentID' value='".escape($row['AppointmentID'])."'>
                            <input type='hidden' name='redirect' value='admin_dashboard.php?view=appointments&search=$searchParam&page=$page'>
                            <select name='Status' onchange='this.form.submit()'>
                                <option value='scheduled' ".($row['Status']=='scheduled'?'selected':'').">Scheduled</option>
                                <option value='confirmed' ".($row['Status']=='confirmed'?'selected':'').">Confirmed</option>
                                <option value='completed' ".($row['Status']=='completed'?'selected':'').">Completed</option>
                                <option value='cancelled' ".($row['Status']=='cancelled'?'selected':'').">Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td>$".escape($row['Cost'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Appointment&id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&page=$page'>Restore</a>";
            } else {
                echo "<a href='edit_appointment.php?id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&page=$page'>Edit</a> | 
                      <a href='soft_delete.php?table=Appointment&id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'appointments', $searchParam);
        break;

    case 'products':
        $where = "LOWER(Name) LIKE ?";
        $params = [$searchLike];
        $types = "s";
        $totalRecords = getRecordCount($conn, 'Products', $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo '<a href="add_product.php?view=products&search='.$searchParam.'">+ Add Product</a><br><br>';
        $stmt = $conn->prepare("SELECT * FROM Products WHERE $where ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Products (Total: $totalRecords)</h2>";
        
        if ($result->num_rows === 0) {
            echo "<p>No products found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Name</th><th>Price</th><th>Category</th><th>Stock</th><th>Deleted?</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            $stockClass = $row['Stock'] == 0 ? 'out-of-stock' : ($row['Stock'] < 10 ? 'low-stock' : '');
            echo "<tr>
                    <td>".escape($row['ProductID'])."</td>
                    <td>".escape($row['Name'])."</td>
                    <td>$".escape($row['Price'])."</td>
                    <td>".escape($row['Category'])."</td>
                    <td class='$stockClass'>".escape($row['Stock'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Products&id=".escape($row['ProductID'])."&view=products&search=$searchParam&page=$page'>Restore</a>";
            } else {
                echo "<a href='edit_product.php?id=".escape($row['ProductID'])."&view=products&search=$searchParam&page=$page'>Edit</a> | 
                      <a href='soft_delete.php?table=Products&id=".escape($row['ProductID'])."&view=products&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'products', $searchParam);
        break;

    case 'services':
        $where = "LOWER(Name) LIKE ?";
        $params = [$searchLike];
        $types = "s";
        $totalRecords = getRecordCount($conn, 'Services', $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo '<a href="add_service.php?view=services&search='.$searchParam.'">+ Add Service</a><br><br>';
        $stmt = $conn->prepare("SELECT * FROM Services WHERE $where ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Services (Total: $totalRecords)</h2>";
        
        if ($result->num_rows === 0) {
            echo "<p>No services found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Time</th><th>Deleted?</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            $descPreview = strlen($row['Description']) > 50 ? substr($row['Description'], 0, 50) . '...' : $row['Description'];
            echo "<tr>
                    <td>".escape($row['ServicesID'])."</td>
                    <td>".escape($row['Name'])."</td>
                    <td title='".escape($row['Description'])."'>".escape($descPreview)."</td>
                    <td>$".escape($row['Price'])."</td>
                    <td>".escape($row['Time'])." minutes</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Services&id=".escape($row['ServicesID'])."&view=services&search=$searchParam&page=$page'>Restore</a>";
            } else {
                echo "<a href='edit_service.php?id=".escape($row['ServicesID'])."&view=services&search=$searchParam&page=$page'>Edit</a> | 
                      <a href='soft_delete.php?table=Services&id=".escape($row['ServicesID'])."&view=services&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'services', $searchParam);
        break;

    case 'orders':
        $where = "LOWER(u.Name) LIKE ?";
        $params = [$searchLike];
        $types = "s";
        $totalRecords = getRecordCount($conn, 'Orders o 
                            LEFT JOIN User u ON o.UserID = u.UserID', 
                            $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo '<a href="add_order.php?view=orders&search='.$searchParam.'">+ Add Order</a><br><br>';
        $stmt = $conn->prepare("SELECT o.*, u.Name AS UserName FROM Orders o 
                            LEFT JOIN User u ON o.UserID = u.UserID
                            WHERE $where ORDER BY o.CreatedAt DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Orders (Total: $totalRecords)</h2>";
        
        if ($result->num_rows === 0) {
            echo "<p>No orders found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>User</th><th>TotalPrice</th><th>Status</th><th>CreatedAt</th><th>Actions</th>
                </tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['OrderID'])."</td>
                    <td>".escape($row['UserName'])."</td>
                    <td>$".escape($row['TotalPrice'])."</td>
                    <td>
                        <form method='POST' action='update_order_status.php' style='margin:0;'>
                            <input type='hidden' name='OrderID' value='".escape($row['OrderID'])."'>
                            <input type='hidden' name='redirect' value='admin_dashboard.php?view=orders&search=$searchParam&page=$page'>
                            <select name='Status' onchange='this.form.submit()'>
                                <option value='Pending' ".($row['Status']=='Pending'?'selected':'').">Pending</option>
                                <option value='Processing' ".($row['Status']=='Processing'?'selected':'').">Processing</option>
                                <option value='Completed' ".($row['Status']=='Completed'?'selected':'').">Completed</option>
                                <option value='Cancelled' ".($row['Status']=='Cancelled'?'selected':'').">Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>";
            
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Orders&id=".escape($row['OrderID'])."&view=orders&search=$searchParam&page=$page'>Restore</a>";
            } else {
                echo "<a href='edit_order.php?id=".escape($row['OrderID'])."&view=orders&search=$searchParam&page=$page'>Edit</a> | 
                      <a href='soft_delete.php?table=Orders&id=".escape($row['OrderID'])."&view=orders&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            }

            echo "</td></tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'orders', $searchParam);
        break;

    case 'reviews':
        $where = "(LOWER(u.Name) LIKE ? OR LOWER(p.Name) LIKE ?)";
        $params = [$searchLike, $searchLike];
        $types = "ss";
        $totalRecords = getRecordCount($conn, 'Reviews r 
                                LEFT JOIN User u ON r.UserID = u.UserID
                                LEFT JOIN Products p ON r.ProductID = p.ProductID', 
                                $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        $stmt = $conn->prepare("SELECT r.*, u.Name AS UserName, p.Name AS ProductName FROM Reviews r 
                                LEFT JOIN User u ON r.UserID = u.UserID
                                LEFT JOIN Products p ON r.ProductID = p.ProductID
                                WHERE $where ORDER BY r.CreatedAt DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Reviews (Total: $totalRecords)</h2>";
        
        if ($result->num_rows === 0) {
            echo "<p>No reviews found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>User</th><th>Product</th><th>Rating</th><th>Comment</th><th>Status</th><th>CreatedAt</th><th>Actions</th>
                </tr>";
        while($row = $result->fetch_assoc()) {
            $commentPreview = strlen($row['Comment']) > 50 ? substr($row['Comment'], 0, 50) . '...' : $row['Comment'];
            echo "<tr>
                    <td>".escape($row['ReviewID'])."</td>
                    <td>".escape($row['UserName'])."</td>
                    <td>".escape($row['ProductName'])."</td>
                    <td>".escape($row['Rating'])."/5</td>
                    <td title='".escape($row['Comment'])."'>".escape($commentPreview)."</td>
                    <td>
                        <form method='POST' action='update_review_status.php' style='margin:0;'>
                            <input type='hidden' name='ReviewID' value='".escape($row['ReviewID'])."'>
                            <input type='hidden' name='redirect' value='admin_dashboard.php?view=reviews&search=$searchParam&page=$page'>
                            <select name='Status' onchange='this.form.submit()'>
                                <option value='pending' ".($row['Status']=='pending'?'selected':'').">Pending</option>
                                <option value='approved' ".($row['Status']=='approved'?'selected':'').">Approved</option>
                                <option value='cancelled' ".($row['Status']=='cancelled'?'selected':'').">Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>
                        <a href='edit_review.php?id=".escape($row['ReviewID'])."&view=reviews&search=$searchParam&page=$page'>Edit</a> | 
                        <a href='soft_delete.php?table=Reviews&id=".escape($row['ReviewID'])."&view=reviews&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                    </td>
                  </tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'reviews', $searchParam);
        break;

    case 'contacts':
        $where = "(LOWER(c.ContactInfo) LIKE ? OR LOWER(c.Message) LIKE ? OR LOWER(u.Name) LIKE ?)";
        $params = [$searchLike, $searchLike, $searchLike];
        $types = "sss";
        $totalRecords = getRecordCount($conn, 'Contact c 
                                LEFT JOIN User u ON c.UserID = u.UserID', 
                                $where, $params, $types);
        $totalPages = ceil($totalRecords / $limit);
        
        echo "<h2>Contacts (Total: $totalRecords)</h2>";
        $stmt = $conn->prepare("SELECT c.*, u.Name AS UserName FROM Contact c 
                                LEFT JOIN User u ON c.UserID = u.UserID 
                                WHERE $where ORDER BY c.CreatedAt DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo "<p>No contacts found.</p>";
            break;
        }
        
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>User</th><th>Message</th><th>ContactInfo</th><th>CreatedAt</th><th>Deleted?</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            $messagePreview = strlen($row['Message']) > 50 ? substr($row['Message'], 0, 50) . '...' : $row['Message'];
            $contactLink = (strpos($row['ContactInfo'], '@') !== false) 
                           ? "mailto:".escape($row['ContactInfo']) 
                           : "tel:".escape($row['ContactInfo']); 
            echo "<tr>
                    <td>".escape($row['ContactID'])."</td>
                    <td>".escape($row['UserName'])."</td>
                    <td title='".escape($row['Message'])."'>".escape($messagePreview)."</td>
                    <td><a href='{$contactLink}'>".escape($row['ContactInfo'])."</a></td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Contact&id=".escape($row['ContactID'])."&view=contacts&search=$searchParam&page=$page'>Restore</a>";
            } else {
                echo "<a href='{$contactLink}'>Contact</a> | 
                      <a href='soft_delete.php?table=Contact&id=".escape($row['ContactID'])."&view=contacts&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        displayPagination($totalPages, $page, 'contacts', $searchParam);
        break;

    default:
        include 'admin_overview_graphs.php';
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
