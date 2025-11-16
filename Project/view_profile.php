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

<<<<<<< HEAD
=======
<style>
/* Profile Page Styling */
.container {
    max-width: 900px;
    margin: 3rem auto;
    padding: 2rem;
    text-align: center;
    font-family: 'Poppins', sans-serif;
}

/* Page Title */
.container h2 {
    font-size: 2rem;
    color: #0c0c0eff; 
    margin-bottom: 2rem;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* Profile Card */
.profile-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    padding: 2.5rem 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
    max-width: 900px; /* make it wider */
}
.profile-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
}

/* Profile Header */
.profile-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 2rem;
}
.profile-avatar {
    background: linear-gradient(135deg, #566bbfff, #4e5cbbff);
    color: #fff;
    font-size: 2.5rem;
    font-weight: 600;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(18, 17, 17, 0.4);
    margin-bottom: 1rem;
    transition: transform 0.3s ease;
}
.profile-avatar:hover {
    transform: scale(1.08);
}
.profile-name {
    font-size: 1.6rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.3rem;
}
.profile-role {
    font-size: 0.95rem;
    color: #777;
    text-transform: capitalize;
}

/* Info Section */
.profile-info {
    margin-top: 1.5rem;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.2rem;
}
.info-item {
    background: #fafafa;
    border-radius: 12px;
    padding: 1rem;
    text-align: left;
    border: 1px solid #eee;
    transition: background 0.3s ease;
}
.info-item:hover {
    background: #fff5f5;
}
.info-label {
    display: block;
    font-weight: 600;
    color: #555;
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
}
.info-value {
    color: #222;
    font-size: 1rem;
}

/* Role Badge */
.role-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    color: #fff;
}
.role-admin {
    background: #d32f2f;
}
.role-customer {
    background: #2e7d32;
}
.role-staff {
    background: #1976d2;
}

.role-manager{
    background: blue;
}
/* Member Since */
.member-since {
    font-style: italic;
    color: #666;
}

/* Profile Actions */
.profile-actions {
    margin-top: 2rem;
}
.profile-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #4b6bccff;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0.8rem 1.6rem;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: background 0.3s ease, transform 0.3s ease;
}
.profile-actions .btn:hover {
    background: #1f1abaff;
    transform: scale(1.05);
}
.profile-actions svg {
    transition: transform 0.3s ease;
}
.profile-actions .btn:hover svg {
    transform: translateX(3px);
}

/* Responsive */
@media (max-width: 600px) {
    .profile-card {
        padding: 1.5rem;
    }
    .profile-avatar {
        width: 70px;
        height: 70px;
        font-size: 2rem;
    }
    .container h2 {
        font-size: 1.5rem;
    }
}


</style>
>>>>>>> main

<div class="container">
    <i class="section-icon fa-solid fa-user fa-5x"></i>
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
