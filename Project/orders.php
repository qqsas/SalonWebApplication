<?php
session_start();
include 'db.php';
include 'header.php';

// Ensure user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit;
}

$userID = $_SESSION['UserID'];
// Define role flags
$isAdmin = isset($_SESSION['Role']) && $_SESSION['Role'] === 'admin';


// Handle actions: cancel, restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['OrderID'])) {
    $orderID = (int)$_POST['OrderID'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT UserID, Status, CreatedAt FROM Orders WHERE OrderID = ?");
    $stmt->bind_param("i", $orderID);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orderData) die("Order not found.");
    if ($orderData['UserID'] != $userID) die("Unauthorized action.");

    $createdTime = strtotime($orderData['CreatedAt']);
    if ((time() - $createdTime) > 2 * 24 * 60 * 60) die("Cannot modify orders older than 2 days.");

    if ($action === 'cancel' && $orderData['Status'] !== 'Cancelled') {
        $stmt = $conn->prepare("UPDATE Orders SET Status='Cancelled' WHERE OrderID=?");
        $stmt->bind_param("i", $orderID);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'restore' && $orderData['Status'] === 'Cancelled') {
        $stmt = $conn->prepare("UPDATE Orders SET Status='Pending' WHERE OrderID=?");
        $stmt->bind_param("i", $orderID);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch orders
if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT o.OrderID, o.UserID, o.TotalPrice, o.Status, o.CreatedAt, u.Name AS UserName
        FROM Orders o
        JOIN User u ON o.UserID = u.UserID
        ORDER BY o.CreatedAt DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT o.OrderID, o.TotalPrice, o.Status, o.CreatedAt
        FROM Orders o
        WHERE o.UserID = ?
        ORDER BY o.CreatedAt DESC
    ");
    $stmt->bind_param("i", $userID);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container1">
  
    <h2>Your Orders</h2>


    <?php if (empty($orders)): ?>
        <p>No orders found.</p>
        <a href="products.php" class="btn">Browse Products</a>
    <?php else: ?>
        <!-- Enhanced Filters -->
        <div class="filters" style="margin-bottom:15px;">
            <!-- Status Filter -->
            <label for="status_filter">Filter by Status:</label>
            <select id="status_filter">
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Paid">Paid</option>
                <option value="Cancelled">Cancelled</option>
                <option value="Completed">Completed</option>
            </select>

            <!-- Date Range Filters -->
            <label for="date_from">From:</label>
            <input type="date" id="date_from" style="padding:5px;">

            <label for="date_to">To:</label>
            <input type="date" id="date_to" style="padding:5px;">

            <!-- Price Range Filters -->
            <label for="min_price">Min Price:</label>
            <input type="number" id="min_price" placeholder="R 0.00" min="0" step="0.01" style="padding:5px; width:100px;">

            <label for="max_price">Max Price:</label>
            <input type="number" id="max_price" placeholder="R 1000.00" min="0" step="0.01" style="padding:5px; width:100px;">

            <!-- Sort Options -->
            <label for="sort_orders">Sort by:</label>
            <select id="sort_orders">
                <option value="date_desc">Date (Newest First)</option>
                <option value="date_asc">Date (Oldest First)</option>
                <option value="total_desc">Total (High to Low)</option>
                <option value="total_asc">Total (Low to High)</option>
                <option value="status_asc">Status (A-Z)</option>
                <option value="status_desc">Status (Z-A)</option>
                <option value="id_desc">Order ID (High to Low)</option>
                <option value="id_asc">Order ID (Low to High)</option>
                <option value="items_desc">Items (Most First)</option>
                <option value="items_asc">Items (Fewest First)</option>
            </select>

            <!-- Quick Action Buttons -->
            <button type="button" id="apply_filters" class="btn" style="margin-left:10px;">Apply Filters</button>
            <button type="button" id="reset_filters" class="btn">Reset</button>
        </div>

        <!-- Quick Status Filters -->
        <div class="filters" style="margin-bottom:15px;">
            <strong>Quick Filters:</strong>
            <button type="button" class="btn quick-filter" data-status="Pending">Pending</button>
            <button type="button" class="btn quick-filter" data-status="Paid">Paid</button>
            <button type="button" class="btn quick-filter" data-status="Cancelled">Cancelled</button>
            <button type="button" class="btn quick-filter" data-status="Reserved">Reserved</button>
            <button type="button" class="btn quick-filter" data-status="">Show All</button>
        </div>

        <div id="orders_container">
        <?php foreach ($orders as $order): 
            $canModify = (time() - strtotime($order['CreatedAt'])) <= 2 * 24 * 60 * 60;
            
            // Get order items count for filtering
            $stmt = $conn->prepare("
                SELECT COUNT(*) as item_count 
                FROM OrderItems 
                WHERE OrderID = ?
            ");
            $stmt->bind_param("i", $order['OrderID']);
            $stmt->execute();
            $countResult = $stmt->get_result();
            $itemCount = $countResult->fetch_assoc()['item_count'];
            $stmt->close();
        ?>
            <div class="order-card" 
                 data-status="<?= htmlspecialchars($order['Status']); ?>" 
                 data-date="<?= strtotime($order['CreatedAt']); ?>" 
                 data-total="<?= $order['TotalPrice']; ?>"
                 data-id="<?= $order['OrderID']; ?>"
                 data-items="<?= $itemCount; ?>">
                <h3>Order #<?= $order['OrderID']; ?></h3>
        <div class="textOrder">
                <?php if ($isAdmin): ?>
                    <p>Customer: <?= htmlspecialchars($order['UserName']); ?></p>
                <?php endif; ?>
                <p>Status: <span class="status-<?= strtolower($order['Status']); ?>"><?= htmlspecialchars($order['Status']); ?></span></p>
                <p>Total: R<?= number_format($order['TotalPrice'], 2); ?></p>
                <p>Date: <?= date('M j, Y g:i A', strtotime($order['CreatedAt'])); ?></p>
                <p>Items: <?= $itemCount; ?></p>
        </div>
                <!-- Fetch order items -->
                <?php
                $stmt = $conn->prepare("
                    SELECT p.ProductID, p.Name, p.Price, oi.Quantity
                    FROM OrderItems oi
                    JOIN Products p ON oi.ProductID = p.ProductID
                    WHERE oi.OrderID = ?
                ");
                $stmt->bind_param("i", $order['OrderID']);
                $stmt->execute();
                $itemsResult = $stmt->get_result();
                $items = $itemsResult->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                ?>
<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Subtotal</th>
            <th>Review</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): 
            $stmt = $conn->prepare("SELECT ReviewID FROM Reviews WHERE ProductID=? AND UserID=? AND IsDeleted=0");
            $stmt->bind_param("ii", $item['ProductID'], $userID);
            $stmt->execute();
            $reviewRes = $stmt->get_result();
            $hasReview = $reviewRes->num_rows > 0;
            $stmt->close();
        ?>
            <tr>
                <td data-label="Product"><?= htmlspecialchars($item['Name']); ?></td>
                <td data-label="Price">R<?= number_format($item['Price'], 2); ?></td>
                <td data-label="Quantity"><?= $item['Quantity']; ?></td>
                <td data-label="Subtotal">R<?= number_format($item['Price'] * $item['Quantity'], 2); ?></td>
                <td data-label="Review">
                    <?php if ($hasReview): ?>
                        <span style="color:green;">Reviewed</span>
                    <?php else: ?>
                        <a href="make_review.php?ProductID=<?= $item['ProductID']; ?>" class="btn">Make Review</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
                <?php if ($canModify): ?>
                    <form method="post" style="margin-top:10px;" >
                        <input type="hidden" name="OrderID" value="<?= $order['OrderID']; ?>">
                        <?php if ($order['Status'] !== 'Cancelled'): ?>
                            <button type="submit" name="action" value="cancel" class="btn btn-cancel">Cancel</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="restore" class="btn btn-restore">Restore</button>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p><em>Order modification period has expired.</em></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
const ordersContainer = document.getElementById('orders_container');
const statusFilter = document.getElementById('status_filter');
const sortSelect = document.getElementById('sort_orders');
const dateFrom = document.getElementById('date_from');
const dateTo = document.getElementById('date_to');
const minPrice = document.getElementById('min_price');
const maxPrice = document.getElementById('max_price');
const applyFilters = document.getElementById('apply_filters');
const resetFilters = document.getElementById('reset_filters');
const quickFilters = document.querySelectorAll('.quick-filter');

function filterAndSortOrders() {
    const statusValue = statusFilter.value;
    const sortValue = sortSelect.value;
    const dateFromValue = dateFrom.value ? new Date(dateFrom.value).getTime() / 1000 : null;
    const dateToValue = dateTo.value ? new Date(dateTo.value).getTime() / 1000 + 86400 : null; // Add 1 day to include the entire day
    const minPriceValue = minPrice.value ? parseFloat(minPrice.value) : null;
    const maxPriceValue = maxPrice.value ? parseFloat(maxPrice.value) : null;

    // Get order cards
    const cards = Array.from(ordersContainer.querySelectorAll('.order-card'));

    // Filter
    cards.forEach(card => {
        let show = true;
        const cardStatus = card.dataset.status;
        const cardDate = parseInt(card.dataset.date);
        const cardTotal = parseFloat(card.dataset.total);
        const cardItems = parseInt(card.dataset.items);

        // Status filter
        if (statusValue && cardStatus !== statusValue) {
            show = false;
        }

        // Date range filter
        if (dateFromValue && cardDate < dateFromValue) {
            show = false;
        }
        if (dateToValue && cardDate > dateToValue) {
            show = false;
        }

        // Price range filter
        if (minPriceValue && cardTotal < minPriceValue) {
            show = false;
        }
        if (maxPriceValue && cardTotal > maxPriceValue) {
            show = false;
        }

        card.style.display = show ? '' : 'none';
    });

    // Sort
    const visibleCards = cards.filter(card => card.style.display !== 'none');
    visibleCards.sort((a,b) => {
        switch(sortValue) {
            case 'date_asc': return a.dataset.date - b.dataset.date;
            case 'date_desc': return b.dataset.date - a.dataset.date;
            case 'total_asc': return a.dataset.total - b.dataset.total;
            case 'total_desc': return b.dataset.total - a.dataset.total;
            case 'status_asc': return a.dataset.status.localeCompare(b.dataset.status);
            case 'status_desc': return b.dataset.status.localeCompare(a.dataset.status);
            case 'id_asc': return a.dataset.id - b.dataset.id;
            case 'id_desc': return b.dataset.id - a.dataset.id;
            case 'items_asc': return a.dataset.items - b.dataset.items;
            case 'items_desc': return b.dataset.items - a.dataset.items;
            default: return 0;
        }
    });

    // Re-append sorted cards
    visibleCards.forEach(card => ordersContainer.appendChild(card));
}

function resetAllFilters() {
    statusFilter.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    minPrice.value = '';
    maxPrice.value = '';
    sortSelect.value = 'date_desc';
    filterAndSortOrders();
}

// Event Listeners
statusFilter.addEventListener('change', filterAndSortOrders);
sortSelect.addEventListener('change', filterAndSortOrders);
applyFilters.addEventListener('click', filterAndSortOrders);
resetFilters.addEventListener('click', resetAllFilters);

// Quick filter buttons
quickFilters.forEach(button => {
    button.addEventListener('click', function() {
        statusFilter.value = this.dataset.status;
        filterAndSortOrders();
    });
});

// Initialize filters
filterAndSortOrders();
</script>


<style>

    /* === Orders Page Styling === */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #ffffff;
    color: #333;
}

/* Container */
.container1 {
    max-width: 1200px;
    margin: 40px auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.container1 h2 {
    color: #050506ff;
    font-size: 2rem;              /* Larger text for emphasis */
    text-align: center;           /* Centers the heading */
    font-weight: 700;             /* Strong but not too heavy */
    margin-top: 10px;             /* Nice distance from the top */
    margin-bottom: 25px;          /* Balanced bottom spacing */
    position: relative;           /* For underline positioning */
    display: inline-block;        /* Keeps underline close to text */
    left: 50%;
    transform: translateX(-50%);  /* Center the inline-block element */
}

/* Subtle underline under the text only */
.container1 h2::after {
    content: "";
    display: block;
    width: 60px;                  /* Short underline */
    height: 3px;                  /* Thickness of the line */
    background-color: #1e3a8a;
    margin: 8px auto 0;           /* Spacing below text */
    border-radius: 2px;           /* Rounded line ends */
}


/* Filters */
.filters {
    background: #f0f4f8;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.filters label {
    font-weight: 600;
    color: #1f2937;
}

.filters input, 
.filters select {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 10px;
    transition: all 0.3s ease;
    background-color: #fff;
}

.filters input:focus, 
.filters select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 5px rgba(37,99,235,0.3);
    outline: none;
}

/* Buttons */
.btn {
    background-color: #2563eb;
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
}

.btn:hover {
    background-color: #1e40af;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(37,99,235,0.3);
}

.btn-cancel {
    background-color: #dc2626;
}

.btn-cancel:hover {
    background-color: #b91c1c;
    box-shadow: 0 2px 6px rgba(220,38,38,0.3);
}

.btn-restore {
    background-color: #16a34a;
}

.btn-restore:hover {
    background-color: #15803d;
    box-shadow: 0 2px 6px rgba(22,163,74,0.3);
}

.quick-filter {
    background-color: #64748b;
}

.quick-filter:hover {
    background-color: #475569;
}

/* Order Cards */
.order-card {
    background: #f9fafb;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.order-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

.order-card h3 {
    color: #1e3a8a;
    margin-bottom: 12px;
}

.textOrder p {
    margin: 5px 0;
    color: #374151;
}

/* Status Colors */
.status-pending { color: #eab308; font-weight: 600; }
.status-paid { color: #16a34a; font-weight: 600; }
.status-cancelled { color: #dc2626; font-weight: 600; }
.status-completed { color: #0284c7; font-weight: 600; }

/* Table Styling */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 14px;
}

thead {
    background: #e0e7ff;
    color: #1e3a8a;
}

thead th {
    padding: 10px;
    text-align: left;
}

tbody tr {
    border-bottom: 1px solid #e5e7eb;
}

tbody td {
    padding: 10px;
}

tbody tr:hover {
    background-color: #f3f4f6;
}

/* Responsive Table */
@media screen and (max-width: 768px) {
    .filters {
        flex-direction: column;
        align-items: flex-start;
    }

    table, thead, tbody, th, td, tr {
        display: block;
    }

    thead {
        display: none;
    }

    tbody td {
        padding: 10px;
        display: flex;
        justify-content: space-between;
        border: 1px solid #e5e7eb;
        margin-bottom: 5px;
    }

    tbody td:before {
        content: attr(data-label);
        font-weight: 600;
        color: #1e3a8a;
    }
}

</style>


<?php
include 'footer.php';
?>