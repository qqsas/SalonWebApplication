<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- USER DATA ---
    $user_name = trim($_POST['UserName']);
    $user_email = trim($_POST['Email']);
    $user_number = trim($_POST['Number']);
    $user_password = trim($_POST['Password']);

    // --- BARBER DATA ---
    $barber_bio = trim($_POST['Bio']);

    // --- SERVICES ---
    $services = $_POST['Services'] ?? [];

    // Validation
    if (empty($user_name)) $errors[] = "User name is required.";
    if (empty($user_email)) $errors[] = "Email is required.";
    if (empty($user_password)) $errors[] = "Password is required.";

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // Insert into User table
            $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO User (Name, Email, Number, Password, Role) VALUES (?, ?, ?, ?, 'barber')");
            $stmt->bind_param("ssss", $user_name, $user_email, $user_number, $hashed_password);
            $stmt->execute();
            $user_id = $stmt->insert_id;
            $stmt->close();

            // Insert into Barber table using User.Name
            $stmt = $conn->prepare("INSERT INTO Barber (UserID, Name, Bio) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $user_name, $barber_bio); // <-- Name comes from User.Name
            $stmt->execute();
            $barber_id = $stmt->insert_id;
            $stmt->close();

            // Assign services
            if (!empty($services)) {
                $stmt = $conn->prepare("INSERT INTO BarberServices (BarberID, ServicesID) VALUES (?, ?)");
                foreach ($services as $service_id) {
                    $stmt->bind_param("ii", $barber_id, $service_id);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $conn->commit();
            $success = "Barber and user created successfully!";
            // Reset fields
            $user_name = $user_email = $user_number = $user_password = '';
            $barber_bio = '';
            $services = [];
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch all services for multi-select
$all_services = [];
$result = $conn->query("SELECT ServicesID, Name FROM Services WHERE IsDeleted=0");
while ($row = $result->fetch_assoc()) {
    $all_services[] = $row;
}
?>

<h2>Add New Barber with User Account & Services</h2>

<?php
if (!empty($errors)) {
    echo "<div style='color:red;'><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul></div>";
}
if ($success) {
    echo "<div style='color:green;'>" . htmlspecialchars($success) . "</div>";
}
?>

<form method="POST" action="">
    <h3>User Account</h3>
    <label>Name:</label><br>
    <input type="text" name="UserName" value="<?php echo htmlspecialchars($user_name ?? ''); ?>" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="Email" value="<?php echo htmlspecialchars($user_email ?? ''); ?>" required><br><br>

    <label>Number:</label><br>
    <input type="text" name="Number" value="<?php echo htmlspecialchars($user_number ?? ''); ?>"><br><br>

    <label>Password:</label><br>
    <input type="password" name="Password" value="" required><br><br>

    <h3>Barber Profile</h3>
    <label>Bio:</label><br>
    <textarea name="Bio"><?php echo htmlspecialchars($barber_bio ?? ''); ?></textarea><br><br>

    <h3>Assign Services</h3>
    <select name="Services[]" multiple size="5">
        <?php foreach ($all_services as $s): ?>
            <option value="<?php echo $s['ServicesID']; ?>" <?php if(in_array($s['ServicesID'], $services ?? [])) echo 'selected'; ?>>
                <?php echo htmlspecialchars($s['Name']); ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <button type="submit">Add Barber</button>
</form>

