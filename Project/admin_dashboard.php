<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';
include 'header.php';

$view = $_GET['view'] ?? 'overview';
$search = isset($_GET['search']) ? "%" . strtolower($_GET['search']) . "%" : "%";

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
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
    <input type="text" name="search" placeholder="Search..." value="<?php echo isset($_GET['search']) ? escape($_GET['search']) : ''; ?>">
    <button type="submit">Search</button>
</form>

<?php
switch($view) {

    case 'users':
        echo '<a href="add_user.php">+ Add Client</a><br><br>';
        $stmt = $conn->prepare("SELECT * FROM User WHERE (LOWER(Name) LIKE ? OR LOWER(Email) LIKE ?)");
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Users</h2>";
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
                    <td>".escape($row['Role'])."</td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=User&id=".escape($row['UserID'])."'>Restore</a>";
            } else {
                echo "<a href='edit_user.php?UserID=".escape($row['UserID'])."'>Edit</a> | 
                      <a href='soft_delete.php?table=User&id=".escape($row['UserID'])."'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        break;

    case 'barbers':
        echo '<a href="add_barber.php">+ Add Barber</a><br><br>';
        $stmt = $conn->prepare("SELECT Barber.*, User.Name AS OwnerName, User.Email FROM Barber 
                                LEFT JOIN User ON Barber.UserID = User.UserID 
                                WHERE LOWER(Barber.Name) LIKE ?");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Barbers</h2>";
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Name</th><th>Owner</th><th>Email</th><th>Bio</th><th>CreatedAt</th><th>Status</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['BarberID'])."</td>
                    <td>".escape($row['Name'])."</td>
                    <td>".escape($row['OwnerName'])."</td>
                    <td>".escape($row['Email'])."</td>
                    <td>".escape($row['Bio'])."</td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Barber&id=".escape($row['BarberID'])."'>Restore</a>";
            } else {
                echo "<a href='edit_barber.php?id=".escape($row['BarberID'])."'>Edit</a> | 
                      <a href='soft_delete.php?table=Barber&id=".escape($row['BarberID'])."'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        break;

    case 'appointments':
        echo '<a href="add_appointment.php">+ Add Appointment</a><br><br>';
        $stmt = $conn->prepare("SELECT a.*, u.Name AS UserName, b.Name AS BarberName FROM Appointment a 
                                LEFT JOIN User u ON a.UserID = u.UserID 
                                LEFT JOIN Barber b ON a.BarberID = b.BarberID
                                WHERE LOWER(u.Name) LIKE ? OR LOWER(b.Name) LIKE ?");
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Appointments</h2>";
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
                    <td>".escape($row['Duration'])."</td>
                    <td>".escape($row['Status'])."</td>
                    <td>".escape($row['Cost'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Appointment&id=".escape($row['AppointmentID'])."'>Restore</a>";
            } else {
                echo "<a href='edit_appointment.php?id=".escape($row['AppointmentID'])."'>Edit</a> | 
                      <a href='soft_delete.php?table=Appointment&id=".escape($row['AppointmentID'])."'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        break;

    case 'products':
        echo '<a href="add_product.php">+ Add Product</a><br><br>';
        $stmt = $conn->prepare("SELECT * FROM Products WHERE LOWER(Name) LIKE ?");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Products</h2>";
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Name</th><th>Price</th><th>Category</th><th>Stock</th><th>Deleted?</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['ProductID'])."</td>
                    <td>".escape($row['Name'])."</td>
                    <td>".escape($row['Price'])."</td>
                    <td>".escape($row['Category'])."</td>
                    <td>".escape($row['Stock'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Products&id=".escape($row['ProductID'])."'>Restore</a>";
            } else {
                echo "<a href='edit_product.php?id=".escape($row['ProductID'])."'>Edit</a> | 
                      <a href='soft_delete.php?table=Products&id=".escape($row['ProductID'])."'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        break;

    case 'services':
        echo '<a href="add_service.php">+ Add Service</a><br><br>';
        $stmt = $conn->prepare("SELECT * FROM Services WHERE LOWER(Name) LIKE ?");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Services</h2>";
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Time</th><th>Deleted?</th><th>Actions</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['ServicesID'])."</td>
                    <td>".escape($row['Name'])."</td>
                    <td>".escape($row['Description'])."</td>
                    <td>".escape($row['Price'])."</td>
                    <td>".escape($row['Time'])."</td>
                    <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                    <td>";
            if ($row['IsDeleted']) {
                echo "<a href='restore.php?table=Services&id=".escape($row['ServicesID'])."'>Restore</a>";
            } else {
                echo "<a href='edit_service.php?id=".escape($row['ServicesID'])."'>Edit</a> | 
                      <a href='soft_delete.php?table=Services&id=".escape($row['ServicesID'])."'>Delete</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
        break;

    case 'orders':
        echo '<a href="add_order.php">+ Add Order</a><br><br>';
        $stmt = $conn->prepare("SELECT o.*, u.Name AS UserName FROM Orders o 
                                LEFT JOIN User u ON o.UserID = u.UserID
                                WHERE LOWER(u.Name) LIKE ?");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Orders</h2>";
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>User</th><th>TotalPrice</th><th>Status</th><th>CreatedAt</th><th>Actions</th>
                </tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['OrderID'])."</td>
                    <td>".escape($row['UserName'])."</td>
                    <td>".escape($row['TotalPrice'])."</td>
                    <td>
                        <form method='POST' action='update_order_status.php' style='margin:0;'>
                            <input type='hidden' name='OrderID' value='".escape($row['OrderID'])."'>
                            <select name='Status' onchange='this.form.submit()'>
                                <option value='Pending' ".($row['Status']=='Pending'?'selected':'').">Pending</option>
                                <option value='Processing' ".($row['Status']=='Processing'?'selected':'').">Processing</option>
                                <option value='Completed' ".($row['Status']=='Completed'?'selected':'').">Completed</option>
                                <option value='Cancelled' ".($row['Status']=='Cancelled'?'selected':'').">Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>
                        <a href='edit_order.php?id=".escape($row['OrderID'])."'>Edit</a> | 
                        <a href='soft_delete.php?table=Orders&id=".escape($row['OrderID'])."'>Delete</a>
                    </td>
                  </tr>";
        }
        echo "</table>";
        break;

    case 'reviews':
        $stmt = $conn->prepare("SELECT r.*, u.Name AS UserName, p.Name AS ProductName FROM Reviews r 
                                LEFT JOIN User u ON r.UserID = u.UserID
                                LEFT JOIN Products p ON r.ProductID = p.ProductID
                                WHERE LOWER(u.Name) LIKE ? OR LOWER(p.Name) LIKE ?");
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h2>Reviews</h2>";
        echo "<table border='1'>
                <tr>
                    <th>ID</th><th>User</th><th>Product</th><th>Rating</th><th>Comment</th><th>CreatedAt</th><th>Actions</th>
                </tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".escape($row['ReviewID'])."</td>
                    <td>".escape($row['UserName'])."</td>
                    <td>".escape($row['ProductName'])."</td>
                    <td>".escape($row['Rating'])."</td>
                    <td>".escape($row['Comment'])."</td>
                    <td>".escape($row['CreatedAt'])."</td>
                    <td>
                        <a href='edit_review.php?id=".escape($row['ReviewID'])."'>Edit</a> | 
                        <a href='soft_delete.php?table=Reviews&id=".escape($row['ReviewID'])."'>Delete</a>
                    </td>
                  </tr>";
        }
        echo "</table>";
        break;

case 'contacts':
    echo "<h2>Contacts</h2>";
    $stmt = $conn->prepare("SELECT c.*, u.Name AS UserName FROM Contact c 
                            LEFT JOIN User u ON c.UserID = u.UserID 
                            WHERE LOWER(c.ContactInfo) LIKE ? OR LOWER(c.Message) LIKE ?");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<table border='1'>
            <tr>
                <th>ID</th><th>User</th><th>Message</th><th>ContactInfo</th><th>CreatedAt</th><th>Deleted?</th><th>Actions</th>
            </tr>";
    while ($row = $result->fetch_assoc()) {
        $contactLink = (strpos($row['ContactInfo'], '@') !== false) 
                       ? "mailto:".escape($row['ContactInfo']) 
                       : "tel:".escape($row['ContactInfo']); // if it's a phone number
        echo "<tr>
                <td>".escape($row['ContactID'])."</td>
                <td>".escape($row['UserName'])."</td>
                <td>".escape($row['Message'])."</td>
                <td><a href='{$contactLink}'>".escape($row['ContactInfo'])."</a></td>
                <td>".escape($row['CreatedAt'])."</td>
                <td>".($row['IsDeleted'] ? "Deleted" : "Active")."</td>
                <td>";
        if ($row['IsDeleted']) {
            echo "<a href='restore.php?table=Contact&id=".escape($row['ContactID'])."'>Restore</a>";
        } else {
            echo "<a href='{$contactLink}'>Contact</a> | 
                  <a href='soft_delete.php?table=Contact&id=".escape($row['ContactID'])."'>Delete</a>";
        }
        echo "</td></tr>";
    }
    echo "</table>";
    break;

    default:
        include 'admin_overview_graphs.php';
        break;
}
?>

