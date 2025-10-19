<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

$user_role = isset($_SESSION['UserID']) ? ($_SESSION['Role'] ?? null) : null;

// Calculate cart item count
$cartItemCount = 0;
if (isset($_SESSION['UserID'])) {
    $user_id = $_SESSION['UserID'];

    $stmt = $conn->prepare("
        SELECT SUM(ci.Quantity) AS total_items 
        FROM Cart c
        JOIN CartItems ci ON c.CartID = ci.CartID
        WHERE c.UserID = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cartItemCount = $row['total_items'] ?? 0;
        }
        $stmt->close();
    }
}
?>
<head>
<link rel="stylesheet" href="adminstyle.css">
<link rel="stylesheet" href="styles.css"> 
</head>
<!-- Header -->
<nav class="Header-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="homepage.php">
            <img src="Img/Logo.jpeg" alt="Logo" style="height: 30px; margin-right: 10px;">
            Kumar Kailey Hair & Beauty Salon
        </a>
        <button class="menu-toggle" onclick="document.querySelector('.Header-navbar').classList.toggle('active')">
            ☰
        </button>
        
        <div class="HeaderNavBody" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="support.php">Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="staff.php">Staff</a></li>
                <li class="nav-item"><a class="nav-link" href="homepage.php">Home</a></li>

                <?php if (isset($_SESSION['UserID'])): ?>
                    <?php
                        $role_lower = strtolower($user_role);
                        if ($role_lower === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a></li>
                        <?php elseif ($role_lower === 'customer'): ?>
                            <li class="nav-item">
                                <a class="CartWrapper" href="cart.php">
                                    <i class="CartBody"></i>
                                    <span>🛒 Cart</span>
                                    <?php if ($cartItemCount > 0): ?>
                                        <span class="cart-number"><?= $cartItemCount ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                            <li class="nav-item"><a class="nav-link" href="view_appointment.php">Appointments</a></li>
                        <?php elseif ($role_lower === 'barber'): ?>
                            <li class="nav-item"><a class="nav-link" href="barber_dashboard.php">Barber Dashboard</a></li>
                        <?php endif; ?>
                    
                    <li class="nav-item"><a class="nav-link" href="view_profile.php">View Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="Logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="Login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="Register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

