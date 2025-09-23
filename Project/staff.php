<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch all barbers
$barberQuery = "SELECT a.BarberID, a.Name, a.Bio, u.Email, u.Number 
                FROM Barber a
                JOIN User u ON a.UserID = u.UserID
                ORDER BY a.Name ASC";
$barberResult = $conn->query($barberQuery);
$barbers = $barberResult ? $barberResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Staff - Kumar Kailey Hair & Beauty</title>
    <link href="styles.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .staff-grid { display:flex; flex-wrap:wrap; gap:15px; }
        .staff-card { border:1px solid #ddd; padding:15px; border-radius:8px; flex:1 1 250px; }
        .staff-card h2 { margin-top:0; }
        .staff-card p { margin:5px 0; }
        .btn { display:inline-block; padding:6px 12px; background:#007BFF; color:white; text-decoration:none; border-radius:4px; }
        .controls { margin-bottom:20px; display:flex; gap:20px; flex-wrap:wrap; align-items:center; }
    </style>
</head>
<body>

<div class="staff-container">
    <h1>Meet Our Barbers</h1>

    <?php if(!empty($barbers)): ?>
    <!-- Search and Sort Controls -->
    <div class="controls">
        <input type="text" id="staffSearch" placeholder="Search barbers...">
        <select id="staffSort">
            <option value="name-asc">Name A → Z</option>
            <option value="name-desc">Name Z → A</option>
            <option value="email-asc">Email A → Z</option>
            <option value="email-desc">Email Z → A</option>
        </select>
    </div>
    <?php endif; ?>

    <?php if(empty($barbers)): ?>
        <p>No barbers available at the moment.</p>
    <?php else: ?>
        <div class="staff-grid" id="staffGrid">
            <?php foreach($barbers as $barber): ?>
                <div class="staff-card"
                     data-name="<?= htmlspecialchars(strtolower($barber['Name'])) ?>"
                     data-email="<?= htmlspecialchars(strtolower($barber['Email'])) ?>"
                     data-bio="<?= htmlspecialchars(strtolower($barber['Bio'])) ?>"
                     data-phone="<?= htmlspecialchars(strtolower($barber['Number'])) ?>">
                    <h2><?= htmlspecialchars($barber['Name']) ?></h2>
                    <p><strong>Email:</strong> <?= htmlspecialchars($barber['Email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($barber['Number']) ?></p>
                    <p><strong>Bio:</strong> <?= htmlspecialchars(substr($barber['Bio'],0,100)) ?>...</p>
                    <a href="staff-detail.php?BarberID=<?= $barber['BarberID'] ?>" class="btn">View Profile</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// SEARCH AND SORT FUNCTIONALITY
const searchInput = document.getElementById('staffSearch');
const sortSelect = document.getElementById('staffSort');
const staffGrid = document.getElementById('staffGrid');
const staffCards = Array.from(staffGrid ? staffGrid.children : []);

// FILTER FUNCTION
function filterStaff() {
    const searchTerm = searchInput.value.toLowerCase();
    staffCards.forEach(card => {
        const name = card.dataset.name;
        const email = card.dataset.email;
        const phone = card.dataset.phone;
        const bio = card.dataset.bio;

        if(name.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm) || bio.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// SORT FUNCTION
function sortStaff() {
    const sortValue = sortSelect.value;
    let sortedCards = [...staffCards];

    sortedCards.sort((a,b) => {
        switch(sortValue) {
            case 'name-asc': return a.dataset.name.localeCompare(b.dataset.name);
            case 'name-desc': return b.dataset.name.localeCompare(a.dataset.name);
            case 'email-asc': return a.dataset.email.localeCompare(b.dataset.email);
            case 'email-desc': return b.dataset.email.localeCompare(a.dataset.email);
            default: return 0;
        }
    });

    // Re-append sorted cards
    sortedCards.forEach(card => staffGrid.appendChild(card));
}

// EVENT LISTENERS
searchInput.addEventListener('input', () => {
    filterStaff();
    sortStaff(); // keep sorted after filtering
});
sortSelect.addEventListener('change', sortStaff);
</script>

</body>
</html>

