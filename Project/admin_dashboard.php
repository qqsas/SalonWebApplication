<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';
include 'header.php';

$view = $_GET['view'] ?? '';
$search = isset($_GET['search']) ? "%" . strtolower($_GET['search']) . "%" : "%";

// --- Admin Actions ---

if (isset($_GET['delete_listing'])) {
    $id = intval($_GET['delete_listing']);
    $stmt = $conn->prepare("UPDATE products SET is_deleted = 1 WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

if (isset($_GET['restore_listing'])) {
    $id = intval($_GET['restore_listing']);
    $stmt = $conn->prepare("UPDATE products SET is_deleted = 0 WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

if (isset($_GET['delete_account'])) {
    $id = intval($_GET['delete_account']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

if (isset($_GET['cancel_order'])) {
    $id = intval($_GET['cancel_order']);
    $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

if (isset($_GET['refund'])) {
    $order_id = intval($_GET['refund']);

    $stmt = $conn->prepare("UPDATE payments SET payment_status = 'refunded' WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
    foreach ($items as $item) {
        $qty = $item['quantity'];
        $pid = $item['product_id'];
        $stmt->bind_param("ii", $qty, $pid);
        $stmt->execute();
    }
}

if (isset($_GET['delete_review'])) {
    $id = intval($_GET['delete_review']);
    $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

if (isset($_GET['resolve_complaint'])) {
    $complaint_id = intval($_GET['resolve_complaint']);
    $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
}

// --- Fetch Data ---

// Define allowed sort options
$allowed_sorts = [
    'name_asc' => 'name ASC',
    'name_desc' => 'name DESC',
    'price_asc' => 'price ASC',
    'price_desc' => 'price DESC',
    'stock_asc' => 'stock ASC',
    'stock_desc' => 'stock DESC'
];

// Get sort param from URL, default to 'name_asc'
$sort = $_GET['sort'] ?? 'name_asc';

// Validate sort param
$order_by = $allowed_sorts[$sort] ?? 'name ASC';

// Prepare statement with dynamic ORDER BY
// Note: ORDER BY cannot be parameterized, so inject safely after whitelist check
$sql = "SELECT * FROM products WHERE LOWER(name) LIKE ? AND is_deleted = 0 ORDER BY $order_by";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $search);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sqlDeleted = "SELECT * FROM products WHERE LOWER(name) LIKE ? AND is_deleted = 1 ORDER BY $order_by";
$stmt = $conn->prepare($sqlDeleted);
$stmt->bind_param("s", $search);
$stmt->execute();
$deletedListings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


$users = $conn->prepare("SELECT * FROM users WHERE LOWER(username) LIKE ? OR LOWER(email) LIKE ?");
$users->bind_param("ss", $search, $search);
$users->execute();
$users = $users->get_result()->fetch_all(MYSQLI_ASSOC);

$orders = $conn->prepare("SELECT * FROM orders WHERE CAST(order_id AS CHAR) LIKE ? OR CAST(user_id AS CHAR) LIKE ?");
$orders->bind_param("ss", $search, $search);
$orders->execute();
$orders = $orders->get_result()->fetch_all(MYSQLI_ASSOC);

$deliveries = $conn->prepare("SELECT * FROM delivery WHERE CAST(order_id AS CHAR) LIKE ? OR LOWER(tracking_number) LIKE ?");
$deliveries->bind_param("ss", $search, $search);
$deliveries->execute();
$deliveries = $deliveries->get_result()->fetch_all(MYSQLI_ASSOC);

$reviews = $conn->prepare("SELECT * FROM reviews WHERE CAST(product_id AS CHAR) LIKE ? OR CAST(user_id AS CHAR) LIKE ?");
$reviews->bind_param("ss", $search, $search);
$reviews->execute();
$reviews = $reviews->get_result()->fetch_all(MYSQLI_ASSOC);

$complaints = $conn->query("
    SELECT c.complaint_id, c.user_id, c.complaint, u.username
    FROM complaints c
    JOIN users u ON c.user_id = u.user_id
    ORDER BY c.submitted_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
    <style>
.navbar-brand,nav {
    display: flex;
    align-items: center
}

nav {
    background: var(--header-bg);
    box-shadow: var(--header-shadow);
    padding: .5rem 1rem;
    flex-wrap: wrap;
    justify-content: space-between;
    width: 100%
}

nav a {
    text-decoration: none;
    color: var(--header-text);
    transition: all .3s ease
}

.navbar-brand {
    font-family: var(--font-heading);
    font-weight: 700;
    font-size: 1.5rem;
    border-radius: var(--border-radius-md);
    overflow: hidden;
    z-index: 1
}

.navbar-brand img {
    height: 30px;
    margin-right: 10px
}

.navbar-brand::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,.1);
    z-index: -1;
    transform: scaleX(0);
    transform-origin: right;
    transition: transform .4s cubic-bezier(.65,0,.35,1)
}

.navbar-brand:hover::before {
    transform: scaleX(1);
    transform-origin: left
}

ul.navbar-nav {
    gap: .5rem;
    margin: 0;
    padding: 0;
    list-style: none
}

.nav-item,.nav-link,.navbar-brand,nav {
    position: relative
}

.nav-link,ul.navbar-nav {
    display: flex;
    align-items: center
}

.nav-link {
    padding: .75rem 1.25rem;
    border-radius: var(--border-radius-md);
    font-weight: 500;
    overflow: hidden
}

.nav-link:hover {
    background: rgba(255,255,255,.1);
    transform: translateY(-2px)
}

.cart-badge,.nav-link::after {
    position: absolute;
    background: var(--accent-color)
}

.nav-link::after {
    content: "";
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    transform: translateX(-50%);
    transition: width .3s ease
}

.nav-link:hover::after {
    width: 70%
}

.nav-link.active {
    background: rgba(255,255,255,.2);
    font-weight: 600
}

.cart-badge {
    top: -5px;
    right: -5px;
    width: 1.25rem;
    height: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: red;
    border-radius: 50%;
    font-size: .7rem;
    font-weight: 700;
    animation: pulse 2s infinite
}

@media (max-width:991.98px) {
    nav,ul.navbar-nav {
        flex-direction: column;
        align-items: flex-start
    }

    .nav-item,.nav-link,ul.navbar-nav {
        width: 100%
    }

    .nav-link {
        padding: 1rem;
        border-bottom: 1px solid rgba(255,255,255,.1)
    }

    .navbar-brand {
        width: 100%;
        margin-bottom: .5rem
    }
}

.dropdown {
    position: relative
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--accent-color);
    border-radius: var(--border-radius-md);
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
    padding: .5rem 0;
    min-width: 200px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all .3s ease;
    z-index: 1000
}

.dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0)
}

.dropdown-item {
    display: block;
    padding: .5rem 1.5rem;
    color: var(--text-color);
    text-decoration: none;
    transition: all .2s ease
}

.dropdown-item:hover {
    background: var(--primary-color);
    color: #fff
}

@media (prefers-color-scheme:dark) {
    .dropdown-menu {
        background: var(--dark-color);
        border: 1px solid rgba(255,255,255,.1)
    }

    .dropdown-item {
        color: var(--light-color)
    }

    .dropdown-item:hover {
        background: var(--accent-color)
    }
}

.bg-light {
    background: var(--bg-color)
}

.card {
    background: var(--light-color);
    border-radius: var(--border-radius-lg);
    transition: var(--transition-base);
    box-shadow: var(--card-shadow)
}

.card:hover {
    box-shadow: var(--card-shadow-hover)
}

.card-title {
    font-family: var(--font-heading);
    font-weight: 600;
    color: var(--text-color)
}

.form-control {
    background: var(--light-color);
    border: 2px solid var(--dark-color);
    border-radius: var(--border-radius-sm);
    transition: var(--transition-fast)
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-hue),var(--saturation),.15)
}

.alert-danger,.btn-primary {
    border-radius: var(--border-radius-sm)
}

.btn-primary {
    background: var(--gradient-primary);
    color: var(--light-color);
    border: 0;
    padding: 1rem 2rem;
    transition: var(--transition-base);
    text-transform: uppercase;
    font-weight: 600
}

.btn-primary:hover {
    background: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: var(--card-shadow-hover)
}

.alert-danger {
    background: hsl(var(--secondary-hue),var(--saturation),90%);
    color: hsl(var(--secondary-hue),var(--saturation),30%);
    border: 2px solid var(--secondary-color);
    padding: 1rem
}

.text-center a {
    color: var(--primary-color);
    text-decoration: none;
    transition: var(--transition-fast)
}

.text-center a:hover {
    color: var(--accent-color);
    text-decoration: underline
}

.mt-5 {
    margin-top: 3rem
}

.mb-4 {
    margin-bottom: 1.5rem
}

.mt-3 {
    margin-top: 1rem
}

.w-100 {
    width: 100%!important
}
@media (min-width: 769px) {
      .menu-toggle {
        display: none !important;
      }
    }
        </style>
<head>
    <title>Admin Dashboard</title>
    <link href="styles1.css" rel="stylesheet">
    
</head>
<body class="dashboard-container">

    <h1 class="dashboard-heading">Admin Dashboard</h1>

    <!-- Search Form -->
    <form class="search-form" method="get">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view ?? '') ?>">
        <div class="search-group">
            <button id="searchBtn" class="search-button" type="submit">Search</button>
            <input id="searchInput" onkeyup="filterTableRows()" type="text" name="search" class="search-input" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <select name="sort" class="sort-dropdown">
                <option value="name_asc" <?= ($sort ?? '') === 'name_asc' ? 'selected' : '' ?>>Name ↑</option>
                <option value="name_desc" <?= ($sort ?? '') === 'name_desc' ? 'selected' : '' ?>>Name ↓</option>
                <option value="price_asc" <?= ($sort ?? '') === 'price_asc' ? 'selected' : '' ?>>Price ↑</option>
                <option value="price_desc" <?= ($sort ?? '') === 'price_desc' ? 'selected' : '' ?>>Price ↓</option>
                <option value="stock_asc" <?= ($sort ?? '') === 'stock_asc' ? 'selected' : '' ?>>Stock ↑</option>
                <option value="stock_desc" <?= ($sort ?? '') === 'stock_desc' ? 'selected' : '' ?>>Stock ↓</option>
            </select>
        </div>
    </form>

    <!-- Tab Navigation -->
    <ul class="tab-nav">
        <li><button class="tab-link active" data-target="listings">Listings</button></li>
        <li><button class="tab-link" data-target="accounts">Accounts</button></li>
        <li><button class="tab-link" data-target="purchases">Purchases</button></li>
        <li><button class="tab-link" data-target="delivery">Deliveries</button></li>
        <li><button class="tab-link" data-target="reviews">Reviews</button></li>
        <li><button class="tab-link" data-target="complaints">Complaints</button></li>
        <li><button class="tab-link" data-target="deleted">Deleted Listings</button></li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <div id="listings" class="tab-panel active">
            <h3>All Listings</h3>
            <table class="admin-table filterable-table" id="listingsTable">
                <thead><tr><th>Name</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($listings as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>R<?= $item['price'] ?></td>
                        <td><?= $item['stock'] ?></td>
                        <td>
                            <a href="?delete_listing=<?= $item['product_id'] ?>&view=listings" class="btn-small btn-danger" onclick="return confirm('Delete this listing?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($listings)): ?>
                    <tr><td colspan="4" class="text-center">No listings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="deleted" class="tab-panel">
            <h3>Deleted Listings</h3>
            <table class="admin-table filterable-table" id="deletedTable">
                <thead><tr><th>Name</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($deletedListings as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>R<?= $item['price'] ?></td>
                        <td><?= $item['stock'] ?></td>
                        <td>
                            <a href="?restore_listing=<?= $item['product_id'] ?>&view=deleted" class="btn-small btn-success" onclick="return confirm('Restore this listing?')">Restore</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($deletedListings)): ?>
                    <tr><td colspan="4" class="text-center">No deleted listings.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="accounts" class="tab-panel">
            <h3>All Accounts</h3>
            <table class="admin-table filterable-table" id="accountsTable">
                <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= $user['role'] ?></td>
                        <td>
                            <a href="?delete_account=<?= $user['user_id'] ?>&view=accounts" class="btn-small btn-danger" onclick="return confirm('Delete this account?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="purchases" class="tab-panel">
            <h3>All Purchases</h3>
            <table class="admin-table filterable-table" id="purchasesTable">
                <thead><tr><th>Order ID</th><th>User ID</th><th>Total</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= $o['order_id'] ?></td>
                        <td><?= $o['user_id'] ?></td>
                        <td>R<?= $o['total_amount'] ?></td>
                        <td><?= $o['order_date'] ?></td>
                        <td>
                            <a href="?cancel_order=<?= $o['order_id'] ?>&view=purchases" class="btn-small btn-warning" onclick="return confirm('Cancel this order?')">Cancel</a>
                            <a href="?refund=<?= $o['order_id'] ?>&view=purchases" class="btn-small btn-neutral">Refund</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="delivery" class="tab-panel">
            <h3>Delivery Statuses</h3>
            <table class="admin-table filterable-table" id="deliveryTable">
                <thead><tr><th>Order ID</th><th>Status</th><th>Tracking #</th><th>Delivery Date</th><th>Edit</th></tr></thead>
                <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr>
                        <td><?= $d['order_id'] ?></td>
                        <td><?= $d['delivery_status'] ?></td>
                        <td><?= $d['tracking_number'] ?></td>
                        <td><?= $d['delivery_date'] ?></td>
                        <td><a href="edit_delivery.php?id=<?= $d['delivery_id'] ?>" class="btn-small btn-warning">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="reviews" class="tab-panel">
            <h3>All Reviews</h3>
            <table class="admin-table filterable-table" id="reviewsTable">
                <thead><tr><th>Product ID</th><th>User ID</th><th>Rating</th><th>Comment</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td><?= $r['product_id'] ?></td>
                        <td><?= $r['user_id'] ?></td>
                        <td><?= $r['rating'] ?></td>
                        <td><?= htmlspecialchars($r['comment']) ?></td>
                        <td><a href="?delete_review=<?= $r['review_id'] ?>&view=reviews" class="btn-small btn-danger">Delete</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="complaints" class="tab-panel">
    <h3>All Complaints</h3>
    <table class="admin-table filterable-table" id="complaintsTable">
        <thead>
            <tr>
                <th>Complaint ID</th>
                <th>User ID</th>
                <th>Complaint</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($complaints as $c): ?>
            <tr>
                <td><?= $c['complaint_id'] ?></td>
                <td><?= $c['user_id'] ?></td>
                <td><?= nl2br(htmlspecialchars($c['complaint'])) ?></td>
                <td>
                    <a href="?resolve_complaint=<?= $c['complaint_id'] ?>&view=complaints" class="btn-small btn-success">Resolve</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


    <!-- Scripts -->
    <script>
    document.querySelector('select[name="sort"]').addEventListener('change', function () {
        this.form.submit();
    });

    function filterTableRows() {
        var input = document.getElementById("searchInput");
        var filter = input.value.toUpperCase();
        var tables = document.querySelectorAll(".filterable-table");

        tables.forEach(function(table) {
            var rows = table.getElementsByTagName("tr");
            for (var i = 1; i < rows.length; i++) {
                var cells = rows[i].getElementsByTagName("td");
                var match = false;
                for (var j = 0; j < cells.length; j++) {
                    var txtValue = cells[j].textContent || cells[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? "" : "none";
            }
        });
    }

    // Tab toggle functionality
    document.querySelectorAll('.tab-link').forEach(button => {
        button.addEventListener('click', function () {
            document.querySelectorAll('.tab-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(this.dataset.target).classList.add('active');
        });
    });
    </script>
</body>
</html>
