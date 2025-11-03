<?php
// admin_overview_graphs.php
if (!isset($conn)) {
    die("Database connection is required.");
}

// Capture selected time frame (default: daily)
$timeFrame = $_GET['timeframe'] ?? 'daily';
$graphType = $_GET['graph_type'] ?? 'line';
$selectedMetrics = $_GET['metrics'] ?? ['User', 'Barber', 'Appointment', 'Orders', 'Products', 'Reviews'];
if (!is_array($selectedMetrics)) {
    $selectedMetrics = [$selectedMetrics];
}

// Determine SQL date format and grouping
switch ($timeFrame) {
    case 'weekly':
        $dateFormat = "%Y-%u";
        $labelTitle = "Weekly Activity Overview";
        $xLabel = "Week";
        break;
    case 'monthly':
        $dateFormat = "%Y-%m";
        $labelTitle = "Monthly Activity Overview";
        $xLabel = "Month";
        break;
    case 'yearly':
        $dateFormat = "%Y";
        $labelTitle = "Yearly Activity Overview";
        $xLabel = "Year";
        break;
    default:
        $dateFormat = "%Y-%m-%d";
        $labelTitle = "Daily Activity Overview";
        $xLabel = "Date";
}

// Available metrics
$availableMetrics = [
    'User' => ['label' => 'Users', 'description' => 'User registrations'],
    'Barber' => ['label' => 'Barbers', 'description' => 'Barber registrations'],
    'Appointment' => ['label' => 'Appointments', 'description' => 'Booked appointments'],
    'Orders' => ['label' => 'Orders', 'description' => 'Product orders'],
    'Products' => ['label' => 'Products', 'description' => 'Product additions'],
    'Reviews' => ['label' => 'Reviews', 'description' => 'Customer reviews']
];

$allMetrics = $availableMetrics;
$overviewData = [];
$allDates = [];
$metricTotals = [];
$growthRates = [];

// Collect data
foreach ($allMetrics as $metric => $config) {
    if (!in_array($metric, $selectedMetrics)) continue;

    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(CreatedAt, ?) AS date, COUNT(*) AS count
        FROM $metric
        GROUP BY DATE_FORMAT(CreatedAt, ?)
        ORDER BY DATE_FORMAT(CreatedAt, ?) ASC
    ");
    
    $stmt->bind_param('sss', $dateFormat, $dateFormat, $dateFormat);
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = [];
    $dates = [];
    $totalCount = 0;

    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $count = (float)$row['count'];
        $counts[$date] = $count;
        $allDates[$date] = true;
        $dates[] = $date;
        $totalCount += $count;
    }

    if (count($dates) >= 2) {
        $firstCount = $counts[$dates[0]] ?? 0;
        $lastCount = $counts[$dates[count($dates)-1]] ?? 0;
        $growthRate = ($firstCount > 0)
            ? (($lastCount - $firstCount) / $firstCount) * 100
            : ($lastCount > 0 ? 100 : 0);
        $growthRates[$metric] = round($growthRate, 1);
    }

    $overviewData[$metric] = $counts;
    $metricTotals[$metric] = $totalCount;
    $stmt->close();
}

// Date range
$dateRangeStmt = $conn->prepare("
    SELECT MIN(CreatedAt) as min_date, MAX(CreatedAt) as max_date 
    FROM (
        SELECT CreatedAt FROM User 
        UNION SELECT CreatedAt FROM Barber 
        UNION SELECT CreatedAt FROM Appointment 
        UNION SELECT CreatedAt FROM Orders
        UNION SELECT CreatedAt FROM Products 
        UNION SELECT CreatedAt FROM Reviews
    ) AS all_dates
");
$dateRangeStmt->execute();
$dateRangeResult = $dateRangeStmt->get_result();
$dateRange = $dateRangeResult->fetch_assoc();
$dateRangeStmt->close();

$allDates = array_keys($allDates);
sort($allDates);

// Fill missing dates
foreach ($overviewData as $metric => $counts) {
    $filledCounts = [];
    foreach ($allDates as $date) {
        $filledCounts[$date] = $counts[$date] ?? 0;
    }
    ksort($filledCounts);
    $overviewData[$metric] = array_values($filledCounts);
}

$labels = $allDates;
$totalRecords = array_sum($metricTotals);
$activeMetrics = count($selectedMetrics);
?>

<!-- Controls -->
<div class="dashboard-controls">
    <form id="dashboardForm" method="GET" style="margin-bottom:20px;">
        <div class="control-group">
            <label for="timeframe"><strong>Time Frame:</strong></label>
            <select id="timeframe" name="timeframe" onchange="this.form.submit()">
                <option value="daily" <?= $timeFrame === 'daily' ? 'selected' : '' ?>>Daily</option>
                <option value="weekly" <?= $timeFrame === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                <option value="monthly" <?= $timeFrame === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                <option value="yearly" <?= $timeFrame === 'yearly' ? 'selected' : '' ?>>Yearly</option>
            </select>

            <label for="graph_type"><strong>Chart Type:</strong></label>
            <select id="graph_type" name="graph_type" onchange="this.form.submit()">
                <option value="line" <?= $graphType === 'line' ? 'selected' : '' ?>>Line Chart</option>
                <option value="bar" <?= $graphType === 'bar' ? 'selected' : '' ?>>Bar Chart</option>
                <option value="area" <?= $graphType === 'area' ? 'selected' : '' ?>>Area Chart</option>
            </select>
        </div>

        <div class="control-group">
            <strong>Metrics to Display:</strong>
            <div class="metrics-checkbox-group">
                <?php foreach ($allMetrics as $metric => $config): ?>
                    <label class="metric-checkbox">
                        <input type="checkbox" name="metrics[]" value="<?= $metric ?>" 
                            <?= in_array($metric, $selectedMetrics) ? 'checked' : '' ?>>
                        <?= $config['label'] ?>
                        <span class="metric-tooltip"><?= $config['description'] ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <div class="quick-actions">
                <button type="button" onclick="selectAllMetrics()" class="btn-small">Select All</button>
                <button type="button" onclick="deselectAllMetrics()" class="btn-small">Deselect All</button>
                <button type="submit" class="btn-primary">Apply Filters</button>
            </div>
        </div>
    </form>
</div>

<!-- Statistics Overview -->
<div class="stats-overview">
    <div class="stat-card">
        <h3>Overview Summary</h3>
        <p><strong>Time Frame:</strong> <?= ucfirst($timeFrame) ?></p>
        <p><strong>Metrics Displayed:</strong> <?= $activeMetrics ?></p>
        <p><strong>Total Records:</strong> <?= number_format($totalRecords) ?></p>
        <p><strong>Date Range:</strong> <?= date('M j, Y', strtotime($dateRange['min_date'])) ?> - <?= date('M j, Y', strtotime($dateRange['max_date'])) ?></p>
    </div>
</div>

<!-- Chart -->
<div class="chart-container">
    <canvas id="overviewChart" width="1200" height="500"></canvas>
</div>

<!-- Chart Controls -->
<div class="chart-controls">
    <button type="button" onclick="toggleDataPoints()" id="togglePointsBtn" class="btn-small">Hide Data Points</button>
    <button type="button" onclick="toggleGridLines()" id="toggleGridBtn" class="btn-small">Hide Grid</button>
    <button type="button" onclick="downloadChart()" class="btn-small">Download Chart</button>
    <button type="button" onclick="resetZoom()" class="btn-small">Reset Zoom</button>
</div>

<!-- Export -->
<div class="data-export">
    <h3>Export Data</h3>
    <button type="button" onclick="exportCSV()" class="btn-small">Export as CSV</button>
    <button type="button" onclick="exportJSON()" class="btn-small">Export as JSON</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js"></script>

<script>
let overviewChart;
const overviewDataJS = <?php echo json_encode($overviewData); ?>;
const labels = <?php echo json_encode($labels); ?>;
const graphType = '<?php echo $graphType; ?>';
const timeFrame = '<?php echo $timeFrame; ?>';
const allMetrics = <?php echo json_encode($allMetrics); ?>;

const colors = [
    '#3366CC', '#DC3912', '#FF9900', '#109618',
    '#990099', '#0099C6', '#DD4477', '#66AA00'
];

const datasets = Object.keys(overviewDataJS).map((metric, i) => ({
    label: allMetrics[metric].label,
    data: overviewDataJS[metric],
    borderColor: colors[i % colors.length],
    backgroundColor: colors[i % colors.length] + '40',
    tension: 0.4,
    pointRadius: 4,
    pointHoverRadius: 8,
    fill: graphType === 'area',
}));

function initializeChart() {
    const ctx = document.getElementById('overviewChart').getContext('2d');
    if (overviewChart) overviewChart.destroy();

    overviewChart = new Chart(ctx, {
        type: graphType === 'area' ? 'line' : graphType,
        data: { labels, datasets },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: '<?php echo $labelTitle; ?>' },
                legend: { position: 'top' },
                zoom: {
                    zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'x' },
                    pan: { enabled: true, mode: 'x' }
                }
            },
            scales: {
                x: {
                    title: { display: true, text: '<?php echo $xLabel; ?>' }
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Number of Records' }
                }
            }
        }
    });
}
initializeChart();

let pointsVisible = true;
function toggleDataPoints() {
    pointsVisible = !pointsVisible;
    overviewChart.data.datasets.forEach(ds => ds.pointRadius = pointsVisible ? 4 : 0);
    overviewChart.update();
    document.getElementById('togglePointsBtn').innerText = pointsVisible ? 'Hide Data Points' : 'Show Data Points';
}

let gridVisible = true;
function toggleGridLines() {
    gridVisible = !gridVisible;
    overviewChart.options.scales.x.grid.display = gridVisible;
    overviewChart.options.scales.y.grid.display = gridVisible;
    overviewChart.update();
    document.getElementById('toggleGridBtn').innerText = gridVisible ? 'Hide Grid' : 'Show Grid';
}

function downloadChart() {
    const link = document.createElement('a');
    link.href = overviewChart.toBase64Image();
    link.download = 'overview_chart.png';
    link.click();
}

function resetZoom() {
    if (overviewChart.resetZoom) overviewChart.resetZoom();
}

function exportCSV() {
    let csv = 'Date,' + Object.keys(overviewDataJS).join(',') + '\n';
    labels.forEach((label, idx) => {
        csv += label + ',' + Object.keys(overviewDataJS).map(metric => overviewDataJS[metric][idx]).join(',') + '\n';
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'overview_data.csv';
    link.click();
}

function exportJSON() {
    const jsonData = {};
    labels.forEach((label, idx) => {
        jsonData[label] = {};
        Object.keys(overviewDataJS).forEach(metric => {
            jsonData[label][metric] = overviewDataJS[metric][idx];
        });
    });
    const blob = new Blob([JSON.stringify(jsonData, null, 2)], { type: 'application/json;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'overview_data.json';
    link.click();
}

function selectAllMetrics() {
    document.querySelectorAll('input[name="metrics[]"]').forEach(cb => cb.checked = true);
}

function deselectAllMetrics() {
    document.querySelectorAll('input[name="metrics[]"]').forEach(cb => cb.checked = false);
}
</script>

<style>
.dashboard-controls { margin-bottom: 20px; }
.control-group { margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.metrics-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
.metric-checkbox { position: relative; display: flex; align-items: center; gap: 4px; }
.metric-tooltip { visibility: hidden; background: #333; color: #fff; border-radius: 4px; padding: 2px 6px; position: absolute; bottom: 120%; left: 50%; transform: translateX(-50%); font-size: 0.75rem; }
.metric-checkbox:hover .metric-tooltip { visibility: visible; }
.btn-small { padding: 4px 10px; margin-right: 5px; font-size: 0.85rem; cursor: pointer; }
.btn-primary { background: #3366CC; color: #fff; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px; }
.stats-overview { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
.stat-card { background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); flex: 1; }
.chart-container { width: 100%; height: 500px; margin-bottom: 20px; }
.chart-controls { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.data-export { margin-top: 20px; }
</style>

