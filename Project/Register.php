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
    $contact = trim($_POST["contact"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    $email = null;
    $number = null;
    $hasError = false;

    if (!$conn) die("Database connection failed: " . mysqli_connect_error());

    if (empty($name)) { $nameError = "Name is required."; $hasError = true; }
    if (empty($contact)) { $contactError = "Please provide either an email or phone number."; $hasError = true; }

    // Determine if contact is email or phone
    if (!empty($contact)) {
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $email = $contact;
        } else {
            $number = preg_replace('/\D/', '', $contact);
            if (substr($number, 0, 1) === '0' && strlen($number) === 10) {
                $number = '+27' . substr($number, 1);
            } elseif (substr($number, 0, 2) === '27' && strlen($number) === 11) {
                $number = '+' . $number;
            } elseif (substr($number, 0, 3) === '271') {
                $number = '+' . $number;
            } else {
                $number = '+27' . $number;
            }
        }
    }

    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT 1 FROM User WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) { $contactError = "Email is already in use."; $hasError = true; }
        $stmt->close();
    }

    if (!empty($number)) {
        $stmt = $conn->prepare("SELECT 1 FROM User WHERE Number = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) { $contactError = "Phone number is already in use."; $hasError = true; }
        $stmt->close();
    }

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

    if (!$hasError) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = "client";
        $emailToInsert = $email ?? '';
        $numberToInsert = $number ?? '';

        $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $emailToInsert, $numberToInsert, $hashed_password, $role);

        if ($stmt->execute()) {
            if (!empty($email)) {
                $subject = "Welcome to Our Store!";
                $body = "<p>Hi " . htmlspecialchars($name) . ",</p>
                         <p>Thank you for registering at our store. Your account is now active!</p>
                         <p>We look forward to serving you.</p>";
                $adminEmails = ['store@example.com'];
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
            color: #d9534f;
            font-size: 0.9rem;
            display: block;
            margin-top: 4px;
        }

        .password-wrapper {
          position: relative;
          width: 100%;
        }

        .password-wrapper input {
          width: 100%;
          padding-right: 35px;
          box-sizing: border-box;
        }

        .toggle-eye {
          position: absolute;
          right: 10px;
          top: 50%;
          transform: translateY(-50%);
          cursor: pointer;
          font-size: 18px;
          color: #777;
        }

        .toggle-eye:hover {
          color: #000;
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
          <div class="password-wrapper">
            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            <span class="toggle-eye">👁</span>
          </div>
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

<script>
document.addEventListener("DOMContentLoaded", function () {
  const pass = document.querySelector('input[name="password"]');
  const confirm = document.querySelector('input[name="confirm_password"]');
  const contact = document.querySelector('input[name="contact"]');
  const form = document.querySelector("form");
  const toggle = document.querySelector(".toggle-eye");

  toggle.addEventListener("click", () => {
    const isPassword = pass.type === "password";
    pass.type = confirm.type = isPassword ? "text" : "password";
    toggle.textContent = isPassword ? "🚫" : "👁";
  });

  confirm.addEventListener("input", () => {
    confirm.style.borderColor = confirm.value === pass.value ? "green" : "red";
  });

  contact.addEventListener("input", () => {
    const val = contact.value.trim();
    if (val.includes("@")) contact.style.borderColor = "green";
    else if (/^\d+$/.test(val)) contact.style.borderColor = "blue";
    else contact.style.borderColor = "";
  });

  form.addEventListener("submit", (e) => {
    if (pass.value !== confirm.value) {
      e.preventDefault();
      alert("Passwords do not match!");
    }
  });
});
</script>
</body>
</html>
