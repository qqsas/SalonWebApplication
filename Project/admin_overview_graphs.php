<?php
// admin_overview_graphs.php
if (!isset($conn)) {
    die("Database connection is required.");
}

// Tables to include in overview
$tables = [
    'User' => 'Users',
    'Barber' => 'Barbers',
    'Appointment' => 'Appointments',
    'Orders' => 'Orders',
    'Products' => 'Products',
    'Reviews' => 'Reviews'
];

$overviewData = [];
$allDates = [];

// Collect dates and counts
foreach ($tables as $table => $label) {
    $stmt = $conn->prepare("
        SELECT DATE(CreatedAt) as date, COUNT(*) as count
        FROM $table
        GROUP BY DATE(CreatedAt)
        ORDER BY DATE(CreatedAt) ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $dates = [];
    $counts = [];

    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['date'];
        $counts[$row['date']] = (int)$row['count'];
        $allDates[$row['date']] = true;
    }

    $overviewData[$label] = $counts;
}

// Sort all dates
$allDates = array_keys($allDates);
sort($allDates);

// Fill missing dates with 0 for all tables
foreach ($overviewData as $label => $counts) {
    foreach ($allDates as $date) {
        if (!isset($counts[$date])) {
            $counts[$date] = 0;
        }
    }
    // Ensure chronological order
    ksort($counts);
    $overviewData[$label] = array_values($counts);
}

// Labels for X-axis
$labels = $allDates;

echo '<canvas id="overviewChart" width="1000" height="400"></canvas>';

echo "<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
<script>
const overviewData = " . json_encode($overviewData) . ";
const labels = " . json_encode($labels) . ";

const colors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40'];
const datasets = [];

let colorIndex = 0;
for (const label in overviewData) {
    datasets.push({
        label: label,
        data: overviewData[label],
        fill: false,
        borderColor: colors[colorIndex % colors.length],
        tension: 0.1
    });
    colorIndex++;
}

const ctx = document.getElementById('overviewChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: datasets
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Daily Creations Overview'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Date'
                },
                ticks: {
                    autoSkip: true,
                    maxTicksLimit: 20
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Records'
                },
                precision: 0
            }
        }
    }
});
</script>";

echo "<p>Line graph showing daily creations of Users, Barbers, Appointments, Orders, Products, and Reviews.</p>";
?>

