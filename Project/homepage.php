<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
include 'header.php';

// Get Services
$stmt = $conn->prepare("SELECT * FROM Services WHERE IsDeleted = 0");
$stmt->execute();
$result = $stmt->get_result();
$getServices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get Barbers (staff)
$stmt = $conn->prepare("SELECT * FROM Barber");
$stmt->execute();
$result = $stmt->get_result();
$getStaff = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch the user's role
$user_role = isset($_SESSION['UserID']) ? $_SESSION['Role'] : null;

// Handle contact form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $number = trim($_POST['number']);
    $message = trim($_POST['message']);

    $userID = isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null;

    // Insert guest if no user logged in
    if (!$userID) {
        $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, ?, '', 'guest')");
        $stmt->bind_param("sss", $name, $email, $number);
        if ($stmt->execute()) {
            $userID = $stmt->insert_id;
        }
        $stmt->close();
    }

    // Insert into Contact table
    $stmt = $conn->prepare("INSERT INTO Contact (UserID, Message, ContactInfo) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userID, $message, $email);
    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Home - My Business</title>
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
        <div class="service-card" onclick="window.location.href='service-detail.php?ServicesID=<?php echo urlencode($service['ServicesID']); ?>'">
          <div class="service-image"></div>
          <div class="card-content">
            <h3><?php echo htmlspecialchars($service['Name']); ?></h3>
            <p><?php echo htmlspecialchars($service['Description']); ?></p>
            <a href="service-detail.php?ServicesID=<?php echo urlencode($service['ServicesID']); ?>" class="read-more">Learn more</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Staff Section -->
  <section class="staff">
    <h2>Meet Our Team</h2>
    <div class="staff-list">
      <?php foreach ($getStaff as $staff): ?>
        <div class="staff-card" onclick="window.location.href='staff-detail.php?BarberID=<?php echo urlencode($staff['BarberID']); ?>'">
          <div class="staff-image"></div>
          <div class="card-content">
            <h3><?php echo htmlspecialchars($staff['Name']); ?></h3>
            <p><?php echo htmlspecialchars($staff['Bio']); ?></p>
            <a href="staff-detail.php?BarberID=<?php echo urlencode($staff['BarberID']); ?>" class="read-more">View profile</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Contact Section -->
  <section class="contact">
    <h2>Get in Touch</h2>
    <p>Have a question? Reach out to us today.</p>

    <div class="contact-container">
      <!-- Contact Form -->
      <div class="contact-form">
        <form action="" method="POST">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" required>

          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>

          <label for="number">Phone Number</label>
          <input type="text" id="number" name="number" required>

          <label for="message">Message</label>
          <textarea id="message" name="message" rows="5" required></textarea>

          <button type="submit">Send Message</button>
        </form>
      </div>

      <!-- External Contact Links -->
      <div class="contact-links">
        <h3>Other Ways to Reach Us</h3>
        <div class="link-grid">
          <a href="https://wa.me/1234567890" target="_blank">
            <img src="Img/Whatsapp.png" alt="WhatsApp Logo">
            WhatsApp
          </a>
          <a href="https://www.facebook.com/YourPage" target="_blank">
            <img src="Img/FaceBook.png" alt="Facebook Logo">
            Facebook
          </a>
          <a href="https://www.instagram.com/YourPage" target="_blank">
            <img src="Img/Instagram.png" alt="Instagram Logo">
            Instagram
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <p>&copy; <?php echo date("Y"); ?> My Business. All Rights Reserved.</p>
  </footer>
</body>
</html>

