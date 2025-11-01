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

// Initialize display filters for each section
$displayFilters = [
    'users' => $_GET['users_filter'] ?? 'all',
    'barbers' => $_GET['barbers_filter'] ?? 'all',
    'appointments' => $_GET['appointments_filter'] ?? 'all',
    'products' => $_GET['products_filter'] ?? 'all',
    'services' => $_GET['services_filter'] ?? 'all',
    'orders' => $_GET['orders_filter'] ?? 'all',
    'reviews' => $_GET['reviews_filter'] ?? 'all',
    'contacts' => $_GET['contacts_filter'] ?? 'all'
];

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

function displayPagination($totalPages, $page, $view, $searchParam, $displayFilters) {
    if ($totalPages <= 1) return;
    
    $filterParam = '';
    if (isset($displayFilters[$view])) {
        $filterParam = "&{$view}_filter=" . urlencode($displayFilters[$view]);
    }
    
    echo "<div class='pagination'>";
    if ($page > 1) {
        echo "<a href='?view=$view&search=$searchParam{$filterParam}&page=".($page-1)."' class='pagination-link pagination-prev'>Previous</a> ";
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) {
            echo "<span class='pagination-current'>$i</span> ";
        } else {
            echo "<a href='?view=$view&search=$searchParam{$filterParam}&page=$i' class='pagination-link'>$i</a> ";
        }
    }
    if ($page < $totalPages) {
        echo "<a href='?view=$view&search=$searchParam{$filterParam}&page=".($page+1)."' class='pagination-link pagination-next'>Next</a>";
    }
    echo "</div>";
}

function getFilterDisplayOptions($currentView, $currentFilter) {
    $options = [
        'users' => [
            'all' => 'All Users',
            'active' => 'Active Only',
            'deleted' => 'Deleted Only',
            'admin' => 'Admins',
            'barber' => 'Barbers',
            'client' => 'Clients'
        ],
        'barbers' => [
            'all' => 'All Barbers',
            'active' => 'Active Only',
            'deleted' => 'Deleted Only'
        ],
        'appointments' => [
            'all' => 'All Appointments',
            'scheduled' => 'Scheduled',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'active' => 'Active Only',
            'deleted' => 'Deleted Only'
        ],
        'products' => [
            'all' => 'All Products',
            'active' => 'Active Only',
            'deleted' => 'Deleted Only',
            'in_stock' => 'In Stock',
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock'
        ],
        'services' => [
            'all' => 'All Services',
            'active' => 'Active Only',
            'deleted' => 'Deleted Only'
        ],
        'orders' => [
            'all' => 'All Orders',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'active' => 'Active Only',
            'deleted' => 'Deleted Only'
        ],
        'reviews' => [
            'all' => 'All Reviews',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'cancelled' => 'Cancelled'
        ],
        'contacts' => [
            'all' => 'All Contacts',
            'active' => 'Active Only',
            'deleted' => 'Deleted Only'
        ]
    ];
    
    if (!isset($options[$currentView])) return '';
    
    $html = "<div class='display-filter'>";
    $html .= "<label class='display-filter-label'>Display: </label>";
    $html .= "<select name='{$currentView}_filter' onchange='this.form.submit()' class='display-filter-select'>";
    foreach ($options[$currentView] as $value => $label) {
        $selected = $currentFilter === $value ? 'selected' : '';
        $html .= "<option value='$value' $selected>$label</option>";
    }
    $html .= "</select>";
    $html .= "</div>";
    
    return $html;
}
?>

<div class="main-content">
    <h1 class="dashboard-title">Admin Dashboard</h1>
    <link rel="stylesheet" href="adminstyle2.css">
    
    <!-- Improved Navigation -->
    <div class="dashboard-nav">
        <ul class="dashboard-nav-links">
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'overview' ? 'active' : ''; ?>" href="?view=overview">Overview</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'users' ? 'active' : ''; ?>" href="?view=users">Users</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'barbers' ? 'active' : ''; ?>" href="?view=barbers">Barbers</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'appointments' ? 'active' : ''; ?>" href="?view=appointments">Appointments</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'orders' ? 'active' : ''; ?>" href="?view=orders">Orders</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'products' ? 'active' : ''; ?>" href="?view=products">Products</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'services' ? 'active' : ''; ?>" href="?view=services">Services</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'reviews' ? 'active' : ''; ?>" href="?view=reviews">Reviews</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'contacts' ? 'active' : ''; ?>" href="?view=contacts">Contacts</a></li>
            <li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'features' ? 'active' : ''; ?>" href="?view=features">Features</a></li>
<li class="dashboard-nav-item"><a class="dashboard-nav-link <?php echo $view === 'gallery' ? 'active' : ''; ?>" href="?view=gallery">Gallery</a></li>
        </ul>
    </div>

    <!-- Improved Search Form with Display Filters -->
    <div class="search-container">
        <form method="GET" class="search-form">
            <input type="hidden" name="view" value="<?php echo escape($view); ?>">
            <div class="search-controls">
                <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo escape($search); ?>">
                <button type="submit" class="search-button">Search</button>
                <?php if ($search): ?>
                    <a href="?view=<?php echo escape($view); ?>" class="clear-search">Clear Search</a>
                <?php endif; ?>
            </div>
            <?php 
            if ($view !== 'overview' && $view !== 'features') {
                echo getFilterDisplayOptions($view, $displayFilters[$view]);
            }
            ?>
        </form>
    </div>

    <?php
    if (isset($_GET['message'])) {
        $messageType = isset($_GET['success']) && $_GET['success'] ? 'success' : 'error';
        $message = escape($_GET['message']);
        echo "<div class='message {$messageType}'>{$message}</div>";
    }

    switch($view) {
        case 'users':
            // Build WHERE clause based on filter
            $where = "(LOWER(Name) LIKE ? OR LOWER(Email) LIKE ?)";
            $params = [$searchLike, $searchLike];
            $types = "ss";
            
            switch($displayFilters['users']) {
                case 'active':
                    $where .= " AND IsDeleted = 0";
                    break;
                case 'deleted':
                    $where .= " AND IsDeleted = 1";
                    break;
                case 'admin':
                    $where .= " AND Role = 'admin' AND IsDeleted = 0";
                    break;
                case 'barber':
                    $where .= " AND Role = 'barber' AND IsDeleted = 0";
                    break;
                case 'client':
                    $where .= " AND Role = 'client' AND IsDeleted = 0";
                    break;
            }
            
            $totalRecords = getRecordCount($conn, 'User', $where, $params, $types);
            $totalPages = ceil($totalRecords / $limit);
            
            echo '<a href="add_user.php?view=users&search='.$searchParam.'&users_filter='.$displayFilters['users'].'" class="add-btn"> <span class="add-btn-icon">+</span> Add Client </a>';
            
            $stmt = $conn->prepare("SELECT * FROM User WHERE $where ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<h2 class='section-title'>Users (Total: $totalRecords)</h2>";
            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No users found.</p>";
                break;
            }
            
            echo "<div class='table-container'>";
            echo "<table class='data-table'> <thead><tr> <th class='table-header'>ID</th><th class='table-header'>Name</th><th class='table-header'>Email</th><th class='table-header'>Number</th><th class='table-header'>Role</th><th class='table-header'>CreatedAt</th><th class='table-header'>Status</th><th class='table-header'>Actions</th> </tr></thead><tbody>";
            while($row = $result->fetch_assoc()) {
                $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
                $statusText = $row['IsDeleted'] ? "Deleted" : "Active";
                echo "<tr class='table-row'> <td class='table-cell'>".escape($row['UserID'])."</td> <td class='table-cell'>".escape($row['Name'])."</td> <td class='table-cell'>".escape($row['Email'])."</td> <td class='table-cell'>".escape($row['Number'])."</td> <td class='table-cell'> <form method='POST' action='update_user_role.php' class='inline-form'> <input type='hidden' name='UserID' value='".escape($row['UserID'])."'> <input type='hidden' name='redirect' value='admin_dashboard.php?view=users&search=$searchParam&users_filter=".$displayFilters['users']."&page=$page'> <select name='Role' onchange='this.form.submit()' class='role-select'> <option value='client' ".($row['Role']=='client'?'selected':'').">Client</option> <option value='admin' ".($row['Role']=='admin'?'selected':'').">Admin</option> <option value='barber' ".($row['Role']=='barber'?'selected':'').">Barber</option> </select> </form> </td> <td class='table-cell'>".escape($row['CreatedAt'])."</td> <td class='table-cell $statusClass'>$statusText</td> <td class='table-cell'> <div class='action-buttons'>";
                if ($row['IsDeleted']) {
                    echo "<a href='restore.php?table=User&id=".escape($row['UserID'])."&view=users&search=$searchParam&users_filter=".$displayFilters['users']."&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
                } else {
                    echo "<a href='edit_user.php?UserID=".escape($row['UserID'])."&view=users&search=$searchParam&users_filter=".$displayFilters['users']."&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
                    echo "<a href='soft_delete.php?table=User&id=".escape($row['UserID'])."&view=users&search=$searchParam&users_filter=".$displayFilters['users']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a>";
                }
                echo " </div> </td> </tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
            displayPagination($totalPages, $page, 'users', $searchParam, $displayFilters);
            break;

        case 'barbers':
            // Build WHERE clause based on filter
            $where = "LOWER(Barber.Name) LIKE ?";
            $params = [$searchLike];
            $types = "s";
            
            switch($displayFilters['barbers']) {
                case 'active':
                    $where .= " AND Barber.IsDeleted = 0";
                    break;
                case 'deleted':
                    $where .= " AND Barber.IsDeleted = 1";
                    break;
            }
            
            $totalRecords = getRecordCount($conn, 'Barber', $where, $params, $types);
            $totalPages = ceil($totalRecords / $limit);
            
            echo '<a href="add_barber.php?view=barbers&search='.$searchParam.'&barbers_filter='.$displayFilters['barbers'].'" class="add-btn"> <span class="add-btn-icon">+</span> Add Barber </a>';
            
            $stmt = $conn->prepare("SELECT Barber.*, User.Name AS OwnerName, User.Email FROM Barber LEFT JOIN User ON Barber.UserID = User.UserID WHERE $where ORDER BY Barber.CreatedAt DESC LIMIT ? OFFSET ?");
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<h2 class='section-title'>Barbers (Total: $totalRecords)</h2>";
            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No barbers found.</p>";
                break;
            }
            
            echo "<div class='table-container'>";
            echo "<table class='data-table'> <thead><tr> <th class='table-header'>ID</th><th class='table-header'>Name</th><th class='table-header'>Owner</th><th class='table-header'>Email</th><th class='table-header'>Bio</th><th class='table-header'>CreatedAt</th><th class='table-header'>Status</th><th class='table-header'>Actions</th> </tr></thead><tbody>";
            while ($row = $result->fetch_assoc()) {
                $bioPreview = strlen($row['Bio']) > 50 ? substr($row['Bio'], 0, 50) . '...' : $row['Bio'];
                $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
                $statusText = $row['IsDeleted'] ? "Deleted" : "Active";
                echo "<tr class='table-row'> <td class='table-cell'>".escape($row['BarberID'])."</td> <td class='table-cell'>".escape($row['Name'])."</td> <td class='table-cell'>".escape($row['OwnerName'])."</td> <td class='table-cell'>".escape($row['Email'])."</td> <td class='table-cell bio-cell' title='".escape($row['Bio'])."'>".escape($bioPreview)."</td> <td class='table-cell'>".escape($row['CreatedAt'])."</td> <td class='table-cell $statusClass'>$statusText</td> <td class='table-cell'> <div class='action-buttons'>";
                if ($row['IsDeleted']) {
                    echo "<a href='restore.php?table=Barber&id=".escape($row['BarberID'])."&view=barbers&search=$searchParam&barbers_filter=".$displayFilters['barbers']."&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
                } else {
                    echo "<a href='edit_barber.php?id=".escape($row['BarberID'])."&view=barbers&search=$searchParam&barbers_filter=".$displayFilters['barbers']."&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
                    echo "<a href='soft_delete.php?table=Barber&id=".escape($row['BarberID'])."&view=barbers&search=$searchParam&barbers_filter=".$displayFilters['barbers']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a>";
                }
                echo " </div> </td> </tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
            displayPagination($totalPages, $page, 'barbers', $searchParam, $displayFilters);
            break;

        case 'appointments':
            // Build WHERE clause based on filter
            $where = "(LOWER(u.Name) LIKE ? OR LOWER(b.Name) LIKE ? OR LOWER(a.ForName) LIKE ?)";
            $params = [$searchLike, $searchLike, $searchLike];
            $types = "sss";
            
            switch($displayFilters['appointments']) {
                case 'scheduled':
                    $where .= " AND a.Status = 'scheduled' AND a.IsDeleted = 0";
                    break;
                case 'confirmed':
                    $where .= " AND a.Status = 'confirmed' AND a.IsDeleted = 0";
                    break;
                case 'completed':
                    $where .= " AND a.Status = 'completed' AND a.IsDeleted = 0";
                    break;
                case 'cancelled':
                    $where .= " AND a.Status = 'cancelled' AND a.IsDeleted = 0";
                    break;
                case 'active':
                    $where .= " AND a.IsDeleted = 0";
                    break;
                case 'deleted':
                    $where .= " AND a.IsDeleted = 1";
                    break;
            }
            
            $totalRecords = getRecordCount($conn, 'Appointment a LEFT JOIN User u ON a.UserID = u.UserID LEFT JOIN Barber b ON a.BarberID = b.BarberID', $where, $params, $types);
            $totalPages = ceil($totalRecords / $limit);
            
            echo '<a href="add_appointment.php?view=appointments&search='.$searchParam.'&appointments_filter='.$displayFilters['appointments'].'" class="add-btn"> <span class="add-btn-icon">+</span> Add Appointment </a>';
            
            $stmt = $conn->prepare("SELECT a.*, u.Name AS UserName, b.Name AS BarberName FROM Appointment a LEFT JOIN User u ON a.UserID = u.UserID LEFT JOIN Barber b ON a.BarberID = b.BarberID WHERE $where ORDER BY a.Time DESC LIMIT ? OFFSET ?");
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<h2 class='section-title'>Appointments (Total: $totalRecords)</h2>";
            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No appointments found.</p>";
                break;
            }
            
            echo "<div class='table-container'>";
            echo "<table class='data-table'> <thead><tr> <th class='table-header'>ID</th><th class='table-header'>User</th><th class='table-header'>Barber</th><th class='table-header'>ForName</th><th class='table-header'>ForAge</th><th class='table-header'>Type</th><th class='table-header'>Time</th><th class='table-header'>Duration</th><th class='table-header'>Status</th><th class='table-header'>Cost</th><th class='table-header'>Status</th><th class='table-header'>Actions</th> </tr></thead><tbody>";
            while ($row = $result->fetch_assoc()) {
                $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
                $statusText = $row['IsDeleted'] ? "Deleted" : "Active";
                echo "<tr class='table-row'> <td class='table-cell'>".escape($row['AppointmentID'])."</td> <td class='table-cell'>".escape($row['UserName'])."</td> <td class='table-cell'>".escape($row['BarberName'])."</td> <td class='table-cell'>".escape($row['ForName'])."</td> <td class='table-cell'>".escape($row['ForAge'])."</td> <td class='table-cell'>".escape($row['Type'])."</td> <td class='table-cell'>".escape($row['Time'])."</td> <td class='table-cell'>".escape($row['Duration'])." minutes</td> <td class='table-cell'> <form method='POST' action='update_appointment_status.php' class='inline-form'> <input type='hidden' name='AppointmentID' value='".escape($row['AppointmentID'])."'> <input type='hidden' name='redirect' value='admin_dashboard.php?view=appointments&search=$searchParam&appointments_filter=".$displayFilters['appointments']."&page=$page'> <select name='Status' onchange='this.form.submit()' class='status-select'> <option value='scheduled' ".($row['Status']=='scheduled'?'selected':'').">Scheduled</option> <option value='confirmed' ".($row['Status']=='confirmed'?'selected':'').">Confirmed</option> <option value='completed' ".($row['Status']=='completed'?'selected':'').">Completed</option> <option value='cancelled' ".($row['Status']=='cancelled'?'selected':'').">Cancelled</option> </select> </form> </td> <td class='table-cell'>R".escape($row['Cost'])."</td> <td class='table-cell $statusClass'>$statusText</td> <td class='table-cell'> <div class='action-buttons'>";
                if ($row['IsDeleted']) {
                    echo "<a href='restore.php?table=Appointment&id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&appointments_filter=".$displayFilters['appointments']."&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
                } else {
                    echo "<a href='edit_appointment.php?id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&appointments_filter=".$displayFilters['appointments']."&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
                    echo "<a href='soft_delete.php?table=Appointment&id=".escape($row['AppointmentID'])."&view=appointments&search=$searchParam&appointments_filter=".$displayFilters['appointments']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a>";
                }
                echo " </div> </td> </tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
            displayPagination($totalPages, $page, 'appointments', $searchParam, $displayFilters);
            break;

        case 'products':
            $where = "LOWER(Name) LIKE ?";
            $params = [$searchLike];
            $types = "s";
            
            switch($displayFilters['products']) {
                case 'active':
                    $where .= " AND IsDeleted = 0";
                    break;
                case 'deleted':
                    $where .= " AND IsDeleted = 1";
                    break;
                case 'in_stock':
                    $where .= " AND Stock > 0 AND IsDeleted = 0";
                    break;
                case 'out_of_stock':
                    $where .= " AND Stock = 0 AND IsDeleted = 0";
                    break;
                case 'low_stock':
                    $where .= " AND Stock > 0 AND Stock < 10 AND IsDeleted = 0";
                    break;
            }
            
            $totalRecords = getRecordCount($conn, 'Products', $where, $params, $types);
            $totalPages = ceil($totalRecords / $limit);
            
            echo '<a href="add_product.php?view=products&search='.$searchParam.'&products_filter='.$displayFilters['products'].'" class="add-btn"> <span class="add-btn-icon">+</span> Add Product </a>';
            
            $stmt = $conn->prepare("SELECT * FROM Products WHERE $where ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<h2 class='section-title'>Products (Total: $totalRecords)</h2>";
            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No products found.</p>";
                break;
            }
            
            echo "<div class='table-container'>";
            echo "<table class='data-table'> <thead><tr> <th class='table-header'>ID</th><th class='table-header'>Name</th><th class='table-header'>Price</th><th class='table-header'>Category</th><th class='table-header'>Stock</th><th class='table-header'>Status</th><th class='table-header'>Actions</th> </tr></thead><tbody>";
            while ($row = $result->fetch_assoc()) {
                $stockClass = $row['Stock'] == 0 ? 'out-of-stock' : ($row['Stock'] < 10 ? 'low-stock' : 'in-stock');
                $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
                $statusText = $row['IsDeleted'] ? "Deleted" : "Active";
                echo "<tr class='table-row'> <td class='table-cell'>".escape($row['ProductID'])."</td> <td class='table-cell'>".escape($row['Name'])."</td> <td class='table-cell'>R".escape($row['Price'])."</td> <td class='table-cell'>".escape($row['Category'])."</td> <td class='table-cell $stockClass'>".escape($row['Stock'])."</td> <td class='table-cell $statusClass'>$statusText</td> <td class='table-cell'> <div class='action-buttons'>";
                if ($row['IsDeleted']) {
                    echo "<a href='restore.php?table=Products&id=".escape($row['ProductID'])."&view=products&search=$searchParam&products_filter=".$displayFilters['products']."&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
                } else {
                    echo "<a href='edit_product.php?id=".escape($row['ProductID'])."&view=products&search=$searchParam&products_filter=".$displayFilters['products']."&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
                    echo "<a href='soft_delete.php?table=Products&id=".escape($row['ProductID'])."&view=products&search=$searchParam&products_filter=".$displayFilters['products']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a>";
                }
                echo " </div> </td> </tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
            displayPagination($totalPages, $page, 'products', $searchParam, $displayFilters);
            break;

case 'services':
    $where = "LOWER(Name) LIKE ?";
    $params = [$searchLike];
    $types = "s";
    
    switch($displayFilters['services']) {
        case 'active':
            $where .= " AND IsDeleted = 0";
            break;
        case 'deleted':
            $where .= " AND IsDeleted = 1";
            break;
    }
    
    $totalRecords = getRecordCount($conn, 'Services', $where, $params, $types);
    $totalPages = ceil($totalRecords / $limit);
    
    echo '<a href="add_service.php?view=services&search='.$searchParam.'&services_filter='.$displayFilters['services'].'" class="add-btn"> <span class="add-btn-icon">+</span> Add Service </a>';
    
    $stmt = $conn->prepare("SELECT * FROM Services WHERE $where ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<h2 class='section-title'>Services (Total: $totalRecords)</h2>";
    if ($result->num_rows === 0) {
        echo "<p class='no-results'>No services found.</p>";
        break;
    }
    
    echo "<div class='table-container'>";
    echo "<table class='data-table'> <thead><tr> <th class='table-header'>ID</th><th class='table-header'>Name</th><th class='table-header'>Categories</th><th class='table-header'>Description</th><th class='table-header'>Price</th><th class='table-header'>Time</th><th class='table-header'>Status</th><th class='table-header'>Actions</th> </tr></thead><tbody>";
    while ($row = $result->fetch_assoc()) {
        $descPreview = strlen($row['Description']) > 50 ? substr($row['Description'], 0, 50) . '...' : $row['Description'];
        $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
        $statusText = $row['IsDeleted'] ? "Deleted" : "Active";
        
        // Handle JSON categories
        $categories = [];
        if (!empty($row['Category'])) {
            // If it's a JSON string, decode it
            if (is_string($row['Category']) && $row['Category'][0] === '[') {
                $categories = json_decode($row['Category'], true) ?: [];
            } 
            // If it's already an array (from json_decode), use it directly
            elseif (is_array($row['Category'])) {
                $categories = $row['Category'];
            }
            // If it's a single string (legacy data), wrap it in an array
            elseif (is_string($row['Category'])) {
                $categories = [$row['Category']];
            }
        }
        
        $categoriesDisplay = !empty($categories) ? implode(', ', array_map('escape', $categories)) : 'No categories';
        
        echo "<tr class='table-row'> 
                <td class='table-cell'>".escape($row['ServicesID'])."</td> 
                <td class='table-cell'>".escape($row['Name'])."</td> 
                <td class='table-cell categories-cell' title='".$categoriesDisplay."'>".$categoriesDisplay."</td> 
                <td class='table-cell description-cell' title='".escape($row['Description'])."'>".escape($descPreview)."</td> 
                <td class='table-cell'>R".escape($row['Price'])."</td> 
                <td class='table-cell'>".escape($row['Time'])." minutes</td> 
                <td class='table-cell $statusClass'>$statusText</td> 
                <td class='table-cell'> 
                    <div class='action-buttons'>";
        if ($row['IsDeleted']) {
            echo "<a href='restore.php?table=Services&id=".escape($row['ServicesID'])."&view=services&search=$searchParam&services_filter=".$displayFilters['services']."&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
        } else {
            echo "<a href='edit_service.php?id=".escape($row['ServicesID'])."&view=services&search=$searchParam&services_filter=".$displayFilters['services']."&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
            echo "<a href='soft_delete.php?table=Services&id=".escape($row['ServicesID'])."&view=services&search=$searchParam&services_filter=".$displayFilters['services']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a>";
        }
        echo "      </div> 
                </td> 
              </tr>";
    }
    echo "</tbody></table>";
    echo "</div>";
    displayPagination($totalPages, $page, 'services', $searchParam, $displayFilters);
    break;

        case 'orders':
            $where = "LOWER(u.Name) LIKE ?";
            $params = [$searchLike];
            $types = "s";
            
            switch($displayFilters['orders']) {
                case 'pending':
                    $where .= " AND o.Status = 'Pending' AND o.IsDeleted = 0";
                    break;
                case 'processing':
                    $where .= " AND o.Status = 'Processing' AND o.IsDeleted = 0";
                    break;
                case 'completed':
                    $where .= " AND o.Status = 'Completed' AND o.IsDeleted = 0";
                    break;
                case 'cancelled':
                    $where .= " AND o.Status = 'Cancelled' AND o.IsDeleted = 0";
                    break;
                case 'active':
                    $where .= " AND o.IsDeleted = 0";
                    break;
                case 'deleted':
                    $where .= " AND o.IsDeleted = 1";
                    break;
            }
            
            $totalRecords = getRecordCount($conn, 'Orders o LEFT JOIN User u ON o.UserID = u.UserID', $where, $params, $types);
            $totalPages = ceil($totalRecords / $limit);
            
            echo '<a href="add_order.php?view=orders&search='.$searchParam.'&orders_filter='.$displayFilters['orders'].'" class="add-btn"> <span class="add-btn-icon">+</span> Add Order </a>';
            
            // Modified query to include order items
            $stmt = $conn->prepare("
                SELECT o.*, u.Name AS UserName 
                FROM Orders o 
                LEFT JOIN User u ON o.UserID = u.UserID 
                WHERE $where 
                ORDER BY o.CreatedAt DESC 
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<h2 class='section-title'>Orders (Total: $totalRecords)</h2>";
            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No orders found.</p>";
                break;
            }
            
            echo "<div class='table-container'>";
            echo "<table class='data-table'> 
                <thead>
                    <tr> 
                        <th class='table-header'>ID</th>
                        <th class='table-header'>User</th>
                        <th class='table-header'>Products</th>
                        <th class='table-header'>TotalPrice</th>
                        <th class='table-header'>Status</th>
                        <th class='table-header'>CreatedAt</th>
                        <th class='table-header'>Actions</th> 
                    </tr>
                </thead>
                <tbody>";
            
            while($row = $result->fetch_assoc()) {
                // Fetch order items for this order
                $orderItemsStmt = $conn->prepare("
                    SELECT oi.*, p.Name AS ProductName, p.Price AS UnitPrice, p.ImgUrl 
                    FROM OrderItems oi 
                    LEFT JOIN Products p ON oi.ProductID = p.ProductID 
                    WHERE oi.OrderID = ? AND oi.IsDeleted = 0
                ");
                $orderItemsStmt->bind_param("i", $row['OrderID']);
                $orderItemsStmt->execute();
                $orderItemsResult = $orderItemsStmt->get_result();
                $orderItems = $orderItemsResult->fetch_all(MYSQLI_ASSOC);
                $orderItemsStmt->close();
                
                $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
                $statusText = $row['IsDeleted'] ? "Deleted" : "Active";
                
                echo "<tr class='table-row'> 
                    <td class='table-cell'>".escape($row['OrderID'])."</td> 
                    <td class='table-cell'>".escape($row['UserName'])."</td> 
                    <td class='table-cell'>";
                
                // Display order items
                if (!empty($orderItems)) {
                    echo "<div class='order-items'>";
                    foreach ($orderItems as $item) {
                        $itemTotal = $item['Quantity'] * $item['UnitPrice'];
                        echo "<div class='order-item'>
                                <div class='order-item-info'>
                                    <strong>".escape($item['ProductName'])."</strong>
                                    <br>
                                    <small>Qty: ".escape($item['Quantity'])." × R".number_format($item['UnitPrice'], 2)." = R".number_format($itemTotal, 2)."</small>
                                </div>
                              </div>";
                    }
                    echo "</div>";
                } else {
                    echo "<span class='no-items'>No items</span>";
                }
                
                echo "</td> 
                    <td class='table-cell'>R".escape($row['TotalPrice'])."</td> 
                    <td class='table-cell'> 
                        <form method='POST' action='update_order_status.php' class='inline-form'> 
                            <input type='hidden' name='OrderID' value='".escape($row['OrderID'])."'> 
                            <input type='hidden' name='redirect' value='admin_dashboard.php?view=orders&search=$searchParam&orders_filter=".$displayFilters['orders']."&page=$page'> 
                            <select name='Status' onchange='this.form.submit()' class='status-select'> 
                                <option value='Pending' ".($row['Status']=='Pending'?'selected':'').">Pending</option> 
                                <option value='Processing' ".($row['Status']=='Processing'?'selected':'').">Processing</option> 
                                <option value='Completed' ".($row['Status']=='Completed'?'selected':'').">Completed</option> 
                                <option value='Cancelled' ".($row['Status']=='Cancelled'?'selected':'').">Cancelled</option> 
                            </select> 
                        </form> 
                    </td> 
                    <td class='table-cell'>".escape($row['CreatedAt'])."</td> 
                    <td class='table-cell'> 
                        <div class='action-buttons'>";
                
                if ($row['IsDeleted']) {
                    echo "<a href='restore.php?table=Orders&id=".escape($row['OrderID'])."&view=orders&search=$searchParam&orders_filter=".$displayFilters['orders']."&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
                } else {
                    echo "<a href='edit_order.php?id=".escape($row['OrderID'])."&view=orders&search=$searchParam&orders_filter=".$displayFilters['orders']."&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
                    echo "<a href='soft_delete.php?table=Orders&id=".escape($row['OrderID'])."&view=orders&search=$searchParam&orders_filter=".$displayFilters['orders']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a>";
                }
                
                echo " </div> 
                    </td> 
                </tr>";
            }
            
            echo "</tbody></table>";
            echo "</div>";
            displayPagination($totalPages, $page, 'orders', $searchParam, $displayFilters);
            break;

        case 'reviews':
            $where = "(LOWER(u.Name) LIKE ? OR LOWER(p.Name) LIKE ?)";
            $params = [$searchLike, $searchLike];
            $types = "ss";
            
            switch($displayFilters['reviews']) {
                case 'pending':
                    $where .= " AND r.Status = 'pending'";
                    break;
                case 'approved':
                    $where .= " AND r.Status = 'approved'";
                    break;
                case 'cancelled':
                    $where .= " AND r.Status = 'cancelled'";
                    break;
            }
            
            $totalRecords = getRecordCount($conn, 'Reviews r LEFT JOIN User u ON r.UserID = u.UserID LEFT JOIN Products p ON r.ProductID = p.ProductID', $where, $params, $types);
            $totalPages = ceil($totalRecords / $limit);
            
            $stmt = $conn->prepare("SELECT r.*, u.Name AS UserName, p.Name AS ProductName FROM Reviews r LEFT JOIN User u ON r.UserID = u.UserID LEFT JOIN Products p ON r.ProductID = p.ProductID WHERE $where ORDER BY r.CreatedAt DESC LIMIT ? OFFSET ?");
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<h2 class='section-title'>Reviews (Total: $totalRecords)</h2>";
            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No reviews found.</p>";
                break;
            }
            
            echo "<div class='table-container'>";
            echo "<table class='data-table'> <thead><tr> <th class='table-header'>ID</th><th class='table-header'>User</th><th class='table-header'>Product</th><th class='table-header'>Rating</th><th class='table-header'>Comment</th><th class='table-header'>Status</th><th class='table-header'>CreatedAt</th><th class='table-header'>Actions</th> </tr></thead><tbody>";
            while($row = $result->fetch_assoc()) {
                $commentPreview = strlen($row['Comment']) > 50 ? substr($row['Comment'], 0, 50) . '...' : $row['Comment'];
                echo "<tr class='table-row'> <td class='table-cell'>".escape($row['ReviewID'])."</td> <td class='table-cell'>".escape($row['UserName'])."</td> <td class='table-cell'>".escape($row['ProductName'])."</td> <td class='table-cell'>".escape($row['Rating'])."/5</td> <td class='table-cell comment-cell' title='".escape($row['Comment'])."'>".escape($commentPreview)."</td> <td class='table-cell'> <form method='POST' action='update_review_status.php' class='inline-form'> <input type='hidden' name='ReviewID' value='".escape($row['ReviewID'])."'> <input type='hidden' name='redirect' value='admin_dashboard.php?view=reviews&search=$searchParam&reviews_filter=".$displayFilters['reviews']."&page=$page'> <select name='Status' onchange='this.form.submit()' class='status-select'> <option value='pending' ".($row['Status']=='pending'?'selected':'').">Pending</option> <option value='approved' ".($row['Status']=='approved'?'selected':'').">Approved</option> <option value='cancelled' ".($row['Status']=='cancelled'?'selected':'').">Cancelled</option> </select> </form> </td> <td class='table-cell'>".escape($row['CreatedAt'])."</td> <td class='table-cell'> <div class='action-buttons'> <a href='edit_review.php?id=".escape($row['ReviewID'])."&view=reviews&search=$searchParam&reviews_filter=".$displayFilters['reviews']."&page=$page' class='btn btn-sm btn-primary'>Edit</a> <a href='soft_delete.php?table=Reviews&id=".escape($row['ReviewID'])."&view=reviews&search=$searchParam&reviews_filter=".$displayFilters['reviews']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a> </div> </td> </tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
            displayPagination($totalPages, $page, 'reviews', $searchParam, $displayFilters);
            break;

case 'contacts':
    $where = "(LOWER(c.ContactInfo) LIKE ? OR LOWER(c.Message) LIKE ? OR LOWER(u.Name) LIKE ?)";
    $params = [$searchLike, $searchLike, $searchLike];
    $types = "sss";
    
    switch($displayFilters['contacts']) {
        case 'active':
            $where .= " AND c.IsDeleted = 0";
            break;
        case 'deleted':
            $where .= " AND c.IsDeleted = 1";
            break;
    }
    
    $totalRecords = getRecordCount($conn, 'Contact c LEFT JOIN User u ON c.UserID = u.UserID', $where, $params, $types);
    $totalPages = ceil($totalRecords / $limit);
    
    echo "<h2 class='section-title'>Contacts (Total: $totalRecords)</h2>";
    $stmt = $conn->prepare("SELECT c.*, u.Name AS UserName FROM Contact c LEFT JOIN User u ON c.UserID = u.UserID WHERE $where ORDER BY c.CreatedAt DESC LIMIT ? OFFSET ?");
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<p class='no-results'>No contacts found.</p>";
        break;
    }
    
    echo "<div class='table-container'>";
    echo "<table class='data-table'> <thead><tr> <th class='table-header'>ID</th><th class='table-header'>User</th><th class='table-header'>Message</th><th class='table-header'>ContactInfo</th><th class='table-header'>CreatedAt</th><th class='table-header'>Status</th><th class='table-header'>Actions</th> </tr></thead><tbody>";
    while ($row = $result->fetch_assoc()) {
        $messagePreview = strlen($row['Message']) > 50 ? substr($row['Message'], 0, 50) . '...' : $row['Message'];

        // Normalize South African phone numbers
        $contact = trim($row['ContactInfo']);
        if (preg_match('/^\+27\d{9}$/', $contact)) {
            $tel = $contact; // already correct format
        } elseif (preg_match('/^27\d{9}$/', $contact)) {
            $tel = '+'.$contact;
        } elseif (preg_match('/^0\d{9}$/', $contact)) {
            $tel = '+27'.substr($contact, 1);
        } else {
            $tel = $contact; // leave emails or unknown format as-is
        }

        $contactLink = (strpos($contact, '@') !== false) ? "mailto:".escape($contact) : "tel:".escape($tel);
        $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
        $statusText = $row['IsDeleted'] ? "Deleted" : "Active";

        echo "<tr class='table-row'> 
                <td class='table-cell'>".escape($row['ContactID'])."</td> 
                <td class='table-cell'>".escape($row['UserName'])."</td> 
                <td class='table-cell message-cell' title='".escape($row['Message'])."'>".escape($messagePreview)."</td> 
                <td class='table-cell'><a href='{$contactLink}' class='contact-link'>".escape($row['ContactInfo'])."</a></td> 
                <td class='table-cell'>".escape($row['CreatedAt'])."</td> 
                <td class='table-cell $statusClass'>$statusText</td> 
                <td class='table-cell'> 
                    <div class='action-buttons'>";
        if ($row['IsDeleted']) {
            echo "<a href='restore.php?table=Contact&id=".escape($row['ContactID'])."&view=contacts&search=$searchParam&contacts_filter=".$displayFilters['contacts']."&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
        } else {
            echo "<a href='{$contactLink}' class='btn btn-sm btn-primary'>Contact</a>";
            echo "<a href='soft_delete.php?table=Contact&id=".escape($row['ContactID'])."&view=contacts&search=$searchParam&contacts_filter=".$displayFilters['contacts']."&page=$page' onclick='return confirm(\"Are you sure?\")' class='btn btn-sm btn-danger'>Delete</a>";
        }
        echo " </div> 
              </td> 
              </tr>";
    }
    echo "</tbody></table>";
    echo "</div>";
    displayPagination($totalPages, $page, 'contacts', $searchParam, $displayFilters);
    break;

        case 'features':
            $result = $conn->query("SELECT * FROM Features ORDER BY FeatureName");
            echo "<h2 class='section-title'>Manage Features</h2>";
            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No features found.</p>";
                break;
            }
            echo "<div class='table-container'>";
            echo "<form method='POST' action='update_features.php' class='features-form'>";
            echo "<table class='data-table'> <thead><tr><th class='table-header'>Feature</th><th class='table-header'>Enabled</th></tr></thead><tbody>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr class='table-row'> <td class='table-cell'>".escape($row['FeatureName'])."</td> <td class='table-cell'> <input type='checkbox' name='features[]' value='".escape($row['FeatureID'])."' ".($row['IsEnabled'] ? 'checked' : '')." class='feature-checkbox'> </td> </tr>";
            }
            echo "</tbody></table><br> <button type='submit' class='btn btn-primary save-features-btn'>Save Changes</button> </form>";
            echo "</div>";
            break;



        case 'gallery':
            // WHERE clause for searching (by title or description)
            $where = "(LOWER(g.Title) LIKE ? OR LOWER(g.Description) LIKE ?)";
            $params = [$searchLike, $searchLike];
            $types = "ss";

            // Count total records
            $totalRecords = getRecordCount($conn, 'Gallery g', $where, $params, $types);
            $totalPages = ceil($totalRecords / $limit);

            // Add button
            echo '<a href="add_gallery.php?view=gallery&search=' . $searchParam . '" class="add-btn"><span class="add-btn-icon">+</span> Add Gallery Item</a>';

            // Fetch gallery items
            $stmt = $conn->prepare("
                SELECT g.* 
                FROM Gallery g
                WHERE $where
                ORDER BY g.CreatedAt DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            echo "<h2 class='section-title'>Gallery (Total: $totalRecords)</h2>";

            if ($result->num_rows === 0) {
                echo "<p class='no-results'>No gallery items found.</p>";
                break;
            }

            echo "<div class='table-container gallery-table'>";
            echo "<table class='data-table'>
                    <thead>
                    <tr>
                        <th class='table-header'>ID</th>
                        <th class='table-header'>Title</th>
                        <th class='table-header'>Description</th>
                        <th class='table-header'>Image</th>
                        <th class='table-header'>Created At</th>
                        <th class='table-header'>Status</th>
                        <th class='table-header'>Actions</th>
                    </tr>
                    </thead>
                    <tbody>";

            while ($row = $result->fetch_assoc()) {
                $imagePath = "" . escape($row['ImageUrl']);
                $statusClass = $row['IsDeleted'] ? 'status-deleted' : 'status-active';
                $statusText = $row['IsDeleted'] ? "Deleted" : "Active";

                echo "<tr class='table-row'>
                        <td class='table-cell'>" . escape($row['GalleryID']) . "</td>
                        <td class='table-cell'>" . escape($row['Title']) . "</td>
                        <td class='table-cell'>" . escape($row['Description']) . "</td>
                        <td class='table-cell'><img src='" . escape($imagePath) . "' alt='Gallery Image' class='gallery-thumb'></td>
                        <td class='table-cell'>" . escape($row['CreatedAt']) . "</td>
                        <td class='table-cell $statusClass'>$statusText</td>
                        <td class='table-cell'>
                            <div class='action-buttons'>";
                if ($row['IsDeleted']) {
                    echo "<a href='restore.php?table=Gallery&id=" . escape($row['GalleryID']) . "&view=gallery&search=$searchParam&page=$page' class='btn btn-sm restore-btn'>Restore</a>";
                } else {
                    echo "<a href='edit_gallery.php?id=" . escape($row['GalleryID']) . "&view=gallery&search=$searchParam&page=$page' class='btn btn-sm btn-primary'>Edit</a>";
                    echo "<a href='soft_delete.php?table=Gallery&id=" . escape($row['GalleryID']) . "&view=gallery&search=$searchParam&page=$page' onclick='return confirm(\"Are you sure you want to delete this image?\")' class='btn btn-sm btn-danger'>Delete</a>";
                }
                echo "      </div>
                        </td>
                    </tr>";
            }

            echo "</tbody></table>";
            echo "</div>";

            displayPagination($totalPages, $page, 'gallery', $searchParam, $displayFilters);
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
    ?>
</div>

<?php include 'footer.php'; ?>
