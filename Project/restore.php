<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? '';

$allowed = [
    'User' => 'UserID',
    'Barber' => 'BarberID',
    'Appointment' => 'AppointmentID',
    'Orders' => 'OrderID',
    'Products' => 'ProductID',
    'Services' => 'ServicesID',
    'Reviews' => 'ReviewID',
    'Contact' => 'ContactID'
];

if (isset($allowed[$table]) && ctype_digit($id)) {
    $pk = $allowed[$table];
    $stmt = $conn->prepare("UPDATE $table SET IsDeleted = 0 WHERE $pk = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: admin_dashboard.php?view=" . strtolower($table) . "s");
exit();

