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

// Extract all unique categories for the filter
$allCategories = [];
foreach ($services as $service) {
    if (!empty($service['Category'])) {
        try {
            $categories = json_decode($service['Category'], true);
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    if (!empty($category) && is_string($category)) {
                        $cleanCategory = trim(str_replace(['\"', '"', '[', ']', '\\'], '', $category));
                        if (!empty($cleanCategory) && !in_array($cleanCategory, $allCategories)) {
                            $allCategories[] = $cleanCategory;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Skip malformed JSON
            continue;
        }
    }
}
sort($allCategories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Services - Kumar Kailey Hair & Beauty</title>
    <link href="styles2.css" rel="stylesheet">
    <link href="mobile.css" rel="stylesheet" media="(max-width:768px)">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <option value="category-asc">Category A → Z</option>
            <option value="category-desc">Category Z → A</option>
        </select>
        
        <!-- Category Filter -->
        <select id="categoryFilter">
            <option value="">All Categories</option>
            <?php foreach($allCategories as $category): ?>
                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
            <?php endforeach; ?>
        </select>
        
        <!-- Apply button only -->
        <button id="applyButton" class="filter-button">Apply</button>
    </div>

    <?php if(empty($services)): ?>
        <p>No services available at the moment.</p>
    <?php else: ?>
        <div class="services-grid" id="servicesGrid">
<?php foreach($services as $service): 
    // Parse categories for this service
    $serviceCategories = [];
    if (!empty($service['Category'])) {
        try {
            $categories = json_decode($service['Category'], true);
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    if (!empty($category) && is_string($category)) {
                        $cleanCategory = trim(str_replace(['\"', '"', '[', ']', '\\'], '', $category));
                        if (!empty($cleanCategory)) {
                            $serviceCategories[] = $cleanCategory;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Skip malformed JSON
        }
    }
    $primaryCategory = !empty($serviceCategories) ? $serviceCategories[0] : 'Uncategorized';
?>
    <div class="service-card" 
         data-name="<?= htmlspecialchars(strtolower($service['Name'])) ?>" 
         data-price="<?= $service['Price'] ?>" 
         data-time="<?= $service['Time'] ?>"
         data-category="<?= htmlspecialchars(strtolower($primaryCategory)) ?>"
         data-categories="<?= htmlspecialchars(json_encode($serviceCategories)) ?>">
        
        <!-- Service Image -->
        <?php if(!empty($service['ImgUrl'])): ?>
          <div class="service-image" style="background-image: url('<?php echo ($service['ImgUrl']) ? htmlspecialchars($service['ImgUrl']) : 'default-image.jpg'; ?>')"></div>
        <?php endif; ?>

        <h2><?= htmlspecialchars($service['Name']) ?></h2>
        
        <!-- Display Categories -->
        <?php if(!empty($serviceCategories)): ?>
            <div class="service-categories">
                <?php foreach($serviceCategories as $category): ?>
                    <span class="category-tag"><?= htmlspecialchars($category) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
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
const categoryFilter = document.getElementById('categoryFilter');
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
    const selectedCategory = categoryFilter.value.toLowerCase();
    let hasMatches = false;
    
    servicesGrid.classList.add('filtering');
    
    serviceCards.forEach(card => {
        const name = card.dataset.name;
        const description = card.querySelector('p').innerText.toLowerCase();
        const categories = JSON.parse(card.dataset.categories || '[]');
        const hasCategory = selectedCategory === '' || 
                           categories.some(cat => cat.toLowerCase() === selectedCategory);
        
        const isMatch = hasCategory && (name.includes(searchTerm) || description.includes(searchTerm));
        
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
    if (!hasMatches && (searchTerm !== '' || selectedCategory !== '')) {
        noResults.classList.add('show');
    } else {
        noResults.classList.remove('show');
    }
    
    setTimeout(() => {
        servicesGrid.classList.remove('filtering');
    }, 300);
    
    return hasMatches;
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
            case 'category-asc': return a.dataset.category.localeCompare(b.dataset.category);
            case 'category-desc': return b.dataset.category.localeCompare(a.dataset.category);
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
categoryFilter.addEventListener('change', () => {
    filterServices();
    sortServices();
});

// Apply button: trigger current filters/sort
const applyButton = document.getElementById('applyButton');
if (applyButton) {
    applyButton.addEventListener('click', () => {
        filterServices();
        sortServices();
    });
}

// Initialize with fade-in
document.addEventListener('DOMContentLoaded', () => {
    serviceCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    // Initial filter and sort
    filterServices();
    sortServices();
});
</script>


</body>
</html>
