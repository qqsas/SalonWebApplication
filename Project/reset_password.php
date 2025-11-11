<?php
session_start();

include 'db.php';
include 'header.php';

$token = $_GET['token'] ?? '';
$errorMessage = '';
$successMessage = '';

function sanitizeToken(string $token): string
{
    return preg_match('/^[A-Fa-f0-9]{64}$/', $token) ? $token : '';
}

function findResetUser(mysqli $conn, string $tokenHash): ?array
{
    $stmt = $conn->prepare(
        'SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function deleteTokenForUser(mysqli $conn, int $userId): void

{
    $delete = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
    if ($delete) {
        $delete->bind_param('i', $userId);
        $delete->execute();
        $delete->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = sanitizeToken($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($token === '') {
        $errorMessage = 'Reset link is invalid or has expired.';
    } elseif ($password === '' || $confirmPassword === '') {
        $errorMessage = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $errorMessage = 'Password must be at least 8 characters long.';
    } else {
        $tokenHash = hash('sha256', $token);
        $resetRow = findResetUser($conn, $tokenHash);

        if (!$resetRow) {
            $errorMessage = 'Reset link is invalid or has expired.';
        } else {
            $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE User SET Password = ? WHERE UserID = ? LIMIT 1');

            if ($update) {
                $update->bind_param('si', $newPasswordHash, $resetRow['user_id']);
                if ($update->execute()) {
                    deleteTokenForUser($conn, (int)$resetRow['user_id']);
                    $successMessage = 'Your password has been reset. You can now log in with your new password.';
                    $token = '';
                } else {
                    $errorMessage = 'We could not reset your password. Please try again.';
                }
                $update->close();
            } else {
                $errorMessage = 'We could not reset your password. Please try again.';
            }
        }
    }
} else {
    $token = sanitizeToken($token);
    if ($token !== '') {
        $tokenHash = hash('sha256', $token);
        $resetRow = findResetUser($conn, $tokenHash);
        if (!$resetRow) {
            $errorMessage = 'Reset link is invalid or has expired.';
            $token = '';
        }
    } else {
        $errorMessage = 'Reset link is invalid or has expired.';
    }
}
?>

<head>
    <title>Reset Password</title>
    <link href="styles.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
  <div class="card">
    <div class="card-body">
      <h2>Reset Password</h2>

      <?php if ($errorMessage): ?>
        <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
      <?php endif; ?>

      <?php if ($successMessage): ?>
        <div class="success"><?php echo htmlspecialchars($successMessage); ?></div>
        <p class="text-center mt-3">
          <a href="Login.php">Return to login</a>
        </p>
      <?php endif; ?>

      <?php if (!$successMessage && $token !== ''): ?>
        <form method="POST" action="">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

          <div class="form-group">
            <label>New password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter a new password" required>
          </div>

          <div class="form-group">
            <label>Confirm new password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter the new password" required>
          </div>

          <input type="submit" value="Update password" class="btn-primary">
        </form>
      <?php elseif (!$successMessage): ?>
        <p class="text-center mt-3">
          <a href="forgot_password.php">Request a new reset link</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!$successMessage && $token !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordField = document.querySelector('input[name="password"]');
    const confirmField = document.querySelector('input[name="confirm_password"]');

    function toggleVisibility(event) {
        const target = event.target.dataset.target;
        const input = document.querySelector(target);
        if (input) {
            input.type = event.target.checked ? 'text' : 'password';
        }
    }

    const togglePassword = document.createElement('label');
    togglePassword.style.display = 'block';
    togglePassword.style.marginTop = '0.5rem';
    togglePassword.innerHTML = '<input type="checkbox" data-target="input[name=&quot;password&quot;]"> Show password';
    passwordField.parentNode.appendChild(togglePassword);

    const toggleConfirm = document.createElement('label');
    toggleConfirm.style.display = 'block';
    toggleConfirm.style.marginTop = '0.5rem';
    toggleConfirm.innerHTML = '<input type="checkbox" data-target="input[name=&quot;confirm_password&quot;]"> Show confirm password';
    confirmField.parentNode.appendChild(toggleConfirm);

    togglePassword.querySelector('input').addEventListener('change', toggleVisibility);
    toggleConfirm.querySelector('input').addEventListener('change', toggleVisibility);
});
</script>
<?php endif; ?>
</body>

