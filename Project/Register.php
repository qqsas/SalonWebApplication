<?php
include 'db.php';

$message = '';
$usernameError = '';
$emailError = '';
$passwordError = '';
$confirmPasswordError = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];

    $hasError = false;

    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // Check if email exists
    $check_sql = "SELECT * FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);

    if ($check_stmt) {
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $emailError = "Email is already in use.";
            $hasError = true;
        }

        $check_stmt->close();
    } else {
        $emailError = "Email check failed: " . $conn->error;
        $hasError = true;
    }

    // Password validation
    if (empty($password)) {
        $passwordError = "Password is required.";
        $hasError = true;
    } else {
        if (strlen($password) < 8) {
            $passwordError = "Password must be at least 8 characters long.";
            $hasError = true;
        }

        if (!preg_match('/[\W_]/', $password)) {
            $passwordError .= "<br>Password must include at least one special character.";
            $hasError = true;
        }
    }

    if ($password !== $confirm_password) {
        $confirmPasswordError = "Passwords do not match.";
        $hasError = true;
    }

    if (!$hasError) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $insert_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        if ($insert_stmt) {
            $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            if ($insert_stmt->execute()) {
                $insert_stmt->close();
                $conn->close();
                header("Location: login.php");
                exit;
            } else {
                $message = "Insert error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        } else {
            $message = "Insert prepare failed: " . $conn->error;
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
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
      <h2>User Registration</h2>
      <form method="POST" action="">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" class="form-control" 
                 value="<?php echo htmlspecialchars($_POST["username"] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" class="form-control" 
                 value="<?php echo htmlspecialchars($_POST["email"] ?? '') ?>" required>
          <span class="error"><?= $emailError ?></span>
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" required>
          <span class="error"><?= $passwordError ?></span>
        </div>

        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
          <span class="error"><?= $confirmPasswordError ?></span>
        </div>

        <div class="form-group">
          <label>Role</label>
          <select name="role" class="form-control">
            <option value="buyer" <?php if (($_POST["role"] ?? '') == "buyer") echo "selected"; ?>>Buyer</option>
            <option value="seller" <?php if (($_POST["role"] ?? '') == "seller") echo "selected"; ?>>Seller</option>
          </select>
        </div>

        <input type="submit" value="Register" class="btn-primary">
      </form>

      <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>
      <p class="text-center" style="color: var(--accent-color);"><?php echo $message; ?></p>
    </div>
  </div>
</div>
</body>
</html>
