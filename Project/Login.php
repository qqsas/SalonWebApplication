<?php
session_start();
include 'db.php';
include 'header.php';
$message = '';
$loginError = '';

// Handle redirect logic
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Only set redirect on GET requests (when initially loading the login page)
    if (isset($_GET['redirect'])) {
        // If redirect parameter is provided (forced login), use that
        $_SESSION['redirect_after_login'] = $_GET['redirect'];
    } elseif (!isset($_SESSION['redirect_after_login']) && isset($_SERVER['HTTP_REFERER'])) {
        // If no redirect set and we have a referer, check if it's not the login page itself
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
        $current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        if (basename($referer) != basename($current) && 
            strpos($_SERVER['HTTP_REFERER'], 'Login.php') === false) {
            $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'];
        }
    }
    
    // Default fallback if nothing else is set
    if (!isset($_SESSION['redirect_after_login'])) {
        $_SESSION['redirect_after_login'] = 'index.php';
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST["identifier"]); // can be email or phone only
    $password = $_POST["password"];

    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    if (empty($identifier) || empty($password)) {
        $loginError = "Please enter your email or phone number and password.";
    } else {
        // Check if identifier is email or phone number
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $isPhone = preg_match('/^(\+27|0|27)\d+$/', $identifier);
        
        if (!$isEmail && !$isPhone) {
            $loginError = "Please enter a valid email address or phone number.";
        } else {
            // Generate phone number variants if it's a phone number
            $numberVariants = [];
            if ($isPhone) {
                if (preg_match('/^\+27\d+$/', $identifier)) {
                    $numberVariants[] = $identifier;                     // +27xxxxxxxxx
                    $numberVariants[] = '0' . substr($identifier, 3);    // 0xxxxxxxxx
                    $numberVariants[] = substr($identifier, 1);          // 27xxxxxxxxx
                } elseif (preg_match('/^0\d+$/', $identifier)) {
                    $numberVariants[] = $identifier;                     // 0xxxxxxxxx
                    $numberVariants[] = '+27' . substr($identifier, 1);  // +27xxxxxxxxx
                    $numberVariants[] = '27' . substr($identifier, 1);   // 27xxxxxxxxx
                } elseif (preg_match('/^27\d+$/', $identifier)) {
                    $numberVariants[] = $identifier;                     // 27xxxxxxxxx
                    $numberVariants[] = '+'.$identifier;                 // +27xxxxxxxxx
                    $numberVariants[] = '0' . substr($identifier, 2);    // 0xxxxxxxxx
                }
            }

            // Prepare SQL query based on identifier type
            if ($isEmail) {
                // Search by email only
                $sql = "SELECT UserID, Name, Email, Number, Password, Role FROM User 
                        WHERE Email = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("s", $identifier);
                }
            } else {
                // Search by phone number variants
                $placeholders = implode(',', array_fill(0, count($numberVariants), '?'));
                $sql = "SELECT UserID, Name, Email, Number, Password, Role FROM User 
                        WHERE Number IN ($placeholders) LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $types = str_repeat('s', count($numberVariants));
                    $stmt->bind_param($types, ...$numberVariants);
                }
            }

            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    // Verify password
                    if (password_verify($password, $row['Password'])) {
                        $_SESSION['UserID'] = $row['UserID'];
                        $_SESSION['Name']   = $row['Name'];
                        $_SESSION['Role']   = $row['Role'];

                        // Redirect to stored page or homepage
                        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
                        unset($_SESSION['redirect_after_login']);

                        $stmt->close();
                        $conn->close();

                        header("Location: " . $redirect);
                        exit;
                    } else {
                        $loginError = "Invalid password.";
                    }
                } else {
                    if ($isEmail) {
                        $loginError = "No account found with that email address.";
                    } else {
                        $loginError = "No account found with that phone number.";
                    }
                }
                $stmt->close();
            } else {
                $message = "Query failed: " . $conn->error;
            }
        }
    }
    $conn->close();
}
?>

<head>
    <title>Login</title>
    <link href="styles.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
  <div class="card">
    <div class="card-body">
      <h2>User Login</h2>
      
      <?php if ($loginError): ?>
        <div class="error"><?php echo $loginError; ?></div>
      <?php endif; ?>
      
      <form method="POST" action="">
        <div class="form-group">
          <label>Email or Phone Number</label>
          <input type="text" name="identifier" class="form-control" 
                 value="<?php echo htmlspecialchars($_POST["identifier"] ?? '') ?>" 
                 placeholder="Enter your email or phone number" required>
          <small style="color: #666; font-size: 0.875rem;">
            Enter your email address or phone number (South African format: +27, 0, or 27)
          </small>
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" 
                 placeholder="Enter your password" required>
        </div>

        <input type="submit" value="Login" class="btn-primary">
      </form>

      <p class="text-center mt-3">Don't have an account? </p>
      <a href="Register.php" style="margin-left: 25%;">Register here</a>
    </div>
  </div>
</div>
</body>

