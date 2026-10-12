
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
            'SELECT id
             FROM public.incidents
             WHERE id::text = ?
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
                 WHERE id::text = ?'
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
            'SELECT id
             FROM public.incidents
             WHERE id::text = ?
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
                 WHERE id::text = ?'
            );
        } else {
            $update = $pdo->prepare(
                'UPDATE public.incidents
                 SET resident_status = ?,
                     latest_update = ?,
                     updated_at = NOW(),
                     resolved_at = NULL
                 WHERE id::text = ?'
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
                 WHERE id::text = ?
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
            $where[] = 'status = ?';
            $params[] = $statusFilter;
        }
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

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
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Fetch incidents failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load incident records. Check the server error log.');
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

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link rel="stylesheet" href="assets/style.css">

    <link
        rel="icon"
        type="image/png"
        href="assets/log.png"
    >
</head>

<body>

<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="dashboard-wrapper">

        <!-- PAGE HEADER -->
        <div class="records-header">
            <div>
                <?php if ($view === 'archive'): ?>
                    <h2 class="dashboard-title mb-1">INCIDENT ARCHIVE</h2>
                    <div class="archive-info">
                        Resolved incidents are automatically stored here.
                    </div>
                <?php else: ?>
                    <h2 class="dashboard-title mb-1">INCIDENT RECORDS</h2>
                <?php endif; ?>
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

        <!-- FILTER CARD -->
        <div class="filter-card">
            <form method="GET" action="records.php">
                <input
                    type="hidden"
                    name="view"
                    value="<?= h($view); ?>"
                >

                <div class="row g-3">

                    <div class="col-md-3">
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

                    <div class="col-md-3">
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

                    <div class="col-md-3">
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

                    <div class="col-md-3">
                        <label class="filter-label" for="filter-status">
                            Status
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

                                <option
                                    value="Pending"
                                    <?= $statusFilter === 'Pending' ? 'selected' : ''; ?>
                                >
                                    Pending
                                </option>

                                <option
                                    value="In Progress"
                                    <?= $statusFilter === 'In Progress' ? 'selected' : ''; ?>
                                >
                                    In Progress
                                </option>

                                <option
                                    value="Active"
                                    <?= $statusFilter === 'Active' ? 'selected' : ''; ?>
                                >
                                    Active
                                </option>
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
        <div class="dash-card">
            <div class="table-responsive">
                <table class="table table-bordered table-striped incident-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Incident</th>
                            <th>Location</th>
                            <th>Priority Level</th>
                            <th>Extremity</th>
                            <th>Status</th>
                            <th>Date Reported</th>

                            <?php if ($view === 'archive'): ?>
                                <th>Date Resolved</th>
                            <?php else: ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!empty($incidents)): ?>

                        <?php foreach ($incidents as $incident): ?>

                            <?php
                            $incidentId = (string) $incident['id'];

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
                                    <strong>
                                        <?= h($incident['category'] ?? 'Unknown'); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= h($incident['location'] ?? 'Unknown'); ?>
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

                                    <td style="min-width: 280px;">

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
                                                <i class="bi bi-save"></i>
                                                Save Update
                                            </button>
                                        </form>

                                        <hr class="my-3">

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
                                                <i class="bi bi-exclamation-triangle"></i>
                                                Save Escalation
                                            </button>
                                        </form>

                                    </td>

                                <?php endif; ?>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="text-center empty-records">
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
                                        All reported incidents have been resolved
                                        or no incidents match your selected filters.
                                    </small>
                                <?php endif; ?>
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

</body>
</html>
