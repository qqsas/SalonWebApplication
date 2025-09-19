<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
include 'header.php';

//Get Services
$stmt = $conn->prepare("SELECT s.* FROM Services s");
$stmt->execute();
$result = $stmt->get_result();
$getServices = $result->fetch_all(MYSQLI_ASSOC);

// Get Staff
$stmt = $conn->prepare("SELECT s.* FROM Admin s");
$stmt->execute();
$result = $stmt->get_result();
$getStaff = $result->fetch_all(MYSQLI_ASSOC);

//Space for contact if needed

// Fetch the user's role
$user_role = isset($_SESSION['UserID']) ? $_SESSION['Role'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Home - My Business</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <!-- Hero Section -->
  <section class="hero">
    <h2>Your trusted partner in quality services</h2>
    <p>We offer professional solutions tailored to your needs.</p>
    <a href="services.php" class="btn">Explore Services</a>
  </section>

  <!-- Services Section -->
  <section class="services">
    <h2>Our Services</h2>
    <div class="service-list">
      <?php foreach ($getServices as $service): ?>
        <div class="service-card">
          <h3><?php echo htmlspecialchars($service['Name']); ?></h3>
          <p><?php echo htmlspecialchars($service['Description']); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Staff Section -->
  <section class="staff">
    <h2>Meet Our Team</h2>
    <div class="staff-list">
      <?php foreach ($getStaff as $staff): ?>
        <div class="staff-card">
          <h3><?php echo htmlspecialchars($staff['FirstName'] . " " . $staff['LastName']); ?></h3>
          <p>Role: <?php echo htmlspecialchars($staff['Role']); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Contact Section -->
  <section class="contact">
    <h2>Get in Touch</h2>
    <p>Have a question? Reach out to us today.</p>
    <a href="contact.php" class="btn">Contact Us</a>
  </section>

  <!-- Footer -->
  <footer>
    <p>&copy; <?php echo date("Y"); ?> My Business. All Rights Reserved.</p>
  </footer>
</body>
</html>

