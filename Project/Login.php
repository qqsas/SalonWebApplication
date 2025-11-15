<?php
session_start();

include 'db.php';
include 'header.php';
include_once 'mail.php';

$message = '';
$loginError = '';

if (!function_exists('login_mask_email')) {
    function login_mask_email(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        [$localPart, $domain] = explode('@', $email);
        $visible = substr($localPart, 0, 2);
        $maskedLocal = $visible . str_repeat('*', max(strlen($localPart) - 2, 0));
        return $maskedLocal . '@' . $domain;
    }
}

if (!function_exists('login_mask_number')) {
    function login_mask_number(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        $lastFour = substr($digits, -4);
        return '•••• ' . $lastFour;
    }
}

if (!function_exists('login_describe_destination')) {
    function login_describe_destination(array $user): string
    {
        if (!empty($user['Email']) && filter_var($user['Email'], FILTER_VALIDATE_EMAIL)) {
            return login_mask_email($user['Email']);
        }
        if (!empty($user['Number'])) {
            return login_mask_number($user['Number']);
        }
        return 'your registered contact information';
    }
}

if (!function_exists('login_send_otp')) {
    function login_send_otp(array $user, string $otp): bool
    {
        $name = $user['Name'] ?? 'there';
        $subject = 'Your one-time login code';
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = "<p>Hi {$safeName},</p>"
            . "<p>Your one-time passcode for Salon Web Application is "
            . "<strong>{$otp}</strong>.</p>"
            . "<p>It expires in 2 minutes. If you did not try to sign in, please ignore this email.</p>"
            . "<p>Thank you,<br>Salon Web Application</p>";

        if (!empty($user['Email']) && filter_var($user['Email'], FILTER_VALIDATE_EMAIL)) {
            return sendEmail($user['Email'], $subject, $body);
        }

        return false;
    }
}

if (isset($_GET['restart']) && $_GET['restart'] === '1') {
    unset($_SESSION['pending_login']);
}

// Handle redirect logic
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Only set redirect on GET requests (when initially loading the login page)
    if (isset($_GET['redirect'])) {
        // If redirect parameter is provided (forced login), use that
        $_SESSION['redirect_after_login'] = $_GET['redirect'];
    } elseif (!isset($_SESSION['redirect_after_login']) && isset($_SERVER['HTTP_REFERER'])) {
        // If no redirect set and we have a referer, check if it's not the login page itself
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
        $current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (basename($referer) !== basename($current) &&
            strpos($_SERVER['HTTP_REFERER'], 'Login.php') === false) {
            $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'];
        }
    }

    // Default fallback if nothing else is set
    if (!isset($_SESSION['redirect_after_login'])) {
        $_SESSION['redirect_after_login'] = 'index.php';
    }
}

$pendingLogin = $_SESSION['pending_login'] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stage = $_POST['stage'] ?? ($pendingLogin ? 'otp' : 'credentials');

    if ($stage === 'otp') {
        $enteredOtp = trim($_POST['otp'] ?? '');
        if (empty($pendingLogin)) {
            $loginError = "Your verification session has expired. Please log in again.";
        } elseif ($enteredOtp === '') {
            $loginError = "Please enter the verification code.";
        } elseif (!ctype_digit($enteredOtp) || strlen($enteredOtp) !== 6) {
            $loginError = "The verification code must contain exactly 6 digits.";
        } elseif (time() > ($pendingLogin['expires_at'] ?? 0)) {
            $loginError = "The verification code has expired. Please start a new login.";
            unset($_SESSION['pending_login']);
            $pendingLogin = null;
        } elseif (!password_verify($enteredOtp, $pendingLogin['otp_hash'] ?? '')) {
            $loginError = "Invalid verification code. Please try again.";
        } else {
            // Successful verification
            $_SESSION['UserID'] = $pendingLogin['UserID'];
            $_SESSION['Name']   = $pendingLogin['Name'];
            $_SESSION['Role']   = $pendingLogin['Role'];

            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['pending_login']);
            unset($_SESSION['redirect_after_login']);

            header("Location: " . $redirect);
            exit;
        }
    } else {
        $identifier = trim($_POST["identifier"] ?? ''); // can be email or phone only
        $password = $_POST["password"] ?? '';

        if (!$conn) {
            die("Database connection failed: " . mysqli_connect_error());
        }

        if ($identifier === '' || $password === '') {
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
                        $numberVariants[] = '+' . $identifier;               // +27xxxxxxxxx
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
                            $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
                            $expiresAt = time() + 120; // 2 minutes

                            $deliverySuccess = login_send_otp($row, $otpCode);
                            $destination = login_describe_destination($row);

                            $infoMessage = $deliverySuccess
                                ? "We sent a 6-digit verification code to {$destination}. Enter the code below within 2 minutes."
                                : "We could not deliver the verification code via email. Use this one-time code: {$otpCode}. It expires in 2 minutes.";

                            $_SESSION['pending_login'] = [
                                'UserID'      => $row['UserID'],
                                'Name'        => $row['Name'],
                                'Role'        => $row['Role'],
                                'Email'       => $row['Email'],
                                'Number'      => $row['Number'],
                                'otp_hash'    => $otpHash,
                                'expires_at'  => $expiresAt,
                                'info'        => $infoMessage,
                                'created_at'  => time(),
                            ];

                            $pendingLogin = $_SESSION['pending_login'];
                            $message = $infoMessage;
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
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }
} else {
    if (!empty($pendingLogin['info'])) {
        $message = $pendingLogin['info'];
    }
}

$showOtpForm = isset($_SESSION['pending_login']);
?>

<head>
    <title>Login</title>
    <link href="styles.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <i class="fa-solid fa-user section-icon fa-5x"></i>
  <div class="card">
    <div class="card-body">
      <h2>User Login</h2>
      
      <?php if ($loginError): ?>
        <div class="error"><?php echo $loginError; ?></div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="info"><?php echo $message; ?></div>
      <?php endif; ?>

      <?php if ($showOtpForm): ?>
        <form method="POST" action="">
          <input type="hidden" name="stage" value="otp">
          <div class="form-group">
            <label>Verification Code</label>
            <input type="text" name="otp" class="form-control"
                   pattern="\d{6}" maxlength="6" inputmode="numeric"
                   placeholder="Enter the 6-digit code" required>
            <small style="color: #666; font-size: 0.875rem;">
              Enter the 6-digit code sent to your contact. Code expires after 2 minutes.
            </small>
          </div>
          <div class="form-action-row">
            <input type="submit" value="Verify Code" class="btn-primary">
          </div>
        </form>
        <form method="GET" action="" style="margin-top: 1rem;">
          <input type="hidden" name="restart" value="1">
          <button type="submit" class="btn-secondary">Use a different account</button>
        </form>
      <?php else: ?>
        <form method="POST" action="">
          <input type="hidden" name="stage" value="credentials">
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
            <input type="checkbox" id="toggleLoginPassword"> Show Password
          </div>

          <div class="form-action-row">
            <input type="submit" value="Login" class="btn-primary">
            <a href="forgot_password.php" class="link-small btn">Forgot your password?</a>
          </div>
        </form>
      <?php endif; ?>

      <p class="text-center mt-3">Don't have an account? </p>
      <a href="Register.php" class="btn">Register here</a>
    </div>
  </div>
</div>
<script>
const togglePassword = document.getElementById('toggleLoginPassword');
if (togglePassword) {
    togglePassword.addEventListener('change', function() {
        const passwordField = document.querySelector('input[name="password"]');
        if (passwordField) {
            passwordField.type = this.checked ? 'text' : 'password';
        }
    });
}
</script>
<?php 
include 'footer.php';  
?>
</body>
