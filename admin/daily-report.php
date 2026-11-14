<?php
session_start();

require_once __DIR__ . '/../config/db.php';

$reportDate = $_GET['report_date'] ?? date('Y-m-d');
if (!is_string($reportDate)) {
    $reportDate = date('Y-m-d');
}

$dateObject = DateTime::createFromFormat('!Y-m-d', $reportDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
    $dateObject = DateTime::createFromFormat('!Y-m-d', $reportDate);
}

$reportStart = $reportDate . ' 00:00:00';
$reportEnd = (clone $dateObject)->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$dailyIncidents = [];
$dailySummary = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0
];
$categoryBreakdown = [];
$priorityBreakdown = ['Critical' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0];
$extremityBreakdown = ['Extreme' => 0, 'Severe' => 0, 'Moderate' => 0, 'Minor' => 0];
$resolvedOnDate = 0;
$reportError = '';

function reportIncidentStatus(array $incident): string
{
    if (!empty($incident['resolved_at'])) {
        return 'Resolved';
    }

    $status = trim((string) ($incident['resident_status'] ?? ''));
    if ($status === '') {
        $status = trim((string) ($incident['status'] ?? ''));
    }

    return $status === ''
        ? 'Submitted'
        : ucwords(strtolower($status));
}

try {
    $dailyStmt = $pdo->prepare("SELECT * FROM public.incidents
        WHERE created_at >= ? AND created_at < ?
        ORDER BY CASE priority_level
            WHEN 'Critical' THEN 1 WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END,
            created_at DESC");
    $dailyStmt->execute([$reportStart, $reportEnd]);
    $dailyIncidents = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dailyIncidents as $incident) {
        $dailySummary['total']++;
        $priority = $incident['priority_level'] ?? 'Medium';
        $extremity = $incident['severity'] ?? 'Moderate';
        $category = $incident['category'] ?? 'Unknown';
        $status = reportIncidentStatus($incident);

        if (!empty($incident['resolved_at'])) {
            $dailySummary['resolved']++;
        } else {
            $dailySummary['open']++;
            if (in_array($status, ['In Progress', 'Response In Progress'], true)) {
                $dailySummary['in_progress']++;
            }
        }

        $priorityKey = strtolower($priority);
        if (isset($dailySummary[$priorityKey])) {
            $dailySummary[$priorityKey]++;
        }
        if (isset($priorityBreakdown[$priority])) {
            $priorityBreakdown[$priority]++;
        }
        if (isset($extremityBreakdown[$extremity])) {
            $extremityBreakdown[$extremity]++;
        }

        $categoryBreakdown[$category] = ($categoryBreakdown[$category] ?? 0) + 1;
    }

    $resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM public.incidents
        WHERE resolved_at >= ? AND resolved_at < ?");
    $resolvedStmt->execute([$reportStart, $reportEnd]);
    $resolvedOnDate = (int) $resolvedStmt->fetchColumn();
    arsort($categoryBreakdown);
} catch (PDOException $e) {
    error_log('Daily incident report error: ' . $e->getMessage());
    $reportError = 'Unable to generate the daily report. Please try again.';
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDateTime($value): string {
    if (empty($value)) return 'N/A';
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? 'N/A' : date('M d, Y h:i A', $timestamp);
}

function csvSafeCell($value): string {
    $cell = (string)($value ?? '');
    return preg_match('/^[\t\r\n ]*[=+\-@]/', $cell) === 1
        ? "'" . $cell
        : $cell;
}

function reportPercentage(int $count, int $total): int {
    return $total > 0
        ? (int) round(($count / $total) * 100)
        : 0;
}

$previousReportDate = (clone $dateObject)->modify('-1 day')->format('Y-m-d');
$nextReportDate = (clone $dateObject)->modify('+1 day')->format('Y-m-d');
$csvRequested = ($_GET['export'] ?? '') === 'csv';

if ($csvRequested && $reportError === '') {
    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="taliresq-daily-report-'
        . $reportDate . '.csv"'
    );
    header('Cache-Control: no-store, max-age=0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        http_response_code(500);
        exit('Unable to start the report export.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['TaliResQ Daily Incident Report']);
    fputcsv($output, ['Report date', $reportDate]);
    fputcsv($output, ['Total reported', $dailySummary['total']]);
    fputcsv($output, ['Open', $dailySummary['open']]);
    fputcsv($output, ['Response in progress', $dailySummary['in_progress']]);
    fputcsv($output, ['Resolved (reported that day)', $dailySummary['resolved']]);
    fputcsv($output, ['Resolved on selected date', $resolvedOnDate]);
    fputcsv($output, []);
    fputcsv($output, [
        'ID',
        'Incident',
        'Location',
        'Priority',
        'Extremity',
        'Status',
        'Date reported',
        'Date resolved'
    ]);

    foreach ($dailyIncidents as $incident) {
        fputcsv($output, array_map('csvSafeCell', [
            $incident['incident_id'] ?? '',
            $incident['category'] ?? 'Unknown',
            $incident['location'] ?? 'Unknown',
            $incident['priority_level'] ?? 'Medium',
            $incident['severity'] ?? 'Moderate',
            reportIncidentStatus($incident),
            formatDateTime($incident['created_at'] ?? null),
            formatDateTime($incident['resolved_at'] ?? null)
        ]));
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Incident Report | TaliResq</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css?v=20261114i">
    <link rel="stylesheet" href="assets/daily-report.css?v=20261114d">
    <link rel="icon" type="image/png" href="assets/log.png">
</head>
<body class="daily-report-page">
<?php include 'sidebar.php'; ?>
<main class="main-content daily-report-content">
    <div class="dashboard-wrapper daily-report-wrapper">
        <div class="records-header report-page-header">
            <div>
                <p class="report-eyebrow">INCIDENT ANALYTICS</p>
                <h1 class="report-page-title admin-page-title">Daily incident report</h1>
                <p class="report-page-description">
                    Review daily activity, incident severity, response status, and resolutions.
                </p>
            </div>
            <div class="records-header-actions no-print">
                <a href="records.php" class="active-btn"><i class="bi bi-list-ul"></i> Incident Records</a>
            </div>
        </div>

        <section class="daily-report" id="dailyReport">
            <div class="daily-report-header no-print">
                <div>
                    <h3><i class="bi bi-file-earmark-bar-graph"></i> Daily Incident Report</h3>
                    <p>Choose a report date, then print or download a copy for your records.</p>
                </div>
                <div class="report-export-actions">
                    <a
                        class="report-export-btn no-print"
                        href="daily-report.php?report_date=<?= h($reportDate); ?>&amp;export=csv"
                    >
                        <i class="bi bi-download" aria-hidden="true"></i>
                        Download CSV
                    </a>
                    <button type="button" class="report-print-btn" onclick="printDailyReport()">
                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                        Print / Save PDF
                    </button>
                </div>
            </div>

            <form method="GET" action="daily-report.php" class="report-date-form no-print">
                <div class="report-date-field">
                    <label for="report_date">Report Date</label>
                    <div class="report-date-input-row">
                        <a
                            class="report-date-nav"
                            href="daily-report.php?report_date=<?= h($previousReportDate); ?>"
                            aria-label="View previous day"
                            title="Previous day"
                        >
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </a>
                        <input
                            type="date"
                            name="report_date"
                            id="report_date"
                            value="<?= h($reportDate); ?>"
                            required
                        >
                        <a
                            class="report-date-nav"
                            href="daily-report.php?report_date=<?= h($nextReportDate); ?>"
                            aria-label="View next day"
                            title="Next day"
                        >
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
                <div class="report-date-actions">
                    <button type="submit" class="report-generate-btn">
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        Generate Report
                    </button>
                    <a
                        class="report-today-btn"
                        href="daily-report.php?report_date=<?= h(date('Y-m-d')); ?>"
                    >
                        Today
                    </a>
                </div>
            </form>

            <?php if ($reportError !== ''): ?>
                <div class="report-error"><?= h($reportError); ?></div>
            <?php else: ?>
                <div id="printableDailyReport">
                    <div class="print-report-heading">
                        <h2>TALIRESQ</h2>
                        <h3>DAILY INCIDENT REPORT</h3>
                        <p>Report Date: <strong><?= h(date('F d, Y', strtotime($reportDate))); ?></strong></p>
                        <p class="report-generated-at">Generated on <?= h(date('F d, Y h:i A')); ?></p>
                    </div>

                    <div class="report-summary-grid">
                        <div class="report-stat total-stat"><span>Total Reported</span><strong><?= (int)$dailySummary['total']; ?></strong></div>
                        <div class="report-stat open-stat"><span>Open (Unresolved)</span><strong><?= (int)$dailySummary['open']; ?></strong></div>
                        <div class="report-stat progress-stat"><span>Response in Progress</span><strong><?= (int)$dailySummary['in_progress']; ?></strong></div>
                        <div class="report-stat resolved-stat"><span>Resolved (Reported That Day)</span><strong><?= (int)$dailySummary['resolved']; ?></strong></div>
                        <div class="report-stat resolved-today-stat"><span>Resolved on Selected Date</span><strong><?= (int)$resolvedOnDate; ?></strong></div>
                    </div>
                    <p class="report-summary-note">
                        Reported counts refer to incidents created on this date. “Resolved on selected date” also includes incidents reported earlier.
                    </p>

                    <div class="report-breakdown-grid">
                        <div class="report-breakdown-card priority-breakdown">
                            <h4>Priority Breakdown</h4>
                            <?php foreach ($priorityBreakdown as $label => $count): ?>
                                <?php $percentage = reportPercentage((int)$count, (int)$dailySummary['total']); ?>
                                <div class="breakdown-row">
                                    <div class="breakdown-row-label">
                                        <span><?= h($label); ?></span>
                                        <strong><?= (int)$count; ?></strong>
                                    </div>
                                    <div class="breakdown-track" role="img" aria-label="<?= h($label . ': ' . $count . ' of ' . $dailySummary['total'] . ' incidents'); ?>">
                                        <span class="breakdown-bar-fill" style="width: <?= $percentage; ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="report-breakdown-card extremity-breakdown">
                            <h4>Extremity Breakdown</h4>
                            <?php foreach ($extremityBreakdown as $label => $count): ?>
                                <?php $percentage = reportPercentage((int)$count, (int)$dailySummary['total']); ?>
                                <div class="breakdown-row">
                                    <div class="breakdown-row-label">
                                        <span><?= h($label); ?></span>
                                        <strong><?= (int)$count; ?></strong>
                                    </div>
                                    <div class="breakdown-track" role="img" aria-label="<?= h($label . ': ' . $count . ' of ' . $dailySummary['total'] . ' incidents'); ?>">
                                        <span class="breakdown-bar-fill" style="width: <?= $percentage; ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="report-breakdown-card category-breakdown">
                            <h4>Incident Category Breakdown</h4>
                            <?php if ($categoryBreakdown): ?>
                                <?php foreach ($categoryBreakdown as $label => $count): ?>
                                    <?php $percentage = reportPercentage((int)$count, (int)$dailySummary['total']); ?>
                                    <div class="breakdown-row">
                                        <div class="breakdown-row-label">
                                            <span><?= h($label); ?></span>
                                            <strong><?= (int)$count; ?></strong>
                                        </div>
                                        <div class="breakdown-track" role="img" aria-label="<?= h($label . ': ' . $count . ' of ' . $dailySummary['total'] . ' incidents'); ?>">
                                            <span class="breakdown-bar-fill" style="width: <?= $percentage; ?>%"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="report-empty">No incidents reported.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="report-details-section">
                        <h4>Incident Details</h4>
                        <p class="report-table-description">Incidents reported on <?= h(date('F d, Y', strtotime($reportDate))); ?>.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered report-details-table">
                                <caption class="visually-hidden">
                                    Incidents reported on <?= h(date('F d, Y', strtotime($reportDate))); ?>
                                </caption>
                                <thead><tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Incident</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Extremity</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date reported</th>
                                    <th scope="col">Date resolved</th>
                                </tr></thead>
                                <tbody>
                                <?php if ($dailyIncidents): ?>
                                    <?php foreach ($dailyIncidents as $item): ?>
                                        <?php
                                        $itemStatus = reportIncidentStatus($item);
                                        $itemPriority = $item['priority_level'] ?? 'Medium';
                                        $itemExtremity = $item['severity'] ?? 'Moderate';
                                        $priorityClasses = [
                                            'Critical' => 'report-priority-critical',
                                            'High' => 'report-priority-high',
                                            'Medium' => 'report-priority-medium',
                                            'Low' => 'report-priority-low'
                                        ];
                                        $extremityClasses = [
                                            'Extreme' => 'report-severity-extreme',
                                            'Severe' => 'report-severity-severe',
                                            'Moderate' => 'report-severity-moderate',
                                            'Minor' => 'report-severity-minor'
                                        ];
                                        $statusClasses = [
                                            'Resolved' => 'report-status-resolved',
                                            'Submitted' => 'report-status-open',
                                            'Under Review' => 'report-status-open',
                                            'Verified' => 'report-status-progress',
                                            'In Progress' => 'report-status-progress',
                                            'Response In Progress' => 'report-status-progress',
                                            'Rejected' => 'report-status-rejected'
                                        ];
                                        ?>
                                        <tr>
                                            <td>#<?= h($item['incident_id'] ?? ''); ?></td>
                                            <td><?= h($item['category'] ?? 'Unknown'); ?></td>
                                            <td><?= h($item['location'] ?? 'Unknown'); ?></td>
                                            <td>
                                                <span class="report-pill <?= h($priorityClasses[$itemPriority] ?? 'report-status-neutral'); ?>">
                                                    <?= h($itemPriority); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="report-pill <?= h($extremityClasses[$itemExtremity] ?? 'report-status-neutral'); ?>">
                                                    <?= h($itemExtremity); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="report-pill <?= h($statusClasses[$itemStatus] ?? 'report-status-neutral'); ?>">
                                                    <?= h($itemStatus); ?>
                                                </span>
                                            </td>
                                            <td><?= h(formatDateTime($item['created_at'] ?? null)); ?></td>
                                            <td><?= h(formatDateTime($item['resolved_at'] ?? null)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="report-empty">No incidents were reported on this date.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="report-footer">
                            <span>Total incidents reported: <?= (int)$dailySummary['total']; ?></span>
                            <span>TaliResq Incident Management System</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<script>
function printDailyReport() {
    if (!document.getElementById('printableDailyReport')) {
        alert('The daily report could not be found.');
        return;
    }
    window.print();
}
</script>
</body>
</html>
