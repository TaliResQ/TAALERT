<?php
session_start();
include '../config/db.php';

$reportDate = $_GET['report_date'] ?? date('Y-m-d');
$dateObject = DateTime::createFromFormat('!Y-m-d', $reportDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
}
$reportStart = $reportDate . ' 00:00:00';
$reportEnd = (new DateTime($reportDate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$dailyIncidents = [];
$dailySummary = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0,
    'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
$categoryBreakdown = [];
$priorityBreakdown = ['Critical' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0];
$extremityBreakdown = ['Extreme' => 0, 'Severe' => 0, 'Moderate' => 0, 'Minor' => 0];
$resolvedOnDate = 0;
$reportError = '';

try {
    $dailyStmt = $pdo->prepare("SELECT * FROM incidents
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

        if (!empty($incident['resolved_at'])) {
            $dailySummary['resolved']++;
        } else {
            $status = $incident['status'] ?? 'Pending';
            if ($status === 'Pending') $dailySummary['pending']++;
            elseif ($status === 'In Progress') $dailySummary['in_progress']++;
        }
        $priorityKey = strtolower($priority);
        if (isset($dailySummary[$priorityKey])) $dailySummary[$priorityKey]++;
        if (isset($priorityBreakdown[$priority])) $priorityBreakdown[$priority]++;
        if (isset($extremityBreakdown[$extremity])) $extremityBreakdown[$extremity]++;
        $categoryBreakdown[$category] = ($categoryBreakdown[$category] ?? 0) + 1;
    }

    $resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM incidents
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Incident Report | TaliResq</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/daily-report.css">
    <link rel="icon" type="image/png" href="assets/log.png">
</head>
<body>
<?php include 'sidebar.php'; ?>
<main class="main-content">
    <div class="dashboard-wrapper">
        <div class="records-header report-page-header">
            <div>
                <h2 class="dashboard-title mb-1">DAILY INCIDENT REPORT</h2>
                <div class="archive-info">Generate a daily summary of reported and resolved incidents.</div>
            </div>
            <div class="records-header-actions no-print">
                <a href="records.php" class="active-btn"><i class="bi bi-list-ul"></i> Incident Records</a>
            </div>
        </div>

        <section class="daily-report" id="dailyReport">
            <div class="daily-report-header no-print">
                <div>
                    <h3><i class="bi bi-file-earmark-bar-graph"></i> Daily Incident Report</h3>
                    <p>Choose a date to review reported incidents, priority, extremity, and resolution totals.</p>
                </div>
                <button type="button" class="report-print-btn" onclick="printDailyReport()">
                    <i class="bi bi-file-earmark-pdf"></i> Print / Save PDF
                </button>
            </div>

            <form method="GET" action="daily_report.php" class="report-date-form no-print">
                <div>
                    <label for="report_date">Report Date</label>
                    <input type="date" name="report_date" id="report_date" value="<?= h($reportDate); ?>" required>
                </div>
                <button type="submit" class="report-generate-btn"><i class="bi bi-calendar-check"></i> Generate Report</button>
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
                        <div class="report-stat pending-stat"><span>Pending</span><strong><?= (int)$dailySummary['pending']; ?></strong></div>
                        <div class="report-stat progress-stat"><span>In Progress</span><strong><?= (int)$dailySummary['in_progress']; ?></strong></div>
                        <div class="report-stat resolved-stat"><span>Resolved (Reported That Day)</span><strong><?= (int)$dailySummary['resolved']; ?></strong></div>
                        <div class="report-stat resolved-today-stat"><span>Resolved on Selected Date</span><strong><?= (int)$resolvedOnDate; ?></strong></div>
                    </div>

                    <div class="report-breakdown-grid">
                        <div class="report-breakdown-card">
                            <h4>Priority Breakdown</h4>
                            <?php foreach ($priorityBreakdown as $label => $count): ?>
                                <div class="breakdown-row"><span><?= h($label); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="report-breakdown-card">
                            <h4>Extremity Breakdown</h4>
                            <?php foreach ($extremityBreakdown as $label => $count): ?>
                                <div class="breakdown-row"><span><?= h($label); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="report-breakdown-card">
                            <h4>Incident Category Breakdown</h4>
                            <?php if ($categoryBreakdown): ?>
                                <?php foreach ($categoryBreakdown as $label => $count): ?>
                                    <div class="breakdown-row"><span><?= h($label); ?></span><strong><?= (int)$count; ?></strong></div>
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
                                <thead><tr>
                                    <th>ID</th><th>Incident</th><th>Location</th><th>Priority</th>
                                    <th>Extremity</th><th>Status</th><th>Date Reported</th><th>Date Resolved</th>
                                </tr></thead>
                                <tbody>
                                <?php if ($dailyIncidents): ?>
                                    <?php foreach ($dailyIncidents as $item): ?>
                                        <?php $itemStatus = !empty($item['resolved_at']) ? 'Resolved' : ($item['status'] ?? 'Pending'); ?>
                                        <tr>
                                            <td>#<?= h($item['id'] ?? ''); ?></td>
                                            <td><?= h($item['category'] ?? 'Unknown'); ?></td>
                                            <td><?= h($item['location'] ?? 'Unknown'); ?></td>
                                            <td><?= h($item['priority_level'] ?? 'Medium'); ?></td>
                                            <td><?= h($item['severity'] ?? 'Moderate'); ?></td>
                                            <td><?= h($itemStatus); ?></td>
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
