
<?php
session_start();

include '../config/db.php';

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$residentStatuses = [
    'Submitted',
    'Under Review',
    'Verified',
    'Response in Progress',
    'Resolved',
    'Needs More Information',
    'Rejected'
];

$priorityOptions = [
    'Critical',
    'High',
    'Medium',
    'Low'
];

$escalationPriorityOptions = [
    'Low',
    'Medium',
    'High',
    'Critical'
];

$extremityOptions = [
    'Extreme',
    'Severe',
    'Moderate',
    'Minor'
];

/*
|--------------------------------------------------------------------------
| HELPER: REDIRECT WITH ERROR
|--------------------------------------------------------------------------
*/

function recordsRedirect(string $query): void
{
    header('Location: records.php?' . $query);
    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE INCIDENT ESCALATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_escalation'])
) {
    $incidentId = trim((string) ($_POST['incident_id'] ?? ''));
    $priorityLevel = trim((string) ($_POST['priority_level'] ?? ''));
    $escalationReason = trim((string) ($_POST['escalation_reason'] ?? ''));

    $isEscalated = isset($_POST['is_escalated'])
        && $_POST['is_escalated'] === '1';

    if (
        $incidentId === ''
        || !in_array($priorityLevel, $escalationPriorityOptions, true)
    ) {
        recordsRedirect('error=invalid_escalation');
    }

    if ($isEscalated && $escalationReason === '') {
        recordsRedirect('error=escalation_reason_required');
    }

    if (mb_strlen($escalationReason) > 2000) {
        recordsRedirect('error=escalation_reason_too_long');
    }

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare(
            'SELECT incident_id
             FROM public.incidents
             WHERE incident_id::text = ?
             FOR UPDATE'
        );

        $check->execute([$incidentId]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            recordsRedirect('error=incident_not_found');
        }

        if ($isEscalated) {
            $update = $pdo->prepare(
                'UPDATE public.incidents
                 SET priority_level = ?,
                     is_escalated = TRUE,
                     escalation_reason = ?,
                     escalated_at = NOW(),
                     updated_at = NOW()
                 WHERE incident_id::text = ?'
            );

            $update->execute([
                $priorityLevel,
                $escalationReason,
                $incidentId
            ]);

            // Keep a history entry for each escalation save.
            $history = $pdo->prepare(
                'INSERT INTO public.incident_escalation_history
                    (incident_id, priority_level, escalation_reason)
                 VALUES (?, ?, ?)'
            );

            $history->execute([
                $incidentId,
                $priorityLevel,
                $escalationReason
            ]);
        } else {
            // Clear the current escalation but retain its history.
            $update = $pdo->prepare(
                'UPDATE public.incidents
                 SET priority_level = ?,
                     is_escalated = FALSE,
                     escalation_reason = NULL,
                     escalated_at = NULL,
                     updated_at = NOW()
                 WHERE id::text = ?'
            );

            $update->execute([
                $priorityLevel,
                $incidentId
            ]);
        }

        $pdo->commit();

        recordsRedirect('escalation_updated=1');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Incident escalation update failed: ' . $e->getMessage());

        http_response_code(500);
        exit('Unable to save the escalation. Check the server error log.');
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE RESIDENT-FACING INCIDENT STATUS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_incident_status'])
) {
    $incidentId = trim((string) ($_POST['incident_id'] ?? ''));
    $newStatus = trim((string) ($_POST['resident_status'] ?? ''));
    $message = trim((string) ($_POST['latest_update'] ?? ''));

    if (
        $incidentId === ''
        || !in_array($newStatus, $residentStatuses, true)
    ) {
        recordsRedirect('error=invalid_status');
    }

    if ($message === '') {
        $message = 'Your report status has been updated to '
            . $newStatus . '.';
    }

    if (mb_strlen($message) > 2000) {
        recordsRedirect('error=message_too_long');
    }

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare(
            'SELECT incident_id
             FROM public.incidents
             WHERE incident_id::text = ?
             FOR UPDATE'
        );

        $check->execute([$incidentId]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            recordsRedirect('error=not_found');
        }

        if ($newStatus === 'Resolved') {
            $update = $pdo->prepare(
                'UPDATE public.incidents
                 SET resident_status = ?,
                     latest_update = ?,
                     updated_at = NOW(),
                     resolved_at = COALESCE(resolved_at, NOW())
                 WHERE incident_id::text = ?'
            );
        } else {
            $update = $pdo->prepare(
                'UPDATE public.incidents
                 SET resident_status = ?,
                     latest_update = ?,
                     updated_at = NOW(),
                     resolved_at = NULL
                 WHERE incident_id::text = ?'
            );
        }

        $update->execute([
            $newStatus,
            $message,
            $incidentId
        ]);

        // Save the status update to the permanent history table.
        $history = $pdo->prepare(
            'INSERT INTO public.incident_status_history
                (incident_id, status, message)
             VALUES (?, ?, ?)'
        );

        $history->execute([
            $incidentId,
            $newStatus,
            $message
        ]);

        $pdo->commit();

        recordsRedirect('updated=1');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Incident status update failed: ' . $e->getMessage());

        http_response_code(500);
        exit('Unable to update the incident status. Check the server error log.');
    }
}

/*
|--------------------------------------------------------------------------
| RESOLVE INCIDENT
|--------------------------------------------------------------------------
|
| The legacy status field is not modified.
| resolved_at controls whether an incident is archived.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['resolve_incident'])
) {
    $incidentId = trim((string) ($_POST['incident_id'] ?? ''));

    if ($incidentId !== '') {
        try {
            $stmt = $pdo->prepare(
                'UPDATE public.incidents
                 SET resolved_at = NOW(),
                     updated_at = NOW()
                 WHERE incident_id::text = ?
                   AND resolved_at IS NULL'
            );

            $stmt->execute([$incidentId]);

        } catch (Throwable $e) {
            error_log('Resolve incident failed: ' . $e->getMessage());

            http_response_code(500);
            exit('Unable to resolve the incident. Check the server error log.');
        }
    }

    recordsRedirect('resolved=1');
}

/*
|--------------------------------------------------------------------------
| CURRENT VIEW AND FILTERS
|--------------------------------------------------------------------------
*/

$view = $_GET['view'] ?? 'active';

if (!in_array($view, ['active', 'archive'], true)) {
    $view = 'active';
}

$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$priorityFilter = trim((string) ($_GET['priority'] ?? ''));
$extremityFilter = trim((string) ($_GET['extremity'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$searchQuery = mb_substr($searchQuery, 0, 100);

/*
|--------------------------------------------------------------------------
| FETCH INCIDENT CATEGORIES
|--------------------------------------------------------------------------
*/

try {
    $categoryStmt = $pdo->query(
        "SELECT DISTINCT category
         FROM public.incidents
         WHERE category IS NOT NULL
           AND category <> ''
         ORDER BY category ASC"
    );

    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {
    error_log('Fetch categories failed: ' . $e->getMessage());
    $categories = [];
}

/*
|--------------------------------------------------------------------------
| BUILD INCIDENT QUERY
|--------------------------------------------------------------------------
*/

try {
    $where = [];
    $params = [];

    if ($view === 'active') {
        $where[] = 'resolved_at IS NULL';
    } else {
        $where[] = 'resolved_at IS NOT NULL';
    }

    if ($categoryFilter !== '') {
        $where[] = 'category = ?';
        $params[] = $categoryFilter;
    }

    if ($priorityFilter !== '') {
        $where[] = 'priority_level = ?';
        $params[] = $priorityFilter;
    }

    if ($extremityFilter !== '') {
        $where[] = 'severity = ?';
        $params[] = $extremityFilter;
    }

    if ($statusFilter !== '') {
        if ($statusFilter === 'Resolved') {
            $where[] = 'resolved_at IS NOT NULL';
        } elseif ($statusFilter === 'Active') {
            $where[] = 'resolved_at IS NULL';
        } else {
            $where[] = 'resident_status = ?';
            $params[] = $statusFilter;
        }
    }

    if ($searchQuery !== '') {
        $searchTerm = '%' . $searchQuery . '%';
        $where[] = "(
            incident_id::text ILIKE ?
            OR COALESCE(category, '') ILIKE ?
            OR COALESCE(location, '') ILIKE ?
            OR COALESCE(priority_level, '') ILIKE ?
            OR COALESCE(severity, '') ILIKE ?
            OR COALESCE(resident_status, '') ILIKE ?
        )";
        array_push(
            $params,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm
        );
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $statsQuery = "
        SELECT
            COUNT(*) AS total_records,
            COUNT(*) FILTER (
                WHERE priority_level IN ('Critical', 'High')
            ) AS urgent_records,
            COUNT(*) FILTER (
                WHERE is_escalated IS TRUE
            ) AS escalated_records
        FROM public.incidents
        $whereSQL
    ";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($params);
    $recordStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalRecords = (int) ($recordStats['total_records'] ?? 0);
    $urgentRecords = (int) ($recordStats['urgent_records'] ?? 0);
    $escalatedRecords = (int) ($recordStats['escalated_records'] ?? 0);

    $pageSize = 15;
    $requestedPage = filter_var(
        $_GET['page'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $totalPages = max(1, (int) ceil($totalRecords / $pageSize));
    $currentPage = min(
        $requestedPage === false ? 1 : $requestedPage,
        $totalPages
    );
    $offset = ($currentPage - 1) * $pageSize;

    $query = "
        SELECT *
        FROM public.incidents
        $whereSQL
        ORDER BY
            CASE priority_level
                WHEN 'Critical' THEN 1
                WHEN 'High' THEN 2
                WHEN 'Medium' THEN 3
                WHEN 'Low' THEN 4
                ELSE 5
            END,
            created_at DESC
        LIMIT ?
        OFFSET ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($params, [$pageSize, $offset]));
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recordRangeStart = $totalRecords > 0 ? $offset + 1 : 0;
    $recordRangeEnd = min($offset + count($incidents), $totalRecords);

} catch (Throwable $e) {
    error_log('Fetch incidents failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load incident records. Check the server error log.');
}

$paginationParams = ['view' => $view];

foreach ([
    'category' => $categoryFilter,
    'priority' => $priorityFilter,
    'extremity' => $extremityFilter,
    'status' => $statusFilter,
    'q' => $searchQuery
] as $key => $value) {
    if ($value !== '') {
        $paginationParams[$key] = $value;
    }
}

/*
|--------------------------------------------------------------------------
| HELPER: HTML ESCAPING
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| HELPER: DATE DISPLAY
|--------------------------------------------------------------------------
*/

function displayDate($value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp === false
        ? 'N/A'
        : date('M d, Y h:i A', $timestamp);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $view === 'archive' ? 'Incident Archive' : 'Incident Records'; ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <link rel="stylesheet" href="assets/style.css?v=20261114i">

    <link
        rel="icon"
        type="image/png"
        href="assets/log.png"
    >
</head>

<body class="records-page">

<?php include 'sidebar.php'; ?>

<main class="main-content records-content">
    <div class="dashboard-wrapper records-wrapper">

        <!-- PAGE HEADER -->
        <div class="records-header">
            <div>
                <p class="records-eyebrow">INCIDENT MANAGEMENT</p>
                <h1 class="records-title admin-page-title">
                    <?= $view === 'archive' ? 'Incident archive' : 'Incident records'; ?>
                </h1>
                <p class="records-subtitle">
                    <?= $view === 'archive'
                        ? 'Review resolved reports and their resolution dates.'
                        : 'Review, prioritize, and update reports from residents.'; ?>
                </p>
            </div>

            <div class="records-header-actions">
                <?php if ($view === 'active'): ?>
                    <a href="records.php?view=archive" class="archive-btn">
                        <i class="bi bi-archive"></i>
                        Archive
                    </a>
                <?php else: ?>
                    <a href="records.php" class="active-btn">
                        <i class="bi bi-arrow-left"></i>
                        Active Incidents
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- FEEDBACK MESSAGES -->
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Resident status update saved successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['escalation_updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Escalation settings saved successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['resolved'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Incident resolved successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php
        $errorMessages = [
            'invalid_status' => 'Please select a valid resident status.',
            'message_too_long' => 'The resident update message must not exceed 2000 characters.',
            'not_found' => 'The incident was not found.',
            'invalid_escalation' => 'Please select a valid priority level.',
            'escalation_reason_required' => 'Enter an escalation reason before saving.',
            'escalation_reason_too_long' => 'The escalation reason must not exceed 2000 characters.',
            'incident_not_found' => 'The incident was not found.'
        ];

        $errorCode = (string) ($_GET['error'] ?? '');

        if (isset($errorMessages[$errorCode])):
        ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= h($errorMessages[$errorCode]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section class="records-stats" aria-label="Incident record summary">
            <article class="records-stat-card">
                <span class="records-stat-icon">
                    <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                </span>
                <div>
                    <p>Matching incidents</p>
                    <strong><?= number_format($totalRecords); ?></strong>
                </div>
            </article>
            <article class="records-stat-card records-stat-urgent">
                <span class="records-stat-icon">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                </span>
                <div>
                    <p>High / critical</p>
                    <strong><?= number_format($urgentRecords); ?></strong>
                </div>
            </article>
            <article class="records-stat-card records-stat-escalated">
                <span class="records-stat-icon">
                    <i class="bi bi-arrow-up-right-circle" aria-hidden="true"></i>
                </span>
                <div>
                    <p>Escalated</p>
                    <strong><?= number_format($escalatedRecords); ?></strong>
                </div>
            </article>
        </section>

        <!-- FILTER CARD -->
        <div class="filter-card">
            <form method="GET" action="records.php" role="search" aria-label="Search and filter incidents">
                <input
                    type="hidden"
                    name="view"
                    value="<?= h($view); ?>"
                >

                <div class="row g-3 align-items-end">
                    <div class="col-12">
                        <label class="filter-label" for="filter-search">
                            Search records
                        </label>
                        <input
                            id="filter-search"
                            name="q"
                            type="search"
                            class="form-control records-search-input"
                            value="<?= h($searchQuery); ?>"
                            maxlength="100"
                            placeholder="ID, incident, location, or status"
                        >
                    </div>

                    <div class="col-6 col-lg-3">
                        <label class="filter-label" for="filter-category">
                            Incident
                        </label>

                        <select
                            id="filter-category"
                            name="category"
                            class="filter-select"
                        >
                            <option value="">All Incidents</option>

                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= h($category); ?>"
                                    <?= $categoryFilter === $category ? 'selected' : ''; ?>
                                >
                                    <?= h($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-lg-3">
                        <label class="filter-label" for="filter-priority">
                            Priority Level
                        </label>

                        <select
                            id="filter-priority"
                            name="priority"
                            class="filter-select"
                        >
                            <option value="">All Priority Levels</option>

                            <?php foreach ($priorityOptions as $priorityOption): ?>
                                <option
                                    value="<?= h($priorityOption); ?>"
                                    <?= $priorityFilter === $priorityOption ? 'selected' : ''; ?>
                                >
                                    <?= h($priorityOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-lg-3">
                        <label class="filter-label" for="filter-extremity">
                            Extremity
                        </label>

                        <select
                            id="filter-extremity"
                            name="extremity"
                            class="filter-select"
                        >
                            <option value="">All Extremity Levels</option>

                            <?php foreach ($extremityOptions as $extremity): ?>
                                <option
                                    value="<?= h($extremity); ?>"
                                    <?= $extremityFilter === $extremity ? 'selected' : ''; ?>
                                >
                                    <?= h($extremity); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-lg-3">
                        <label class="filter-label" for="filter-status">
                            Resident status
                        </label>

                        <select
                            id="filter-status"
                            name="status"
                            class="filter-select"
                        >
                            <?php if ($view === 'archive'): ?>
                                <option value="Resolved" selected>
                                    Resolved
                                </option>
                            <?php else: ?>
                                <option value="">All Active Statuses</option>

                                <?php foreach ($residentStatuses as $statusOption): ?>
                                    <?php if ($statusOption === 'Resolved') continue; ?>
                                    <option
                                        value="<?= h($statusOption); ?>"
                                        <?= $statusFilter === $statusOption ? 'selected' : ''; ?>
                                    >
                                        <?= h($statusOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="filter-actions">
                            <button type="submit" class="filter-btn">
                                <i class="bi bi-funnel"></i>
                                Apply Filters
                            </button>

                            <a
                                href="records.php?view=<?= h($view); ?>"
                                class="clear-filter-btn"
                            >
                                <i class="bi bi-x-circle"></i>
                                Clear
                            </a>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <!-- INCIDENT TABLE -->
        <section class="records-table-card" aria-labelledby="records-list-title">
            <div class="records-table-header">
                <div>
                    <h2 id="records-list-title">Incident list</h2>
                    <p>Review the key details, then open Manage to make changes.</p>
                </div>
                <span class="records-page-count">
                    Page <?= number_format($currentPage); ?> of <?= number_format($totalPages); ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover incident-table">
                    <caption class="visually-hidden">
                        <?= $view === 'archive' ? 'Archived incidents' : 'Active incidents'; ?>
                    </caption>

                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Incident / location</th>
                            <th scope="col">Priority</th>
                            <th scope="col">Extremity</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date reported</th>

                            <?php if ($view === 'archive'): ?>
                                <th scope="col">Date resolved</th>
                            <?php else: ?>
                                <th scope="col">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!empty($incidents)): ?>

                        <?php foreach ($incidents as $incident): ?>

                            <?php
                            $incidentId = (string) $incident['incident_id'];

                            $priority = $incident['priority_level'] ?? 'Medium';

                            $priorityClasses = [
                                'Critical' => 'priority-critical',
                                'High' => 'priority-high',
                                'Medium' => 'priority-medium',
                                'Low' => 'priority-low'
                            ];

                            $priorityClass = $priorityClasses[$priority]
                                ?? 'priority-medium';

                            $extremity = $incident['severity'] ?? 'Moderate';

                            $severityClasses = [
                                'Extreme' => 'severity-extreme',
                                'Severe' => 'severity-severe',
                                'Moderate' => 'severity-moderate',
                                'Minor' => 'severity-minor'
                            ];

                            $severityClass = $severityClasses[$extremity]
                                ?? 'severity-moderate';

                            $displayStatus = !empty($incident['resolved_at'])
                                ? 'Resolved'
                                : ($incident['resident_status'] ?? 'Submitted');

                            $statusClasses = [
                                'Submitted' => 'status-pending',
                                'Under Review' => 'status-default',
                                'Verified' => 'status-progress',
                                'Response in Progress' => 'status-progress',
                                'Resolved' => 'status-resolved',
                                'Needs More Information' => 'status-default',
                                'Rejected' => 'status-default'
                            ];

                            $statusClass = $statusClasses[$displayStatus]
                                ?? 'status-pending';

                            $selectedResidentStatus =
                                $incident['resident_status'] ?? 'Submitted';

                            $selectedPriority =
                                $incident['priority_level'] ?? 'Medium';
                            ?>

                            <tr>
                                <td>#<?= h($incidentId); ?></td>

                                <td>
                                    <div class="incident-summary">
                                        <strong>
                                            <?= h($incident['category'] ?? 'Unknown'); ?>
                                        </strong>
                                        <span>
                                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                            <?= h($incident['location'] ?? 'Unknown'); ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="priority-badge <?= h($priorityClass); ?>">
                                        <?= h($priority); ?>
                                    </span>

                                    <?php if (!empty($incident['is_escalated'])): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-danger">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                                Escalated
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="severity-badge <?= h($severityClass); ?>">
                                        <?= h($extremity); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-badge <?= h($statusClass); ?>">
                                        <?= h($displayStatus); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= h(displayDate($incident['created_at'] ?? null)); ?>
                                </td>

                                <?php if ($view === 'archive'): ?>

                                    <td>
                                        <?= h(displayDate($incident['resolved_at'] ?? null)); ?>
                                    </td>

                                <?php else: ?>

                                    <td class="records-action-cell">
                                    <button
                                        type="button"
                                        class="records-action-toggle"
                                        data-record-dialog-open="record-actions-<?= h($incidentId); ?>"
                                        aria-controls="record-actions-<?= h($incidentId); ?>"
                                        aria-haspopup="dialog"
                                        aria-label="Manage incident #<?= h($incidentId); ?>"
                                    >
                                        <i class="bi bi-sliders" aria-hidden="true"></i>
                                        Manage
                                    </button>

                                    <dialog
                                        class="records-action-dialog"
                                        id="record-actions-<?= h($incidentId); ?>"
                                        aria-labelledby="record-actions-title-<?= h($incidentId); ?>"
                                    >
                                        <div class="records-dialog-header">
                                            <div>
                                                <p>INCIDENT #<?= h($incidentId); ?></p>
                                                <h2 id="record-actions-title-<?= h($incidentId); ?>">
                                                    Manage incident
                                                </h2>
                                            </div>
                                            <button
                                                type="button"
                                                class="records-dialog-close"
                                                data-record-dialog-close
                                                aria-label="Close incident actions"
                                            >
                                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                            </button>
                                        </div>

                                        <div class="records-dialog-grid">
                                            <section class="records-dialog-section">
                                                <h3>Update resident</h3>

                                                <!-- RESIDENT STATUS FORM -->
                                                <form method="POST" class="incident-status-form">
                                                    <input
                                                        type="hidden"
                                                        name="incident_id"
                                                        value="<?= h($incidentId); ?>"
                                                    >

                                                    <label
                                                        class="form-label"
                                                        for="resident-status-<?= h($incidentId); ?>"
                                                    >
                                                        Resident Status
                                                    </label>

                                                    <select
                                                        class="form-select mb-2"
                                                        id="resident-status-<?= h($incidentId); ?>"
                                                        name="resident_status"
                                                        required
                                                    >
                                                        <?php foreach ($residentStatuses as $statusOption): ?>
                                                            <option
                                                                value="<?= h($statusOption); ?>"
                                                                <?= $selectedResidentStatus === $statusOption
                                                                    ? 'selected'
                                                                    : ''; ?>
                                                            >
                                                                <?= h($statusOption); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <label
                                                        class="form-label"
                                                        for="latest-update-<?= h($incidentId); ?>"
                                                    >
                                                        Update Message
                                                    </label>

                                                    <textarea
                                                        class="form-control mb-2"
                                                        id="latest-update-<?= h($incidentId); ?>"
                                                        name="latest_update"
                                                        rows="3"
                                                        maxlength="2000"
                                                        placeholder="Write an update for the resident..."
                                                    ><?= h($incident['latest_update'] ?? ''); ?></textarea>

                                                    <button
                                                        type="submit"
                                                        name="update_incident_status"
                                                        value="1"
                                                        class="btn btn-primary btn-sm"
                                                    >
                                                        <i class="bi bi-save" aria-hidden="true"></i>
                                                        Save Update
                                                    </button>
                                                </form>
                                            </section>

                                            <section class="records-dialog-section">
                                                <h3>Priority and escalation</h3>

                                                <!-- ESCALATION FORM -->
                                                <form method="POST" class="incident-escalation-form">
                                                    <input
                                                        type="hidden"
                                                        name="incident_id"
                                                        value="<?= h($incidentId); ?>"
                                                    >

                                                    <label
                                                        class="form-label"
                                                        for="priority-level-<?= h($incidentId); ?>"
                                                    >
                                                        Priority Level
                                                    </label>

                                                    <select
                                                        class="form-select mb-2"
                                                        id="priority-level-<?= h($incidentId); ?>"
                                                        name="priority_level"
                                                        required
                                                    >
                                                        <?php foreach ($escalationPriorityOptions as $priorityOption): ?>
                                                            <option
                                                                value="<?= h($priorityOption); ?>"
                                                                <?= $selectedPriority === $priorityOption
                                                                    ? 'selected'
                                                                    : ''; ?>
                                                            >
                                                                <?= h($priorityOption); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <div class="form-check mb-2">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="is_escalated"
                                                            value="1"
                                                            id="is-escalated-<?= h($incidentId); ?>"
                                                            <?= !empty($incident['is_escalated'])
                                                                ? 'checked'
                                                                : ''; ?>
                                                        >

                                                        <label
                                                            class="form-check-label"
                                                            for="is-escalated-<?= h($incidentId); ?>"
                                                        >
                                                            Escalate this incident
                                                        </label>
                                                    </div>

                                                    <label
                                                        class="form-label"
                                                        for="escalation-reason-<?= h($incidentId); ?>"
                                                    >
                                                        Escalation Reason
                                                    </label>

                                                    <textarea
                                                        class="form-control mb-2"
                                                        id="escalation-reason-<?= h($incidentId); ?>"
                                                        name="escalation_reason"
                                                        rows="2"
                                                        maxlength="2000"
                                                        placeholder="Explain why this incident needs escalation..."
                                                    ><?= h($incident['escalation_reason'] ?? ''); ?></textarea>

                                                    <button
                                                        type="submit"
                                                        name="save_escalation"
                                                        value="1"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                                        Save Escalation
                                                    </button>
                                                </form>
                                            </section>
                                        </div>
                                    </dialog>
                                    </td>

                                <?php endif; ?>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="text-center empty-records">
                                <?php if ($view === 'archive'): ?>
                                    <i class="bi bi-archive" style="font-size: 35px;"></i>

                                    <div class="mt-2">
                                        No archived incidents.
                                    </div>

                                    <small>
                                        Resolved incidents will automatically appear here.
                                    </small>
                                <?php else: ?>
                                    <i class="bi bi-check-circle" style="font-size: 35px;"></i>

                                    <div class="mt-2">
                                        No active incident records.
                                    </div>

                                    <small>
                                        No incidents match the current search or filters.
                                        Try adjusting your search or clearing the filters.
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endif; ?>
                    </tbody>

                </table>
            </div>
            <?php
            $visiblePageStart = max(1, $currentPage - 2);
            $visiblePageEnd = min($totalPages, $currentPage + 2);
            $paginationUrl = static function (int $pageNumber) use ($paginationParams): string {
                return 'records.php?' . http_build_query(
                    $paginationParams + ['page' => $pageNumber]
                );
            };
            ?>
            <nav class="records-pagination" aria-label="Incident records pages">
                <p class="records-pagination-summary" aria-live="polite">
                    Showing <?= number_format($recordRangeStart); ?>-<?= number_format($recordRangeEnd); ?>
                    of <?= number_format($totalRecords); ?>
                    <?= $totalRecords === 1 ? 'record' : 'records'; ?>
                </p>

                <?php if ($totalPages > 1): ?>
                    <div class="records-pagination-links">
                        <?php if ($currentPage > 1): ?>
                            <a
                                class="records-page-link"
                                href="<?= h($paginationUrl($currentPage - 1)); ?>"
                                aria-label="Previous page"
                            >
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                Previous
                            </a>
                        <?php else: ?>
                            <span class="records-page-link is-disabled" aria-disabled="true">
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                Previous
                            </span>
                        <?php endif; ?>

                        <?php if ($visiblePageStart > 1): ?>
                            <a class="records-page-link" href="<?= h($paginationUrl(1)); ?>">1</a>
                            <?php if ($visiblePageStart > 2): ?>
                                <span class="records-page-ellipsis" aria-hidden="true">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($pageNumber = $visiblePageStart; $pageNumber <= $visiblePageEnd; $pageNumber++): ?>
                            <a
                                class="records-page-link <?= $pageNumber === $currentPage ? 'is-current' : ''; ?>"
                                href="<?= h($paginationUrl($pageNumber)); ?>"
                                <?= $pageNumber === $currentPage ? 'aria-current="page"' : ''; ?>
                            >
                                <?= number_format($pageNumber); ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($visiblePageEnd < $totalPages): ?>
                            <?php if ($visiblePageEnd < $totalPages - 1): ?>
                                <span class="records-page-ellipsis" aria-hidden="true">...</span>
                            <?php endif; ?>
                            <a class="records-page-link" href="<?= h($paginationUrl($totalPages)); ?>">
                                <?= number_format($totalPages); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a
                                class="records-page-link"
                                href="<?= h($paginationUrl($currentPage + 1)); ?>"
                                aria-label="Next page"
                            >
                                Next
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="records-page-link is-disabled" aria-disabled="true">
                                Next
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </nav>
        </section>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('click', function (event) {
    if (!(event.target instanceof Element)) {
        return;
    }

    const openButton = event.target.closest('[data-record-dialog-open]');

    if (openButton) {
        const dialog = document.getElementById(
            openButton.getAttribute('data-record-dialog-open')
        );

        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }

        return;
    }

    const closeButton = event.target.closest('[data-record-dialog-close]');

    if (closeButton) {
        const dialog = closeButton.closest('dialog');

        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }
    }
});

document.querySelectorAll('.records-action-dialog').forEach(function (dialog) {
    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            dialog.close();
        }
    });
});
</script>

</body>
</html>
