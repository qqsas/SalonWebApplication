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
        $dateFormat = "%Y-%u"; // Year-week
        $labelTitle = "Weekly Activity Overview";
        $xLabel = "Week";
        $groupFormat = "WEEK(CreatedAt)";
        break;
    case 'monthly':
        $dateFormat = "%Y-%m"; // Year-month
        $labelTitle = "Monthly Activity Overview";
        $xLabel = "Month";
        $groupFormat = "MONTH(CreatedAt)";
        break;
    case 'yearly':
        $dateFormat = "%Y"; // Year
        $labelTitle = "Yearly Activity Overview";
        $xLabel = "Year";
        $groupFormat = "YEAR(CreatedAt)";
        break;
    default:
        $dateFormat = "%Y-%m-%d"; // Daily
        $labelTitle = "Daily Activity Overview";
        $xLabel = "Date";
        $groupFormat = "DATE(CreatedAt)";
}

// Available metrics with display names and descriptions
$availableMetrics = [
    'User' => ['label' => 'Users', 'description' => 'User registrations'],
    'Barber' => ['label' => 'Barbers', 'description' => 'Barber registrations'],
    'Appointment' => ['label' => 'Appointments', 'description' => 'Booked appointments'],
    'Orders' => ['label' => 'Orders', 'description' => 'Product orders'],
    'Products' => ['label' => 'Products', 'description' => 'Product additions'],
    'Reviews' => ['label' => 'Reviews', 'description' => 'Customer reviews']
];

// Additional metrics for business insights
$additionalMetrics = [
    'Revenue' => ['label' => 'Revenue', 'description' => 'Total revenue (R)', 'table' => 'Orders', 'field' => 'TotalPrice'],
    'AvgOrderValue' => ['label' => 'Avg Order Value', 'description' => 'Average order value (R)', 'table' => 'Orders', 'field' => 'TotalPrice', 'aggregate' => 'AVG'],
    'CompletedAppointments' => ['label' => 'Completed Apps', 'description' => 'Completed appointments', 'table' => 'Appointment', 'condition' => "Status = 'Completed'"]
];

// Merge all metrics
$allMetrics = array_merge($availableMetrics, $additionalMetrics);
$tables = array_keys($availableMetrics);

$overviewData = [];
$allDates = [];
$totals = [];
$metricTotals = [];
$growthRates = [];

// Collect data from selected tables
foreach ($allMetrics as $metric => $config) {
    if (!in_array($metric, $selectedMetrics)) {
        continue;
    }

    // Handle different metric types
    if (isset($config['table'])) {
        // Custom metric with specific table and conditions
        $table = $config['table'];
        $field = $config['field'] ?? '*';
        $aggregate = $config['aggregate'] ?? 'COUNT';
        $condition = $config['condition'] ?? '1=1';
        
        if ($aggregate === 'COUNT' && $field === '*') {
            $selectField = 'COUNT(*) AS count';
        } else if ($aggregate === 'SUM') {
            $selectField = "SUM($field) AS count";
        } else if ($aggregate === 'AVG') {
            $selectField = "AVG($field) AS count";
        } else {
            $selectField = "COUNT($field) AS count";
        }

        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(CreatedAt, ?) AS date, $selectField
            FROM $table
            WHERE $condition
            GROUP BY DATE_FORMAT(CreatedAt, ?)
            ORDER BY DATE_FORMAT(CreatedAt, ?) ASC
        ");
    } else {
        // Standard count metric
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(CreatedAt, ?) AS date, COUNT(*) AS count
            FROM $metric
            GROUP BY DATE_FORMAT(CreatedAt, ?)
            ORDER BY DATE_FORMAT(CreatedAt, ?) ASC
        ");
    }
    
    $stmt->bind_param('sss', $dateFormat, $dateFormat, $dateFormat);
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = [];
    $totalCount = 0;
    $dates = [];

    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $count = (float)$row['count']; // Use float for monetary values
        $counts[$date] = $count;
        $allDates[$date] = true;
        $dates[] = $date;
        $totalCount += $count;
    }

    // Calculate growth rate if we have multiple data points
    if (count($dates) >= 2) {
        $firstCount = $counts[$dates[0]] ?? 0;
        $lastCount = $counts[$dates[count($dates)-1]] ?? 0;
        if ($firstCount > 0) {
            $growthRate = (($lastCount - $firstCount) / $firstCount) * 100;
        } else {
            $growthRate = $lastCount > 0 ? 100 : 0;
        }
        $growthRates[$metric] = round($growthRate, 1);
    }

    $overviewData[$metric] = $counts;
    $metricTotals[$metric] = $totalCount;
    $stmt->close();
}

// Get date range for better filtering
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

// Fill missing dates with 0s for each label
foreach ($overviewData as $metric => $counts) {
    $filledCounts = [];
    foreach ($allDates as $date) {
        $filledCounts[$date] = $counts[$date] ?? 0;
    }
    ksort($filledCounts);
    $overviewData[$metric] = array_values($filledCounts);
}

$labels = $allDates;

// Calculate overall statistics
$totalRecords = array_sum($metricTotals);
$activeMetrics = count($selectedMetrics);
?>

<!-- Enhanced Controls Section -->
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
                <button type="button" onclick="selectBusinessMetrics()" class="btn-small">Business Metrics</button>
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

<!-- Metrics Summary Cards -->
<div class="metrics-summary">
    <?php foreach ($selectedMetrics as $metric): 
        $config = $allMetrics[$metric];
        $total = $metricTotals[$metric] ?? 0;
        $growth = $growthRates[$metric] ?? 0;
        $growthClass = $growth >= 0 ? 'positive' : 'negative';
    ?>
        <div class="metric-card" data-metric="<?= $metric ?>">
            <div class="metric-header">
                <h4><?= $config['label'] ?></h4>
                <span class="metric-total"><?= is_float($total) ? 'R' . number_format($total, 2) : number_format($total) ?></span>
            </div>
            <p class="metric-description"><?= $config['description'] ?></p>
            <?php if (isset($growthRates[$metric])): ?>
                <div class="metric-growth <?= $growthClass ?>">
                    <?= $growth >= 0 ? '↗' : '↘' ?> <?= abs($growth) ?>%
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Chart Container -->
<div class="chart-container">
    <canvas id="overviewChart" width="1200" height="500"></canvas>
</div>

<!-- Chart Controls -->
<div class="chart-controls">
    <button type="button" onclick="toggleDataPoints()" id="togglePointsBtn" class="btn-small">Hide Data Points</button>
    <button type="button" onclick="toggleGridLines()" id="toggleGridBtn" class="btn-small">Hide Grid</button>
    <button type="button" onclick="downloadChart()" class="btn-small">Download Chart</button>
    <button type="button" onclick="resetZoom()" class="btn-small">Reset Zoom</button>
    
    <label for="yAxisScale"><strong>Y-Axis:</strong></label>
    <select id="yAxisScale" onchange="updateYAxisScale()">
        <option value="linear">Linear</option>
        <option value="logarithmic">Logarithmic</option>
    </select>
</div>

<!-- Data Export -->
<div class="data-export">
    <h3>Export Data</h3>
    <button type="button" onclick="exportCSV()" class="btn-small">Export as CSV</button>
    <button type="button" onclick="exportJSON()" class="btn-small">Export as JSON</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-crosshair@1.2.0"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
// Global chart reference
let overviewChart;

// Configuration
const overviewDataJS = <?php echo json_encode($overviewData); ?>;
const labels = <?php echo json_encode($labels); ?>;
const graphType = '<?php echo $graphType; ?>';
const timeFrame = '<?php echo $timeFrame; ?>';
const allMetrics = <?php echo json_encode($allMetrics); ?>;

// Enhanced color palette
const colors = [
    '#3366CC', '#DC3912', '#FF9900', '#109618', '#990099', '#0099C6',
    '#DD4477', '#66AA00', '#B82E2E', '#316395', '#994499', '#22AA99',
    '#AAAA11', '#6633CC', '#E67300', '#8B0707', '#651067', '#329262'
];

// Create datasets based on selected metrics
const datasets = Object.keys(overviewDataJS).map((metric, i) => {
    const config = allMetrics[metric];
    const isCurrency = config.label.includes('Revenue') || config.label.includes('Value');
    
    return {
        label: config.label,
        data: overviewDataJS[metric],
        borderColor: colors[i % colors.length],
        backgroundColor: colors[i % colors.length] + '40', // Add transparency
        tension: 0.4,
        pointRadius: 4,
        pointHoverRadius: 8,
        pointBackgroundColor: colors[i % colors.length],
        fill: graphType === 'area',
        yAxisID: isCurrency ? 'yCurrency' : 'yCount'
    };
});

// Initialize chart
function initializeChart() {
    const ctx = document.getElementById('overviewChart').getContext('2d');
    
    if (overviewChart) {
        overviewChart.destroy();
    }
    
    overviewChart = new Chart(ctx, {
        type: graphType === 'area' ? 'line' : graphType,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'nearest', axis: 'x', intersect: false },
            plugins: {
                title: { display: true, text: '<?php echo $labelTitle; ?>', font: { size: 18, weight: 'bold' } },
                legend: {
                    display: true,
                    position: 'top',
                    labels: { usePointStyle: true, padding: 20, font: { size: 12 } },
                    onClick: function(e, legendItem, legend) {
                        const index = legendItem.datasetIndex;
                        const ci = this.chart;
                        const meta = ci.getDatasetMeta(index);
                        meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                        ci.update();
                    }
                },
                tooltip: {
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            let value = context.parsed.y || 0;
                            if (context.dataset.label.includes('Revenue') || context.dataset.label.includes('Value')) {
                                value = 'R' + value.toFixed(2);
                            } else {
                                value = Math.round(value).toLocaleString();
                            }
                            return `${label}: ${value}`;
                        },
                        afterLabel: function(context) {
                            const total = context.chart.data.datasets
                                .map(ds => ds.data[context.dataIndex])
                                .reduce((a, b) => a + b, 0);
                            const value = context.parsed.y || 0;
                            const percentage = total ? ((value / total) * 100).toFixed(1) : 0;
                            return `Contribution: ${percentage}%`;
                        }
                    }
                },
                zoom: { zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'x' }, pan: { enabled: true, mode: 'x' } },
                crosshair: { line: { color: '#666', width: 1, dash: [5,5] }, sync: { enabled: false }, zoom: { enabled: false } }
            },
            scales: {
                x: {
                    title: { display: true, text: '<?php echo $xLabel; ?>', font: { weight: 'bold' } },
                    ticks: { maxTicksLimit: 20, autoSkip: true, callback: function(value) {
                        const label = this.getLabelForValue(value);
                        switch(timeFrame) {
                            case 'yearly': return label;
                            case 'monthly': return label.substring(5);
                            case 'weekly': return 'W' + label.split('-')[1];
                            default: return label.substring(5);
                        }
                    }}
                },
                yCount: {
                    type: 'linear',
                    beginAtZero: true,
                    position: 'left',
                    title: { display: true, text: 'Number of Records', font: { weight: 'bold' } },
                    ticks: { precision: 0, callback: function(value) { return value >= 1000 ? (value/1000).toFixed(1)+'k' : value; } }
                },
                yCurrency: {
                    type: 'linear',
                    beginAtZero: true,
                    position: 'right',
                    title: { display: true, text: 'Amount (R)', font: { weight: 'bold'},
                    ticks: { callback: function(value) { return 'R' + value.toLocaleString(); } },
                    grid: { drawOnChartArea: false }
}
                }
            }
        }
    });
}

// Initialize chart on page load
initializeChart();

// Toggle data points
let pointsVisible = true;
function toggleDataPoints() {
    pointsVisible = !pointsVisible;
    overviewChart.data.datasets.forEach(ds => ds.pointRadius = pointsVisible ? 4 : 0);
    overviewChart.update();
    document.getElementById('togglePointsBtn').innerText = pointsVisible ? 'Hide Data Points' : 'Show Data Points';
}

// Toggle grid lines
let gridVisible = true;
function toggleGridLines() {
    gridVisible = !gridVisible;
    overviewChart.options.scales.x.grid.display = gridVisible;
    overviewChart.options.scales.yCount.grid.display = gridVisible;
    overviewChart.options.scales.yCurrency.grid.display = gridVisible;
    overviewChart.update();
    document.getElementById('toggleGridBtn').innerText = gridVisible ? 'Hide Grid' : 'Show Grid';
}

// Download chart as image
function downloadChart() {
    const link = document.createElement('a');
    link.href = overviewChart.toBase64Image();
    link.download = 'overview_chart.png';
    link.click();
}

// Reset zoom
function resetZoom() {
    if (overviewChart.resetZoom) overviewChart.resetZoom();
}

// Change Y-axis scale
function updateYAxisScale() {
    const scale = document.getElementById('yAxisScale').value;
    overviewChart.options.scales.yCount.type = scale;
    overviewChart.options.scales.yCurrency.type = scale;
    overviewChart.update();
}

// Export data as CSV
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

// Export data as JSON
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

// Metric selection shortcuts
function selectAllMetrics() {
    document.querySelectorAll('input[name="metrics[]"]').forEach(cb => cb.checked = true);
}

function deselectAllMetrics() {
    document.querySelectorAll('input[name="metrics[]"]').forEach(cb => cb.checked = false);
}

function selectBusinessMetrics() {
    const businessMetrics = ['Revenue', 'AvgOrderValue', 'CompletedAppointments'];
    document.querySelectorAll('input[name="metrics[]"]').forEach(cb => {
        cb.checked = businessMetrics.includes(cb.value);
    });
}
</script>

<style>
.dashboard-controls { margin-bottom: 20px; }
.control-group { margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.metrics-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
.metric-checkbox { position: relative; display: flex; align-items: center; gap: 4px; }
.metric-tooltip { visibility: hidden; background: #333; color: #fff; text-align: center; border-radius: 4px; padding: 2px 6px; position: absolute; z-index: 1; bottom: 120%; left: 50%; transform: translateX(-50%); font-size: 0.75rem; white-space: nowrap; }
.metric-checkbox:hover .metric-tooltip { visibility: visible; }
.btn-small { padding: 4px 10px; margin-right: 5px; font-size: 0.85rem; cursor: pointer; }
.btn-primary { background: #3366CC; color: #fff; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px; }
.stats-overview, .metrics-summary { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
.stat-card, .metric-card { background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); min-width: 180px; flex: 1; }
.metric-growth.positive { color: green; font-weight: bold; }
.metric-growth.negative { color: red; font-weight: bold; }
.chart-container { width: 100%; height: 500px; margin-bottom: 20px; }
.chart-controls { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.data-export { margin-top: 20px; }
</style>

