<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch all barbers
$barberQuery = "SELECT a.BarberID, a.Name, a.Bio, a.ImgUrl, u.Email, u.Number 
                FROM Barber a
                JOIN User u ON a.UserID = u.UserID
                WHERE a.IsDeleted = 0
                ORDER BY a.Name ASC";
$barberResult = $conn->query($barberQuery);
$barbers = $barberResult ? $barberResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Staff - Kumar Kailey Hair & Beauty</title>
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="services-container">
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
        <!-- ADDED: Apply button only -->
        <button id="applyButton" class="filter-button">Apply</button>
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

                    <!-- Barber Image -->
                    <?php if(!empty($barber['ImgUrl'])): ?>
        <div class="staff-image" style="background-image: url('<?php echo ($barber['ImgUrl']) ? htmlspecialchars($barber['ImgUrl']) : 'default-staff.jpg'; ?>')"></div>
                    <?php endif; ?>

                    <h2><?= htmlspecialchars($barber['Name']) ?></h2>
                    <p><strong>Email:</strong> <?= htmlspecialchars($barber['Email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($barber['Number']) ?></p>
                    <p><strong>Bio:</strong> <?= htmlspecialchars(substr($barber['Bio'],0,100)) ?>...</p>
                    <a href="staff-detail.php?BarberID=<?= $barber['BarberID'] ?>" class="btn">View Profile</a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="no-results">No barbers found matching your search.</div>
    <?php endif; ?>
</div>

<script>
// SEARCH AND SORT FUNCTIONALITY
const searchInput = document.getElementById('staffSearch');
const sortSelect = document.getElementById('staffSort');
const staffGrid = document.getElementById('staffGrid');
const staffCards = Array.from(staffGrid ? staffGrid.children : []);
const noResults = document.querySelector('.no-results');

// FILTER FUNCTION
function filterStaff() {
    const searchTerm = searchInput.value.toLowerCase();
    let hasMatches = false;

    staffCards.forEach(card => {
        const name = card.dataset.name;
        const email = card.dataset.email;
        const phone = card.dataset.phone;
        const bio = card.dataset.bio;

        const match = name.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm) || bio.includes(searchTerm);
        card.style.display = match ? '' : 'none';
        if(match) hasMatches = true;
    });

    noResults.classList.toggle('show', !hasMatches && searchTerm !== '');
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

    sortedCards.forEach(card => staffGrid.appendChild(card));
}

// EVENT LISTENERS
searchInput.addEventListener('input', () => {
    filterStaff();
    sortStaff();
});
sortSelect.addEventListener('change', sortStaff);

// Apply button: trigger current filters/sort (button was added only)
const applyButton = document.getElementById('applyButton');
if (applyButton) {
    applyButton.addEventListener('click', () => {
        filterStaff();
        sortStaff();
    });
}
</script>

</body>
</html>

