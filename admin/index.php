
<?php
/*
|--------------------------------------------------------------------------
| TALIRESQ - ADMIN DASHBOARD
|--------------------------------------------------------------------------
| PHP 7.4+ | Supabase PostgreSQL | Bootstrap 5.3
|
| Existing tables:
| users
| incidents
| community_activities
| activity_notifications
|
| Existing files:
| config/db.php
| sidebar.php
| assets/style.css
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

function dashboardLog($section, $error)
{
    error_log(
        '[TaliResQ Dashboard - ' . $section . '] ' .
        $error->getMessage()
    );
}

$error_message = '';
$activity_success = '';
$activity_error = '';

require_once __DIR__ . '/../config/db.php';
/*
|--------------------------------------------------------------------------
| DASHBOARD DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$totalReports = 0;
$activeIncidents = 0;
$totalUsers = 0;
$totalActivities = 0;
$publishedAlerts = 0;
$pendingVerifications = 0;
$openRequests = 0;
$emergencyHotlines = 0;

$reports = [];
$incidentTypes = [];
$reportsOverTime = [];
$incidentStatusCounts = [];
$assistanceStatusCounts = [];
$verificationStatusCounts = [];

/*
|--------------------------------------------------------------------------
| CREATE COMMUNITY ACTIVITY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_activity'])
) {
    $title = trim($_POST['activity_title'] ?? '');
    $description = trim($_POST['activity_message'] ?? '');
    $activityDate = trim($_POST['activity_date'] ?? '');
    $targetArea = trim($_POST['target_area'] ?? '');

    if (
        $title === '' ||
        $description === '' ||
        $activityDate === '' ||
        $targetArea === ''
    ) {
        $activity_error = 'Please complete all required fields.';

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activityDate) ||
        !checkdate(
            (int) substr($activityDate, 5, 2),
            (int) substr($activityDate, 8, 2),
            (int) substr($activityDate, 0, 4)
        )
    ) {
        $activity_error = 'Please enter a valid activity date.';

    } elseif (!$pdo) {
        $activity_error = 'Database connection is unavailable.';

    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
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
                RETURNING activity_id
            ");

            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':activity_date' => $activityDate,
                ':target_area' => $targetArea
            ]);

            $activityId = $stmt->fetchColumn();

            if (!$activityId) {
                throw new RuntimeException(
                    'Activity ID was not returned.'
                );
            }

            $notification = $pdo->prepare("
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
                ':message' => $title . ' — ' . $targetArea
            ]);

            $pdo->commit();

            $activity_success =
                'Community activity successfully published!';

            // Clear submitted values after successful publishing.
            $_POST = [];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            dashboardLog('Activity Creation', $e);

            $activity_error =
                'Unable to publish activity. Please check the server log.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| SAFE DATABASE QUERY
|--------------------------------------------------------------------------
*/

function fetchDashboardValue($pdo, $label, $sql, &$error_message)
{
    try {
        return $pdo->query($sql)->fetchColumn();

    } catch (Throwable $e) {
        dashboardLog($label, $e);

        $error_message =
            'Some dashboard information could not be loaded.';

        return 0;
    }
}

function fetchDashboardRows($pdo, $label, $sql, &$error_message)
{
    try {
        return $pdo->query($sql)->fetchAll();

    } catch (Throwable $e) {
        dashboardLog($label, $e);

        $error_message =
            'Some dashboard information could not be loaded.';

        return [];
    }
}

/*
|--------------------------------------------------------------------------
| LOAD DASHBOARD DATA
|--------------------------------------------------------------------------
*/

if ($pdo) {

    // Total incident reports.
    $totalReports = (int) fetchDashboardValue(
        $pdo,
        'Total Reports',
        "
            SELECT COUNT(*)
            FROM incidents
        ",
        $error_message
    );

    // Active incidents.
    // resolved_at IS NULL = active.
    $activeIncidents = (int) fetchDashboardValue(
        $pdo,
        'Active Incidents',
        "
            SELECT COUNT(*)
            FROM incidents
            WHERE resolved_at IS NULL
        ",
        $error_message
    );

    // Registered users.
    $totalUsers = (int) fetchDashboardValue(
        $pdo,
        'Registered Users',
        "
            SELECT COUNT(*)
            FROM public.users
        ",
        $error_message
    );

    // Published community activities.
    $totalActivities = (int) fetchDashboardValue(
        $pdo,
        'Published Activities',
        "
            SELECT COUNT(*)
            FROM public.community_activities
            WHERE is_published = TRUE
        ",
        $error_message
    );

    // Published public alerts.
    $publishedAlerts = (int) fetchDashboardValue(
        $pdo,
        'Published Alerts',
        "
            SELECT COUNT(*)
            FROM public.alerts
            WHERE is_published = TRUE
        ",
        $error_message
    );

    // Resident accounts awaiting verification.
    $pendingVerifications = (int) fetchDashboardValue(
        $pdo,
        'Pending Resident Verifications',
        "
            SELECT COUNT(*)
            FROM public.users
            WHERE role::text = 'resident'
              AND verification_status::text = 'pending'
        ",
        $error_message
    );

    // Assistance requests that have not been completed or rejected.
    $openRequests = (int) fetchDashboardValue(
        $pdo,
        'Open Assistance Requests',
        "
            SELECT COUNT(*)
            FROM public.assistance_requests
            WHERE status::text IN (
                'pending',
                'under_review',
                'assistance_coordinated',
                'needs_more_information'
            )
        ",
        $error_message
    );

    // Emergency contact records.
    $emergencyHotlines = (int) fetchDashboardValue(
        $pdo,
        'Emergency Hotlines',
        "
            SELECT COUNT(*)
            FROM hotlines
            WHERE is_emergency = TRUE
        ",
        $error_message
    );

    /*
    |--------------------------------------------------------------------------
    | RECENT INCIDENT REPORTS
    |--------------------------------------------------------------------------
    */

    $reports = fetchDashboardRows(
        $pdo,
        'Recent Reports',
        "
            SELECT
                incident_id,
                created_at,
                category,
                location,
                status,
                description
            FROM incidents
            ORDER BY created_at DESC
            LIMIT 10
        ",
        $error_message
    );

    /*
    |--------------------------------------------------------------------------
    | INCIDENT CATEGORIES
    |--------------------------------------------------------------------------
    */

    $incidentTypes = fetchDashboardRows(
        $pdo,
        'Incident Categories',
        "
            SELECT
                COALESCE(category, 'Uncategorized') AS category,
                COUNT(*) AS total
            FROM incidents
            GROUP BY COALESCE(category, 'Uncategorized')
            ORDER BY total DESC
        ",
        $error_message
    );

    /*
    |--------------------------------------------------------------------------
    | REPORTS OVER THE LAST SEVEN DAYS
    |--------------------------------------------------------------------------
    | Generate all seven dates, including dates with zero reports.
    |--------------------------------------------------------------------------
    */

    $reportsOverTime = fetchDashboardRows(
        $pdo,
        'Reports Over Time',
        "
            SELECT
                days.report_date::date AS report_date,
                COUNT(i.incident_id) AS total
            FROM generate_series(
                CURRENT_DATE - INTERVAL '6 days',
                CURRENT_DATE,
                INTERVAL '1 day'
            ) AS days(report_date)
            LEFT JOIN incidents i
                ON DATE(i.created_at) = days.report_date::date
            GROUP BY days.report_date
            ORDER BY days.report_date ASC
        ",
        $error_message
    );

    $incidentStatusCounts = fetchDashboardRows(
        $pdo,
        'Incident Status Counts',
        "
            SELECT 'Open' AS status, COUNT(*) AS total
            FROM incidents
            WHERE resolved_at IS NULL

            UNION ALL

            SELECT 'Resolved' AS status, COUNT(*) AS total
            FROM incidents
            WHERE resolved_at IS NOT NULL
        ",
        $error_message
    );

    $assistanceStatusCounts = fetchDashboardRows(
        $pdo,
        'Assistance Request Status Counts',
        "
            SELECT
                status::text AS status,
                COUNT(*) AS total
            FROM public.assistance_requests
            GROUP BY status::text
            ORDER BY total DESC
        ",
        $error_message
    );

    $verificationStatusCounts = fetchDashboardRows(
        $pdo,
        'Resident Verification Status Counts',
        "
            SELECT
                verification_status::text AS status,
                COUNT(*) AS total
            FROM public.users
            WHERE role::text = 'resident'
            GROUP BY verification_status::text
            ORDER BY total DESC
        ",
        $error_message
    );
}

/*
|--------------------------------------------------------------------------
| CHART DATA
|--------------------------------------------------------------------------
*/

$chartColors = [
    '#3978c5',
    '#d98735',
    '#258253',
    '#8b75c9',
    '#c94b45',
    '#36a6a6',
    '#71849a',
    '#d16b9c'
];

$jsonFlags =
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP;

$incidentTypesJson = json_encode($incidentTypes, $jsonFlags);
$reportsOverTimeJson = json_encode($reportsOverTime, $jsonFlags);
$incidentStatusCountsJson = json_encode($incidentStatusCounts, $jsonFlags);
$assistanceStatusCountsJson = json_encode($assistanceStatusCounts, $jsonFlags);
$verificationStatusCountsJson = json_encode($verificationStatusCounts, $jsonFlags);
$chartColorsJson = json_encode($chartColors);

$incidentTypesJson = $incidentTypesJson ?: '[]';
$reportsOverTimeJson = $reportsOverTimeJson ?: '[]';
$incidentStatusCountsJson = $incidentStatusCountsJson ?: '[]';
$assistanceStatusCountsJson = $assistanceStatusCountsJson ?: '[]';
$verificationStatusCountsJson = $verificationStatusCountsJson ?: '[]';
$chartColorsJson = $chartColorsJson ?: '[]';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>TaliResQ | Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="stylesheet" href="assets/style.css?v=20261114i">

    <link rel="icon" type="image/png" href="assets/log.png">

<style>
/* =========================================================
   01. GLOBAL DESIGN
========================================================= */

:root {
    --navy: #203b6b;
    --navy-dark: #082747;

    --blue: #3978c5;
    --blue-soft: #edf4fc;

    --green: #258253;
    --green-soft: #eaf5ee;

    --orange: #d98735;
    --orange-soft: #fff3e6;

    --purple: #8b75c9;
    --purple-soft: #f2effa;

    --red: #c94b45;
    --red-soft: #fceeed;

    --text: #183653;
    --muted: #62768a;

    --border: #e3eaf1;
    --surface: #ffffff;
    --background: #f4f7fb;

    --shadow: 0 3px 14px rgba(22, 48, 78, .045);
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    background: var(--navy) !important;
    color: var(--text) !important;
    font-family: "Segoe UI", Arial, Helvetica, sans-serif;
    font-size: 14px;
}

/* =========================================================
   02. SIDEBAR AND MAIN CONTENT
========================================================= */

.sidebar {
    position: fixed !important;
    inset: 0 auto 0 0 !important;

    width: 225px !important;
    min-width: 225px !important;
    height: 100vh !important;

    z-index: 1000;
}

.main-content {
    width: calc(100% - 225px) !important;
    min-height: 100vh !important;

    margin-left: 225px !important;
    padding: 7px !important;

    background: transparent !important;
}

.dashboard-wrapper {
    min-height: calc(100vh - 14px);
    width: 100%;

    padding: 22px 24px 28px !important;

    background: #fff !important;
    border-radius: 15px !important;
    color: var(--text) !important;
}

/* =========================================================
   03. DASHBOARD HEADER
========================================================= */

.dashboard-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 12px;
    margin-bottom: 16px;
}

.dashboard-title {
    margin: 0 0 6px !important;

    color: var(--navy-dark) !important;
    font-family: "Segoe UI", Arial, Helvetica, sans-serif !important;
    font-size: 25px !important;
    font-weight: 900 !important;

    letter-spacing: -.7px;
}

.dashboard-subtitle {
    margin: 0;

    color: var(--muted) !important;
    font-size: 13px;
}

.dashboard-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dashboard-date {
    display: flex;
    align-items: center;
    gap: 7px;

    color: #62768a;
    font-size: 12px;
    white-space: nowrap;
}

.header-action {
    display: flex;
    align-items: center;
    justify-content: center;

    width: 38px;
    height: 38px;

    border-radius: 11px;

    background: var(--navy-dark);
    color: white;

    font-size: 17px;
    text-decoration: none;

    transition: .2s;
}

.header-action:hover {
    background: var(--blue);
    color: white;
}

/* =========================================================
   04. DASHBOARD GRID
========================================================= */

.master-grid {
    display: grid !important;

    grid-template-columns: repeat(12, minmax(0, 1fr));
    grid-template-rows: none !important;
    grid-auto-rows: minmax(100px, auto);

    gap: var(--dashboard-card-gap) !important;
    align-items: stretch;
}

.span-3 { grid-column: span 3; }
.span-4 { grid-column: span 4; }
.span-5 { grid-column: span 5; }
.span-6 { grid-column: span 6; }
.span-7 { grid-column: span 7; }
.span-8 { grid-column: span 8; }
.span-12 { grid-column: span 12; }

.overview-chart-half { grid-column: span 6; }
.verification-summary-card { grid-column: span 3; }
.advisory-card { grid-column: span 9; }

/* =========================================================
   05. DASHBOARD CARDS
========================================================= */

.dash-card {
    position: relative;
    min-width: 0;

    padding: 16px !important;

    background: var(--surface) !important;

    border: 1px solid #dfe7f0 !important;
    border-radius: 11px !important;

    box-shadow: var(--shadow) !important;

    color: var(--text) !important;

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        transform .2s ease;
}

.dash-card:hover {
    border-color: #bfd0e3 !important;
    box-shadow: 0 6px 19px rgba(22, 48, 78, .075) !important;
}

.card-heading {
    display: flex;
    align-items: center;
    gap: 9px;

    margin-bottom: 12px;
}

.card-heading-icon {
    display: flex;
    align-items: center;
    justify-content: center;

    width: 34px;
    height: 34px;
    min-width: 34px;

    border-radius: 10px;

    background: var(--blue-soft);
    color: var(--blue);

    font-size: 16px;
}

.card-title {
    margin: 0 !important;

    color: var(--navy-dark) !important;
    font-family: "Segoe UI", Arial, Helvetica, sans-serif !important;
    font-size: 15px !important;
    font-weight: 850 !important;

    letter-spacing: -.1px;
}

.card-description {
    margin: 4px 0 0;

    color: var(--muted);
    font-size: 12px;
    line-height: 1.45;
}

.card-header-flex {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;
    margin-bottom: 12px;
}

.view-all {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    color: var(--blue) !important;

    font-size: 12px;
    font-weight: 750;

    text-decoration: none;
    white-space: nowrap;
}

.view-all:hover {
    text-decoration: underline;
}

/* =========================================================
   06. SUMMARY STATISTICS
========================================================= */

.stat-card {
    display: flex;
    align-items: center;

    min-height: 88px;

    padding: 14px !important;

    border-top: 3px solid var(--blue) !important;
}

.stat-card:nth-child(2) {
    border-top-color: var(--red) !important;
}

.stat-card:nth-child(3) {
    border-top-color: var(--green) !important;
}

.stat-card:nth-child(4) {
    border-top-color: var(--purple) !important;
}

.stat-card:nth-child(5) {
    border-top-color: var(--orange) !important;
}

.stat-card:nth-child(6) {
    border-top-color: var(--purple) !important;
}

.stat-card:nth-child(7) {
    border-top-color: var(--red) !important;
}

.stat-card:nth-child(8) {
    border-top-color: var(--green) !important;
}

.stat-inner {
    display: flex;
    align-items: center;
    gap: 11px;
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;

    width: 40px;
    height: 40px;
    min-width: 40px;

    border-radius: 12px;

    background: var(--blue-soft);
    color: var(--blue);

    font-size: 18px;
}

.stat-icon.red {
    background: var(--red-soft);
    color: var(--red);
}

.stat-icon.green {
    background: var(--green-soft);
    color: var(--green);
}

.stat-icon.purple {
    background: var(--purple-soft);
    color: var(--purple);
}

.stat-icon.orange {
    background: var(--orange-soft);
    color: var(--orange);
}

.stat-label {
    display: block;

    margin-bottom: 4px;

    color: #5e7388;
    font-size: 12px;
    font-weight: 600;
}

.stat-number {
    margin: 0 !important;

    color: var(--navy-dark) !important;
    font-family: "Segoe UI", Arial, Helvetica, sans-serif !important;
    font-size: 27px !important;
    font-weight: 850 !important;

    line-height: 1;
}

/* =========================================================
   07. ALERT MESSAGES
========================================================= */

.dashboard-alert {
    display: flex;
    align-items: center;
    gap: 8px;

    padding: 10px 12px;
    margin-bottom: 12px;

    border: 1px solid #f0d9a2;
    border-radius: 9px;

    background: #fff8e8;
    color: #89631c;

    font-size: 13px;
}

/* =========================================================
   08. RECENT INCIDENT TABLE
========================================================= */

.inner-box {
    overflow: hidden;

    border: 1px solid var(--border);
    border-radius: 10px;
}

.table-responsive {
    border-radius: 10px;
}

.dash-card table {
    width: 100%;
    margin: 0;

    border-collapse: collapse;
}

.dash-card table thead {
    background: #f5f8fc !important;
}

.dash-card table th {
    padding: 10px 10px !important;

    border-bottom: 1px solid var(--border) !important;

    color: #5e7388 !important;

    font-family: "Segoe UI", Arial, Helvetica, sans-serif !important;
    font-size: 11px !important;
    font-weight: 800 !important;

    letter-spacing: .35px;
    text-transform: uppercase;
    white-space: nowrap;
}

.dash-card table td {
    padding: 10px 10px !important;

    border-bottom: 1px solid #edf1f5 !important;

    color: #344e67 !important;
    font-size: 12px !important;

    vertical-align: middle;
}

.dash-card table tbody tr:last-child td {
    border-bottom: 0 !important;
}

.dash-card table tbody tr:hover {
    background: #f8fbff !important;
}

.incident-id {
    color: var(--blue) !important;
    font-weight: 800;
    white-space: nowrap;
}

.incident-category {
    color: var(--navy-dark);
    font-weight: 750;
}

.incident-location {
    max-width: 170px;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.incident-date {
    color: #62768a;
    white-space: nowrap;
}

.status-label {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    padding: 4px 8px;

    border-radius: 20px;

    background: #edf3fa;
    color: #3978c5;

    font-size: 11px;
    font-weight: 750;

    white-space: nowrap;
}

.status-label.status-critical,
.status-label.status-urgent {
    background: var(--red-soft);
    color: var(--red);
}

.status-label.status-ongoing,
.status-label.status-pending {
    background: var(--orange-soft);
    color: #b87925;
}

.status-label.status-resolved {
    background: var(--green-soft);
    color: var(--green);
}

.no-data-msg {
    padding: 24px 12px !important;

    color: #63778b !important;
    text-align: center;
}

/* =========================================================
   09. CHARTS
========================================================= */

.chart-container {
    position: relative;

    width: 100%;
    height: 210px;

    min-height: 175px;
}

.compact-chart-container {
    height: 185px;
    min-height: 170px;
}

.donut-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;

    margin-top: 11px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 7px;

    padding: 5px 8px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #f8fafc;

    color: #344e67 !important;
    font-size: 12px !important;
    font-weight: 600;
}

.dot {
    width: 11px;
    height: 11px;
    min-width: 11px;

    border-radius: 50%;
    border: 1px solid rgba(24, 54, 83, .14);
}

/* =========================================================
   10. COMMUNITY ACTIVITY FORM
========================================================= */

.form-scroll-wrapper {
    max-height: 390px;
    overflow-y: auto;

    padding-right: 3px;
}

.form-scroll-wrapper .form-label {
    display: block;

    margin: 10px 0 5px;

    color: #435d76 !important;

    font-family: "Segoe UI", Arial, Helvetica, sans-serif !important;
    font-size: 12px !important;
    font-weight: 750;

    letter-spacing: .2px;
}

.form-scroll-wrapper .form-control,
.form-scroll-wrapper .form-select {
    min-height: 38px;

    padding: 8px 10px;

    border: 1px solid #dce4ed !important;
    border-radius: 8px !important;

    background: #fff !important;
    color: #263f58 !important;

    font-size: 13px !important;

    box-shadow: none !important;
}

.form-scroll-wrapper textarea.form-control {
    min-height: 82px;
}

.form-scroll-wrapper .form-control:focus,
.form-scroll-wrapper .form-select:focus {
    border-color: #8bb8e9 !important;

    box-shadow: 0 0 0 3px rgba(57,120,197,.09) !important;
}

.form-scroll-wrapper .form-control::placeholder {
    color: #a0adbb !important;
}

.btn-send {
    width: 100%;

    margin-top: 12px;
    padding: 10px 12px;

    border: none;
    border-radius: 8px;

    background: var(--navy-dark) !important;
    color: white !important;

    font-size: 13px;
    font-weight: 750;

    transition: .2s;
}

.btn-send:hover {
    background: var(--blue) !important;
}

.activity-feedback {
    font-size: 12px;
}

/* =========================================================
   11. MAP MONITORING
========================================================= */

#monitoringMap {
    width: 100%;
    height: 270px;

    margin-top: 5px;

    border: 1px solid var(--border);
    border-radius: 10px;

    z-index: 1;
}

.map-note {
    margin-top: 10px;
    color: #61758a;
    font-size: 12px;
    line-height: 1.6;
}

/* =========================================================
   12. DISASTER ADVISORIES
========================================================= */

.advisory-card {
    grid-column: span 9;
    border-color: #bfd0e3 !important;
}

.advisory-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}

.advisory-header .card-heading {
    margin-bottom: 0;
    flex: 1 1 auto;
}

.advisory-card .card-title {
    font-size: 15px !important;
}

.advisory-card .card-description {
    font-size: 12px;
    line-height: 1.45;
}

.advisory-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #f8fafc;
    color: var(--blue);
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.advisory-intro {
    margin: 8px 0 12px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}

.advisory-agency-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.advisory-agency {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 14px;
    border: 1px solid #e1eaf4;
    border-left: 3px solid var(--blue);
    border-radius: 9px;
    background: #f5f9ff;
}

.advisory-agency.phivolcs {
    border-left-color: var(--orange);
    background: #fffbf5;
}

.advisory-agency-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    min-width: 38px;
    border-radius: 10px;
    background: #e7f0fc;
    color: var(--blue);
    font-size: 18px;
}

.advisory-agency.phivolcs .advisory-agency-icon {
    background: #fff0dc;
    color: #a96c25;
}

.advisory-agency-content {
    min-width: 0;
}

.advisory-agency-content h4 {
    margin: 0 0 5px;
    color: var(--navy-dark);
    font-size: 14px;
    font-weight: 800;
}

.advisory-agency-content p {
    margin: 0;
    color: #526a81;
    font-size: 12px;
    line-height: 1.55;
}

.advisory-agency-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 9px;
    padding: 7px 10px;
    border: 1px solid #d8e5f3;
    border-radius: 6px;
    background: #fff;
    color: var(--blue) !important;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.advisory-agency.phivolcs .advisory-agency-link {
    border-color: #f0dfc6;
    color: #a96c25 !important;
}

.advisory-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 14px;
    padding: 12px;
    border: 1px dashed #dce5ef;
    border-radius: 8px;
    background: #fbfcfe;
}

.advisory-notice > i {
    margin-top: 1px;
    color: var(--blue);
    font-size: 18px;
}

.advisory-notice strong {
    display: block;
    margin-bottom: 4px;
    color: #29445f;
    font-size: 13px;
}

.advisory-notice p {
    margin: 0;
    color: #586f85;
    font-size: 12px;
    line-height: 1.55;
}

.advisory-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
    padding-top: 10px;
    border-top: 1px solid #edf0f4;
    color: #5e7388;
    font-size: 11px;
}


/* =========================================================
   13. RESPONSIVE DESIGN
========================================================= */

@media (max-width: 1200px) {

    .span-3 {
        grid-column: span 6;
    }

    .span-4,
    .span-5,
    .span-6,
    .span-7,
    .span-8 {
        grid-column: span 12;
    }

    .master-grid > .overview-chart-half {
        grid-column: span 6;
    }

    .master-grid > .verification-summary-card {
        grid-column: span 3;
    }

    .master-grid > .advisory-card {
        grid-column: span 9;
    }
}

@media (max-width: 768px) {

    .sidebar {
        width: 70px !important;
        min-width: 70px !important;
    }

    .main-content {
        width: calc(100% - 70px) !important;
        margin-left: 70px !important;
    }

    .dashboard-wrapper {
        padding: 16px 13px 20px !important;
    }

    .span-3,
    .span-4,
    .span-5,
    .span-6,
    .span-7,
    .span-8,
    .span-12,
    .advisory-card {
        grid-column: span 12;
    }

    .master-grid > .overview-chart-half,
    .master-grid > .verification-summary-card,
    .master-grid > .advisory-card {
        grid-column: span 12;
    }

    .dashboard-title {
        font-size: 22px !important;
    }

    .advisory-agency-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {

    .main-content {
        padding: 4px !important;
    }

    .dashboard-wrapper {
        padding: 16px 11px 20px !important;
        border-radius: 10px !important;
    }

    .dashboard-topbar {
        align-items: flex-start;
    }

    .dashboard-header-right {
        gap: 8px;
    }

    .dashboard-date {
        font-size: 11px;
    }

    .stat-card {
        min-height: 78px;
    }

    .stat-number {
        font-size: 24px !important;
    }

    .dash-card {
        padding: 13px !important;
    }

    .chart-container {
        height: 190px;
    }

    .card-header-flex {
        align-items: flex-start;
    }

    #monitoringMap {
        height: 250px;
    }
}
</style>
</head>

<body>

<?php include 'sidebar.php'; ?>

<main class="main-content">

<div class="dashboard-wrapper">

    <!-- =====================================================
         DASHBOARD HEADER
    ====================================================== -->

    <header class="dashboard-topbar">

        <div>
            <h1 class="dashboard-title admin-page-title">Dashboard</h1>

            <p class="dashboard-subtitle">
                Monitor community reports, incidents, residents, and activities.
            </p>
        </div>

        <div class="dashboard-header-right">

            <span class="dashboard-date">
                <i class="bi bi-calendar3"></i>
                <?= h(date('F d, Y')) ?>
            </span>

            <a href="records.php"
               class="header-action"
               title="View incident records"
               aria-label="View incident records">

                <i class="bi bi-clipboard2-check"></i>

            </a>

        </div>

    </header>

    <!-- DATABASE ERROR -->

    <?php if ($error_message !== ''): ?>

        <div class="dashboard-alert" role="alert">

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= h($error_message) ?>

        </div>

    <?php endif; ?>


    <div class="master-grid">

        <!-- =================================================
             SUMMARY CARDS
        ================================================== -->

        <!-- TOTAL REPORTS -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon">
                    <i class="bi bi-clipboard-data"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Total Incident Reports
                    </span>

                    <p class="stat-number">
                        <?= number_format($totalReports) ?>
                    </p>

                </div>

            </div>

        </section>


        <!-- ACTIVE INCIDENTS -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon red">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Active Incidents
                    </span>

                    <p class="stat-number">
                        <?= number_format($activeIncidents) ?>
                    </p>

                </div>

            </div>

        </section>


        <!-- REGISTERED RESIDENTS -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon green">
                    <i class="bi bi-people-fill"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Registered Residents
                    </span>

                    <p class="stat-number">
                        <?= number_format($totalUsers) ?>
                    </p>

                </div>

            </div>

        </section>


        <!-- PUBLISHED ACTIVITIES -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon purple">
                    <i class="bi bi-calendar-check"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Published Activities
                    </span>

                    <p class="stat-number">
                        <?= number_format($totalActivities) ?>
                    </p>

                </div>

            </div>

        </section>

        <!-- PUBLISHED ALERTS -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon orange">
                    <i class="bi bi-megaphone-fill"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Published Alerts
                    </span>

                    <p class="stat-number">
                        <?= number_format($publishedAlerts) ?>
                    </p>

                </div>

            </div>

        </section>


        <!-- PENDING VERIFICATIONS -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon purple">
                    <i class="bi bi-person-check-fill"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Pending Verifications
                    </span>

                    <p class="stat-number">
                        <?= number_format($pendingVerifications) ?>
                    </p>

                </div>

            </div>

        </section>


        <!-- OPEN ASSISTANCE REQUESTS -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon red">
                    <i class="bi bi-life-preserver"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Open Assistance Requests
                    </span>

                    <p class="stat-number">
                        <?= number_format($openRequests) ?>
                    </p>

                </div>

            </div>

        </section>


        <!-- EMERGENCY HOTLINES -->

        <section class="dash-card stat-card span-3">

            <div class="stat-inner">

                <div class="stat-icon green">
                    <i class="bi bi-telephone-fill"></i>
                </div>

                <div>

                    <span class="stat-label">
                        Emergency Hotlines
                    </span>

                    <p class="stat-number">
                        <?= number_format($emergencyHotlines) ?>
                    </p>

                </div>

            </div>

        </section>


        <!-- =================================================
             RECENT INCIDENT REPORTS
        ================================================== -->

        <section class="dash-card span-7">

            <div class="card-header-flex">

                <div class="card-heading mb-0">

                    <div class="card-heading-icon">
                        <i class="bi bi-list-ul"></i>
                    </div>

                    <div>

                        <h3 class="card-title">
                            Recent Incident Reports
                        </h3>

                        <p class="card-description">
                            Latest reports submitted by residents.
                        </p>

                    </div>

                </div>

                <a href="records.php" class="view-all">
                    View all
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>


            <div class="inner-box">

                <div class="table-responsive">

                    <table>

                        <thead>

                            <tr>
                                <th>Report ID</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($reports)): ?>

                            <tr>

                                <td colspan="5" class="no-data-msg">

                                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>

                                    No incident reports found.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($reports as $report): ?>

                                <?php

                                $status = strtolower(
                                    trim((string) ($report['status'] ?? 'reported'))
                                );

                                $statusClass = preg_replace(
                                    '/[^a-z0-9_-]/',
                                    '',
                                    $status
                                );

                                $dateValue = $report['created_at'] ?? '';

                                $formattedDate = 'N/A';

                                if ($dateValue !== '') {
                                    $timestamp = strtotime($dateValue);

                                    if ($timestamp !== false) {
                                        $formattedDate = date(
                                            'M d, Y',
                                            $timestamp
                                        );
                                    }
                                }

                                ?>

                                <tr>

                                    <td class="incident-id">
                                        #<?= h($report['incident_id']) ?>
                                    </td>

                                    <td class="incident-category">
                                        <?= h($report['category'] ?: 'Uncategorized') ?>
                                    </td>

                                    <td class="incident-location"
                                        title="<?= h($report['location'] ?? '') ?>">

                                        <?= h($report['location'] ?: 'Not specified') ?>

                                    </td>

                                    <td>

                                        <span class="status-label status-<?= h($statusClass) ?>">

                                            <?= h(ucwords(str_replace('_', ' ', $status))) ?>

                                        </span>

                                    </td>

                                    <td class="incident-date">
                                        <?= h($formattedDate) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>


        <!-- =================================================
             INCIDENT CATEGORY CHART
        ================================================== -->

        <section class="dash-card span-5">

            <div class="card-heading">

                <div class="card-heading-icon">
                    <i class="bi bi-pie-chart"></i>
                </div>

                <div>

                    <h3 class="card-title">
                        Incidents by Category
                    </h3>

                    <p class="card-description">
                        Distribution of submitted incident reports.
                    </p>

                </div>

            </div>

            <div class="chart-container">
                <canvas id="donutChart"></canvas>
            </div>

            <div
                class="donut-legend"
                id="donutLegend"
                role="list"
                aria-label="Incident categories and chart colors"
            ></div>

        </section>


        <!-- =================================================
             CREATE COMMUNITY ACTIVITY
        ================================================== -->

        <section class="dash-card span-4">

            <div class="card-heading">

                <div class="card-heading-icon">
                    <i class="bi bi-calendar-plus"></i>
                </div>

                <div>

                    <h3 class="card-title">
                        Create Community Activity
                    </h3>

                    <p class="card-description">
                        Publish an announcement or upcoming activity.
                    </p>

                </div>

            </div>


            <div class="form-scroll-wrapper">

                <?php if ($activity_success !== ''): ?>

                    <div class="alert alert-success py-2 activity-feedback">

                        <i class="bi bi-check-circle me-1"></i>

                        <?= h($activity_success) ?>

                    </div>

                <?php endif; ?>


                <?php if ($activity_error !== ''): ?>

                    <div class="alert alert-danger py-2 activity-feedback">

                        <i class="bi bi-exclamation-circle me-1"></i>

                        <?= h($activity_error) ?>

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
                        value="<?= h($_POST['activity_title'] ?? '') ?>"
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
                        min="<?= h(date('Y-m-d')) ?>"
                        value="<?= h($_POST['activity_date'] ?? '') ?>"
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
                        style="resize:none;"
                        required
                    ><?= h($_POST['activity_message'] ?? '') ?></textarea>


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

                        <?php

                        $areas = [
                            'All Areas',
                            'Talisay',
                            'Agoncillo',
                            'Laurel',
                            'San Nicolas',
                            'Santa Teresita',
                            'Lemery'
                        ];

                        $selectedArea = $_POST['target_area'] ?? '';

                        foreach ($areas as $area):

                        ?>

                            <option
                                value="<?= h($area) ?>"
                                <?= $selectedArea === $area ? 'selected' : '' ?>
                            >

                                <?= h($area) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <button
                        type="submit"
                        name="create_activity"
                        value="1"
                        class="btn-send"
                    >

                        <i class="bi bi-megaphone me-1"></i>

                        PUBLISH ACTIVITY

                    </button>

                </form>

            </div>

        </section>


        <!-- =================================================
             MAP MONITORING
        ================================================== -->

        <section class="dash-card span-8">

            <div class="card-header-flex">

                <div class="card-heading mb-0">

                    <div class="card-heading-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>

                    <div>

                        <h3 class="card-title">
                            Map Monitoring
                        </h3>

                        <p class="card-description">
                            Location overview of the Talisay area.
                        </p>

                    </div>

                </div>

                <a href="map-monitoring.php" class="view-all">
                    View full map
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>


            <div id="monitoringMap"></div>


            <div class="donut-legend">

                <div class="legend-item">
                    <span class="dot" style="background:#d9534f;"></span>
                    Critical
                </div>

                <div class="legend-item">
                    <span class="dot" style="background:#e6a23c;"></span>
                    On-Going
                </div>

                <div class="legend-item">
                    <span class="dot" style="background:#4b91d1;"></span>
                    Reported
                </div>

                <div class="legend-item">
                    <span class="dot" style="background:#36a477;"></span>
                    Resolved
                </div>

            </div>


            <p class="map-note">

                <i class="bi bi-info-circle me-1"></i>

                Map markers are demonstration locations, not live incident coordinates.

            </p>

        </section>


        <!-- =================================================
             REPORTS OVER TIME
        ================================================== -->

        <section class="dash-card span-12">

            <div class="card-heading">

                <div class="card-heading-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>

                <div>

                    <h3 class="card-title">
                        Reports Over Time
                    </h3>

                    <p class="card-description">
                        Daily incident reports over the last seven days.
                    </p>

                </div>

            </div>


            <div class="chart-container">
                <canvas id="lineChart"></canvas>
            </div>

        </section>

        <section class="dash-card span-6 overview-chart-half">

            <div class="card-heading">
                <div class="card-heading-icon">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
                <div>
                    <h3 class="card-title">Incidents by Status</h3>
                    <p class="card-description">Open and resolved reports.</p>
                </div>
            </div>

            <div class="chart-container compact-chart-container">
                <canvas id="incidentStatusChart"></canvas>
            </div>

            <div
                class="donut-legend"
                id="incidentStatusLegend"
                role="list"
                aria-label="Incident status chart legend"
            ></div>

        </section>

        <section class="dash-card span-6 overview-chart-half">

            <div class="card-heading">
                <div class="card-heading-icon">
                    <i class="bi bi-life-preserver"></i>
                </div>
                <div>
                    <h3 class="card-title">Assistance Requests</h3>
                    <p class="card-description">Requests grouped by status.</p>
                </div>
            </div>

            <div class="chart-container compact-chart-container">
                <canvas id="assistanceStatusChart"></canvas>
            </div>

        </section>

        <section class="dash-card span-3 verification-summary-card">

            <div class="card-heading">
                <div class="card-heading-icon">
                    <i class="bi bi-person-check"></i>
                </div>
                <div>
                    <h3 class="card-title">Resident Verification</h3>
                    <p class="card-description">Account review status.</p>
                </div>
            </div>

            <div class="chart-container compact-chart-container">
                <canvas id="verificationStatusChart"></canvas>
            </div>

            <div
                class="donut-legend"
                id="verificationStatusLegend"
                role="list"
                aria-label="Resident verification chart legend"
            ></div>

        </section>


        <!-- =================================================
             DISASTER ADVISORIES
        ================================================== -->

        <section class="dash-card advisory-card span-9">

            <div class="advisory-header">
                <div class="card-heading">
                    <div class="card-heading-icon">
                        <i class="bi bi-shield-exclamation"></i>
                    </div>
                    <div>
                        <h3 class="card-title">Disaster Advisories</h3>
                        <p class="card-description">
                            Official sources for weather, earthquake, and volcanic activity updates.
                        </p>
                    </div>
                </div>

                <span class="advisory-badge">
                    <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                    Government Sources
                </span>
            </div>

            <p class="advisory-intro">
                Check the official agencies below for current bulletins and public safety announcements.
            </p>

            <div class="advisory-agency-grid">

                <article class="advisory-agency pagasa">
                    <div class="advisory-agency-icon">
                        <i class="bi bi-cloud-lightning-rain" aria-hidden="true"></i>
                    </div>
                    <div class="advisory-agency-content">
                        <h4>PAGASA</h4>
                        <p>
                            Philippine Atmospheric, Geophysical and Astronomical Services Administration.
                            Check weather forecasts, tropical cyclone bulletins, rainfall warnings,
                            and other weather advisories.
                        </p>
                        <a
                            href="https://www.pagasa.dost.gov.ph/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="advisory-agency-link"
                        >
                            Visit PAGASA
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>

                <article class="advisory-agency phivolcs">
                    <div class="advisory-agency-icon">
                        <i class="bi bi-activity" aria-hidden="true"></i>
                    </div>
                    <div class="advisory-agency-content">
                        <h4>PHIVOLCS</h4>
                        <p>
                            Philippine Institute of Volcanology and Seismology.
                            Check earthquake information, volcano bulletins, volcanic activity
                            updates, and tsunami advisories.
                        </p>
                        <a
                            href="https://www.phivolcs.dost.gov.ph/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="advisory-agency-link"
                        >
                            Visit PHIVOLCS
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>

            </div>

            <div class="advisory-notice">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <div>
                    <strong>Live advisory feed is not connected yet</strong>
                    <p>
                        TaliResQ does not currently retrieve live advisory information.
                        Use the official agency websites to verify the latest announcements
                        before sharing information with residents.
                    </p>
                </div>
            </div>

            <div class="advisory-footer">
                <span>
                    <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                    Verify emergency information through official government announcements.
                </span>
                <span>
                    <i class="bi bi-clock me-1" aria-hidden="true"></i>
                    Official source links.
                </span>
            </div>

        </section>

    </div>

</div>

</main>


<!-- =========================================================
     JAVASCRIPT LIBRARIES
========================================================== -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>

/* =========================================================
   01. PHP DATA
========================================================= */

const incidentData = <?= $incidentTypesJson ?>;

const reportsOverTime = <?= $reportsOverTimeJson ?>;

const incidentStatusData = <?= $incidentStatusCountsJson ?>;

const assistanceStatusData = <?= $assistanceStatusCountsJson ?>;

const verificationStatusData = <?= $verificationStatusCountsJson ?>;

const chartColors = <?= $chartColorsJson ?>;

const incidentCategoryColors = {
    fire: '#c94b45',
    flood: '#3978c5',
    landslide: '#d98735',
    earthquake: '#8b75c9',
    other: '#258253',
    uncategorized: '#71849a'
};

const standardIncidentCategories = [
    'Flood',
    'Fire',
    'Landslide',
    'Earthquake',
    'Other'
];

function getIncidentCategoryColor(category, index) {

    const key = String(category || 'Uncategorized').trim().toLowerCase();

    return incidentCategoryColors[key]
        || chartColors[index % chartColors.length];

}


/* =========================================================
   02. CHART DEFAULTS
========================================================= */

if (typeof Chart !== 'undefined') {

    Chart.defaults.color = '#586f85';

    Chart.defaults.font.family =
        '"Segoe UI", Arial, Helvetica, sans-serif';

    Chart.defaults.font.size = 12;

}


/* =========================================================
   03. INCIDENT CATEGORY DONUT CHART
========================================================= */

const donutCanvas = document.getElementById('donutChart');

const donutLegend = document.getElementById('donutLegend');

const chartIncidentData = standardIncidentCategories.map(category => {

    const categoryData = incidentData.find(item =>
        String(item.category || 'Uncategorized').trim().toLowerCase()
            === category.toLowerCase()
    );

    return {
        category,
        total: categoryData ? Number(categoryData.total) || 0 : 0
    };

});

incidentData.forEach(item => {

    const category = item.category || 'Uncategorized';
    const isStandardCategory = standardIncidentCategories.some(
        standardCategory => standardCategory.toLowerCase()
            === String(category).trim().toLowerCase()
    );

    if (!isStandardCategory) {
        chartIncidentData.push({
            category,
            total: Number(item.total) || 0
        });
    }

});

if (donutLegend) {

    donutLegend.innerHTML = chartIncidentData.map((item, index) => {

        const category = item.category || 'Uncategorized';

        const color = getIncidentCategoryColor(category, index);

        return `
            <div class="legend-item" role="listitem">

                <span class="dot"
                      aria-hidden="true"
                      style="background:${color};">
                </span>

                ${escapeHtml(category)}

                (${Number(item.total) || 0})

            </div>
        `;

    }).join('');

}


if (donutCanvas && typeof Chart !== 'undefined') {

    const labels = chartIncidentData.map(
        item => item.category || 'Uncategorized'
    );

    const values = chartIncidentData.map(
        item => Number(item.total) || 0
    );

    const total = values.reduce(
        (sum, value) => sum + value,
        0
    );

    new Chart(donutCanvas.getContext('2d'), {

        type: 'doughnut',

        data: {

            labels: labels,

            datasets: [{

                data: values,

                backgroundColor: labels.map(
                    (category, index) => getIncidentCategoryColor(category, index)
                ),

                borderWidth: 3,
                borderColor: '#ffffff',

                hoverOffset: 6

            }]

        },

        options: {

            responsive: true,
            maintainAspectRatio: false,

            cutout: '73%',

            plugins: {

                legend: {
                    display: false
                },

                tooltip: {
                    backgroundColor: '#102d4d',
                    padding: 11
                }

            }

        },

        plugins: [{

            id: 'centerText',

            beforeDraw(chart) {

                const ctx = chart.ctx;

                ctx.save();

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                ctx.fillStyle = '#102d4d';

                ctx.font = 'bold 25px Arial';

                ctx.fillText(
                    total.toLocaleString(),
                    chart.width / 2,
                    chart.height / 2 - 5
                );

                ctx.fillStyle = '#8293a7';

                ctx.font = '10px Arial';

                ctx.fillText(
                    'TOTAL REPORTS',
                    chart.width / 2,
                    chart.height / 2 + 17
                );

                ctx.restore();

            }

        }]

    });

}


/* =========================================================
   04. REPORTS OVER TIME LINE CHART
========================================================= */

const lineCanvas = document.getElementById('lineChart');

if (lineCanvas && typeof Chart !== 'undefined') {

    const lineLabels = reportsOverTime.map(item => {

        const date = new Date(item.report_date + 'T00:00:00');

        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });

    });

    const lineValues = reportsOverTime.map(
        item => Number(item.total) || 0
    );

    new Chart(lineCanvas.getContext('2d'), {

        type: 'line',

        data: {

            labels: lineLabels,

            datasets: [{

                label: 'Incident Reports',

                data: lineValues,

                borderColor: '#3978c5',

                backgroundColor: 'rgba(57,120,197,.10)',

                fill: true,

                borderWidth: 2.5,

                tension: .35,

                pointRadius: 4,

                pointHoverRadius: 6,

                pointBackgroundColor: '#3978c5',

                pointBorderColor: '#ffffff',

                pointBorderWidth: 2

            }]

        },

        options: {

            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                intersect: false,
                mode: 'index'
            },

            plugins: {

                legend: {
                    display: false
                },

                tooltip: {
                    backgroundColor: '#102d4d',
                    padding: 11
                }

            },

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0,
                        stepSize: 1
                    },

                    grid: {
                        color: '#edf1f5'
                    }

                },

                x: {

                    ticks: {
                        color: '#8293a7'
                    },

                    grid: {
                        display: false
                    }

                }

            }

        }

    });

}

/* =========================================================
   05. SYSTEM STATUS CHARTS
========================================================= */

function getStatusTotal(rows, key) {

    const match = rows.find(item =>
        String(item.status || '').trim().toLowerCase() === key
    );

    return match ? Number(match.total) || 0 : 0;

}

function buildStatusRows(rows, definitions) {

    const knownKeys = new Set(definitions.map(item => item.key));
    const result = definitions.map(item => ({
        ...item,
        total: getStatusTotal(rows, item.key)
    }));

    rows.forEach(item => {

        const key = String(item.status || 'unknown').trim().toLowerCase();

        if (!knownKeys.has(key)) {
            result.push({
                key,
                label: key
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, character => character.toUpperCase()),
                total: Number(item.total) || 0
            });
        }

    });

    return result;

}

function renderStatusLegend(elementId, rows, colors) {

    const legend = document.getElementById(elementId);

    if (!legend) {
        return;
    }

    legend.innerHTML = rows.map((row, index) => {

        const color = colors[row.key]
            || chartColors[index % chartColors.length];

        return `
            <div class="legend-item" role="listitem">
                <span class="dot" aria-hidden="true"
                      style="background:${color};"></span>
                ${escapeHtml(row.label)} (${row.total})
            </div>
        `;

    }).join('');

}

function renderStatusDonut(canvasId, rows, colors) {

    const canvas = document.getElementById(canvasId);

    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: rows.map(row => row.label),
            datasets: [{
                data: rows.map(row => row.total),
                backgroundColor: rows.map((row, index) =>
                    colors[row.key]
                        || chartColors[index % chartColors.length]
                ),
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#102d4d',
                    padding: 10
                }
            }
        }
    });

}

const incidentStatusRows = buildStatusRows(incidentStatusData, [
    { key: 'open', label: 'Open' },
    { key: 'resolved', label: 'Resolved' }
]);

const incidentStatusColors = {
    open: '#c94b45',
    resolved: '#258253'
};

renderStatusLegend(
    'incidentStatusLegend',
    incidentStatusRows,
    incidentStatusColors
);
renderStatusDonut(
    'incidentStatusChart',
    incidentStatusRows,
    incidentStatusColors
);

const assistanceStatusRows = buildStatusRows(assistanceStatusData, [
    { key: 'pending', label: 'Pending' },
    { key: 'under_review', label: 'Under Review' },
    { key: 'verified', label: 'Verified' },
    { key: 'assistance_coordinated', label: 'Assistance Coordinated' },
    { key: 'completed', label: 'Completed' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'needs_more_information', label: 'Needs More Information' }
]);

const assistanceStatusColors = {
    pending: '#d98735',
    under_review: '#3978c5',
    verified: '#8b75c9',
    assistance_coordinated: '#36a6a6',
    completed: '#258253',
    rejected: '#c94b45',
    needs_more_information: '#71849a'
};

const assistanceCanvas = document.getElementById('assistanceStatusChart');

if (assistanceCanvas && typeof Chart !== 'undefined') {

    new Chart(assistanceCanvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: assistanceStatusRows.map(row => row.label),
            datasets: [{
                label: 'Requests',
                data: assistanceStatusRows.map(row => row.total),
                backgroundColor: assistanceStatusRows.map((row, index) =>
                    assistanceStatusColors[row.key]
                        || chartColors[index % chartColors.length]
                ),
                borderRadius: 4,
                maxBarThickness: 16
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#102d4d',
                    padding: 10
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#586f85'
                    },
                    grid: {
                        color: '#edf1f5'
                    }
                },
                y: {
                    ticks: {
                        color: '#344e67',
                        font: { size: 11 }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

}

const verificationStatusRows = buildStatusRows(
    verificationStatusData,
    [
        { key: 'pending', label: 'Pending' },
        { key: 'verified', label: 'Verified' },
        { key: 'rejected', label: 'Rejected' }
    ]
);

const verificationStatusColors = {
    pending: '#d98735',
    verified: '#258253',
    rejected: '#c94b45'
};

renderStatusLegend(
    'verificationStatusLegend',
    verificationStatusRows,
    verificationStatusColors
);
renderStatusDonut(
    'verificationStatusChart',
    verificationStatusRows,
    verificationStatusColors
);


/* =========================================================
   06. SAFE HTML ESCAPING
========================================================= */

function escapeHtml(value) {

    return String(value).replace(/[&<>"']/g, function(character) {

        const entities = {

            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'

        };

        return entities[character];

    });

}


/* =========================================================
   06. LEAFLET MAP MONITORING
========================================================= */

const mapElement = document.getElementById('monitoringMap');

if (mapElement && typeof L !== 'undefined') {

    const monitoringMap = L.map('monitoringMap').setView(
        [14.0940, 121.0197],
        13
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }
    ).addTo(monitoringMap);


    const markerColors = {

        critical: '#d9534f',

        ongoing: '#e6a23c',

        reported: '#4b91d1',

        resolved: '#36a477'

    };


    function createMarker(lat, lng, color, popupText) {

        L.circleMarker([lat, lng], {

            radius: 8,

            fillColor: color,

            color: '#ffffff',

            weight: 2,

            fillOpacity: 1

        })

        .addTo(monitoringMap)

        .bindPopup(popupText);

    }


    /*
     * DEMONSTRATION MARKERS
     * These are not connected to actual incident coordinates.
     */

    createMarker(
        14.0940,
        121.0197,
        markerColors.critical,
        '<b>Talisay Center</b><br>Sample Critical Incident'
    );

    createMarker(
        14.0850,
        121.0300,
        markerColors.ongoing,
        '<b>Aya, Talisay</b><br>Sample Ongoing Incident'
    );

    createMarker(
        14.1000,
        121.0000,
        markerColors.reported,
        '<b>Banga, Talisay</b><br>Sample Reported Incident'
    );

    createMarker(
        14.0900,
        120.9800,
        markerColors.resolved,
        '<b>Sampaloc</b><br>Sample Resolved Incident'
    );


    setTimeout(function() {

        monitoringMap.invalidateSize();

    }, 300);

}

</script>

</body>
</html>
