
<?php

$host = 'aws-1-ap-northeast-1.pooler.supabase.com';
$port = '6543';
$dbname = 'postgres';
$user = 'postgres.okwnknzodfvbsbgetvzh';
$password = 'taliresq67_';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

$success = '';
$error = '';

$title = '';
$type = '';
$message = '';
$target_barangay = '';
$alert_date = '';

$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterBarangay = trim($_GET['barangay'] ?? '');

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Unable to connect to the database. Please check your configuration.');
}


/*
|--------------------------------------------------------------------------
| ALERT TYPES
|--------------------------------------------------------------------------
| Keep these values synchronized with your existing database constraint.
| Retain your existing $alertTypes, $typeClasses and $typeIcons arrays
| if they already contain additional supported categories.
|--------------------------------------------------------------------------
*/

$alertTypes = [
    'general_advisory' => 'General Advisory',
    'weather_warning' => 'Weather Warning',
    'evacuation_order' => 'Evacuation Order',
    'volcanic_warning' => 'Volcanic Warning',
    'flood_warning' => 'Flood Warning',
    'earthquake_warning' => 'Earthquake Warning',
    'typhoon_warning' => 'Typhoon Warning'
];

$typeClasses = [
    'general_advisory' => 'type-general',
    'weather_warning' => 'type-weather',
    'evacuation_order' => 'type-evacuation',
    'volcanic_warning' => 'type-volcanic',
    'flood_warning' => 'type-flood',
    'earthquake_warning' => 'type-earthquake',
    'typhoon_warning' => 'type-typhoon'
];

$typeIcons = [
    'general_advisory' => 'bi-info-circle',
    'weather_warning' => 'bi-cloud-rain',
    'evacuation_order' => 'bi-house-exclamation',
    'volcanic_warning' => 'bi-exclamation-triangle',
    'flood_warning' => 'bi-water',
    'earthquake_warning' => 'bi-activity',
    'typhoon_warning' => 'bi-wind'
];


/*
|--------------------------------------------------------------------------
| LOAD BARANGAYS
|--------------------------------------------------------------------------
*/

$barangays = [];

try {
    $stmt = $pdo->query("
        SELECT DISTINCT barangay
        FROM public.users
        WHERE barangay IS NOT NULL
          AND TRIM(barangay) <> ''
        ORDER BY barangay ASC
    ");

    $barangays = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log('Barangay loading failed: ' . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| CREATE ALERT + COMMUNITY ACTIVITY + RESIDENT NOTIFICATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_alert'])
) {
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target_barangay = trim($_POST['target_barangay'] ?? '');
    $alert_date = trim($_POST['alert_date'] ?? '');

    if (
        $title === '' ||
        $type === '' ||
        $message === '' ||
        $alert_date === ''
    ) {
        $error = 'Please complete all required alert fields.';

    } elseif (!array_key_exists($type, $alertTypes)) {
        $error = 'Please select a valid alert category.';

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $alert_date) ||
        !checkdate(
            (int)substr($alert_date, 5, 2),
            (int)substr($alert_date, 8, 2),
            (int)substr($alert_date, 0, 4)
        )
    ) {
        $error = 'Please enter a valid alert date.';

    } elseif (strlen($title) > 150 || strlen($message) > 5000) {
        $error = 'The alert title or message exceeds the allowed length.';

    } else {

        try {
            $pdo->beginTransaction();

            /*
             * STEP 1:
             * Save the alert in the existing alerts table.
             */

            $alertStmt = $pdo->prepare("
                INSERT INTO public.alerts (
                    title,
                    type,
                    message,
                    target_barangay,
                    alert_date,
                    sent_by,
                    created_at,
                    updated_at,
                    is_published
                )
                VALUES (
                    :title,
                    :type,
                    :message,
                    :target_barangay,
                    :alert_date,
                    NULL,
                    NOW(),
                    NOW(),
                    TRUE
                )
                RETURNING id
            ");

            $alertStmt->execute([
                ':title' => $title,
                ':type' => $type,
                ':message' => $message,
                ':target_barangay' => $target_barangay,
                ':alert_date' => $alert_date
            ]);

            $alertId = $alertStmt->fetchColumn();


            /*
             * STEP 2:
             * Create a corresponding community activity.
             *
             * This follows the same structure as your working
             * Dashboard Create Activity functionality.
             */

            $activityStmt = $pdo->prepare("
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

            $activityStmt->execute([
                ':title' => $title,
                ':description' => $message,
                ':activity_date' => $alert_date,
                ':target_area' => $target_barangay !== ''
                    ? $target_barangay
                    : 'All Barangays'
            ]);

            $activityId = $activityStmt->fetchColumn();


            /*
             * STEP 3:
             * Generate the resident notification.
             *
             * The activity_id links this notification to the
             * newly created community activity.
             */

            $notificationStmt = $pdo->prepare("
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

            $notificationStmt->execute([
                ':activity_id' => $activityId,
                ':title' => 'New Emergency Alert',
                ':message' => $title . ' — ' .
                    ($target_barangay !== ''
                        ? $target_barangay
                        : 'All Barangays') .
                    "\n\n" . $message
            ]);


            /*
             * STEP 4:
             * Commit everything together.
             */

            $pdo->commit();

            $success = 'Alert published successfully! A corresponding community activity and resident notification have also been created.';

            // Clear form values after successful submission.
            $title = '';
            $type = '';
            $message = '';
            $target_barangay = '';
            $alert_date = '';

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Alert publishing failed: ' . $e->getMessage()
            );

            $error = 'Unable to publish alert and notification. Please check your database configuration and server error log.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| ALERT STATISTICS
|--------------------------------------------------------------------------
*/

$totalAlerts = 0;
$publishedAlerts = 0;
$draftAlerts = 0;
$todayAlerts = 0;

try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE is_published = TRUE) AS published,
            COUNT(*) FILTER (WHERE is_published = FALSE) AS drafts,
            COUNT(*) FILTER (
                WHERE created_at::date = CURRENT_DATE
            ) AS today
        FROM public.alerts
    ");

    $stats = $stmt->fetch();

    $totalAlerts = (int)$stats['total'];
    $publishedAlerts = (int)$stats['published'];
    $draftAlerts = (int)$stats['drafts'];
    $todayAlerts = (int)$stats['today'];

} catch (PDOException $e) {
    error_log('Alert statistics failed: ' . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| ALERT HISTORY + SEARCH + FILTERS
|--------------------------------------------------------------------------
*/

$filteredAlerts = [];

try {
    $sql = "
        SELECT
            id,
            title,
            type,
            message,
            target_barangay,
            alert_date,
            created_at,
            updated_at,
            is_published
        FROM public.alerts
        WHERE 1 = 1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            AND (
                title ILIKE :search_title
                OR message ILIKE :search_message
            )
        ";

        $params[':search_title'] = '%' . $search . '%';
        $params[':search_message'] = '%' . $search . '%';
    }

    if ($filterType !== '') {
        $sql .= " AND type = :filter_type";
        $params[':filter_type'] = $filterType;
    }

    if ($filterBarangay !== '') {
        $sql .= " AND target_barangay = :filter_barangay";
        $params[':filter_barangay'] = $filterBarangay;
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $filteredAlerts = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Alert history loading failed: ' . $e->getMessage());
    $error = $error ?: 'Unable to load alert history.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alert Management | TaliResQ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">

    <style>
        :root {
            --navy: #052241;
            --blue: #0d4f8b;
            --muted: #8492a3;
            --border: #edf0f4;
        }

        .alerts-header {
            display:flex; justify-content:space-between; align-items:center;
            gap:20px; margin-bottom:25px;
        }
        .dashboard-title {
            color:var(--navy); font-size:25px; font-weight:700;
            letter-spacing:.5px; margin:0 0 6px;
        }
        .page-description { color:var(--muted); font-size:13px; margin:0; }
        .header-icon {
            width:53px; height:53px; display:flex; align-items:center;
            justify-content:center; background:var(--navy); color:#fff;
            border-radius:13px; font-size:23px; flex-shrink:0;
        }
        .stats-grid {
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px; margin-bottom:23px;
        }
        .stat-card {
            background:#fff; border:1px solid var(--border); border-radius:12px;
            padding:19px; display:flex; align-items:center; gap:14px;
            box-shadow:0 3px 12px rgba(5,34,65,.035);
            transition:transform .2s ease,box-shadow .2s ease;
        }
        .stat-card:hover { transform:translateY(-2px); box-shadow:0 5px 16px rgba(5,34,65,.07); }
        .stat-icon {
            width:43px; height:43px; display:flex; align-items:center;
            justify-content:center; border-radius:10px; font-size:19px; flex-shrink:0;
        }
        .stat-icon.navy { background:#eaf0f7; color:var(--navy); }
        .stat-icon.blue { background:#e8f2ff; color:#2363a1; }
        .stat-icon.green { background:#e9f5ed; color:#287747; }
        .stat-icon.orange { background:#fff2e7; color:#b96a28; }
        .stat-label { color:var(--muted); font-size:11px; font-weight:600; margin-bottom:4px; }
        .stat-value { color:var(--navy); font-size:23px; font-weight:700; line-height:1.2; }
        .dash-card {
            background:#fff; border:1px solid var(--border); border-radius:12px;
            padding:24px; margin-bottom:22px; box-shadow:0 3px 12px rgba(5,34,65,.035);
        }
        .section-heading { display:flex; align-items:center; gap:12px; margin-bottom:5px; }
        .section-icon {
            width:39px; height:39px; display:flex; align-items:center;
            justify-content:center; background:#eaf1f8; color:var(--navy);
            border-radius:9px; font-size:18px; flex-shrink:0;
        }
        .card-title { color:var(--navy); font-size:17px; font-weight:700; margin:0; }
        .card-description { color:#8793a1; font-size:12px; margin:0 0 23px 51px; }
        .alert-form { display:flex; flex-direction:column; gap:19px; }
        .form-group { width:100%; }
        .alert-form label { display:block; color:#263b50; font-size:13px; font-weight:600; margin-bottom:8px; }
        .required { color:#dc3545; }
        .alert-form .form-control,.alert-form .form-select {
            width:100%; min-height:48px; padding:11px 14px; background:#fff;
            color:#263b50; border:1px solid #dce2e9; border-radius:8px;
            font-size:13px; box-shadow:none; transition:all .2s ease;
        }
        /* Keep entered text readable in every form field, including focused/autofilled fields. */
        .alert-form .form-control,
        .alert-form .form-select,
        .alert-form textarea,
        .filter-area .form-control,
        .filter-area .form-select,
        input.form-control,
        select.form-select,
        textarea.form-control {
            color:#212529 !important;
            -webkit-text-fill-color:#212529 !important;
            background-color:#ffffff !important;
            caret-color:#052241 !important;
            opacity:1 !important;
            color-scheme:light;
        }
        .alert-form .form-control::placeholder,
        .filter-area .form-control::placeholder,
        input.form-control::placeholder,
        textarea.form-control::placeholder {
            color:#6c757d !important;
            -webkit-text-fill-color:#6c757d !important;
            opacity:1 !important;
        }
        .alert-form .form-control:focus,
        .alert-form .form-select:focus,
        .filter-area .form-control:focus,
        .filter-area .form-select:focus,
        input.form-control:focus,
        select.form-select:focus,
        textarea.form-control:focus {
            color:#212529 !important;
            -webkit-text-fill-color:#212529 !important;
            background-color:#ffffff !important;
            border-color:var(--blue);
            outline:0;
        }
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        textarea:-webkit-autofill,
        select:-webkit-autofill {
            -webkit-text-fill-color:#212529 !important;
            box-shadow:0 0 0 1000px #ffffff inset !important;
            caret-color:#052241 !important;
        }
        .alert-form .form-control::placeholder { color:#6c757d !important; }
        .alert-form .form-control:hover,.alert-form .form-select:hover { border-color:#8da8c2; }
        .alert-form .form-control:focus,.alert-form .form-select:focus {
            border-color:var(--blue); box-shadow:0 0 0 3px rgba(13,79,139,.10);
        }
        .alert-form textarea.form-control { min-height:125px; resize:vertical; line-height:1.6; }
        .select-wrapper { position:relative; width:100%; }
        .select-wrapper .form-select { appearance:none; padding-right:43px; cursor:pointer; }
        .select-wrapper:after {
            content:"\F282"; font-family:"bootstrap-icons"; position:absolute;
            right:15px; top:50%; transform:translateY(-50%);
            color:var(--navy); font-size:14px; pointer-events:none;
        }
        .form-note { color:#8793a1; font-size:11px; margin-top:7px; line-height:1.5; }
        .form-note i { color:var(--blue); margin-right:4px; }
        .message-footer { display:flex; justify-content:space-between; gap:10px; margin-top:7px; }
        .char-counter { color:#8793a1; font-size:11px; white-space:nowrap; }
        .char-counter.near-limit { color:#c17a24; }
        .char-counter.at-limit { color:#dc3545; font-weight:600; }
        .create-alert-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
            background:var(--navy); color:#fff; border:0; border-radius:8px;
            padding:12px 22px; font-size:13px; font-weight:600; transition:all .2s ease;
        }
        .create-alert-btn:hover { background:var(--blue); color:#fff; box-shadow:0 4px 12px rgba(5,34,65,.15); transform:translateY(-1px); }
        .history-header { display:flex; justify-content:space-between; align-items:center; gap:15px; margin-bottom:20px; }
        .history-count {
            background:#edf3f9; color:var(--navy); border-radius:20px;
            padding:7px 12px; font-size:11px; font-weight:600; white-space:nowrap;
        }
        .filter-area {
            display:grid; grid-template-columns:minmax(180px,1.5fr) 1fr 1fr auto;
            gap:10px; margin-bottom:18px;
        }
        .filter-area .form-control,.filter-area .form-select {
            min-height:42px; border:1px solid #dce2e9; border-radius:8px;
            font-size:12px; color:#34495e; background:#fff; box-shadow:none;
        }
        .filter-area .form-control:focus,.filter-area .form-select:focus {
            border-color:var(--blue); box-shadow:0 0 0 3px rgba(13,79,139,.08);
        }
        .filter-button {
            background:var(--navy); color:#fff; border:0; border-radius:8px;
            padding:0 16px; font-size:12px; font-weight:600;
        }
        .filter-button:hover { background:var(--blue); color:#fff; }
        .clear-filter {
            display:inline-flex; align-items:center; justify-content:center;
            color:#6b7b8c; font-size:12px; text-decoration:none; padding:8px 10px;
        }
        .clear-filter:hover { color:var(--navy); }
        .table-responsive { border:1px solid var(--border); border-radius:9px; }
        .alert-table { width:100%; margin:0; }
        .alert-table thead th {
            background:#f6f8fb; color:#64748b; font-size:10px; font-weight:700;
            text-transform:uppercase; letter-spacing:.45px; padding:15px 13px;
            white-space:nowrap; border-bottom:1px solid #e9edf2;
        }
        .alert-table tbody td {
            color:#526174; font-size:12px; padding:14px 13px;
            vertical-align:middle; border-color:#f0f2f5;
        }
        .alert-table tbody tr:hover { background:#f9fbfd; }
        .alert-id { color:#94a3b8!important; font-weight:600; }
        .alert-title-text { color:var(--navy); font-size:12px; font-weight:700; min-width:140px; }
        .alert-message {
            color:#718096; font-size:11px; line-height:1.5; min-width:190px;
            max-width:300px; white-space:pre-wrap; overflow-wrap:anywhere;
        }
        .alert-date { color:#526174; white-space:nowrap; font-size:11px; }
        .alert-date small { color:#94a3b8; }
        .type-badge {
            display:inline-flex; align-items:center; gap:6px; padding:7px 9px;
            border-radius:6px; font-size:10px; font-weight:700; white-space:nowrap;
        }
        .type-general { background:#edf2f7; color:#526174; }
        .type-weather { background:#e8f2ff; color:#2363a1; }
        .type-evacuation { background:#fff0e5; color:#b65b17; }
        .type-volcanic { background:#fce9e7; color:#b33c32; }
        .type-flood { background:#e6f3fa; color:#16709b; }
        .type-earthquake { background:#f2eafa; color:#7442a3; }
        .type-typhoon { background:#e9edff; color:#4656a5; }
        .status-badge {
            display:inline-flex; align-items:center; gap:5px; padding:6px 9px;
            border-radius:20px; font-size:10px; font-weight:700; white-space:nowrap;
        }
        .status-published { background:#e8f5ed; color:#287747; }
        .status-draft { background:#f0f1f3; color:#687381; }
        .alert-empty { text-align:center; padding:45px 20px!important; }
        .empty-icon {
            width:53px; height:53px; display:flex; align-items:center; justify-content:center;
            margin:0 auto 12px; border-radius:50%; background:#f0f4f8;
            color:#7890a8; font-size:22px;
        }
        .alert-empty strong { display:block; color:#34495e; font-size:13px; margin-bottom:5px; }
        .alert-empty span { color:#94a3b8; font-size:11px; }
        .alert-dismissible { border-radius:9px; font-size:13px; }
        @media(max-width:1100px) {
            .stats-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .filter-area { grid-template-columns:1fr 1fr; }
        }
        @media(max-width:650px) {
            .alerts-header { align-items:flex-start; }
            .dashboard-title { font-size:21px; }
            .stats-grid { grid-template-columns:1fr 1fr; gap:10px; }
            .stat-card { padding:13px; gap:9px; }
            .stat-icon { width:36px; height:36px; font-size:16px; }
            .stat-value { font-size:20px; }
            .stat-label { font-size:10px; }
            .dash-card { padding:17px; }
            .card-description { margin-left:0; margin-top:9px; }
            .history-header { align-items:flex-start; flex-direction:column; }
            .filter-area { grid-template-columns:1fr; }
            .filter-button { min-height:42px; }
            .create-alert-btn { width:100%; }
            .alert-table { min-width:950px; }
        }

        /* FORCE INPUT TEXT TO REMAIN VISIBLE EVEN WHEN IT IS NOT SELECTED */
        html body .dash-card form.alert-form input#title.form-control,
        html body .dash-card form.alert-form input#alert_date.form-control,
        html body .dash-card form.alert-form textarea#message.form-control,
        html body .dash-card form.alert-form select#type.form-select,
        html body .dash-card form.alert-form select#target_barangay.form-select,
        html body .filter-area input.form-control,
        html body .filter-area select.form-select {
            color: #172b4d !important;
            -webkit-text-fill-color: #172b4d !important;
            text-shadow: none !important;
            opacity: 1 !important;
            visibility: visible !important;
            background: #ffffff !important;
            caret-color: #172b4d !important;
            filter: none !important;
        }

        html body .dash-card form.alert-form input#title.form-control:focus,
        html body .dash-card form.alert-form input#alert_date.form-control:focus,
        html body .dash-card form.alert-form textarea#message.form-control:focus {
            color: #172b4d !important;
            -webkit-text-fill-color: #172b4d !important;
            background-color: #ffffff !important;
        }

        html body .dash-card form.alert-form input#title.form-control::placeholder,
        html body .dash-card form.alert-form textarea#message.form-control::placeholder {
            color: #738096 !important;
            -webkit-text-fill-color: #738096 !important;
            opacity: 1 !important;
        }

    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="dashboard-wrapper">

        <div class="alerts-header">
            <div>
                <h2 class="dashboard-title">ALERTS</h2>
                <p class="page-description">Manage emergency announcements and keep residents informed.</p>
            </div>
            <div class="header-icon"><i class="bi bi-bell-fill"></i></div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= h($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= h($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon navy"><i class="bi bi-bell"></i></div>
                <div><div class="stat-label">Total Alerts</div><div class="stat-value"><?= $totalAlerts ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-send-check"></i></div>
                <div><div class="stat-label">Published</div><div class="stat-value"><?= $publishedAlerts ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-file-earmark-text"></i></div>
                <div><div class="stat-label">Drafts</div><div class="stat-value"><?= $draftAlerts ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-calendar-event"></i></div>
                <div><div class="stat-label">Created Today</div><div class="stat-value"><?= $todayAlerts ?></div></div>
            </div>
        </div>

        <div class="dash-card">
            <div class="section-heading">
                <div class="section-icon"><i class="bi bi-megaphone-fill"></i></div>
                <h3 class="card-title">Create New Alert</h3>
            </div>
            <p class="card-description">Compose an announcement or emergency notification for residents.</p>

            <form method="POST" action="alerts.php" class="alert-form">
                <input type="hidden" name="create_alert" value="1">

                <div class="form-group">
                    <label for="title">Alert Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control"
                           maxlength="150" placeholder="e.g. Flood Warning in Talisay"
                           value="<?= h($title) ?>" required>
                </div>

                <div class="form-group">
                    <label for="type">Alert Type <span class="required">*</span></label>
                    <div class="select-wrapper">
                        <select name="type" id="type" class="form-select" required>
                            <option value="" disabled <?= $type === '' ? 'selected' : '' ?>>Select an alert category</option>
                            <?php foreach ($alertTypes as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= $type === $value ? 'selected' : '' ?>>
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-note"><i class="bi bi-info-circle"></i>Choose the category that best describes the announcement.</div>
                </div>

                <div class="form-group">
                    <label for="target_barangay">Target Barangay</label>
                    <div class="select-wrapper">
                        <select name="target_barangay" id="target_barangay" class="form-select">
                            <option value="" <?= $target_barangay === '' ? 'selected' : '' ?>>All Barangays</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?= h($barangay) ?>" <?= $target_barangay === $barangay ? 'selected' : '' ?>>
                                    <?= h($barangay) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-note"><i class="bi bi-geo-alt"></i>Select a specific barangay or leave All Barangays selected.</div>
                </div>

                <div class="form-group">
                    <label for="alert_date">Alert Date <span class="required">*</span></label>
                    <input type="date" id="alert_date" name="alert_date" class="form-control"
                           value="<?= h($alert_date) ?>" min="<?= h(date('Y-m-d')) ?>" required>
                    <div class="form-note"><i class="bi bi-calendar-event"></i>Select the date when this alert is intended to apply. This is separate from the date the alert is created.</div>
                </div>

                <div class="form-group">
                    <label for="message">Alert Message <span class="required">*</span></label>
                    <textarea id="message" name="message" class="form-control" rows="5"
                              maxlength="5000"
                              placeholder="Write the emergency announcement, instructions, or safety reminders..."
                              required><?= h($message) ?></textarea>
                    <div class="message-footer">
                        <div class="form-note"><i class="bi bi-pencil-square"></i>Keep your message clear and easy to understand.</div>
                        <span class="char-counter" id="charCounter">0 / 5000 characters</span>
                    </div>
                </div>

                <div>
                    <button type="submit" class="btn create-alert-btn">
                        <i class="bi bi-send-fill"></i> Publish Alert
                    </button>
                </div>
            </form>
        </div>

        <div class="dash-card">
            <div class="history-header">
                <div>
                    <div class="section-heading">
                        <div class="section-icon"><i class="bi bi-clock-history"></i></div>
                        <h3 class="card-title">Alert History</h3>
                    </div>
                    <p class="card-description">Review and search previously created announcements.</p>
                </div>
                <span class="history-count"><i class="bi bi-bell me-1"></i><?= count($filteredAlerts) ?> matching alerts</span>
            </div>

            <form method="GET" action="alerts.php" class="filter-area">
                <input type="search" name="search" class="form-control"
                       placeholder="Search alert title or message..." value="<?= h($search) ?>">

                <select name="type" class="form-select">
                    <option value="">All Alert Types</option>
                    <?php foreach ($alertTypes as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $filterType === $value ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="barangay" class="form-select">
                    <option value="">All Barangays</option>
                    <?php foreach ($barangays as $barangay): ?>
                        <option value="<?= h($barangay) ?>" <?= $filterBarangay === $barangay ? 'selected' : '' ?>>
                            <?= h($barangay) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="filter-button"><i class="bi bi-search me-1"></i>Filter</button>

                <?php if ($search !== '' || $filterType !== '' || $filterBarangay !== ''): ?>
                    <a href="alerts.php" class="clear-filter"><i class="bi bi-x-circle me-1"></i>Clear filters</a>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table table-hover alert-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Alert Details</th>
                            <th>Category</th>
                            <th>Target</th>
                            <th>Alert Date</th>
                            <th>Date Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($filteredAlerts)): ?>
                        <?php foreach ($filteredAlerts as $alert): ?>
                            <?php
                                $currentType = $alert['type'] ?? '';
                                $typeLabel = $alertTypes[$currentType] ?? ucwords(str_replace('_', ' ', $currentType));
                                $typeClass = $typeClasses[$currentType] ?? 'type-general';
                                $typeIcon = $typeIcons[$currentType] ?? 'bi-info-circle';
                                $createdTimestamp = !empty($alert['created_at']) ? strtotime($alert['created_at']) : false;
                                $effectiveTimestamp = !empty($alert['alert_date']) ? strtotime($alert['alert_date']) : false;
                            ?>
                            <tr>
                                <td class="alert-id">#<?= h($alert['id']) ?></td>
                                <td>
                                    <div class="alert-title-text"><?= h($alert['title']) ?></div>
                                    <div class="alert-message mt-1"><?= h($alert['message']) ?></div>
                                </td>
                                <td>
                                    <span class="type-badge <?= h($typeClass) ?>">
                                        <i class="bi <?= h($typeIcon) ?>"></i><?= h($typeLabel) ?>
                                    </span>
                                </td>
                                <td><i class="bi bi-geo-alt me-1"></i><?= h($alert['target_barangay'] ?: 'All Barangays') ?></td>
                                <td class="alert-date">
                                    <?= $effectiveTimestamp ? h(date('M d, Y', $effectiveTimestamp)) : 'Not set' ?>
                                </td>
                                <td class="alert-date">
                                    <?= $createdTimestamp ? h(date('M d, Y', $createdTimestamp)) : 'N/A' ?>
                                    <br><small><?= $createdTimestamp ? h(date('h:i A', $createdTimestamp)) : '' ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($alert['is_published'])): ?>
                                        <span class="status-badge status-published"><i class="bi bi-check-circle-fill"></i>Published</span>
                                    <?php else: ?>
                                        <span class="status-badge status-draft"><i class="bi bi-file-earmark"></i>Draft</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="alert-empty">
                                <div class="empty-icon"><i class="bi bi-search"></i></div>
                                <strong>
                                    <?= ($search !== '' || $filterType !== '' || $filterBarangay !== '')
                                        ? 'No matching alerts found.'
                                        : 'No alerts available yet.' ?>
                                </strong>
                                <span>
                                    <?= ($search !== '' || $filterType !== '' || $filterBarangay !== '')
                                        ? 'Try changing your search or filters.'
                                        : 'Your announcements will appear here after you publish one.' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const messageInput = document.getElementById('message');
const charCounter = document.getElementById('charCounter');

function updateCharacterCount() {
    const count = messageInput.value.length;
    charCounter.textContent = `${count} / 5000 characters`;
    charCounter.classList.remove('near-limit', 'at-limit');

    if (count >= 4800) {
        charCounter.classList.add('at-limit');
    } else if (count >= 4300) {
        charCounter.classList.add('near-limit');
    }
}

messageInput.addEventListener('input', updateCharacterCount);
updateCharacterCount();
</script>
</body>
</html>
