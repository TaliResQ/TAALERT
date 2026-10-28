
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection is not configured correctly.');
}

$statuses = [
    'pending' => 'Pending',
    'under_review' => 'Under Review',
    'verified' => 'Verified',
    'assistance_coordinated' => 'Assistance Coordinated',
    'completed' => 'Completed',
    'rejected' => 'Rejected',
    'needs_more_information' => 'Needs More Information',
];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| UPDATE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $id = trim($_POST['id'] ?? '');
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['admin_notes'] ?? '');

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    if (!preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        $id
    )) {
        http_response_code(400);
        exit('Invalid request ID.');
    }

    if (!is_string($status) || !array_key_exists($status, $statuses)) {
        http_response_code(400);
        exit('Invalid request status.');
    }

    if (!is_string($notes) || strlen($notes) > 5000) {
        http_response_code(400);
        exit('Admin notes cannot exceed 5,000 characters.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE public.assistance_requests
             SET status = :status,
                 admin_notes = :admin_notes,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'status' => $status,
            'admin_notes' => $notes,
            'id' => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare(
                'SELECT 1
                 FROM public.assistance_requests
                 WHERE id = :id'
            );

            $check->execute(['id' => $id]);

            if (!$check->fetchColumn()) {
                $pdo->rollBack();
                http_response_code(404);
                exit('Assistance request not found.');
            }
        }

        $pdo->commit();

        header('Location: recovery-requests.php?updated=1');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Recovery request update failed: ' . $e->getMessage());

        http_response_code(500);
        exit('Unable to save changes. Please check the server logs.');
    }
}

/*
|--------------------------------------------------------------------------
| LOAD REQUESTS
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->query(
        'SELECT id, request_category, barangay, address,
                description, contact_number, status, admin_notes,
                created_at
         FROM public.assistance_requests
         ORDER BY created_at DESC'
    );

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Recovery requests query failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load assistance requests. Please check the server logs.');
}

/*
|--------------------------------------------------------------------------
| STATUS COUNTS
|--------------------------------------------------------------------------
*/

$statusCounts = array_fill_keys(array_keys($statuses), 0);

foreach ($requests as $request) {
    $currentStatus = $request['status'] ?? '';

    if (isset($statusCounts[$currentStatus])) {
        $statusCounts[$currentStatus]++;
    }
}

$totalRequests = count($requests);
$pendingRequests = $statusCounts['pending'];
$completedRequests = $statusCounts['completed'];
$needsAttention = $statusCounts['needs_more_information'];

/*
|--------------------------------------------------------------------------
| STATUS BADGE CLASS
|--------------------------------------------------------------------------
*/

function getStatusClass($status): string
{
    switch ($status) {
        case 'pending':
            return 'status-pending';

        case 'under_review':
            return 'status-review';

        case 'verified':
            return 'status-verified';

        case 'assistance_coordinated':
            return 'status-coordinated';

        case 'completed':
            return 'status-completed';

        case 'rejected':
            return 'status-rejected';

        case 'needs_more_information':
            return 'status-information';

        default:
            return 'status-default';
    }
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

    <title>Recovery Assistance Requests | TAALERT</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <!-- Existing dashboard stylesheet, if available -->
    <link rel="stylesheet" href="assets/style.css">

    <style>
        @font-face {
            font-family: 'Retro Floral';
            src: url('Capstone/assets/retro-floral.regular.ttf')
                format('truetype');
            font-display: swap;
        }

        :root {
            --primary-blue: #223868;
            --card-blue: #223868;
            --white: #ffffff;
            --accent-yellow: #f5b041;
            --accent-red: #c92a2a;
            --accent-green: #2ecc71;
            --font-main: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --font-retro: 'Retro Floral', Impact, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--primary-blue);
            color: var(--white);
            font-family: var(--font-main);
        }

        /* MAIN CONTENT */

        .main-content {
            min-width: 0;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .dashboard-wrapper {
            background: var(--white);
            color: #222;
            border-radius: 15px;
            padding: 22px;
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .dashboard-title {
            color: var(--primary-blue);
            font-family: var(--font-retro);
            font-size: 1.8rem;
            letter-spacing: 1px;
            margin: 0 0 6px;
        }

        .page-description {
            color: #727782;
            font-size: 14px;
            margin: 0 0 22px;
            line-height: 1.6;
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card-blue);
            color: var(--white);
            border-radius: 12px;
            padding: 20px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }

        .stat-heading {
            font-family: var(--font-retro);
            font-size: 0.85rem;
            letter-spacing: 1px;
            line-height: 1.5;
            margin: 0 0 12px;
            opacity: 0.9;
        }

        .stat-value {
            font-family: var(--font-retro);
            font-size: 2.2rem;
            line-height: 1;
            margin: 0;
        }

        .stat-icon {
            color: var(--accent-yellow);
            font-size: 1.2rem;
            margin-right: 7px;
        }

        /* REQUEST CARD */

        .dash-card {
            background: var(--card-blue);
            color: var(--white);
            border-radius: 12px;
            padding: 18px;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .card-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 15px;
        }

        .card-title {
            font-family: var(--font-retro);
            font-size: 1rem;
            font-weight: normal;
            letter-spacing: 1px;
            margin: 0;
        }

        .request-count {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            white-space: nowrap;
        }

        /* SEARCH */

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 15px;
        }

        .search-wrapper {
            position: relative;
            flex: 1;
            min-width: 220px;
            max-width: 400px;
        }

        .search-wrapper i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aab5ca;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 8px;
            color: #fff;
            font: inherit;
            font-size: 13px;
            outline: none;
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .search-input:focus {
            border-color: var(--accent-yellow);
        }

        .filter-select {
            min-width: 180px;
            max-width: 100%;
            padding: 10px 12px;
            background: var(--card-blue);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 8px;
            color: #fff;
            font: inherit;
            font-size: 13px;
            outline: none;
        }

        .filter-select option {
            background: var(--card-blue);
            color: #fff;
        }

        /* TABLE */

        .table-frame {
            width: 100%;
            min-width: 0;
            overflow: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 9px;
        }

        .requests-table {
            width: 100%;
            min-width: 1450px;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            color: #222;
            font-size: 13px;
        }

        .requests-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 15px 12px;
            background: var(--card-blue);
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.25);
            text-align: left;
            white-space: nowrap;
            font-family: var(--font-retro);
            font-size: 0.85rem;
            font-weight: normal;
            letter-spacing: 0.8px;
        }

        .requests-table tbody td {
            padding: 14px 12px;
            color: #222 !important;
            background: #fff;
            border-bottom: 1px solid #e8ebf0;
            vertical-align: top;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .requests-table tbody tr:hover td {
            background: #f5f7fb;
        }

        .requests-table tbody tr:last-child td {
            border-bottom: none;
        }

        .requests-table .category-cell {
            min-width: 125px;
            font-weight: 600;
            color: var(--primary-blue) !important;
        }

        .requests-table .barangay-cell {
            min-width: 125px;
        }

        .requests-table .address-cell {
            min-width: 180px;
            max-width: 240px;
        }

        .requests-table .description-cell {
            min-width: 240px;
            max-width: 320px;
            white-space: pre-wrap;
        }

        .requests-table .contact-cell {
            min-width: 130px;
        }

        .requests-table .status-cell {
            min-width: 205px;
        }

        .requests-table .notes-cell {
            min-width: 240px;
        }

        .requests-table .date-cell {
            min-width: 155px;
            white-space: nowrap;
        }

        .requests-table .action-cell {
            min-width: 145px;
        }

        /* STATUS BADGES */

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            max-width: 100%;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.4;
            white-space: normal;
            text-align: center;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-review {
            background: #cfe2ff;
            color: #084298;
        }

        .status-verified {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-coordinated {
            background: #e2d9f3;
            color: #59359a;
        }

        .status-completed {
            background: #198754;
            color: #fff;
        }

        .status-rejected {
            background: #f8d7da;
            color: #842029;
        }

        .status-information {
            background: #ffe5d0;
            color: #984c0c;
        }

        .status-default {
            background: #e2e3e5;
            color: #41464b;
        }

        /* FORM CONTROLS */

        .request-form {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .field-label {
            color: #566071;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            margin: 0;
        }

        .request-form select,
        .request-form textarea {
            width: 100%;
            min-width: 0;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #fff;
            color: #303744;
            padding: 9px 10px;
            font-family: var(--font-main);
            font-size: 12px;
            outline: none;
        }

        .request-form select:focus,
        .request-form textarea:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(34, 56, 104, 0.1);
        }

        .request-form textarea {
            min-height: 90px;
            resize: vertical;
            line-height: 1.5;
        }

        .save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            width: 100%;
            padding: 10px 12px;
            border: none;
            border-radius: 8px;
            background: var(--primary-blue);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .save-button:hover {
            background: #17294d;
            transform: translateY(-1px);
        }

        .save-button:focus-visible {
            outline: 3px solid #f5b041;
            outline-offset: 2px;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 45px 20px !important;
            text-align: center;
            color: #737d8e !important;
        }

        .empty-state i {
            display: block;
            font-size: 30px;
            margin-bottom: 10px;
            color: #9ba6b7;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 5px;
            color: #465166;
        }

        /* ALERTS */

        .alert {
            border-radius: 9px;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .alert i {
            margin-right: 6px;
        }

        /* FOOTER */

        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 13px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
        }

        /* SCROLLBARS */

        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(34, 56, 104, 0.4) #eef0f5;
        }

        .table-frame::-webkit-scrollbar {
            width: 7px;
            height: 9px;
        }

        .table-frame::-webkit-scrollbar-track {
            background: #eef0f5;
            border-radius: 8px;
        }

        .table-frame::-webkit-scrollbar-thumb {
            background: #8794ad;
            border-radius: 8px;
        }

        /* RESPONSIVE */

        @media (min-width: 1200px) {
            .main-content {
                padding: 20px 20px 20px 0;
            }
        }

        @media (max-width: 1199px) {
            .main-content {
                padding: 16px;
            }

            .dashboard-wrapper {
                padding: 18px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .dashboard-wrapper {
                padding: 14px;
                border-radius: 12px;
            }

            .dashboard-title {
                font-size: 1.5rem;
            }

            .page-description {
                font-size: 13px;
                margin-bottom: 16px;
            }

            .stats-grid {
                gap: 10px;
                margin-bottom: 14px;
            }

            .stat-card {
                min-height: 100px;
                padding: 14px;
            }

            .stat-heading {
                font-size: 0.72rem;
            }

            .stat-value {
                font-size: 1.8rem;
            }

            .dash-card {
                padding: 12px;
            }

            .card-title {
                font-size: 0.9rem;
            }

            .toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .search-wrapper {
                max-width: none;
                min-width: 0;
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .requests-table {
                min-width: 1350px;
            }

            .requests-table thead th,
            .requests-table tbody td {
                padding: 11px 10px;
            }
        }

        @media (max-width: 400px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                min-height: 85px;
            }
        }
    </style>
</head>

<body>

    <?php
    /*
     * Load the existing sidebar when this page is in the same
     * directory as sidebar.php. Otherwise, the page still works.
     */
    if (is_file(__DIR__ . '/sidebar.php')) {
        include __DIR__ . '/sidebar.php';
    }
    ?>

    <main class="main-content">

        <div class="dashboard-wrapper">

            <h1 class="dashboard-title">
                RECOVERY ASSISTANCE
            </h1>

            <p class="page-description">
                Review and manage recovery assistance requests submitted
                through the TaliResQ mobile application.
            </p>

            <!-- STATISTICS -->

            <section class="stats-grid">

                <div class="stat-card">
                    <h2 class="stat-heading">
                        <i class="bi bi-inbox stat-icon"></i>
                        TOTAL REQUESTS
                    </h2>

                    <p class="stat-value">
                        <?= $totalRequests ?>
                    </p>
                </div>

                <div class="stat-card">
                    <h2 class="stat-heading">
                        <i class="bi bi-hourglass-split stat-icon"></i>
                        PENDING REQUESTS
                    </h2>

                    <p class="stat-value">
                        <?= $pendingRequests ?>
                    </p>
                </div>

                <div class="stat-card">
                    <h2 class="stat-heading">
                        <i class="bi bi-check-circle stat-icon"></i>
                        COMPLETED
                    </h2>

                    <p class="stat-value">
                        <?= $completedRequests ?>
                    </p>
                </div>

                <div class="stat-card">
                    <h2 class="stat-heading">
                        <i class="bi bi-info-circle stat-icon"></i>
                        NEEDS INFORMATION
                    </h2>

                    <p class="stat-value">
                        <?= $needsAttention ?>
                    </p>
                </div>

            </section>

            <!-- REQUEST MANAGEMENT -->

            <section class="dash-card">

                <div class="card-header-flex">

                    <h2 class="card-title">
                        <i class="bi bi-clipboard-data me-2"></i>
                        ASSISTANCE REQUEST RECORDS
                    </h2>

                    <span class="request-count">
                        <?= $totalRequests ?>
                        <?= $totalRequests === 1 ? 'request' : 'requests' ?>
                        recorded
                    </span>

                </div>

                <?php if (
                    isset($_GET['updated']) &&
                    $_GET['updated'] === '1'
                ): ?>

                    <div class="alert alert-success py-2" role="status">
                        <i class="bi bi-check-circle-fill"></i>
                        Request updated successfully!
                    </div>

                <?php endif; ?>

                <!-- SEARCH AND FILTER -->

                <div class="toolbar">

                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>

                        <input
                            type="search"
                            id="requestSearch"
                            class="search-input"
                            placeholder="Search category, barangay, address..."
                            aria-label="Search assistance requests"
                        >
                    </div>

                    <select
                        id="statusFilter"
                        class="filter-select"
                        aria-label="Filter requests by status"
                    >
                        <option value="">All Statuses</option>

                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= escapeHtml($value) ?>">
                                <?= escapeHtml($label) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- TABLE -->

                <div class="table-frame">

                    <table class="requests-table">

                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Barangay</th>
                                <th>Address</th>
                                <th>Description</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Admin Notes</th>
                                <th>Date Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="requestTableBody">

                            <?php if (empty($requests)): ?>

                                <tr class="empty-row">
                                    <td colspan="9" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <strong>No assistance requests found.</strong>
                                        Requests submitted through the mobile
                                        app will appear here.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($requests as $request): ?>

                                    <?php
                                    $formId = 'update-' . $request['id'];
                                    $currentStatus = $request['status'] ?? '';
                                    ?>

                                    <tr
                                        data-status="<?= escapeHtml($currentStatus) ?>"
                                    >

                                        <td class="category-cell">
                                            <?= escapeHtml($request['request_category']) ?>
                                        </td>

                                        <td class="barangay-cell">
                                            <?= escapeHtml($request['barangay']) ?>
                                        </td>

                                        <td class="address-cell">
                                            <?= escapeHtml($request['address']) ?>
                                        </td>

                                        <td class="description-cell">
                                            <?= escapeHtml($request['description']) ?>
                                        </td>

                                        <td class="contact-cell">
                                            <?= escapeHtml($request['contact_number']) ?>
                                        </td>

                                        <td class="status-cell">

                                            <div style="margin-bottom: 10px;">
                                                <span class="status-badge <?= escapeHtml(getStatusClass($currentStatus)) ?>">
                                                    <?= escapeHtml($statuses[$currentStatus] ?? 'Unknown Status') ?>
                                                </span>
                                            </div>

                                            <form
                                                id="<?= escapeHtml($formId) ?>"
                                                class="request-form"
                                                method="POST"
                                                action="recovery-requests.php"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escapeHtml($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= escapeHtml($request['id']) ?>"
                                                >

                                                <label
                                                    class="field-label"
                                                    for="status-<?= escapeHtml($request['id']) ?>"
                                                >
                                                    Update Status
                                                </label>

                                                <select
                                                    id="status-<?= escapeHtml($request['id']) ?>"
                                                    name="status"
                                                    required
                                                >

                                                    <?php foreach ($statuses as $value => $label): ?>

                                                        <option
                                                            value="<?= escapeHtml($value) ?>"
                                                            <?= $currentStatus === $value ? 'selected' : '' ?>
                                                        >
                                                            <?= escapeHtml($label) ?>
                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                            </form>

                                        </td>

                                        <td class="notes-cell">

                                            <label
                                                class="field-label"
                                                for="notes-<?= escapeHtml($request['id']) ?>"
                                                style="display:block; margin-bottom:8px;"
                                            >
                                                Administrative Notes
                                            </label>

                                            <textarea
                                                id="notes-<?= escapeHtml($request['id']) ?>"
                                                name="admin_notes"
                                                form="<?= escapeHtml($formId) ?>"
                                                maxlength="5000"
                                                placeholder="Enter administrative notes..."
                                            ><?= escapeHtml($request['admin_notes']) ?></textarea>

                                        </td>

                                        <td class="date-cell">
                                            <?php
                                            $timestamp = strtotime(
                                                $request['created_at'] ?? ''
                                            );

                                            echo $timestamp !== false
                                                ? escapeHtml(date('M d, Y', $timestamp))
                                                    . '<br><small style="color:#7a8290;">'
                                                    . escapeHtml(date('h:i A', $timestamp))
                                                    . '</small>'
                                                : escapeHtml($request['created_at']);
                                            ?>
                                        </td>

                                        <td class="action-cell">

                                            <button
                                                type="submit"
                                                form="<?= escapeHtml($formId) ?>"
                                                class="save-button"
                                            >
                                                <i class="bi bi-check2-circle"></i>
                                                Save Changes
                                            </button>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                <tr id="noSearchResults" hidden>
                                    <td colspan="9" class="empty-state">
                                        <i class="bi bi-search"></i>
                                        <strong>No matching requests found.</strong>
                                        Try a different keyword or status.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <div class="table-footer">

                    <span>
                        <i class="bi bi-shield-check me-1"></i>
                        TAALERT Recovery Request Management
                    </span>

                    <span id="visibleCount">
                        Showing <?= $totalRequests ?> requests
                    </span>

                </div>

            </section>

        </div>

    </main>

    <!-- SEARCH AND STATUS FILTER -->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('requestSearch');
            const statusFilter = document.getElementById('statusFilter');
            const tableBody = document.getElementById('requestTableBody');
            const visibleCount = document.getElementById('visibleCount');
            const noResults = document.getElementById('noSearchResults');

            if (!searchInput || !statusFilter || !tableBody) {
                return;
            }

            function filterRequests() {
                const keyword = searchInput.value
                    .trim()
                    .toLowerCase();

                const selectedStatus = statusFilter.value;

                const rows = Array.from(
                    tableBody.querySelectorAll('tr[data-status]')
                );

                let visible = 0;

                rows.forEach(function (row) {
                    const rowText = row.textContent.toLowerCase();
                    const rowStatus = row.dataset.status;

                    const matchesKeyword = rowText.includes(keyword);

                    const matchesStatus =
                        selectedStatus === '' ||
                        rowStatus === selectedStatus;

                    const show =
                        matchesKeyword && matchesStatus;

                    row.hidden = !show;

                    if (show) {
                        visible++;
                    }
                });

                if (noResults) {
                    noResults.hidden =
                        rows.length === 0 || visible > 0;
                }

                if (visibleCount) {
                    visibleCount.textContent =
                        'Showing ' + visible + ' of ' +
                        rows.length + ' requests';
                }
            }

            searchInput.addEventListener('input', filterRequests);

            statusFilter.addEventListener('change', filterRequests);
        });
    </script>

</body>
</html>
