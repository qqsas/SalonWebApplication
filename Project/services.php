<?php
session_start();
include 'db.php';
include 'header.php';

// Fetch all services with new price range support
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
        <input type="text" id="serviceSearch" placeholder="Search services by name, description, or category...">
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
        
        <!-- Multi-select Category Filter -->
        <div class="category-filter-container">
            <div class="category-multiselect">
                <div class="category-select" id="categorySelect">
                    <span id="categoryPlaceholder">Select categories...</span>
                    <div id="selectedCategories"></div>
                </div>
                <div class="category-dropdown" id="categoryDropdown">
                    <?php foreach($allCategories as $category): ?>
                        <label class="category-option">
                            <input type="checkbox" value="<?= htmlspecialchars($category) ?>">
                            <?= htmlspecialchars($category) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="clear-categories" id="clearCategories">Clear</button>
        </div>
        
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
    
    // Calculate display price based on price type
    $displayPrice = '';
    $sortPrice = 0;
    
    if ($service['PriceType'] === 'range' && $service['MinPrice'] && $service['MaxPrice']) {
        $displayPrice = 'R' . number_format($service['MinPrice'], 2) . ' - R' . number_format($service['MaxPrice'], 2);
        $sortPrice = ($service['MinPrice'] + $service['MaxPrice']) / 2; // Use average for sorting
    } else {
        $displayPrice = 'R' . number_format($service['Price'], 2);
        $sortPrice = $service['Price'];
    }
?>
<div class="service-card" 
     data-name="<?= htmlspecialchars(strtolower($service['Name'])) ?>" 
     data-description="<?= htmlspecialchars(strtolower($service['Description'])) ?>"
     data-price="<?= $sortPrice ?>" 
     data-time="<?= $service['Time'] ?>"
     data-category="<?= htmlspecialchars(strtolower($primaryCategory)) ?>"
     data-categories="<?= htmlspecialchars(json_encode($serviceCategories)) ?>"
     data-price-type="<?= htmlspecialchars($service['PriceType']) ?>"
     data-min-price="<?= $service['MinPrice'] ?? 0 ?>"
     data-max-price="<?= $service['MaxPrice'] ?? 0 ?>"
     onclick="window.location.href='service-detail.php?ServicesID=<?= $service['ServicesID'] ?>'"
     style="cursor: pointer;">
        
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
        
        <p class="service-description"><?= nl2br(htmlspecialchars($service['Description'])) ?></p>
        
        <!-- Display Price based on type -->
        <p class="service-price">
            <strong>Price:</strong> 
            <?php if ($service['PriceType'] === 'range' && $service['MinPrice'] && $service['MaxPrice']): ?>
                <span class="price-range">
                    R<?= number_format($service['MinPrice'], 2) ?> - R<?= number_format($service['MaxPrice'], 2) ?>
                </span>
            <?php else: ?>
                <span class="fixed-price">
                    R<?= number_format($service['Price'], 2) ?>
                </span>
            <?php endif; ?>
        </p>
        
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

// Multi-select category elements
const categorySelect = document.getElementById('categorySelect');
const categoryDropdown = document.getElementById('categoryDropdown');
const categoryPlaceholder = document.getElementById('categoryPlaceholder');
const selectedCategoriesContainer = document.getElementById('selectedCategories');
const clearCategoriesBtn = document.getElementById('clearCategories');
const categoryCheckboxes = categoryDropdown.querySelectorAll('input[type="checkbox"]');

// Track selected categories
let selectedCategories = [];

// Create no results message
const noResults = document.createElement('div');
noResults.className = 'no-results';
noResults.innerHTML = '<p>No services found matching your search. Try different keywords.</p>';
servicesGrid.parentNode.insertBefore(noResults, servicesGrid.nextSibling);

// Multi-select category functionality
categorySelect.addEventListener('click', (e) => {
    e.stopPropagation();
    categoryDropdown.classList.toggle('show');
});

// Close dropdown when clicking outside
document.addEventListener('click', () => {
    categoryDropdown.classList.remove('show');
});

// Prevent dropdown close when clicking inside
categoryDropdown.addEventListener('click', (e) => {
    e.stopPropagation();
});

// Handle category selection
categoryCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', (e) => {
        const category = e.target.value;
        
        if (e.target.checked) {
            if (!selectedCategories.includes(category)) {
                selectedCategories.push(category);
            }
        } else {
            selectedCategories = selectedCategories.filter(cat => cat !== category);
        }
        
        updateSelectedCategoriesDisplay();
    });
});

// Clear all categories
clearCategoriesBtn.addEventListener('click', () => {
    selectedCategories = [];
    categoryCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectedCategoriesDisplay();
    filterServices();
    sortServices();
});

// Update the display of selected categories
function updateSelectedCategoriesDisplay() {
    selectedCategoriesContainer.innerHTML = '';
    
    if (selectedCategories.length === 0) {
        categoryPlaceholder.style.display = 'block';
        return;
    }
    
    categoryPlaceholder.style.display = 'none';
    
    selectedCategories.forEach(category => {
        const tag = document.createElement('span');
        tag.className = 'selected-category-tag';
        tag.innerHTML = `
            ${category}
            <span class="remove" onclick="removeCategory('${category}')">×</span>
        `;
        selectedCategoriesContainer.appendChild(tag);
    });
}

// Remove individual category
function removeCategory(category) {
    selectedCategories = selectedCategories.filter(cat => cat !== category);
    
    // Uncheck the corresponding checkbox
    categoryCheckboxes.forEach(checkbox => {
        if (checkbox.value === category) {
            checkbox.checked = false;
        }
    });
    
    updateSelectedCategoriesDisplay();
    filterServices();
    sortServices();
}

// Function to highlight search terms in text
function highlightText(text, searchTerm) {
    if (!searchTerm) return text;
    
    const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<span class="search-highlight">$1</span>');
}

// ENHANCED FILTER FUNCTION
function filterServices() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    let hasMatches = false;
    
    servicesGrid.classList.add('filtering');
    
    serviceCards.forEach(card => {
        const name = card.dataset.name;
        const description = card.dataset.description || '';
        const categories = JSON.parse(card.dataset.categories || '[]');
        const categoryString = categories.join(' ').toLowerCase();
        
        // Check category match - if no categories selected, show all
        const hasCategory = selectedCategories.length === 0 || 
                           categories.some(cat => selectedCategories.includes(cat));
        
        // Check if matches search term in name, description, OR categories
        const isSearchMatch = searchTerm === '' || 
                             name.includes(searchTerm) || 
                             description.includes(searchTerm) || 
                             categoryString.includes(searchTerm);
        
        const isMatch = hasCategory && isSearchMatch;
        
        if(isMatch) {
            card.style.display = '';
            card.classList.add('matched');
            hasMatches = true;
            
            // Highlight search terms in the displayed content
            if (searchTerm) {
                const nameElement = card.querySelector('h2');
                const descriptionElement = card.querySelector('.service-description');
                const categoryElements = card.querySelectorAll('.category-tag');
                
                // Highlight in name
                nameElement.innerHTML = highlightText(nameElement.textContent, searchTerm);
                
                // Highlight in description
                descriptionElement.innerHTML = highlightText(descriptionElement.textContent, searchTerm);
                
                // Highlight in categories
                categoryElements.forEach(tag => {
                    tag.innerHTML = highlightText(tag.textContent, searchTerm);
                });
            }
        } else {
            card.style.display = 'none';
            card.classList.remove('matched');
            
            // Remove highlighting when not matched
            if (searchTerm) {
                const nameElement = card.querySelector('h2');
                const descriptionElement = card.querySelector('.service-description');
                const categoryElements = card.querySelectorAll('.category-tag');
                
                // Restore original text without highlights
                nameElement.innerHTML = nameElement.textContent;
                descriptionElement.innerHTML = descriptionElement.textContent;
                categoryElements.forEach(tag => {
                    tag.innerHTML = tag.textContent;
                });
            }
        }
    });
    
    // Show/hide no results message
    if (!hasMatches && (searchTerm !== '' || selectedCategories.length > 0)) {
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

// Add keyboard shortcut for search (Ctrl+F or Cmd+F)
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        searchInput.focus();
        searchInput.select();
    }
});
</script>

</body>
</html>
