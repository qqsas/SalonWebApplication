<?php
// db.php

$host = 'localhost';
$user = 'root'; // Default for XAMPP
$password = ''; // Default is empty for XAMPP
$database = 'SalonDB2'; // Replace with your database name

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
