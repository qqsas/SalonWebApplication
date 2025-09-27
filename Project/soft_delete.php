<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';

$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? '';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;

$allowed = [
    'User'       => ['pk' => 'UserID',       'view' => 'users'],
    'Barber'     => ['pk' => 'BarberID',     'view' => 'barbers'],
    'Appointment'=> ['pk' => 'AppointmentID','view' => 'appointments'],
    'Orders'     => ['pk' => 'OrderID',      'view' => 'orders'],
    'Products'   => ['pk' => 'ProductID',    'view' => 'products'],
    'Services'   => ['pk' => 'ServicesID',   'view' => 'services'],
    'Reviews'    => ['pk' => 'ReviewID',     'view' => 'reviews'],
    'Contact'    => ['pk' => 'ContactID',    'view' => 'contacts']
];

if (isset($allowed[$table]) && ctype_digit($id)) {
    $pk = $allowed[$table]['pk'];
    $stmt = $conn->prepare("UPDATE $table SET IsDeleted = 1 WHERE $pk = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

$redirect = "admin_dashboard.php?view=" . $allowed[$table]['view'];
if ($search !== '') $redirect .= "&search=" . urlencode($search);
if ($page !== '') $redirect .= "&page=" . intval($page);

header("Location: $redirect");
exit();

