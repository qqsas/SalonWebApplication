<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
include 'mail.php'; // PHPMailer setup

// Handle AJAX contact form submission **before including header.php**
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax'])) {
    $name = trim($_POST['name']);
    $contactInfo = trim($_POST['contact']); // combined email/phone
    $message_text = trim($_POST['message']);

    $error = '';
    $success = '';
    $userID = isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null;

    if (empty($contactInfo)) {
        $error = "Please provide your email or phone number.";
    } else {
        // Insert guest if no user logged in
        if (!$userID) {
            $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, '', '', 'guest')");
            $stmt->bind_param("ss", $name, $contactInfo);
            if ($stmt->execute()) {
                $userID = $stmt->insert_id;
            }
            $stmt->close();
        }

        // Insert into Contact table
        $stmt = $conn->prepare("INSERT INTO Contact (UserID, Message, ContactInfo) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userID, $message_text, $contactInfo);
        if ($stmt->execute()) {
            $success = "✅ Your message has been sent successfully!";
        } else {
            $error = "❌ There was an error sending your message. Please try again.";
        }
        $stmt->close();

        // Send email notification
        $mail = getMailer();
        if ($mail) {
            try {
                $mail->addAddress('support@yourbusiness.com', 'Support Team');
                $mail->Subject = "New Contact Message from $name";
                $mail->Body = "Name: $name\nContact: $contactInfo\nMessage:\n$message_text";
                $mail->send();
            } catch (Exception $e) {
                error_log("Mail Exception: " . $e->getMessage());
            }
        }
    }

    if ($error) {
        echo '<p class="error">' . htmlspecialchars($error) . '</p>';
    } elseif ($success) {
        echo '<p class="success">' . htmlspecialchars($success) . '</p>';
    }
    exit; // important: stops header.php and full page from loading
}

// --- Now include header.php for normal page loads ---
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact - My Business</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .error { color: red; margin-bottom: 15px; }
    .success { color: green; margin-bottom: 15px; }
  </style>
</head>
<body>
  <!-- Contact Section -->
  <section class="contact" id="contact-section">
    <h2>Get in Touch</h2>
    <p>Have a question? Reach out to us today.</p>

    <div class="contact-container">
      <!-- Contact Form -->
      <div class="contact-form">
        <div id="form-messages"></div>
        <form id="contactForm">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" required>

          <label for="contact">Email or Phone Number</label>
          <input type="text" id="contact" name="contact" placeholder="Enter your email or phone number" required>

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
            <img src="Img/Whatsapp.png" alt="WhatsApp Logo"> WhatsApp
          </a>
          <a href="https://www.facebook.com/YourPage" target="_blank">
            <img src="Img/FaceBook.png" alt="Facebook Logo"> Facebook
          </a>
          <a href="https://www.instagram.com/YourPage" target="_blank">
            <img src="Img/Instagram.png" alt="Instagram Logo"> Instagram
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <p>&copy; <?php echo date("Y"); ?> My Business. All Rights Reserved.</p>
  </footer>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('ajax', '1'); // mark AJAX request

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        const formMessages = document.getElementById('form-messages');
        formMessages.innerHTML = html;

        if (html.includes('✅')) {
            form.reset();
        }

        document.getElementById('contact-section').scrollIntoView({ behavior: 'smooth' });
    })
    .catch(err => console.error('Error:', err));
});
</script>
</body>
</html>

