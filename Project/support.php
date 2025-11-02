<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
include 'mail.php'; // PHPMailer setup

// Handle AJAX contact form submission before including header.php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax'])) {
    $name = trim($_POST['name']);
    $contactInfo = trim($_POST['contact']); // could be email or phone
    $message_text = trim($_POST['message']);

    $error = '';
    $success = '';
    $userID = isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null;

    // --- Validate Name ---
    if (empty($name)) {
        $error = "Please enter your full name.";
    }

    // --- Validate Contact Info ---
    elseif (empty($contactInfo)) {
        $error = "Please provide your email or phone number.";
    } else {
        // Check if it's an email
        if (filter_var($contactInfo, FILTER_VALIDATE_EMAIL)) {
            if (!str_contains($contactInfo, '@') || !str_contains($contactInfo, '.')) {
                $error = "Email must contain both '@' and '.' symbols.";
            }
            $isEmail = true;
            $isPhone = false;
        } 
        // Or check if it's a phone number
        else {
            // Remove all non-digit characters except leading +
            $cleanedNumber = preg_replace('/\s+/', '', $contactInfo); // Remove spaces first
            $digitsOnly = preg_replace('/\D/', '', $cleanedNumber); // Remove all non-digits
            
            // Check if the original had a + at the beginning
            $hasPlus = (strpos($cleanedNumber, '+') === 0);
            
            // --- South African phone number standardization ---
            if (preg_match('/^0\d{9}$/', $digitsOnly)) {
                // Starts with 0 (10 digits) → convert to +27
                $contactInfo = '+27' . substr($digitsOnly, 1);
                $isPhone = true;
                $isEmail = false;
            } elseif (preg_match('/^27\d{9}$/', $digitsOnly)) {
                // Starts with 27 (11 digits) → ensure it starts with +
                $contactInfo = '+' . $digitsOnly;
                $isPhone = true;
                $isEmail = false;
            } elseif (preg_match('/^\+27\d{9}$/', $cleanedNumber)) {
                // Already in correct +27 format (12 characters including +)
                $contactInfo = $cleanedNumber;
                $isPhone = true;
                $isEmail = false;
            } elseif (preg_match('/^\+?[1-9]\d{6,14}$/', $cleanedNumber)) {
                // Other valid international number (7-15 digits after country code)
                if (!$hasPlus) {
                    $contactInfo = '+' . $digitsOnly;
                } else {
                    $contactInfo = $cleanedNumber;
                }
                $isPhone = true;
                $isEmail = false;
            } else {
                $error = "Please enter a valid email address or phone number (e.g., 0123456789, +27123456789, or international format).";
                $isEmail = false;
                $isPhone = false;
            }

            // Final safety check for valid phone length
            if ($isPhone) {
                $finalDigits = preg_replace('/\D/', '', $contactInfo);
                if (strlen($finalDigits) < 10 || strlen($finalDigits) > 15) {
                    $error = "Phone number must be between 10 and 15 digits.";
                    $isPhone = false;
                }
            }
        }
    }

    // --- Validate Message ---
    if (empty($message_text) && !$error) {
        $error = "Please enter a message before submitting.";
    }

    // --- Proceed if no errors ---
    if (empty($error)) {
        // Create guest user if not logged in
        if (!$userID) {
            $email = $isEmail ? $contactInfo : '';
            $number = $isPhone ? $contactInfo : '';
            $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, ?, '', 'guest')");
            $stmt->bind_param("sss", $name, $email, $number);
            if ($stmt->execute()) {
                $userID = $stmt->insert_id;
            }
            $stmt->close();
        }

        // Save contact message
        $stmt = $conn->prepare("INSERT INTO Contact (UserID, Message, ContactInfo) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userID, $message_text, $contactInfo);
        if ($stmt->execute()) {
            $success = "Your message has been sent successfully!";
        } else {
            $error = "There was an error sending your message. Please try again.";
        }
        $stmt->close();

        // Send email notification
        if (empty($error)) {
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
    }

    // --- Response Output ---
    if ($error) {
        echo '<p class="error">' . htmlspecialchars($error) . '</p>';
    } elseif ($success) {
        echo '<p class="success">' . htmlspecialchars($success) . '</p>';
    }
    exit;
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact - My Business</title>
  <link rel="stylesheet" href="styles2.css">
  <style>
    .error { color: red; margin-bottom: 15px; }
    .success { color: green; margin-bottom: 15px; }
  </style>
</head>
<body>
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

    <!-- External Links -->
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

<footer>
  <p>&copy; <?php echo date("Y"); ?> My Business. All Rights Reserved.</p>
</footer>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const name = form.name.value.trim();
    const contact = form.contact.value.trim();
    const message = form.message.value.trim();
    const formMessages = document.getElementById('form-messages');

    // Clear previous messages
    formMessages.innerHTML = '';

    if (!name) {
        formMessages.innerHTML = '<p class="error">Please enter your full name.</p>';
        return;
    }

    // Enhanced validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    // Phone regex that accepts various formats
    const phoneRegex = /^(\+?\d{1,4}?[-.\s]?)?(\(?\d{1,4}?\)?[-.\s]?)?[\d\s.-]{7,15}$/;
    
    // Check if it's a valid email OR phone
    const isEmail = emailRegex.test(contact);
    const isPhone = phoneRegex.test(contact);
    
    if (!isEmail && !isPhone) {
        formMessages.innerHTML = '<p class="error">Please enter a valid email or phone number (e.g., 0123456789, +27123456789, or international format).</p>';
        return;
    }

    if (!message) {
        formMessages.innerHTML = '<p class="error">Please enter a message.</p>';
        return;
    }

    const formData = new FormData(form);
    formData.append('ajax', '1');

    fetch('', { method: 'POST', body: formData })
    .then(response => response.text())
    .then(html => {
        formMessages.innerHTML = html;
        if (html.includes('successfully')) form.reset();
        document.getElementById('contact-section').scrollIntoView({ behavior: 'smooth' });
    })
    .catch(err => {
        console.error('Error:', err);
        formMessages.innerHTML = '<p class="error">There was an error sending your message. Please try again.</p>';
    });
});
</script>
</body>
</html>
