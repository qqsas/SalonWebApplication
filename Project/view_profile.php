<?php
session_start();
include 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.php");
    exit();
}

$user_id = $_SESSION['UserID'];

// Fetch user info
$stmt = $conn->prepare("SELECT Name, Email, Number, Role, CreatedAt FROM User WHERE UserID = ? AND IsDeleted = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $number, $role, $createdAt);
$stmt->fetch();
$stmt->close();
?>

<?php include 'header.php'; ?>

<div class="container">
    <link rel="stylesheet" href="styles2.css">
    <h2>My Profile</h2>

    <!-- Modern Card Layout -->
    <div class="profile-card">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($name, 0, 1)); ?>
            </div>
            <h3 class="profile-name"><?php echo htmlspecialchars($name); ?></h3>
            <p class="profile-role"><?php echo htmlspecialchars($role); ?></p>
        </div>

        <!-- Profile Information -->
        <div class="profile-info">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Email Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($email); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($number); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Role</span>
                    <span class="info-value role-badge role-<?php echo strtolower($role); ?>">
                        <?php echo htmlspecialchars($role); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value member-since"><?php echo htmlspecialchars($createdAt); ?></span>
                </div>
            </div>
        </div>

        <!-- Profile Actions -->
        <div class="profile-actions">
            <a href="edit_profile.php" class="btn">
                <span>Edit Profile</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                </svg>
            </a>
        </div>
    </div>

    <!-- Alternative: Keep this commented out if you prefer the modern card layout -->
    <!--
    <table class="profile-table">
        <tr>
            <th>Name</th>
            <td><?php echo htmlspecialchars($name); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo htmlspecialchars($email); ?></td>
        </tr>
        <tr>
            <th>Phone Number</th>
            <td><?php echo htmlspecialchars($number); ?></td>
        </tr>
        <tr>
            <th>Role</th>
            <td>
                <span class="role-badge role-<?php echo strtolower($role); ?>">
                    <?php echo htmlspecialchars($role); ?>
                </span>
            </td>
        </tr>
        <tr>
            <th>Member Since</th>
            <td class="member-since"><?php echo htmlspecialchars($createdAt); ?></td>
        </tr>
    </table>
    -->
</div>

<?php include 'footer.php'; ?>
