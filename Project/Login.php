<?php
session_start();
include 'db.php';
include 'header.php';
$message = '';
$loginError = '';

// Capture referring page for redirect if not already set
if (!isset($_SESSION['redirect_after_login']) && isset($_SERVER['HTTP_REFERER'])) {
    if (strpos($_SERVER['HTTP_REFERER'], 'Login.php') === false) {
        $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST["identifier"]); // can be name, email, or phone
    $password = $_POST["password"];

    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    if (empty($identifier) || empty($password)) {
        $loginError = "Please enter your name, email, or phone number and password.";
    } else {
        // Generate all acceptable phone number variants
        $numberVariants = [];
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
        } else {
            $numberVariants[] = $identifier; // treat as name/email
        }

        // Prepare SQL with dynamic number placeholders
        $placeholders = implode(',', array_fill(0, count($numberVariants), '?'));
        $sql = "SELECT UserID, Name, Email, Number, Password, Role FROM User 
                WHERE Name = ? OR Email = ? OR Number IN ($placeholders) LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Bind parameters dynamically
            $types = str_repeat('s', 2 + count($numberVariants));
            $params = array_merge([$identifier, $identifier], $numberVariants);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Verify password
                if (password_verify($password, $row['Password'])) {
                    $_SESSION['UserID'] = $row['UserID'];
                    $_SESSION['Name']   = $row['Name'];
                    $_SESSION['Role']   = $row['Role'];

                    // Redirect to original page or homepage
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
                $loginError = "No account found with that name, email, or phone number.";
            }
            $stmt->close();
        } else {
            $message = "Query failed: " . $conn->error;
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="styles2.css" rel="stylesheet">
    <style>
        .error { 
            color: hsl(var(--secondary-hue), var(--saturation), 40%); 
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: block;
            background: hsl(var(--secondary-hue), var(--saturation), 95%);
            padding: 0.5rem;
            border-radius: var(--border-radius-sm);
            border-left: 4px solid var(--secondary-color);
        }
    </style>
</head>
<body class="bg-light">
<div class="container">
  <div class="card">
    <div class="card-body">
      <h2>User Login</h2>
      <form method="POST" action="">
        <div class="form-group">
          <label>Name / Email / Phone</label>
          <input type="text" name="identifier" class="form-control" 
                 value="<?php echo htmlspecialchars($_POST["identifier"] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <input type="submit" value="Login" class="btn-primary">
      </form>

      <p class="text-center mt-3">Don't have an account? <a href="Register.php">Register here</a></p>
    </div>
  </div>
</div>
</body>
</html>

