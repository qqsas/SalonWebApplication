<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch all services
$serviceQuery = "SELECT * 
                 FROM Services 
                 WHERE IsDeleted = 0 
                 ORDER BY Name ASC";
$serviceResult = $conn->query($serviceQuery);
$services = $serviceResult ? $serviceResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Services - Kumar Kailey Hair & Beauty</title>
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    @media (min-width:769px) {
        .menu-toggle { display: none !important; }
    }
    .service-card { border: 1px solid #ddd; padding: 15px; margin: 10px; border-radius: 8px; }
    .service-buttons { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
    .controls { margin-bottom: 20px; display:flex; gap:20px; flex-wrap:wrap; align-items:center; }
    </style>
</head>
<body>

<div class="services-container">
    <h1>Our Services</h1>

    <!-- Search and Sort Controls -->
    <div class="controls">
        <input type="text" id="serviceSearch" placeholder="Search services...">
        <select id="serviceSort">
            <option value="name-asc">Name A → Z</option>
            <option value="name-desc">Name Z → A</option>
            <option value="price-asc">Price Low → High</option>
            <option value="price-desc">Price High → Low</option>
            <option value="time-asc">Duration Short → Long</option>
            <option value="time-desc">Duration Long → Short</option>
        </select>
    </div>

    <?php if(empty($services)): ?>
        <p>No services available at the moment.</p>
    <?php else: ?>
        <div class="services-grid" id="servicesGrid">
<?php foreach($services as $service): ?>
    <div class="service-card" 
         data-name="<?= htmlspecialchars(strtolower($service['Name'])) ?>" 
         data-price="<?= $service['Price'] ?>" 
         data-time="<?= $service['Time'] ?>">
        
        <!-- Service Image -->
        <?php if(!empty($service['ImgUrl'])): ?>
          <div class="service-image" style="background-image: url('<?php echo ($service['ImgUrl']) ? htmlspecialchars($service['ImgUrl']) : 'default-image.jpg'; ?>')"></div>
        <?php endif; ?>

        <h2><?= htmlspecialchars($service['Name']) ?></h2>
        <p><?= nl2br(htmlspecialchars($service['Description'])) ?></p>
        <p><strong>Price:</strong> R<?= number_format($service['Price'],2) ?></p>
        <p><strong>Duration:</strong> <?= htmlspecialchars($service['Time']) ?> mins</p>

        <div class="service-buttons">
            <?php
            $barberStmt = $conn->prepare("
                SELECT b.BarberID, b.Name 
                FROM Barber b
                JOIN BarberServices bs ON b.BarberID = bs.BarberID
                WHERE bs.ServicesID = ? AND bs.IsDeleted = 0
                ORDER BY b.Name ASC
            ");
            $barberStmt->bind_param("i", $service['ServicesID']);
            $barberStmt->execute();
            $barberResult = $barberStmt->get_result();
            $barbers = $barberResult ? $barberResult->fetch_all(MYSQLI_ASSOC) : [];
            $barberStmt->close();

            if(!empty($barbers)):
                foreach($barbers as $barber): ?>
                    <a href="make_appointment.php?ServicesID=<?= $service['ServicesID'] ?>&BarberID=<?= $barber['BarberID'] ?>" class="btn">
                        Book with <?= htmlspecialchars($barber['Name']) ?>
                    </a>
                <?php endforeach;
            else: ?>
                <p>No barbers available for this service.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// ENHANCED SEARCH AND SORT FUNCTIONALITY
const searchInput = document.getElementById('serviceSearch');
const sortSelect = document.getElementById('serviceSort');
const servicesGrid = document.getElementById('servicesGrid');
const serviceCards = Array.from(servicesGrid.children);

// Create no results message
const noResults = document.createElement('div');
noResults.className = 'no-results';
noResults.innerHTML = '<p>No services found matching your search. Try different keywords.</p>';
servicesGrid.parentNode.insertBefore(noResults, servicesGrid.nextSibling);

// ENHANCED FILTER FUNCTION
function filterServices() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    let hasMatches = false;
    
    servicesGrid.classList.add('filtering');
    
    serviceCards.forEach(card => {
        const name = card.dataset.name;
        const description = card.querySelector('p').innerText.toLowerCase();
        const isMatch = name.includes(searchTerm) || description.includes(searchTerm);
        
        if(isMatch) {
            card.style.display = '';
            card.classList.add('matched');
            hasMatches = true;
        } else {
            card.style.display = 'none';
            card.classList.remove('matched');
        }
    });
    
    // Show/hide no results message
    if (!hasMatches && searchTerm !== '') {
        noResults.classList.add('show');
    } else {
        noResults.classList.remove('show');
    }
    
    setTimeout(() => {
        servicesGrid.classList.remove('filtering');
    }, 300);
}

// ENHANCED SORT FUNCTION
function sortServices() {
    const sortValue = sortSelect.value;
    let sortedCards = [...serviceCards].filter(card => card.style.display !== 'none');
    
    sortedCards.sort((a,b) => {
        switch(sortValue) {
            case 'name-asc': return a.dataset.name.localeCompare(b.dataset.name);
            case 'name-desc': return b.dataset.name.localeCompare(a.dataset.name);
            case 'price-asc': return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
            case 'price-desc': return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
            case 'time-asc': return parseInt(a.dataset.time) - parseInt(b.dataset.time);
            case 'time-desc': return parseInt(b.dataset.time) - parseInt(a.dataset.time);
            default: return 0;
        }
    });
    
    // Smooth reordering
    servicesGrid.style.opacity = '0.7';
    sortedCards.forEach((card, index) => {
        setTimeout(() => {
            servicesGrid.appendChild(card);
        }, index * 50);
    });
    
    setTimeout(() => {
        servicesGrid.style.opacity = '1';
    }, sortedCards.length * 50);
}

// EVENT LISTENERS
searchInput.addEventListener('input', () => {
    filterServices();
    sortServices();
});

sortSelect.addEventListener('change', sortServices);

// Initialize with fade-in
document.addEventListener('DOMContentLoaded', () => {
    serviceCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});</script>

</body>
</html>

