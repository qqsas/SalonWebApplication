<?php
session_start();
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<style>
    @media (min-width: 769px) {
        .menu-toggle {
            display: none !important;
        }
    }

    .hours-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin: 20px 0;
    }

    .location-section, .hours-section {
        background: #f9f9f9;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    /* === WORKING HOURS TABLE === */
    .hours-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed; /* evenly space columns */
    }

    .hours-table tr {
        border-bottom: 1px solid #eee;
    }

    .hours-table td {
        padding: 12px 10px;
        text-align: left;
        vertical-align: middle;
        font-size: 0.95rem;
        color: #333;
    }

    .hours-table td:first-child {
        font-weight: 600;
        width: 50%; /* let each column share the width evenly */
        text-align: left;
    }

    .hours-table td:last-child {
        text-align: right; /* aligns times to the right */
        color: black;
    }

    /* Optional: subtle hover for better readability */
    .hours-table tr:hover {
        background: #f1f1f1;
    }

    .map-link {
        color: #007bff;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 10px;
    }

    .map-link:hover {
        text-decoration: underline;
    }

    .map-container {
        margin-top: 15px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .map-iframe {
        width: 100%;
        height: 250px;
        border: none;
    }

    @media (max-width: 768px) {
        .hours-container {
            grid-template-columns: 1fr;
        }

        .map-iframe {
            height: 200px;
        }

        .hours-table td {
            padding: 10px 6px;
            font-size: 0.9rem;
        }
    }

    /* === SLIDESHOW STYLES === */
    .slideshow-container {
        position: relative;
        max-width: 100%;
        margin: 2rem auto;
        padding: 0 70px; /* Add padding to prevent content from being covered by arrows */
    }

    .slide {
        display: none;
        animation: fadeIn 0.8s ease-in-out;
    }

    .slide.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* Navigation arrows */
    .prev, .next {
        cursor: pointer;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 50px;
        color: white;
        font-weight: bold;
        font-size: 24px;
        transition: 0.3s ease;
        border-radius: 50%;
        background-color: rgba(0, 31, 63, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        user-select: none;
        z-index: 10;
    }

    .prev {
        left: 10px;
    }

    .next {
        right: 10px;
    }

    .prev:hover, .next:hover {
        background-color: rgba(0, 31, 63, 0.9);
        transform: translateY(-50%) scale(1.1);
    }

    /* Slide indicators/dots */
    .dot {
        cursor: pointer;
        height: 15px;
        width: 15px;
        margin: 0 5px;
        background-color: #bbb;
        border-radius: 50%;
        display: inline-block;
        transition: background-color 0.3s ease;
    }

    .dot:hover, .dot.active {
        background-color: var(--primary-color, #001f3f);
        transform: scale(1.2);
    }


    /* Adjust section styles within slides */
    .slideshow-container .section {
        margin-bottom: 0;
    }

    /* Responsive styles for slideshow */
    @media (max-width: 768px) {
        .slideshow-container {
            padding: 0 50px; /* Reduce padding on mobile */
        }

        .prev, .next {
            width: 40px;
            height: 40px;
            font-size: 20px;
        }

        .prev {
            left: 5px;
        }

        .next {
            right: 5px;
        }

        .dot {
            height: 12px;
            width: 12px;
            margin: 0 4px;
        }
    }

    @media (max-width: 480px) {
        .slideshow-container {
            padding: 0 45px; /* Even smaller padding on very small screens */
        }
    }

    /* Footer styling to ensure full width - break out of container */
    footer {
        display: block !important;
        width: 100vw !important;
        max-width: 100vw !important;
        margin-left: calc(50% - 50vw) !important;
        margin-right: calc(50% - 50vw) !important;
        margin-top: 40px !important;
        margin-bottom: 0 !important;
        padding: 20px 15px !important;
        clear: both;
        box-sizing: border-box;
        position: relative;
    }

    /* Prevent horizontal scroll from viewport width */
    html {
        overflow-x: hidden;
    }
    
    body {
        overflow-x: hidden;
        max-width: 100%;
    }
</style>
    <meta charset="UTF-8">
    <title>About Us - E-Commerce Platform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<!-- About Us Content -->
<div class="containerAbout">
    <h1>About Us</h1>

    <p class="lead"><strong>Kumar Kailey's Hair and Beauty Salon</strong> is a professional barber shop and salon dedicated to offering quality haircuts, grooming, and beauty services in a welcoming and stylish environment.</p>

    <div class="hours-container">
        <div class="location-section">
            <h3>Our Location</h3>
            <p>
                Shop 10B, The Bridge Shopping Centre<br>
                Buccleuch Drive, Buccleuch<br>
                Johannesburg, 2090<br>
                South Africa
            </p>
            
            <!-- Embedded Google Map -->
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3581.122421108743!2d28.07025927607156!3d-26.16260916292215!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1e9514348a0d3a87%3A0x7d822b9b76c4b1f1!2sThe%20Bridge%20Shopping%20Centre%2C%20Buccleuch%20Dr%2C%20Buccleuch%2C%20Johannesburg%2C%202090%2C%20South%20Africa!5e0!3m2!1sen!2sza!4v1698765432107!5m2!1sen!2sza" 
                    class="map-iframe"
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Kumar Kailey's Hair and Beauty Salon Location">
                </iframe>
            </div>
            
            <a href="https://maps.google.com/?q=Shop+10B+The+Bridge+Shopping+Centre+Buccleuch+Johannesburg+2090+South+Africa" 
               target="_blank" 
               class="map-link">
                Open in Google Maps
            </a>
        </div>
        
        <div class="hours-section">
            <h3>Opening Hours</h3>
            <table class="hours-table">
                <tr>
                    <td>Monday</td>
                    <td>08:00 - 19:30</td>
                </tr>
                <tr>
                    <td>Tuesday</td>
                    <td>08:00 - 19:30</td>
                </tr>
                <tr>
                    <td>Wednesday</td>
                    <td>08:00 - 19:30</td>
                </tr>
                <tr>
                    <td>Thursday</td>
                    <td>08:00 - 19:30</td>
                </tr>
                <tr>
                    <td>Friday</td>
                    <td>08:00 - 19:30</td>
                </tr>
                <tr>
                    <td>Saturday</td>
                    <td>08:00 - 19:30</td>
                </tr>
                <tr>
                    <td>Sunday</td>
                    <td>08:00 - 19:30</td>
                </tr>
            </table>
        </div>
    </div>


  <!-- Slideshow Container -->
    <div class="slideshow-container">
        <!-- Slide 1: Our Mission -->
        <div class="slide fade active">
            <div class="section">
                <h3>Our Mission</h3>
                <p>To provide every client with exceptional hair and beauty services that leave them feeling confident, refreshed, and looking their absolute best.</p>
            </div>
        </div>

        <!-- Slide 2: Our Vision -->
        <div class="slide fade">
            <div class="section">
                <h3>Our Vision</h3>
                <p>We envision becoming the go-to destination for both men and women who seek top-quality barbering and salon experiences, blending tradition with modern trends.</p>
            </div>
        </div>

        <!-- Slide 3: What We Offer -->
        <div class="slide fade">
            <div class="section">
                <h3>What We Offer</h3>
                <ul class="custom-list">
                    <li><strong>Barbering:</strong> Classic and modern haircuts, beard trims, and grooming services tailored to your style.</li>
                    <li><strong>Salon Services:</strong> Hair coloring, styling, treatments, and beauty services designed to suit every occasion.</li>
                    <li><strong>Personalized Care:</strong> Friendly and professional staff who take the time to understand your needs.</li>
                </ul>
            </div>
        </div>

        <!-- Slide 4: Why Choose Us? -->
        <div class="slide fade">
            <div class="section">
                <h3>Why Choose Us?</h3>
                <ul class="custom-list">
                    <li><strong></strong>Experienced barbers and stylists with a passion for hair and beauty</li>
                    <li><strong></strong>A clean, relaxing, and welcoming salon environment</li>
                    <li><strong></strong>Fair pricing with premium service quality</li>
                    <li><strong></strong>Tailored grooming and beauty solutions for every client</li>
                    <li><strong></strong>Convenient location with ample parking</li>
                    <li><strong></strong>Extended opening hours to suit your schedule</li>
                </ul>
            </div>
        </div>

        <!-- Navigation arrows -->
        <a class="prev" onclick="changeSlide(-1)">&#10094;</a>
        <a class="next" onclick="changeSlide(1)">&#10095;</a>
    </div>

    <!-- Slide indicators/dots -->
    <div style="text-align:center; margin: 20px 0;">
        <span class="dot active" onclick="currentSlide(1)"></span>
        <span class="dot" onclick="currentSlide(2)"></span>
        <span class="dot" onclick="currentSlide(3)"></span>
        <span class="dot" onclick="currentSlide(4)"></span>
    </div>

</div>


<script>
    let slideIndex = 1;
    let slideInterval;

    // Initialize slideshow
    showSlide(slideIndex);

    // Function to change slide
    function changeSlide(n) {
        showSlide(slideIndex += n);
        resetAutoSlide();
    }

    // Function to go to a specific slide
    function currentSlide(n) {
        showSlide(slideIndex = n);
        resetAutoSlide();
    }

    // Function to display slides
    function showSlide(n) {
        let slides = document.getElementsByClassName("slide");
        let dots = document.getElementsByClassName("dot");
        
        if (n > slides.length) {
            slideIndex = 1;
        }
        if (n < 1) {
            slideIndex = slides.length;
        }
        
        // Hide all slides
        for (let i = 0; i < slides.length; i++) {
            slides[i].classList.remove("active");
        }
        
        // Remove active class from all dots
        for (let i = 0; i < dots.length; i++) {
            dots[i].classList.remove("active");
        }
        
        // Show current slide and activate corresponding dot
        if (slides[slideIndex - 1]) {
            slides[slideIndex - 1].classList.add("active");
        }
        if (dots[slideIndex - 1]) {
            dots[slideIndex - 1].classList.add("active");
        }
    }

    // Auto-play slideshow (optional - uncomment to enable)
    function startAutoSlide() {
        slideInterval = setInterval(function() {
            changeSlide(1);
        }, 5000); // Change slide every 5 seconds
    }

    // Reset auto-play timer
    function resetAutoSlide() {
        clearInterval(slideInterval);
        
    }

    

    // Pause auto-play when user hovers over slideshow
    const slideshowContainer = document.querySelector('.slideshow-container');
    if (slideshowContainer) {
        slideshowContainer.addEventListener('mouseenter', function() {
            clearInterval(slideInterval);
        });
        slideshowContainer.addEventListener('mouseleave', function() {
            
        });
    }
</script>

<?php
include 'footer.php';
?>