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
    $requestId = trim($_POST['request_id'] ?? '');
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
        $requestId
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
             WHERE request_id = :request_id'
        );

        $stmt->execute([
            'status' => $status,
            'admin_notes' => $notes,
            'request_id' => $requestId,
        ]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare(
                'SELECT 1
                 FROM public.assistance_requests
                 WHERE request_id = :request_id'
            );

            $check->execute(['request_id' => $requestId]);

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
        'SELECT request_id, request_category, barangay, address,
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
$inProgressRequests = $statusCounts['under_review']
    + $statusCounts['verified']
    + $statusCounts['assistance_coordinated'];
$completedRequests = $statusCounts['completed'];
$needsAttention = $statusCounts['needs_more_information'];
$rejectedRequests = $statusCounts['rejected'];

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
        content="width=device-width, initial-scale=1.0">

    <title>Recovery Assistance Requests | TAALERT</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Existing dashboard stylesheet -->
    <link rel="stylesheet" href="assets/style.css?v=20261114i">
    <link rel="icon" type="image/png" href="assets/log.png">

    <style>
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

        .dashboard-title,
        .stat-heading,
        .stat-value,
        .card-title,
        .requests-table thead th {
            font-family: var(--font-main);
        }

        .dashboard-title {
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .stats-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .stat-card {
            min-height: 104px;
            padding: 17px 19px;
        }

        .stat-heading {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            line-height: 1.4;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 750;
        }

        .stat-caption {
            color: rgba(255, 255, 255, 0.72);
            font-size: 11px;
            line-height: 1.4;
            margin-top: 6px;
        }

        .toolbar {
            display: grid;
            grid-template-columns: minmax(220px, 1.5fr) repeat(2, minmax(160px, 0.8fr)) auto;
            align-items: center;
        }

        .clear-filter-button,
        .pagination-button {
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font: inherit;
            font-size: 13px;
            font-weight: 650;
            padding: 10px 13px;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .clear-filter-button:hover,
        .pagination-button:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .clear-filter-button:focus-visible,
        .pagination-button:focus-visible,
        .filter-select:focus-visible,
        .search-input:focus-visible,
        .page-size-select:focus-visible {
            outline: 3px solid var(--accent-yellow);
            outline-offset: 2px;
        }

        .request-list {
            display: grid;
            gap: 12px;
        }

        .request-card {
            min-width: 0;
            padding: 17px;
            border: 1px solid #e0e5ee;
            border-radius: 11px;
            background: #fff;
            color: #252d3b;
            box-shadow: 0 2px 8px rgba(10, 28, 62, 0.08);
        }

        .request-card[hidden],
        .empty-state[hidden] {
            display: none;
        }

        .request-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .request-category {
            margin: 0;
            color: var(--primary-blue);
            font-size: 17px;
            font-weight: 750;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .request-submitted {
            margin: 5px 0 0;
            color: #687386;
            font-size: 12px;
        }

        .request-summary-grid {
            display: grid;
            grid-template-columns: minmax(220px, 0.8fr) minmax(0, 1.4fr);
            gap: 12px;
        }

        .request-detail {
            min-width: 0;
            padding: 12px 14px;
            border-radius: 9px;
            background: #f5f7fb;
        }

        .request-detail-label,
        .editor-field>span {
            display: block;
            margin-bottom: 6px;
            color: #667287;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .request-detail p {
            margin: 0;
            color: #303949;
            font-size: 13px;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .request-detail p+.request-detail-label,
        .request-detail .request-contact {
            margin-top: 10px;
        }

        .request-description {
            white-space: pre-wrap;
        }

        .request-contact {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #164b98;
            font-size: 13px;
            font-weight: 650;
            text-decoration: none;
        }

        .request-contact:hover {
            text-decoration: underline;
        }

        .request-admin-note {
            display: -webkit-box;
            margin: 10px 0 0 !important;
            overflow: hidden;
            color: #536077 !important;
            font-size: 12px !important;
            line-height: 1.45 !important;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .request-admin-note strong {
            color: #33425d;
        }

        .request-editor {
            margin-top: 13px;
            border-top: 1px solid #e7eaf0;
        }

        .request-editor summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 2px 0;
            color: var(--primary-blue);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            list-style: none;
        }

        .request-editor summary::-webkit-details-marker {
            display: none;
        }

        .request-editor summary::after {
            content: "Edit";
            color: #667287;
            font-size: 11px;
            font-weight: 600;
        }

        .request-editor[open] summary::after {
            content: "Close";
        }

        .request-editor summary:focus-visible {
            outline: 3px solid #f5b041;
            outline-offset: 3px;
        }

        .request-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 12px;
            padding-top: 14px;
        }

        .request-form-fields {
            display: grid;
            grid-template-columns: minmax(180px, 0.8fr) minmax(0, 1.2fr);
            gap: 12px;
        }

        .editor-field {
            display: block;
            min-width: 0;
            margin: 0;
        }

        .editor-field select,
        .editor-field textarea {
            width: 100%;
            min-width: 0;
            border: 1px solid #d5dce7;
            border-radius: 8px;
            background: #fff;
            color: #303744;
            padding: 9px 10px;
            font: inherit;
            font-size: 13px;
        }

        .editor-field select:focus,
        .editor-field textarea:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(34, 56, 104, 0.12);
            outline: none;
        }

        .editor-field textarea {
            min-height: 72px;
            resize: vertical;
        }

        .save-button {
            width: auto;
            min-width: 142px;
            min-height: 40px;
        }

        .empty-state {
            padding: 34px 18px;
            border: 1px dashed rgba(255, 255, 255, 0.35);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.82);
            text-align: center;
        }

        .empty-state i {
            display: block;
            margin-bottom: 9px;
            color: var(--accent-yellow);
            font-size: 25px;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 5px;
            color: #fff;
        }

        .request-list .empty-state {
            padding: 34px 18px !important;
            border: 1px dashed rgba(255, 255, 255, 0.35);
            color: rgba(255, 255, 255, 0.82) !important;
        }

        .request-list .empty-state strong {
            color: #fff !important;
        }

        .table-footer {
            align-items: center;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .page-size-label {
            display: flex;
            align-items: center;
            gap: 7px;
            color: rgba(255, 255, 255, 0.82);
        }

        .page-size-select {
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 7px;
            background: var(--card-blue);
            color: #fff;
            font: inherit;
            padding: 7px 9px;
        }

        .pagination-button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .page-indicator {
            min-width: 75px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .toolbar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .search-wrapper {
                grid-column: 1 / -1;
            }

            .request-summary-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .request-form-fields {
                grid-template-columns: minmax(0, 1fr);
            }

            .request-form {
                grid-template-columns: minmax(0, 1fr);
            }

            .save-button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .toolbar {
                grid-template-columns: minmax(0, 1fr);
            }

            .search-wrapper {
                grid-column: auto;
            }

            .clear-filter-button {
                width: 100%;
            }

            .request-card {
                padding: 13px;
            }

            .request-card-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .pagination-controls {
                justify-content: flex-start;
            }
        }
    </style>
</head>

<body>

    <?php
    if (is_file(__DIR__ . '/sidebar.php')) {
        include __DIR__ . '/sidebar.php';
    }
    ?>

    <main class="main-content">

        <div class="dashboard-wrapper">

            <h1 class="dashboard-title admin-page-title">
                RECOVERY ASSISTANCE
            </h1>

            <p class="page-description">
                Review and manage recovery assistance requests submitted
                through the TaliResQ mobile application.
            </p>

            <!-- STATISTICS -->

            <section class="stats-grid" aria-label="Recovery request summary">

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
                        <i class="bi bi-arrow-repeat stat-icon"></i>
                        IN PROGRESS
                    </h2>

                    <p class="stat-value">
                        <?= $inProgressRequests ?>
                    </p>
                    <span class="stat-caption">Under review, verified, or coordinated</span>
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

                <div class="stat-card">
                    <h2 class="stat-heading">
                        <i class="bi bi-x-circle stat-icon"></i>
                        REJECTED
                    </h2>

                    <p class="stat-value">
                        <?= $rejectedRequests ?>
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
                            placeholder="Search category, location, contact, notes..."
                            aria-label="Search all request details">
                    </div>

                    <select
                        id="statusFilter"
                        class="filter-select"
                        aria-label="Filter requests by status">
                        <option value="">All Statuses</option>

                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= escapeHtml($value) ?>">
                                <?= escapeHtml($label) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <select
                        id="sortOrder"
                        class="filter-select"
                        aria-label="Sort requests">
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>

                    <button
                        type="button"
                        id="clearFilters"
                        class="clear-filter-button">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Clear filters
                    </button>

                </div>

                <div class="request-list" id="requestList" aria-label="Assistance requests">

                    <?php foreach ($requests as $request): ?>

                        <?php
                        $requestId = (string) $request['request_id'];
                        $formId = 'update-' . $requestId;
                        $currentStatus = $request['status'] ?? '';
                        $timestamp = strtotime((string) ($request['created_at'] ?? ''));
                        $submittedDate = $timestamp !== false
                            ? date('M d, Y \a\t h:i A', $timestamp)
                            : (string) ($request['created_at'] ?? '');
                        $adminNote = trim((string) ($request['admin_notes'] ?? ''));
                        $searchText = implode(' ', [
                            $requestId,
                            $request['request_category'] ?? '',
                            $request['barangay'] ?? '',
                            $request['address'] ?? '',
                            $request['description'] ?? '',
                            $request['contact_number'] ?? '',
                            $statuses[$currentStatus] ?? $currentStatus,
                            $request['admin_notes'] ?? '',
                            $request['created_at'] ?? '',
                        ]);
                        $phoneNumber = preg_replace(
                            '/[^0-9+]/',
                            '',
                            (string) ($request['contact_number'] ?? '')
                        );
                        ?>

                        <article
                            class="request-card"
                            data-status="<?= escapeHtml($currentStatus) ?>"
                            data-created-at="<?= $timestamp !== false ? (int) $timestamp : 0 ?>"
                            data-search="<?= escapeHtml($searchText) ?>">
                            <header class="request-card-header">
                                <div>
                                    <h3 class="request-category">
                                        <?= escapeHtml($request['request_category'] ?? 'Assistance request') ?>
                                    </h3>
                                    <p class="request-submitted">
                                        <i class="bi bi-clock me-1" aria-hidden="true"></i>
                                        Submitted <?= escapeHtml($submittedDate) ?>
                                    </p>
                                </div>

                                <span class="status-badge <?= escapeHtml(getStatusClass($currentStatus)) ?>">
                                    <?= escapeHtml($statuses[$currentStatus] ?? 'Unknown Status') ?>
                                </span>
                            </header>

                            <div class="request-summary-grid">
                                <section class="request-detail" aria-label="Request location">
                                    <span class="request-detail-label">Location</span>
                                    <p><strong><?= escapeHtml($request['barangay'] ?? 'Barangay not provided') ?></strong></p>
                                    <p><?= escapeHtml($request['address'] ?? 'Address not provided') ?></p>
                                </section>

                                <section class="request-detail" aria-label="Request details">
                                    <span class="request-detail-label">What is needed</span>
                                    <p class="request-description"><?= escapeHtml($request['description'] ?? 'No description provided.') ?></p>

                                    <?php if ($phoneNumber !== ''): ?>
                                        <a
                                            class="request-contact"
                                            href="tel:<?= escapeHtml($phoneNumber) ?>">
                                            <i class="bi bi-telephone" aria-hidden="true"></i>
                                            <?= escapeHtml($request['contact_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <p class="request-contact">No contact number provided</p>
                                    <?php endif; ?>

                                    <?php if ($adminNote !== ''): ?>
                                        <p class="request-admin-note">
                                            <strong>Admin note:</strong>
                                            <?= escapeHtml($adminNote) ?>
                                        </p>
                                    <?php endif; ?>
                                </section>
                            </div>

                            <details class="request-editor">
                                <summary>
                                    <span><i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Update status and notes</span>
                                </summary>

                                <form
                                    id="<?= escapeHtml($formId) ?>"
                                    class="request-form"
                                    method="POST"
                                    action="recovery-requests.php">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= escapeHtml($requestId) ?>">

                                    <div class="request-form-fields">
                                        <label class="editor-field" for="status-<?= escapeHtml($requestId) ?>">
                                            <span>Request status</span>
                                            <select
                                                id="status-<?= escapeHtml($requestId) ?>"
                                                name="status"
                                                required>
                                                <?php foreach ($statuses as $value => $label): ?>
                                                    <option
                                                        value="<?= escapeHtml($value) ?>"
                                                        <?= $currentStatus === $value ? 'selected' : '' ?>>
                                                        <?= escapeHtml($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>

                                        <label class="editor-field" for="notes-<?= escapeHtml($requestId) ?>">
                                            <span>Administrative notes</span>
                                            <textarea
                                                id="notes-<?= escapeHtml($requestId) ?>"
                                                name="admin_notes"
                                                maxlength="5000"
                                                placeholder="Add follow-up details or coordination notes..."><?= escapeHtml($request['admin_notes'] ?? '') ?></textarea>
                                        </label>
                                    </div>

                                    <button type="submit" class="save-button">
                                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                        Save changes
                                    </button>
                                </form>
                            </details>
                        </article>

                    <?php endforeach; ?>

                    <div id="noRequestData" class="empty-state" <?= $totalRequests > 0 ? 'hidden' : '' ?>>
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <strong>No assistance requests yet.</strong>
                        Requests submitted through the mobile app will appear here.
                    </div>

                    <div id="noMatchingRequests" class="empty-state" hidden>
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <strong>No matching requests found.</strong>
                        Try another search or clear the selected filters.
                    </div>

                </div>

                <div class="table-footer">

                    <span>
                        <i class="bi bi-shield-check me-1"></i>
                        TAALERT Recovery Request Management
                    </span>

                    <div class="pagination-controls">
                        <span id="visibleCount" aria-live="polite">
                            Showing <?= $totalRequests ?> requests
                        </span>

                        <label class="page-size-label" for="pageSize">
                            Per page
                            <select id="pageSize" class="page-size-select" aria-label="Requests per page">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="all">All</option>
                            </select>
                        </label>

                        <button type="button" id="previousPage" class="pagination-button" aria-label="Previous page">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </button>
                        <span id="pageIndicator" class="page-indicator">Page 1 of 1</span>
                        <button type="button" id="nextPage" class="pagination-button" aria-label="Next page">
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>

                </div>

            </section>

        </div>

    </main>

    <!-- REQUEST SEARCH, FILTER, AND PAGINATION -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('requestSearch');
            const statusFilter = document.getElementById('statusFilter');
            const sortOrder = document.getElementById('sortOrder');
            const pageSize = document.getElementById('pageSize');
            const requestList = document.getElementById('requestList');
            const visibleCount = document.getElementById('visibleCount');
            const pageIndicator = document.getElementById('pageIndicator');
            const previousPage = document.getElementById('previousPage');
            const nextPage = document.getElementById('nextPage');
            const clearFilters = document.getElementById('clearFilters');
            const noRequestData = document.getElementById('noRequestData');
            const noMatchingRequests = document.getElementById('noMatchingRequests');

            if (
                !searchInput ||
                !statusFilter ||
                !sortOrder ||
                !pageSize ||
                !requestList
            ) {
                return;
            }

            const cards = Array.from(
                requestList.querySelectorAll('.request-card')
            );
            let currentPage = 1;

            function renderRequests() {
                const keyword = searchInput.value.trim().toLocaleLowerCase();
                const selectedStatus = statusFilter.value;
                const matchingCards = cards.filter(function(card) {
                    return (
                        card.dataset.search.toLocaleLowerCase().includes(keyword) &&
                        (selectedStatus === '' ||
                            card.dataset.status === selectedStatus)
                    );
                });

                matchingCards.sort(function(first, second) {
                    const firstDate = Number(first.dataset.createdAt) || 0;
                    const secondDate = Number(second.dataset.createdAt) || 0;
                    return sortOrder.value === 'oldest' ?
                        firstDate - secondDate :
                        secondDate - firstDate;
                });

                const selectedPageSize = pageSize.value;
                const rowsPerPage = selectedPageSize === 'all' ?
                    Math.max(matchingCards.length, 1) :
                    Math.max(Number.parseInt(selectedPageSize, 10) || 10, 1);
                const pageCount = Math.max(
                    Math.ceil(matchingCards.length / rowsPerPage),
                    1
                );
                currentPage = Math.min(currentPage, pageCount);

                const startIndex = (currentPage - 1) * rowsPerPage;
                const pageCards = new Set(
                    matchingCards.slice(startIndex, startIndex + rowsPerPage)
                );

                cards.forEach(function(card) {
                    card.hidden = !pageCards.has(card);
                });

                if (noRequestData) {
                    noRequestData.hidden = cards.length > 0;
                }

                if (noMatchingRequests) {
                    noMatchingRequests.hidden =
                        cards.length === 0 || matchingCards.length > 0;
                }

                if (visibleCount) {
                    if (matchingCards.length === 0) {
                        visibleCount.textContent = cards.length === 0 ?
                            'No requests to display' :
                            'No requests match the current filters';
                    } else {
                        const lastIndex = Math.min(
                            startIndex + rowsPerPage,
                            matchingCards.length
                        );
                        visibleCount.textContent =
                            'Showing ' + (startIndex + 1) + '–' + lastIndex +
                            ' of ' + matchingCards.length + ' matching requests';
                    }
                }

                if (pageIndicator) {
                    pageIndicator.textContent =
                        'Page ' + currentPage + ' of ' + pageCount;
                }

                if (previousPage) {
                    previousPage.disabled =
                        currentPage <= 1 || matchingCards.length === 0;
                }

                if (nextPage) {
                    nextPage.disabled =
                        currentPage >= pageCount || matchingCards.length === 0;
                }
            }

            searchInput.addEventListener('input', function() {
                currentPage = 1;
                renderRequests();
            });

            statusFilter.addEventListener('change', function() {
                currentPage = 1;
                renderRequests();
            });

            sortOrder.addEventListener('change', function() {
                currentPage = 1;
                renderRequests();
            });

            pageSize.addEventListener('change', function() {
                currentPage = 1;
                renderRequests();
            });

            previousPage.addEventListener('click', function() {
                currentPage = Math.max(currentPage - 1, 1);
                renderRequests();
            });

            nextPage.addEventListener('click', function() {
                currentPage += 1;
                renderRequests();
            });

            clearFilters.addEventListener('click', function() {
                searchInput.value = '';
                statusFilter.value = '';
                sortOrder.value = 'newest';
                pageSize.value = '10';
                currentPage = 1;
                renderRequests();
                searchInput.focus();
            });

            renderRequests();
        });
    </script>

</body>

</html>