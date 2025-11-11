<?php
session_start();

require_once 'db.php';
require_once 'mail.php';
include 'header.php';

$successMessage = '';
$errorMessage = '';
$emailValue = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim($_POST['email'] ?? '');

    if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT UserID, Name FROM User WHERE Email = ? LIMIT 1');
        if ($stmt === false) {
            $errorMessage = 'We could not process your request right now. Please try again later.';
        } else {
            $stmt->bind_param('s', $emailValue);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                try {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

                    $upsert = $conn->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP'
                    );

                    if ($upsert) {
                        $upsert->bind_param('iss', $user['UserID'], $tokenHash, $expiresAt);
                        $upsert->execute();
                        $upsert->close();

                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                        if ($basePath === '' || $basePath === '.') {
                            $basePath = '';
                        }
                        $resetLink = sprintf(
                            '%s://%s%s/reset_password.php?token=%s',
                            $scheme,
                            $host,
                            $basePath,
                            urlencode($token)
                        );

                        $userName = htmlspecialchars($user['Name'] ?? 'there', ENT_QUOTES, 'UTF-8');
                        $emailBody = "
                            <p>Hi {$userName},</p>
                            <p>We received a request to reset the password for your account. If you made this request, click the button below to choose a new password.</p>
                            <p style=\"text-align:center;\">
                                <a href=\"{$resetLink}\" style=\"display:inline-block;padding:10px 18px;background-color:#222;color:#fff;text-decoration:none;border-radius:4px;\">Reset Password</a>
                            </p>
                            <p>If the button does not work, copy and paste this link into your browser:</p>
                            <p><a href=\"{$resetLink}\">{$resetLink}</a></p>
                            <p>This link will expire in 1 hour. If you did not request a password reset, you can ignore this email.</p>
                            <p>Best regards,<br>The Salon Team</p>
                        ";

                        sendEmail($emailValue, 'Password Reset Request', $emailBody);
                    }
                } catch (Throwable $th) {
                    // Swallow token/crypto/mail errors to avoid leaking details
                }
            }

            $successMessage = 'If an account with that email exists, we\'ve sent a password reset link.';
        }
    }
}
?>

<head>
    <title>Forgot Password</title>
    <link href="styles.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
  <div class="card">
    <div class="card-body">
      <h2>Forgot Password</h2>

      <?php if ($errorMessage): ?>
        <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
      <?php endif; ?>

      <?php if ($successMessage): ?>
        <div class="success"><?php echo htmlspecialchars($successMessage); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label>Email address</label>
          <input type="email" name="email" class="form-control"
                 value="<?php echo htmlspecialchars($emailValue); ?>"
                 placeholder="Enter the email associated with your account" required>
        </div>

        <input type="submit" value="Send reset link" class="btn-primary">
      </form>

      <p class="text-center mt-3">
        <a href="Login.php">Return to login</a>
      </p>
    </div>
  </div>
</div>
</body>

