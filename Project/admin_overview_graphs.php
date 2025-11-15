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
        $lastCount = $counts[$dates[count($dates) - 1]] ?? 0;
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



<!-- Statistics Overview -->
<div class="overview-grid">
    <!-- <div class="stats-overview"> -->
        <div class="stat-card">
            <h3>Overview Summary</h3>
            <p><strong>Time Frame:</strong> <?= ucfirst($timeFrame) ?></p>
            <p><strong>Metrics Displayed:</strong> <?= $activeMetrics ?></p>
            <p><strong>Total Records:</strong> <?= number_format($totalRecords) ?></p>
            <p><strong>Date Range:</strong> <?= date('M j, Y', strtotime($dateRange['min_date'])) ?> - <?= date('M j, Y', strtotime($dateRange['max_date'])) ?></p>
        </div>
    <!-- </div> -->

    <!-- Chart -->
    <div class="chart-container">
        <canvas id="overviewChart"></canvas>
    </div>

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

    <!-- Chart Controls -->
    <div class="chart-controls">
        <button type="button" onclick="toggleDataPoints()" id="togglePointsBtn" class="btn-small">Hide Data Points</button>
        <button type="button" onclick="toggleGridLines()" id="toggleGridBtn" class="btn-small">Hide Grid</button>
        <button type="button" onclick="downloadChart()" class="btn-small">Download Chart</button>
        <button type="button" onclick="resetZoom()" class="btn-small">Reset Zoom</button>
    </div>
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

    function updateLegendButtonStates() {
        if (!overviewChart) return;

        // Find the legend - Chart.js renders it near the canvas
        const chartContainer = document.querySelector('.chart-container');
        if (!chartContainer) return;

        // Look for the legend ul element (Chart.js creates it as a sibling or child)
        let legendList = chartContainer.querySelector('ul');
        if (!legendList) {
            // If not found, try finding it by looking for elements with that may be a legend
            const allLists = document.querySelectorAll('.chart-container ul, #overviewChart + ul, canvas + ul');
            for (let list of allLists) {
                if (list.querySelectorAll('li').length === overviewChart.data.datasets.length) {
                    legendList = list;
                    break;
                }
            }
        }

        if (!legendList) return;

        const legendItems = legendList.querySelectorAll('li');
        legendItems.forEach((item, index) => {
            if (index >= overviewChart.data.datasets.length) return;

            const meta = overviewChart.getDatasetMeta(index);
            const isHidden = meta.hidden;

            // Remove existing state classes
            item.classList.remove('active', 'hidden');

            // Add appropriate state class
            if (isHidden) {
                item.classList.add('hidden');
            } else {
                item.classList.add('active');
            }
        });
    }

    function initializeChart() {
        const ctx = document.getElementById('overviewChart').getContext('2d');
        if (overviewChart) overviewChart.destroy();

        overviewChart = new Chart(ctx, {
            type: graphType === 'area' ? 'line' : graphType,
            data: {
                labels,
                datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '<?php echo $labelTitle; ?>'
                    },
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 13,
                                weight: '500'
                            },
                            boxWidth: 16,
                            boxHeight: 16
                        },
                        onClick: function(e, legendItem) {
                            const index = legendItem.datasetIndex;
                            const chart = this.chart;
                            const meta = chart.getDatasetMeta(index);

                            // Toggle visibility
                            meta.hidden = !meta.hidden;
                            chart.update();

                            // Update button states after chart update
                            setTimeout(updateLegendButtonStates, 100);
                        }
                    },
                    zoom: {
                        zoom: {
                            wheel: {
                                enabled: true
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: 'x'
                        },
                        pan: {
                            enabled: true,
                            mode: 'x'
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: '<?php echo $xLabel; ?>'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Records'
                        }
                    }
                }
            }
        });

        // Update legend button states after initial render
        setTimeout(updateLegendButtonStates, 100);
    }
    initializeChart();

    // Let CSS container control height via clamp(); Chart.js will resize responsively
    window.addEventListener('resize', function() {
        if (overviewChart) overviewChart.resize();
    });

    // Ensure width re-expands with container using ResizeObserver
    (function attachResizeObserver() {
        const container = document.querySelector('.chart-container');
        const canvas = document.getElementById('overviewChart');
        if (!container || !canvas || !window.ResizeObserver) return;
        const ro = new ResizeObserver(() => {
            // Reset canvas CSS width to fluid, then ask Chart.js to recompute
            canvas.style.width = '100%';
            if (overviewChart) overviewChart.resize();
        });
        ro.observe(container);
    })();

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
        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
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
        const blob = new Blob([JSON.stringify(jsonData, null, 2)], {
            type: 'application/json;charset=utf-8;'
        });
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
    /* Chart.js Legend Button Styling */
    .chart-container {
        position: relative;
    }

    .chart-container canvas {
        margin-bottom: 20px;
    }

    /* Style the legend container - Represented as a ul element by chartjs*/
    .chart-container ul {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        margin: 0 auto 20px auto;
        padding: 10px 0;
        list-style: none;
    }

    /* Style each legend item as a button */
    .chart-container ul li {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        margin: 0;
        background-color: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 13px;
        font-weight: 500;
        color: #333;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        list-style: none;
    }

    /* Hover effect */
    .chart-container ul li:hover {
        background-color: #f5f5f5;
        border-color: #3366CC;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    /* Active/selected state - visible datasets */
    .chart-container ul li.active {
        background-color: #3366CC;
        border-color: #3366CC;
        color: #fff;
    }

    .chart-container ul li.active:hover {
        background-color: #2952a3;
        border-color: #2952a3;
    }

    /* Disabled/hidden state - hidden datasets */
    .chart-container ul li.hidden {
        opacity: 0.5;
        text-decoration: line-through;
        background-color: #f0f0f0;
        border-color: #ccc;
    }

    .chart-container ul li.hidden:hover {
        opacity: 0.7;
        background-color: #e5e5e5;
    }

    /* Style the legend box (color indicator) - Represented as a span element by chartjs */
    .chart-container ul li span {
        display: inline-block;
        vertical-align: middle;
        margin-right: 8px;
    }

    /* Ensure proper spacing for legend text */
    .chart-container ul li {
        white-space: nowrap;
    }
</style>