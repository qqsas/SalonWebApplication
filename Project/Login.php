<?php
session_start();
include 'db.php';
include 'header.php';
$message = '';
$loginError = '';

// If no redirect target is set yet, capture the referring page
if (!isset($_SESSION['redirect_after_login']) && isset($_SERVER['HTTP_REFERER'])) {
    // Avoid setting the login page itself as the redirect target
    if (strpos($_SERVER['HTTP_REFERER'], 'Login.php') === false) {
        $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST["identifier"]); // can be name, email, or number
    $password = $_POST["password"];

    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    if (empty($identifier) || empty($password)) {
        $loginError = "Please enter your name, email, or phone number and password.";
    } else {
        // Look up user by Name OR Email OR Number
        $sql = "SELECT UserID, Name, Email, Number, Password, Role FROM User 
                WHERE Name = ? OR Email = ? OR Number = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("sss", $identifier, $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Verify password
                if (password_verify($password, $row['Password'])) {
                    // Store user session
                    $_SESSION['UserID'] = $row['UserID'];
                    $_SESSION['Name']   = $row['Name'];
                    $_SESSION['Role']   = $row['Role'];

                    // Determine where to redirect
                    $redirect = $_SESSION['redirect_after_login'] ?? 'homepage.php';
                    unset($_SESSION['redirect_after_login']); // clear after use

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
    <link href="styles.css" rel="stylesheet">
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
          <span class="error"><?= $loginError ?></span>
        </div>

        <input type="submit" value="Login" class="btn-primary">
      </form>

      <p class="text-center mt-3">Don't have an account? <a href="Register.php">Register here</a></p>
      <p class="text-center" style="color: var(--accent-color);"><?php echo $message; ?></p>
    </div>
  </div>
</div>
</body>
</html>

