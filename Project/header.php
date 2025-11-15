<?php
// Enhanced session security
// ini_set('session.cookie_httponly', 1);
// ini_set('session.use_strict_mode', 1);
// ini_set('session.cookie_samesite', 'Strict');
// ini_set('session.gc_maxlifetime', 3600); // 1 hour

// // Enable secure cookies if using HTTPS
// if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
//     ini_set('session.cookie_secure', 1);
// }

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Include security functions
include 'security.php';

// Check session security (IP validation)
if (isset($_SESSION['UserID'])) {
    if (!checkSessionSecurity()) {
        header("Location: Login.php?error=session_invalid");
        exit();
    }
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
<?php
    // Determine which stylesheet to use based on required role for the page
    // Pages can optionally set $pageRoleRequirement = 'admin' | 'barber' before including header
    $requiredRole = isset($pageRoleRequirement) ? strtolower(trim($pageRoleRequirement)) : null;

    // If not explicitly provided, infer from current script name
    if (!$requiredRole) {
        $currentScript = basename($_SERVER['PHP_SELF']);
        if (stripos($currentScript, 'admin') !== false) {
            $requiredRole = 'admin';
        } elseif (stripos($currentScript, 'barber') !== false) {
            $requiredRole = 'barber';
        }
    }

    // Select stylesheet: admin -> adminstyle.css, barber -> barberstyle.css, default -> styles.css
    if ($requiredRole === 'admin') {
        echo '<link rel="stylesheet" href="adminstyle.css">';
    } elseif ($requiredRole === 'barber') {
        echo '<link rel="stylesheet" href="adminstyle.css">';
        echo '<link rel="stylesheet" href="barberstyle.css">';
        
    } else {
        echo '<link rel="stylesheet" href="styles.css">';
    }
?>
</head>
<!-- Header -->
<nav class="Header-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php" aria-label="Kumar Kailey Hair & Beauty Salon">
    <img src="Img/KSLOGO.png" alt="">
        </a>
        
        <!-- Burger Menu Toggle -->
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">
            <span class="burger-line"></span>
            <span class="burger-line"></span>
            <span class="burger-line"></span>
        </button>
        
        <div class="HeaderNavBody" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="staff.php">Staff</a></li>
                <li class="nav-item"><a class="nav-link" href="gallery.php">Gallery</a></li>
                <li class="nav-item"><a class="nav-link" href="support.php">Contact</a></li>

                <?php if (isset($_SESSION['UserID'])): ?>
                    <?php
                        $role_lower = strtolower($user_role);
                        if ($role_lower === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a></li>
                        <?php elseif ($role_lower === 'customer'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="cart.php">
                                    <span class="cart-label">Cart</span>
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


<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const navbar = document.querySelector('.Header-navbar');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Toggle menu
    menuToggle.addEventListener('click', function() {
        navbar.classList.toggle('active');
        
        // Add overlay when menu is open
        if (navbar.classList.contains('active')) {
            const overlay = document.createElement('div');
            overlay.className = 'overlay';
            overlay.addEventListener('click', closeMenu);
            document.body.appendChild(overlay);
        } else {
            const overlay = document.querySelector('.overlay');
            if (overlay) overlay.remove();
        }
    });
    
    // Close menu when clicking on links (mobile)
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMenu();
            }
        });
    });
    
    // Close menu function
    function closeMenu() {
        navbar.classList.remove('active');
        const overlay = document.querySelector('.overlay');
        if (overlay) overlay.remove();
    }
    
    // Close menu on window resize (if resizing to desktop)
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMenu();
        }
    });
});
</script>
