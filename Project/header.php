<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

$user_role = isset($_SESSION['UserID']) ? $_SESSION['Role'] ?? null : null;

// Calculate cart item count
$cartItemCount = 0;
if (isset($_SESSION['UserID'])) {
    $user_id = $_SESSION['UserID'];
    $stmt = $conn->prepare("SELECT SUM(Quantity) AS total_items FROM Cart WHERE UserID = ?");
    $stmt->bind_param("i", $UserID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $cartItemCount = $row['total_items'] ?? 0;
    }
}
?>

<!-- Header -->
<nav class="Header-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="homepage.php">
            <img src="Img/logo.jpeg" alt="Logo" style="height: 30px; margin-right: 10px;">
            Kumar Kailey Hair & Beauty Salon
        </a>
<button class="menu-toggle" onclick="document.querySelector('.Header-navbar').classList.toggle('active')">
    ☰
</button>
        
        <div class="HeaderNavBody" id="navbarNav">
            
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="support.php">Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="products.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="specials.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="specials.php">Staff</a></li>
                <li class="nav-item"><a class="nav-link" href="homepage.php">Home</a></li>

                <?php if (isset($_SESSION['UserID'])): ?>
                    <?php if ($user_role === 'Admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a></li>
                    <?php elseif ($user_role === 'Barber'): ?>
                        <li class="nav-item"><a class="nav-link" href="seller_dashboard.php">Dashboard</a></li>
                    <?php elseif ($user_role === 'Customer'): ?>
                        <li class="nav-item">
                            <a class="CartWrapper" href="cart.php">
                                <i class="CartBody"></i>
                                <span>🛒 Cart</span>
                                <?php if ($cartItemCount > 0): ?>
                                    <span class="cart-number"><?= $cartItemCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="buyer_orders.php">Orders</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                <?php endif; ?>
                
            </ul>
        </div>
    </div>
</nav>
