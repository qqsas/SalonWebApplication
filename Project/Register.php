<?php
include 'db.php';
include 'mail.php'; // PHPMailer setup (sendEmail function)
include 'header.php';

$message = '';
$nameError = '';
$contactError = '';
$passwordError = '';
$confirmPasswordError = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"] ?? '');
    $contact = trim($_POST["contact"] ?? ''); // Combined email/phone input
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    $email = null;
    $number = null;
    $hasError = false;

    if (!$conn) die("Database connection failed: " . mysqli_connect_error());

    // Require name
    if (empty($name)) { $nameError = "Name is required."; $hasError = true; }

    // At least one contact required
    if (empty($contact)) { $contactError = "Please provide either an email or phone number."; $hasError = true; }

    // Determine if contact is email or phone
    if (!empty($contact)) {
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $email = $contact;
        } else {
            // Normalize phone number to +27xxxxxxxxx
            $number = preg_replace('/\D/', '', $contact); // digits only
            if (substr($number, 0, 1) === '0' && strlen($number) === 10) {
                $number = '+27' . substr($number, 1);
            } elseif (substr($number, 0, 2) === '27' && strlen($number) === 11) {
                $number = '+' . $number;
            } elseif (substr($number, 0, 3) === '271') {
                $number = '+' . $number; // already correct
            } else {
                $number = '+27' . $number; // fallback
            }
        }
    }

    // Check email uniqueness
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT 1 FROM User WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) { $contactError = "Email is already in use."; $hasError = true; }
        $stmt->close();
    }

    // Check number uniqueness
    if (!empty($number)) {
        $stmt = $conn->prepare("SELECT 1 FROM User WHERE Number = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) { $contactError = "Phone number is already in use."; $hasError = true; }
        $stmt->close();
    }

    // Password validation
    if (empty($password)) {
        $passwordError = "Password is required."; $hasError = true;
    } else {
        if (strlen($password) < 8) { $passwordError = "Password must be at least 8 characters long."; $hasError = true; }
        if (!preg_match('/[\W_]/', $password)) { 
            $passwordError .= "<br>Password must include at least one special character."; 
            $hasError = true; 
        }
    }

    if ($password !== $confirm_password) { $confirmPasswordError = "Passwords do not match."; $hasError = true; }

    // Insert into database if no errors
    if (!$hasError) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = "client";

        $emailToInsert = $email ?? '';
        $numberToInsert = $number ?? '';

        $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $emailToInsert, $numberToInsert, $hashed_password, $role);

        if ($stmt->execute()) {
            // Send welcome email
            if (!empty($email)) {
                $subject = "Welcome to Our Store!";
                $body = "<p>Hi " . htmlspecialchars($name) . ",</p>
                         <p>Thank you for registering at our store. Your account is now active!</p>
                         <p>We look forward to serving you.</p>";
                $adminEmails = ['store@example.com']; // Optional: notify admin(s)
                sendEmail($email, $subject, $body, $adminEmails);
            }

            $stmt->close();
            $conn->close();

            header("Location: Login.php?msg=Registered");
            exit;
        } else {
            $message = "Insert failed: " . $stmt->error;
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
          <label>Full Name</label>
          <input type="text" name="name" class="form-control" 
                 value="<?= htmlspecialchars($_POST["name"] ?? '') ?>" required>
          <span class="error"><?= htmlspecialchars($nameError) ?></span>
        </div>

        <div class="contact-note">Please provide either an email or a phone number</div>
        <div class="form-group">
          <label>Email or Phone</label>
          <input type="text" name="contact" class="form-control" 
                 value="<?= htmlspecialchars($_POST["contact"] ?? '') ?>" required>
          <span class="error"><?= htmlspecialchars($contactError) ?></span>
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" required>
          <div class="password-requirements">
              <strong>Password must:</strong>
              <ul>
                  <li>Be at least 8 characters long</li>
                  <li>Include at least one special character</li>
              </ul>
          </div>
          <span class="error"><?= htmlspecialchars($passwordError) ?></span>
        </div>

        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
          <span class="error"><?= htmlspecialchars($confirmPasswordError) ?></span>
        </div>

        <input type="submit" value="Register" class="btn-primary">
      </form>

      <p class="text-center mt-3">Already have an account? <a href="Login.php">Login here</a></p>
      <p class="text-center" style="color: var(--accent-color);"><?= htmlspecialchars($message) ?></p>
    </div>
  </div>
</div>
</body>
</html>

