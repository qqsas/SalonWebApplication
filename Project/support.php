<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
include 'header.php';

// Fetch the user's role
$user_role = isset($_SESSION['UserID']) ? $_SESSION['Role'] : null;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $number = trim($_POST['number']);
    $message = trim($_POST['message']);

    $userID = isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null;

    // Insert guest if no user logged in
    if (!$userID) {
        $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role)
                                VALUES (?, ?, ?, '', 'guest')");
        $stmt->bind_param("sss", $name, $email, $number);
        if ($stmt->execute()) {
            $userID = $stmt->insert_id;
        }
    }

    // Insert into Contact table
    $stmt = $conn->prepare("INSERT INTO Contact (UserID, Message, ContactInfo) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userID, $message, $email);
    $stmt->execute();

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Home - My Business</title>
</head>
<body>
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
