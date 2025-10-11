<?php
// admin_overview_graphs.php
if (!isset($conn)) {
    die("Database connection is required.");
}

// Capture selected time frame (default: daily)
$timeFrame = $_GET['timeframe'] ?? 'daily';

// Determine SQL date format and grouping
switch ($timeFrame) {
    case 'weekly':
        $dateFormat = "%Y-%u"; // Year-week
        $labelTitle = "Weekly Activity Overview";
        $xLabel = "Week";
        break;
    case 'monthly':
        $dateFormat = "%Y-%m"; // Year-month
        $labelTitle = "Monthly Activity Overview";
        $xLabel = "Month";
        break;
    case 'yearly':
        $dateFormat = "%Y"; // Year
        $labelTitle = "Yearly Activity Overview";
        $xLabel = "Year";
        break;
    default:
        $dateFormat = "%Y-%m-%d"; // Daily
        $labelTitle = "Daily Activity Overview";
        $xLabel = "Date";
}

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
$totals = [];

// Collect data from all tables
foreach ($tables as $table => $label) {
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(CreatedAt, ?) AS date, COUNT(*) AS count
        FROM $table
        GROUP BY DATE_FORMAT(CreatedAt, ?)
        ORDER BY DATE_FORMAT(CreatedAt, ?) ASC
    ");
    $stmt->bind_param('sss', $dateFormat, $dateFormat, $dateFormat);
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = [];
    $totalCount = 0;

    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $count = (int)$row['count'];
        $counts[$date] = $count;
        $allDates[$date] = true;
        $totalCount += $count;
    }

    $overviewData[$label] = $counts;
    $totals[$label] = $totalCount;
}

$allDates = array_keys($allDates);
sort($allDates);

// Fill missing dates with 0s for each label
foreach ($overviewData as $label => $counts) {
    foreach ($allDates as $date) {
        if (!isset($counts[$date])) {
            $counts[$date] = 0;
        }
    }
    ksort($counts);
    $overviewData[$label] = array_values($counts);
}

$labels = $allDates;

// Dropdown for time frame
echo "<form id='timeframeForm' style='margin-bottom:15px;'>
    <label for='timeframe' style='margin-right:8px;font-weight:bold;'>Select Time Frame:</label>
    <select id='timeframe' name='timeframe' onchange='updateTimeframe()' style='padding:5px;border-radius:5px;'>
        <option value='daily' " . ($timeFrame === 'daily' ? 'selected' : '') . ">Daily</option>
        <option value='weekly' " . ($timeFrame === 'weekly' ? 'selected' : '') . ">Weekly</option>
        <option value='monthly' " . ($timeFrame === 'monthly' ? 'selected' : '') . ">Monthly</option>
        <option value='yearly' " . ($timeFrame === 'yearly' ? 'selected' : '') . ">Yearly</option>
    </select>
</form>";

// Totals summary
echo "<div style='display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px;'>";
foreach ($totals as $label => $total) {
    echo "<div style='flex:1; min-width:150px; background:#f9f9f9; border-radius:8px; padding:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1); text-align:center;'>
            <h4 style='margin:0; font-size:1rem;'>$label</h4>
            <p style='margin:5px 0 0; font-weight:bold; font-size:1.2rem;'>$total</p>
          </div>";
}
echo "</div>";

echo '<canvas id="overviewChart" style="width:100%; max-width:1200px; height:400px;"></canvas>';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-crosshair"></script>

<script>
function updateTimeframe() {
    const timeframe = document.getElementById('timeframe').value;
    const params = new URLSearchParams(window.location.search);
    params.set('timeframe', timeframe);
    window.location.search = params.toString();
}

const overviewData = <?php echo json_encode($overviewData); ?>;
const labels = <?php echo json_encode($labels); ?>;

const colors = ['#0072B2', '#D55E00', '#009E73', '#CC79A7', '#F0E442', '#56B4E9'];

const datasets = Object.keys(overviewData).map((label, i) => ({
    label: label,
    data: overviewData[label],
    borderColor: colors[i % colors.length],
    backgroundColor: colors[i % colors.length],
    tension: 0.2,
    pointRadius: 4,
    pointHoverRadius: 6,
    fill: false
}));

const ctx = document.getElementById('overviewChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
        responsive: true,
        interaction: { mode: 'nearest', axis: 'x', intersect: false },
        plugins: {
            title: {
                display: true,
                text: '<?php echo $labelTitle; ?>',
                font: { size: 18 }
            },
            legend: {
                display: true,
                position: 'bottom',
                labels: { usePointStyle: true }
            },
            tooltip: {
                usePointStyle: true,
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const value = context.parsed.y || 0;
                        const total = context.chart.data.datasets
                            .map(ds => ds.data[context.dataIndex])
                            .reduce((a, b) => a + b, 0);
                        const percentage = total ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            },
            crosshair: {
                line: { color: '#999', width: 1 },
                sync: { enabled: false },
                zoom: { enabled: false }
            }
        },
        scales: {
            x: {
                title: { display: true, text: '<?php echo $xLabel; ?>' },
                ticks: { maxTicksLimit: 15, autoSkip: true }
            },
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Number of Records' },
                ticks: { precision: 0 }
            }
        }
    }
});
</script>

<p style="margin-top:10px;">Line graph showing record creation trends across Users, Barbers, Appointments, Orders, Products, and Reviews.</p>

