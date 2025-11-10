<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
include 'header.php';

// Get Services
$getServices = [];
$stmt = $conn->prepare("SELECT * FROM Services WHERE IsDeleted = 0");
if ($stmt) {
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result) {
    $getServices = $result->fetch_all(MYSQLI_ASSOC);
  }
  $stmt->close();
} else {
  error_log("Services query prepare failed: " . $conn->error);
}

// Get Barbers (staff)
$getStaff = [];
$stmt = $conn->prepare("SELECT * FROM Barber");
if ($stmt) {
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result) {
    $getStaff = $result->fetch_all(MYSQLI_ASSOC);
  }
  $stmt->close();
} else {
  error_log("Barber query prepare failed: " . $conn->error);
}

// Fetch the user's role
$user_role = isset($_SESSION['UserID']) ? $_SESSION['Role'] : null;
?>

<head>
  <meta charset="UTF-8">
  <title>Home - My Business</title>
  
  <style>
    .error { color: red; margin-bottom: 15px; }
    .success { color: green; margin-bottom: 15px; }
  </style>
</head>
<body>

  <!-- Hero Section -->
  <section class="hero">
    <h2>KUMAR KAILEY HAIR & BEAUTY SALON</h2>
    <p>We offer professional solutions tailored to your needs.</p>
    <a href="services.php" class="btn">Explore Services</a>
  </section>


<!-- Services Section -->
<section class="services">
  <h2>Our Services</h2>

  <div class="scroll-container">
    <button class="scroll-btn left" onclick="scrollServices(-1)">&#10094;</button>

    <div class="service-list" id="serviceList">
      <?php foreach ($getServices as $service): ?>
        <div class="service-cardindex" onclick="window.location.href='service-detail.php?ServicesID=<?php echo urlencode($service['ServicesID']); ?>'">
          <div class="service-imageindex" style="background-image: url('<?php echo ($service['ImgUrl']) ? htmlspecialchars($service['ImgUrl']) : 'Img/default-image.jpg'; ?>')"></div>
          <div class="card-content">
            <h3><?php echo htmlspecialchars($service['Name']); ?></h3>
            <p><?php echo htmlspecialchars($service['Description']); ?></p>
            <a href="service-detail.php?ServicesID=<?php echo urlencode($service['ServicesID']); ?>" class="read-more">Learn more</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <button class="scroll-btn right" onclick="scrollServices(1)">&#10095;</button>
  </div>
</section>

<!-- Gallery Section -->
  <?php
  // Get Gallery items
  $getGallery = [];
  $stmt = $conn->prepare("SELECT * FROM Gallery WHERE IsDeleted = 0 ORDER BY CreatedAt DESC");
  if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
      $getGallery = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
  } else {
    error_log("Gallery query prepare failed: " . $conn->error);
  }
  ?>

<section class="gallery">
    <h2>Our Gallery</h2>
    <div class="gallery-list">
      <?php if (empty($getGallery)): ?>
        <p>No gallery images available yet. Please check back later.</p>
      <?php else: ?>
        <?php foreach ($getGallery as $item): ?>
          <div class="gallery-card">
            <div class="gallery-image" 
                 style="background-image: url('<?php echo htmlspecialchars($item['ImageUrl']); ?>');">
            </div>
            <div class="card-content">
              <h3><?php echo htmlspecialchars($item['Title'] ?? 'Untitled'); ?></h3>
              <p><?php echo htmlspecialchars($item['Description'] ?? ''); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

<!-- Staff Section -->
<section class="staff">
  <h2>Meet Our Team</h2>
  <div class="staff-list">
    <?php foreach ($getStaff as $staff): ?>
      <div class="staff-card" onclick="window.location.href='staff-detail.php?BarberID=<?php echo urlencode($staff['BarberID']); ?>'">
        <div class="staff-image" style="background-image: url('<?php echo ($staff['ImgUrl']) ? htmlspecialchars($staff['ImgUrl']) : 'default-staff.jpg'; ?>')"></div>
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

<?php
include 'footer.php';
?>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent page reload

    const form = e.target;
    const formData = new FormData(form);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Temporary div to parse response
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;

        // Extract messages
        const errorMsg = tempDiv.querySelector('.error');
        const successMsg = tempDiv.querySelector('.success');

        const formMessages = document.getElementById('form-messages');
        formMessages.innerHTML = '';

        if (errorMsg) {
            formMessages.innerHTML = `<p class="error">${errorMsg.textContent}</p>`;
        } else if (successMsg) {
            formMessages.innerHTML = `<p class="success">${successMsg.textContent}</p>`;
            form.reset(); // Clear form
        }

        // Scroll to contact section (optional)
        document.getElementById('contact-section').scrollIntoView({ behavior: 'smooth' });
    })
    .catch(err => console.error('Error:', err));
});
</script>

<script>
const serviceList = document.getElementById('serviceList');
const cards = Array.from(serviceList.children);
const cardWidth = 320; // 300px width + ~20px gap

// Clone cards to simulate infinite loop
cards.forEach(card => {
  const clone = card.cloneNode(true);
  serviceList.appendChild(clone);
});

let scrollPosition = 0;

function scrollServices(direction) {
  const totalCards = cards.length;
  const totalWidth = cardWidth * totalCards;

  scrollPosition += direction * cardWidth * 2; // scroll 2 cards per click

  // Loop logic
  if (scrollPosition < 0) {
    scrollPosition = totalWidth - (cardWidth * 3);
  } else if (scrollPosition >= totalWidth * 2 - (cardWidth * 2)) {
    scrollPosition = totalWidth - (cardWidth);
  }

  serviceList.scrollTo({
    left: scrollPosition,
    behavior: 'smooth'
  });
}
</script>
</body>
</html>

<?php
// Handle contact form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $contactInfo = trim($_POST['contact']);
    $message_text = trim($_POST['message']);

    $error = '';
    $success = '';

    if (empty($contactInfo)) {
        $error = "Please provide your email or phone number.";
    } else {
        $userID = isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null;

        if (!$userID) {
            $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, '', '', 'guest')");
            $stmt->bind_param("ss", $name, $contactInfo);
            if ($stmt->execute()) {
                $userID = $stmt->insert_id;
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO Contact (UserID, Message, ContactInfo) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userID, $message_text, $contactInfo);
        if ($stmt->execute()) {
            $success = "Your message has been sent successfully!";
        } else {
            $error = "There was an error sending your message. Please try again.";
        }
        $stmt->close();
    }

    // Output messages directly for AJAX
    if ($error) {
        echo '<p class="error">' . htmlspecialchars($error) . '</p>';
    } elseif ($success) {
        echo '<p class="success">' . htmlspecialchars($success) . '</p>';
    }
    exit;
}
?>
