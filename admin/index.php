<?php
$host = 'aws-1-ap-northeast-1.pooler.supabase.com';
$port = '6543';
$dbname = 'postgres';
$user = 'postgres.okwnknzodfvbsbgetvzh';
$password = 'taliresq67_'; 

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

$activity_success = '';
$activity_error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_activity'])
) {
    $title = trim($_POST['activity_title'] ?? '');
    $description = trim($_POST['activity_message'] ?? '');
    $activityDate = $_POST['activity_date'] ?? '';
    $targetArea = trim($_POST['target_area'] ?? '');

    if (
        $title === '' ||
        $description === '' ||
        $activityDate === '' ||
        $targetArea === ''
    ) {
        $activity_error = 'Please complete all activity fields.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activityDate) ||
        !checkdate(
            (int) substr($activityDate, 5, 2),
            (int) substr($activityDate, 8, 2),
            (int) substr($activityDate, 0, 4)
        )
    ) {
        $activity_error = 'Please enter a valid activity date.';
    } else {
        try {
            $activityPdo = new PDO(
                $dsn,
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );


$activityPdo->beginTransaction();

try {
    $stmt = $activityPdo->prepare("
        INSERT INTO public.community_activities (
            title,
            description,
            activity_date,
            target_area,
            is_published
        )
        VALUES (
            :title,
            :description,
            :activity_date,
            :target_area,
            TRUE
        )
        RETURNING id
    ");

    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':activity_date' => $activityDate,
        ':target_area' => $targetArea,
    ]);

    $activityId = $stmt->fetchColumn();

    $notification = $activityPdo->prepare("
        INSERT INTO public.activity_notifications (
            activity_id,
            title,
            message
        )
        VALUES (
            :activity_id,
            :title,
            :message
        )
    ");

    $notification->execute([
        ':activity_id' => $activityId,
        ':title' => 'New Activity Posted',
        ':message' => $title . ' — ' . $targetArea,
    ]);

    $activityPdo->commit();

    $activity_success =
        'Activity and notification published successfully!';

} catch (PDOException $e) {
    if ($activityPdo->inTransaction()) {
        $activityPdo->rollBack();
    }

    throw $e;
}


        } catch (PDOException $e) {
            error_log(
                'Activity creation failed: ' . $e->getMessage()
            );

            $activity_error =
                'Unable to publish activity. Check your server error log.';
        }
    }
}

$reports = [];
$incidentTypes = [];
$reportsOverTime = [];
$totalReports = 0;
$totalUsers = 0;
$error_message = null;

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Total registered users
    $stmt = $pdo->query("
        SELECT public.get_registered_users() AS total
    ");
    $totalUsers = $stmt->fetch()['total'];

    // Total incidents (reports)
    $stmt = $pdo->query("
        SELECT COUNT(*) AS total
        FROM incidents
    ");
    $totalReports = $stmt->fetch()['total'];

    // Recent incidents
    $stmt = $pdo->query("
    SELECT
        id,
        created_at,
        category,  -- Changed from 'type' to 'category'
        location,
        status,    -- Added 'status' to display dynamically
        description,
        photo_url
    FROM incidents
    ORDER BY created_at DESC
    LIMIT 10
    ");

    $reports = $stmt->fetchAll();

    // Incidents by category for the donut chart
    $stmt = $pdo->query("
    SELECT
        category,  -- Changed from 'type' to 'category'
        COUNT(*) AS total
    FROM incidents
    GROUP BY category
    ORDER BY total DESC
    ");

    $incidentTypes = $stmt->fetchAll();

    // Reports over time (last 7 days)
    $stmt = $pdo->query("
    SELECT
        DATE(created_at) AS report_date,
        COUNT(*) AS total
    FROM incidents
    GROUP BY DATE(created_at)
    ORDER BY report_date ASC
    ");

    $reportsOverTime = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaliResQ Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Leaflet CSS for the Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/log.png">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navigation Area -->


        <!-- White Dashboard Area -->
        <div class="dashboard-wrapper">
            <h2 class="dashboard-title">DASHBOARD</h2>

            <!-- MASTER CSS GRID -->
            <div class="master-grid">
                <!-- ROW 1 (Stats) -->
                <div class="dash-card stat-card span-3">
                    <h3 class="card-title">TOTAL REPORTS</h3>
                    <p class="stat-number">
                        <?php echo $totalReports; ?>
                    </p>
                </div>
                <div class="dash-card stat-card span-3">
                    <h3 class="card-title">ACTIVE INCIDENTS</h3>
                    <p class="stat-number">0</p>
                </div>
                <div class="dash-card stat-card span-3">
                    <h3 class="card-title">REGISTERED USERS</h3>
                    <p class="stat-number">
                        <?php echo $totalUsers; ?>
                    </p>
                </div>
                <div class="dash-card stat-card span-3">
                    <h3 class="card-title">ALERTS SENT TODAY</h3>
                    <p class="stat-number">0</p>
                </div>

                <!-- ROW 2 (Table, Chart, Form) -->
                <div class="dash-card span-6">
                    <div class="card-header-flex">
                        <h3 class="card-title">RECENT INCIDENT REPORTS</h3>
                        <a href="#" class="view-all">View all</a>
                    </div>
                    <div class="inner-box mt-1">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>CATEGORY</th>
                                        <th>LOCATION</th>
                                        <th>STATUS</th>
                                        <th>DATE</th>
                                    </tr>
                                </thead>
                                <tbody id="incidentTableBody">
                                    <?php if ($error_message): ?>
                                        <tr>
                                            <td colspan="5">
                                                Database Error:
                                                <?php echo htmlspecialchars($error_message); ?>
                                            </td>
                                        </tr>
                                    <?php elseif (count($reports) === 0): ?>
                                        <tr>
                                            <td colspan="5" class="no-data-msg">
                                                No reports found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($report['id']); ?></td>
                                                <td>
                                                    <!-- Updated to category -->
                                                    <?php echo htmlspecialchars($report['category']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($report['location']); ?>
                                                </td>
                                                <td style="font-weight: 600;">
                                                    <!-- Dynamically showing status and capitalizing the first letter -->
                                                    <?php echo ucfirst(htmlspecialchars($report['status'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo date(
                                                        'M d, Y',
                                                        strtotime($report['created_at'])
                                                    ); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="dash-card span-3">
                    <h3 class="card-title mb-2">INCIDENT BY TYPE</h3>
                    <div class="chart-container">
                        <canvas id="donutChart"></canvas>
                    </div>
                    <div class="donut-legend">
                        <?php
                        $colors = [
                            '#d9534f',
                            '#f0ad4e',
                            '#5cb85c',
                            '#5bc0de',
                            '#9370DB',
                            '#FF7F50',
                            '#20B2AA',
                            '#708090'
                        ];
                        foreach ($incidentTypes as $index => $item):
                            ?>
                            <div class="legend-item">
                                <div class="dot" style="background-color: <?php echo $colors[$index % count($colors)]; ?>">
                                </div>
                                <!-- Updated to category -->
                                <?php echo htmlspecialchars($item['category']); ?>
                                (<?php echo $item['total']; ?>)
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

<div class="dash-card span-3">
    <h3 class="card-title mb-3">CREATE ACTIVITY</h3>

    <div class="form-scroll-wrapper">

        <?php if ($activity_success !== ''): ?>
            <div class="alert alert-success py-2" role="status">
                <?= htmlspecialchars($activity_success) ?>
            </div>
        <?php endif; ?>

        <?php if ($activity_error !== ''): ?>
            <div class="alert alert-danger py-2" role="alert">
                <?= htmlspecialchars($activity_error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="activity_title" class="form-label mt-0">
                ACTIVITY TITLE
            </label>

            <input
                type="text"
                id="activity_title"
                name="activity_title"
                class="form-control"
                placeholder="Enter activity title"
                maxlength="200"
                required
            >

            <label for="activity_date" class="form-label">
                ACTIVITY DATE
            </label>

            <input
                type="date"
                id="activity_date"
                name="activity_date"
                class="form-control"
                required
            >

            <label for="activity_message" class="form-label">
                MESSAGE / DESCRIPTION
            </label>

            <textarea
                id="activity_message"
                name="activity_message"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Describe the activity..."
                style="resize: none; min-height: 80px;"
                required
            ></textarea>

            <label for="target_area" class="form-label">
                TARGET AREA
            </label>

            <select
                id="target_area"
                name="target_area"
                class="form-select"
                required
            >
                <option value="">Select area</option>
                <option value="All Areas">All Areas</option>
                <option value="Talisay">Talisay</option>
                <option value="Agoncillo">Agoncillo</option>
                <option value="Laurel">Laurel</option>
                <option value="San Nicolas">San Nicolas</option>
                <option value="Santa Teresita">Santa Teresita</option>
                <option value="Lemery">Lemery</option>
            </select>

            <button
                type="submit"
                name="create_activity"
                value="1"
                class="btn-send"
            >
                <i class="bi bi-calendar-plus me-1"></i>
                PUBLISH ACTIVITY
            </button>
        </form>
    </div>
</div>


                <!-- ROW 3 (Map, Reports Graph) -->
                <div class="dash-card span-6">
                    <div class="card-header-flex">
                        <h3 class="card-title">MAP MONITORING</h3>
                        <a href="#" class="view-all">View Full Map</a>
                    </div>
                    <!-- Map Container -->
                    <div id="monitoringMap"
                        style="border-radius: 8px; flex-grow: 1; min-height: 0; margin-top: 5px; z-index: 1;"></div>
                    <div class="donut-legend" style="justify-content: flex-start; margin-top: 10px;">
                        <div class="legend-item">
                            <div class="dot" style="background-color: var(--accent-red);"></div> Critical
                        </div>
                        <div class="legend-item">
                            <div class="dot" style="background-color: var(--accent-yellow);"></div> On-Going
                        </div>
                        <div class="legend-item">
                            <div class="dot" style="background-color: var(--accent-blue);"></div> Reported
                        </div>
                        <div class="legend-item">
                            <div class="dot" style="background-color: var(--accent-green);"></div> Resolved
                        </div>
                    </div>
                </div>

                <div class="dash-card span-6">
                    <h3 class="card-title mb-2">REPORTS OVER TIME</h3>
                    <div class="inner-box mt-1 p-2 border-opacity-25">
                        <div class="chart-container">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>


    <!-- Leaflet JS for the Map -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Chart JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const incidentData = <?php echo json_encode($incidentTypes); ?>;
    </script>
    <script>
        const reportsOverTime = <?php echo json_encode($reportsOverTime); ?>;
    </script>
    <script>
        Chart.defaults.color = '#ffffff';
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

        // Donut Chart
        // FIXED: Changed item.type to item.category
        const labels = incidentData.map(item => item.category);
        const values = incidentData.map(item => Number(item.total));
        const chartColors = [
            '#d9534f',
            '#f0ad4e',
            '#5cb85c',
            '#5bc0de',
            '#9370DB',
            '#FF7F50',
            '#20B2AA',
            '#708090'
        ];
        const total = values.reduce((a, b) => a + b, 0);
        const ctxDonut = document
            .getElementById("donutChart")
            .getContext("2d");

        new Chart(ctxDonut, {
            type: "doughnut",
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: chartColors.slice(0, labels.length),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "75%",
                plugins: {
                    legend: {
                        display: false
                    }
                }
            },
            plugins: [{
                id: "textCenter",
                beforeDraw(chart) {
                    const { ctx, width, height } = chart;
                    ctx.save();
                    ctx.fillStyle = "#fff";
                    ctx.textAlign = "center";
                    ctx.font = "bold 34px Arial";
                    ctx.fillText(total, width / 2, height / 2);
                    ctx.font = "13px Arial";
                    ctx.fillText("TOTAL", width / 2, height / 2 + 20);
                    ctx.restore();
                }

            }]
        });
        // Reports Over Time
        const lineLabels = reportsOverTime.map(item => {
            const d = new Date(item.report_date);
            return d.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        });
        const lineValues = reportsOverTime.map(item => Number(item.total));
        const ctxLine = document
            .getElementById('lineChart')
            .getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: lineLabels,
                datasets: [{
                    label: 'Reports',
                    data: lineValues,
                    borderColor: '#ffffff',
                    backgroundColor: 'rgba(255,255,255,.15)',
                    fill: true,
                    borderWidth: 2,
                    tension: .35,
                    pointRadius: 4,
                    pointBackgroundColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255,255,255,.08)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Sidebar logic
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function (e) {
                if (this.parentElement.classList.contains('bottom-nav')) return;
                document.querySelectorAll('.nav-menu .nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Map Integration (Leaflet.js)
        const monitoringMap = L.map('monitoringMap').setView([14.0940, 121.0197], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(monitoringMap);
        const colors = {
            critical: '#c92a2a',
            ongoing: '#f5b041',
            reported: '#3498db',
            resolved: '#2ecc71'
        };
        function createMarker(lat, lng, colorCode, popupText) {
            L.circleMarker([lat, lng], {
                radius: 8,
                fillColor: colorCode,
                color: '#ffffff',
                weight: 2,
                fillOpacity: 1
            }).addTo(monitoringMap).bindPopup(popupText);
        }

        createMarker(14.0940, 121.0197, colors.critical, "<b>Talisay Center</b><br>Critical Volcanic Tremor");
        createMarker(14.0850, 121.0300, colors.ongoing, "<b>Aya, Talisay</b><br>Road Blockage On-Going");
        createMarker(14.1000, 121.0000, colors.reported, "<b>Banga, Talisay</b><br>Ashfall Reported");
        createMarker(14.0900, 120.9800, colors.resolved, "<b>Sampaloc</b><br>Gas Emission Resolved");

        setTimeout(() => {
            monitoringMap.invalidateSize();
        }, 100);
    </script>
</body>

</html>