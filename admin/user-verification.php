
<?php
/*
|--------------------------------------------------------------------------
| TALIRESQ - RESIDENT VERIFICATION
|--------------------------------------------------------------------------
| PHP 7.4+ | Supabase PostgreSQL | Existing admin session/sidebar
| Private Supabase Storage ID preview using temporary signed URLs.
| No database schema changes.
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/db.php';

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}


function redirectWithMessage(string $type, string $message): void
{
    $_SESSION['verification_flash'] = [
        'type' => $type,
        'message' => $message
    ];

    header('Location: user-verification.php');
    exit;
}

function statusClass(string $status): string
{
    $classes = [
        'pending' => 'status-pending',
        'verified' => 'status-verified',
        'rejected' => 'status-rejected'
    ];

    return $classes[$status] ?? 'status-unknown';
}

function accountStatusClass(string $status): string
{
    $classes = [
        'active' => 'account-active',
        'inactive' => 'account-inactive',
        'suspended' => 'account-suspended'
    ];

    return $classes[$status] ?? 'account-unknown';
}

/*
|--------------------------------------------------------------------------
| SUPABASE PRIVATE STORAGE - SIGNED URL
|--------------------------------------------------------------------------
| Creates a temporary URL for an object inside:
| resident-valid-ids
|
| The stored valid_id value should be a Storage object path, for example:
| USER_UUID/valid-id-123456.jpg
|
| Signed URL expiry: 5 minutes.
|--------------------------------------------------------------------------
*/

function createValidIdSignedUrl(?string $filePath): ?string
{
    if (empty($filePath)) {
        return null;
    }

    $supabaseUrl = rtrim((string)getenv('SUPABASE_URL'), '/');
    $serviceRoleKey = (string)getenv('SUPABASE_SERVICE_ROLE_KEY');

    if ($supabaseUrl === '' || $serviceRoleKey === '') {
        error_log('Valid ID preview: Supabase Storage environment variables are missing.');
        return null;
    }

    if (!function_exists('curl_init')) {
        error_log('Valid ID preview: PHP cURL extension is not enabled.');
        return null;
    }

    /*
     * Encode individual path segments.
     * This preserves "/" between the user's folder and filename.
     */
    $segments = explode('/', trim($filePath, '/'));

    if (empty($segments) || in_array('', $segments, true)) {
        error_log('Valid ID preview: Invalid Storage object path.');
        return null;
    }

    $encodedPath = implode('/', array_map('rawurlencode', $segments));

    $endpoint = $supabaseUrl
        . '/storage/v1/object/sign/resident-valid-ids/'
        . $encodedPath;

    $payload = json_encode([
        'expiresIn' => 300
    ]);

    $ch = curl_init($endpoint);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $serviceRoleKey,
            'Authorization: Bearer ' . $serviceRoleKey
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log('Valid ID signed URL cURL error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Valid ID signed URL request returned HTTP ' . $httpCode);
        return null;
    }

    $result = json_decode($response, true);

    if (!is_array($result) || empty($result['signedURL'])) {
        error_log('Valid ID signed URL response did not contain signedURL.');
        return null;
    }

    $signedPath = $result['signedURL'];

    // Some Supabase responses return an absolute URL.
    if (
        strpos($signedPath, 'https://') === 0 ||
        strpos($signedPath, 'http://') === 0
    ) {
        return $signedPath;
    }

    // Other responses return a relative Storage URL.
    return $supabaseUrl . '/storage/v1' . (
        strpos($signedPath, '/') === 0
            ? $signedPath
            : '/' . $signedPath
    );
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| VERIFY / REJECT RESIDENT
|--------------------------------------------------------------------------
| Only pending resident applications can be reviewed.
| Existing enum values: pending, verified, rejected.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        redirectWithMessage(
            'danger',
            'Your request could not be validated. Please try again.'
        );
    }

    $residentId = trim($_POST['resident_id'] ?? '');
    $action = $_POST['action'] ?? '';

    if (!preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        $residentId
    )) {
        redirectWithMessage('danger', 'Invalid resident ID.');
    }

    if (!in_array($action, ['verify', 'reject'], true)) {
        redirectWithMessage('danger', 'Invalid verification action.');
    }

    $newStatus = ($action === 'verify') ? 'verified' : 'rejected';

    try {
        $updateStmt = $pdo->prepare("
            UPDATE public.users
            SET verification_status = :new_status,
                updated_at = NOW()
            WHERE id = CAST(:resident_id AS UUID)
              AND role::text = 'resident'
              AND verification_status::text = 'pending'
            RETURNING fullname
        ");

        $updateStmt->execute([
            ':new_status' => $newStatus,
            ':resident_id' => $residentId
        ]);

        $updatedResident = $updateStmt->fetch();

        if (!$updatedResident) {
            redirectWithMessage(
                'warning',
                'Resident not found or this application has already been reviewed.'
            );
        }

        $residentName = $updatedResident['fullname'] ?? 'Resident';

        if ($action === 'verify') {
            redirectWithMessage(
                'success',
                $residentName . ' has been verified as a TaliResQ resident.'
            );
        }

        redirectWithMessage(
            'warning',
            $residentName . '\'s residency application has been rejected.'
        );

    } catch (PDOException $e) {
        error_log('Resident verification update failed: ' . $e->getMessage());

        redirectWithMessage(
            'danger',
            'Unable to update resident verification. Please try again.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH AND STATUS FILTER
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['status'] ?? 'all');

$allowedFilters = ['all', 'pending', 'verified', 'rejected'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

/*
|--------------------------------------------------------------------------
| RESIDENT STATISTICS
|--------------------------------------------------------------------------
*/

try {
    $countsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,

            COUNT(*) FILTER (
                WHERE verification_status::text = 'pending'
            ) AS pending,

            COUNT(*) FILTER (
                WHERE verification_status::text = 'verified'
            ) AS verified,

            COUNT(*) FILTER (
                WHERE verification_status::text = 'rejected'
            ) AS rejected

        FROM public.users
        WHERE role::text = 'resident'
    ");

    $countsRow = $countsStmt->fetch();

    $counts = [
        'total' => (int)($countsRow['total'] ?? 0),
        'pending' => (int)($countsRow['pending'] ?? 0),
        'verified' => (int)($countsRow['verified'] ?? 0),
        'rejected' => (int)($countsRow['rejected'] ?? 0)
    ];

} catch (PDOException $e) {
    error_log('Resident statistics failed: ' . $e->getMessage());
    die('Unable to retrieve resident statistics.');
}

/*
|--------------------------------------------------------------------------
| RESIDENT RECORDS
|--------------------------------------------------------------------------
*/

try {
    $sql = "
        SELECT
            id,
            fullname,
            email,
            contact_number,
            barangay,
            address,
            birthdate,
            valid_id,
            verification_status,
            status,
            created_at,
            updated_at
        FROM public.users
        WHERE role::text = 'resident'
    ";

    $params = [];

    if ($filter !== 'all') {
        $sql .= " AND verification_status::text = :verification_status";
        $params[':verification_status'] = $filter;
    }

    if ($search !== '') {
        $sql .= "
            AND (
                fullname ILIKE :search_name
                OR email ILIKE :search_email
                OR barangay ILIKE :search_barangay
                OR address ILIKE :search_address
            )
        ";

        $searchTerm = '%' . $search . '%';

        $params[':search_name'] = $searchTerm;
        $params[':search_email'] = $searchTerm;
        $params[':search_barangay'] = $searchTerm;
        $params[':search_address'] = $searchTerm;
    }

    $sql .= " ORDER BY created_at DESC";

    $residentsStmt = $pdo->prepare($sql);
    $residentsStmt->execute($params);

    $residents = $residentsStmt->fetchAll();

    /*
     * Generate signed URLs only for records currently displayed.
     * The URLs are temporary and are not saved in the database.
     */
    foreach ($residents as &$resident) {
        $resident['valid_id_url'] = !empty($resident['valid_id'])
            ? createValidIdSignedUrl($resident['valid_id'])
            : null;
    }

    unset($resident);

} catch (PDOException $e) {
    error_log('Resident records loading failed: ' . $e->getMessage());
    die('Unable to retrieve resident records. Please check the database configuration.');
}

/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$flash = $_SESSION['verification_flash'] ?? null;
unset($_SESSION['verification_flash']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Verification | TaliResQ</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <link rel="stylesheet" href="assets/style.css?v=20261114i">
    <link rel="icon" type="image/png" href="assets/log.png">

    <style>
        :root {
            --navy: #052241;
            --blue: #0d4f8b;
            --muted: #8492a3;
            --border: #edf0f4;
        }

        .verification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .dashboard-title {
            color: var(--navy);
            font-size: 25px;
            font-weight: 700;
            letter-spacing: .5px;
            margin: 0 0 6px;
        }

        .page-description {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .header-icon {
            width: 53px;
            height: 53px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--navy);
            color: #fff;
            border-radius: 13px;
            font-size: 23px;
            flex-shrink: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 23px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 19px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 3px 12px rgba(5,34,65,.035);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(5,34,65,.07);
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 19px;
            flex-shrink: 0;
        }

        .stat-icon.navy { background: #eaf0f7; color: var(--navy); }
        .stat-icon.orange { background: #fff2e7; color: #b96a28; }
        .stat-icon.green { background: #e9f5ed; color: #287747; }
        .stat-icon.red { background: #fcebea; color: #b33c32; }

        .stat-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-value {
            color: var(--navy);
            font-size: 23px;
            font-weight: 700;
            line-height: 1.2;
        }

        .dash-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 3px 12px rgba(5,34,65,.035);
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 5px;
        }

        .section-icon {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eaf1f8;
            color: var(--navy);
            border-radius: 9px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .card-title {
            color: var(--navy);
            font-size: 17px;
            font-weight: 700;
            margin: 0;
        }

        .card-description {
            color: #8793a1;
            font-size: 12px;
            margin: 0 0 23px 51px;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .history-count {
            background: #edf3f9;
            color: var(--navy);
            border-radius: 20px;
            padding: 7px 12px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .filter-area {
            display: grid;
            grid-template-columns: minmax(180px, 1.5fr) minmax(150px, .8fr) auto auto;
            gap: 10px;
            margin-bottom: 18px;
        }

        .filter-area .form-control,
        .filter-area .form-select {
            min-height: 42px;
            border: 1px solid #dce2e9;
            border-radius: 8px;
            font-size: 12px;
            color: #212529 !important;
            -webkit-text-fill-color: #212529 !important;
            background: #fff !important;
            box-shadow: none;
        }

        .filter-area .form-control::placeholder {
            color: #6c757d !important;
            -webkit-text-fill-color: #6c757d !important;
            opacity: 1;
        }

        .filter-area .form-control:focus,
        .filter-area .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(13,79,139,.08);
        }

        .filter-button {
            background: var(--navy);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 0 16px;
            font-size: 12px;
            font-weight: 600;
            min-height: 42px;
        }

        .filter-button:hover {
            background: var(--blue);
            color: #fff;
        }

        .clear-filter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #6b7b8c;
            font-size: 12px;
            text-decoration: none;
            padding: 8px 10px;
            white-space: nowrap;
        }

        .clear-filter:hover { color: var(--navy); }

        .table-responsive {
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .resident-table {
            width: 100%;
            margin: 0;
        }

        .resident-table thead th {
            background: #f6f8fb;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .45px;
            padding: 15px 13px;
            white-space: nowrap;
            border-bottom: 1px solid #e9edf2;
        }

        .resident-table tbody td {
            color: #526174;
            font-size: 12px;
            padding: 14px 13px;
            vertical-align: middle;
            border-color: #f0f2f5;
        }

        .resident-table tbody tr:hover { background: #f9fbfd; }

        .resident-avatar {
            width: 39px;
            height: 39px;
            border-radius: 50%;
            background: #eaf0f7;
            color: var(--navy);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .resident-name {
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
            min-width: 130px;
        }

        .resident-email {
            color: #8793a1;
            font-size: 11px;
            overflow-wrap: anywhere;
        }

        .resident-date {
            color: #526174;
            white-space: nowrap;
            font-size: 11px;
        }

        .status-badge,
        .account-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
            text-transform: capitalize;
        }

        .status-pending { background: #fff2d9; color: #986817; }
        .status-verified { background: #e8f5ed; color: #287747; }
        .status-rejected { background: #fcebea; color: #b33c32; }
        .status-unknown { background: #f0f1f3; color: #687381; }

        .account-active { background: #e8f5ed; color: #287747; }
        .account-inactive { background: #f0f1f3; color: #687381; }
        .account-suspended { background: #fcebea; color: #b33c32; }
        .account-unknown { background: #f0f1f3; color: #687381; }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .action-buttons form { margin: 0; }

        .action-buttons .btn {
            width: 31px;
            height: 31px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 7px;
            font-size: 12px;
        }

        .btn-view {
            color: var(--blue);
            border: 1px solid #cbd9e8;
            background: #fff;
        }

        .btn-view:hover {
            color: #fff;
            background: var(--blue);
            border-color: var(--blue);
        }

        .btn-verify {
            color: #fff;
            background: #287747;
            border: 1px solid #287747;
        }

        .btn-verify:hover { background: #20643b; color: #fff; }

        .btn-reject {
            color: #b33c32;
            background: #fff;
            border: 1px solid #e8c4c1;
        }

        .btn-reject:hover {
            color: #fff;
            background: #b33c32;
            border-color: #b33c32;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px !important;
        }

        .empty-icon {
            width: 53px;
            height: 53px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: #f0f4f8;
            color: #7890a8;
            font-size: 22px;
        }

        .empty-state strong {
            display: block;
            color: #34495e;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .empty-state span {
            color: #94a3b8;
            font-size: 11px;
        }

        .alert-dismissible { border-radius: 9px; font-size: 13px; }

        .detail-label {
            color: #8793a1;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .detail-value {
            color: var(--navy);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 17px;
            overflow-wrap: anywhere;
        }

        .modal-content {
            border: 0;
            border-radius: 13px;
            overflow: hidden;
        }

        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            padding: 19px 22px;
        }

        .modal-title {
            color: var(--navy);
            font-size: 17px;
        }

        .modal-body { padding: 23px; }

        .valid-id-box {
            background: #f6f8fb;
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 15px;
            color: #64748b;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .valid-id-preview-wrapper {
            margin-top: 12px;
            text-align: center;
        }

        .valid-id-preview {
            display: block;
            width: 100%;
            max-height: 420px;
            object-fit: contain;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 8px;
        }

        .valid-id-actions {
            display: flex;
            justify-content: center;
            margin-top: 13px;
        }

        .valid-id-open {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--navy);
            color: #fff;
            padding: 9px 15px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        .valid-id-open:hover {
            background: var(--blue);
            color: #fff;
        }

        .valid-id-error {
            color: #b33c32;
            font-size: 12px;
            margin-top: 10px;
        }

        @media(max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-area { grid-template-columns: 1fr 1fr; }
        }

        @media(max-width: 992px) {
            .main-content { margin-left: 0; padding: 18px; }
        }

        @media(max-width: 650px) {
            .verification-header { align-items: flex-start; }
            .dashboard-title { font-size: 21px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 13px; gap: 9px; }
            .stat-icon { width: 36px; height: 36px; font-size: 16px; }
            .stat-value { font-size: 20px; }
            .stat-label { font-size: 10px; }
            .dash-card { padding: 17px; }
            .card-description { margin-left: 0; margin-top: 9px; }
            .history-header { align-items: flex-start; flex-direction: column; }
            .filter-area { grid-template-columns: 1fr; }
            .filter-button { min-height: 42px; }
            .resident-table { min-width: 980px; }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="dashboard-wrapper">

        <!-- PAGE HEADER -->
        <div class="verification-header">
            <div>
                <h2 class="dashboard-title admin-page-title">USER VERIFICATION</h2>
                <p class="page-description">
                    Review resident registration details and verify residency applications.
                </p>
            </div>

            <div class="header-icon">
                <i class="bi bi-person-check-fill"></i>
            </div>
        </div>

        <!-- FLASH MESSAGE -->
        <?php if ($flash): ?>
            <div
                class="alert alert-<?= e($flash['type']); ?> alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-<?=
                    $flash['type'] === 'success'
                        ? 'check-circle-fill'
                        : 'exclamation-circle-fill';
                ?> me-2"></i>

                <?= e($flash['message']); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>
        <?php endif; ?>

        <!-- STATISTICS -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon navy">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Total Residents</div>
                    <div class="stat-value"><?= (int)$counts['total']; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="stat-label">Pending Review</div>
                    <div class="stat-value"><?= (int)$counts['pending']; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Verified</div>
                    <div class="stat-value"><?= (int)$counts['verified']; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-label">Rejected</div>
                    <div class="stat-value"><?= (int)$counts['rejected']; ?></div>
                </div>
            </div>

        </div>

        <!-- RESIDENT TABLE -->
        <div class="dash-card">

            <div class="history-header">
                <div>
                    <div class="section-heading">
                        <div class="section-icon">
                            <i class="bi bi-person-lines-fill"></i>
                        </div>

                        <h3 class="card-title">Registered Residents</h3>
                    </div>

                    <p class="card-description">
                        Review resident information, uploaded valid IDs, and verification status.
                    </p>
                </div>

                <span class="history-count">
                    <i class="bi bi-people me-1"></i>
                    <?= count($residents); ?> matching residents
                </span>
            </div>

            <!-- SEARCH AND FILTER -->
            <form
                method="GET"
                action="user-verification.php"
                class="filter-area"
            >
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    placeholder="Search name, email, barangay, or address..."
                    value="<?= e($search); ?>"
                >

                <select name="status" class="form-select">
                    <option value="all" <?= $filter === 'all' ? 'selected' : ''; ?>>
                        All Statuses
                    </option>

                    <option value="pending" <?= $filter === 'pending' ? 'selected' : ''; ?>>
                        Pending
                    </option>

                    <option value="verified" <?= $filter === 'verified' ? 'selected' : ''; ?>>
                        Verified
                    </option>

                    <option value="rejected" <?= $filter === 'rejected' ? 'selected' : ''; ?>>
                        Rejected
                    </option>
                </select>

                <button type="submit" class="filter-button">
                    <i class="bi bi-search me-1"></i>Filter
                </button>

                <?php if ($search !== '' || $filter !== 'all'): ?>
                    <a href="user-verification.php" class="clear-filter">
                        <i class="bi bi-x-circle me-1"></i>Clear filters
                    </a>
                <?php endif; ?>
            </form>

            <!-- TABLE -->
            <div class="table-responsive">
                <table class="table table-hover resident-table">

                    <thead>
                        <tr>
                            <th>Resident</th>
                            <th>Contact</th>
                            <th>Barangay</th>
                            <th>Registration Date</th>
                            <th>Verification</th>
                            <th>Account</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($residents)): ?>

                        <?php foreach ($residents as $resident): ?>

                            <?php
                                $name = $resident['fullname'] ?? 'Unknown Resident';

                                $initial = function_exists('mb_substr')
                                    ? mb_strtoupper(
                                        mb_substr($name, 0, 1, 'UTF-8'),
                                        'UTF-8'
                                    )
                                    : strtoupper(substr($name, 0, 1));

                                $verification = $resident['verification_status'] ?? 'pending';
                                $accountStatus = $resident['status'] ?? 'unknown';

                                $createdTimestamp = !empty($resident['created_at'])
                                    ? strtotime($resident['created_at'])
                                    : false;

                                $residentJson = json_encode(
                                    $resident,
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_TAG |
                                    JSON_HEX_AMP
                                );
                            ?>

                            <tr>

                                <!-- RESIDENT -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">

                                        <span class="resident-avatar">
                                            <?= e($initial); ?>
                                        </span>

                                        <div>
                                            <div class="resident-name">
                                                <?= e($name); ?>
                                            </div>

                                            <div class="resident-email">
                                                <?= e($resident['email'] ?? ''); ?>
                                            </div>
                                        </div>

                                    </div>
                                </td>

                                <!-- CONTACT -->
                                <td>
                                    <?= e($resident['contact_number'] ?? 'N/A'); ?>
                                </td>

                                <!-- BARANGAY -->
                                <td>
                                    <i class="bi bi-geo-alt me-1"></i>
                                    <?= e($resident['barangay'] ?? 'N/A'); ?>
                                </td>

                                <!-- REGISTRATION DATE -->
                                <td class="resident-date">
                                    <?= $createdTimestamp
                                        ? e(date('M d, Y', $createdTimestamp))
                                        : 'N/A';
                                    ?>
                                </td>

                                <!-- VERIFICATION STATUS -->
                                <td>
                                    <span class="status-badge <?= e(statusClass($verification)); ?>">

                                        <?php if ($verification === 'verified'): ?>
                                            <i class="bi bi-check-circle-fill"></i>

                                        <?php elseif ($verification === 'rejected'): ?>
                                            <i class="bi bi-x-circle-fill"></i>

                                        <?php else: ?>
                                            <i class="bi bi-clock-fill"></i>
                                        <?php endif; ?>

                                        <?= e(ucfirst($verification)); ?>

                                    </span>
                                </td>

                                <!-- ACCOUNT STATUS -->
                                <td>
                                    <span class="account-badge <?= e(accountStatusClass($accountStatus)); ?>">
                                        <?= e(ucfirst($accountStatus)); ?>
                                    </span>
                                </td>

                                <!-- ACTIONS -->
                                <td>
                                    <div class="action-buttons">

                                        <!-- VIEW -->
                                        <button
                                            type="button"
                                            class="btn btn-view"
                                            data-bs-toggle="modal"
                                            data-bs-target="#residentModal"
                                            data-resident="<?= e($residentJson ?: '{}'); ?>"
                                            title="View resident details"
                                            aria-label="View resident details"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if ($verification === 'pending'): ?>

                                            <!-- VERIFY -->
                                            <form
                                                method="POST"
                                                action="user-verification.php"
                                                onsubmit="return confirm('Verify this resident?');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="resident_id"
                                                    value="<?= e($resident['id']); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="verify"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-verify"
                                                    title="Verify resident"
                                                    aria-label="Verify resident"
                                                >
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>

                                            <!-- REJECT -->
                                            <form
                                                method="POST"
                                                action="user-verification.php"
                                                onsubmit="return confirm('Reject this residency application?');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="resident_id"
                                                    value="<?= e($resident['id']); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-reject"
                                                    title="Reject application"
                                                    aria-label="Reject application"
                                                >
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>

                                        <?php endif; ?>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty-state">

                                <div class="empty-icon">
                                    <i class="bi bi-people"></i>
                                </div>

                                <strong>
                                    <?= ($search !== '' || $filter !== 'all')
                                        ? 'No matching residents found.'
                                        : 'No resident records available yet.';
                                    ?>
                                </strong>

                                <span>
                                    <?= ($search !== '' || $filter !== 'all')
                                        ? 'Try changing your search term or status filter.'
                                        : 'Resident registrations will appear here.';
                                    ?>
                                </span>

                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div class="text-secondary small mt-3">
                Showing <?= count($residents); ?> resident record(s).
            </div>

        </div>
    </div>
</main>

<!-- RESIDENT DETAILS MODAL -->
<div
    class="modal fade"
    id="residentModal"
    tabindex="-1"
    aria-labelledby="residentModalTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content shadow">

            <div class="modal-header">

                <div>
                    <h5 class="modal-title fw-bold" id="residentModalTitle">
                        Resident Information
                    </h5>

                    <small class="text-secondary">
                        Registration details and residency information
                    </small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>

            <div class="modal-body">

                <div class="row">

                    <div class="col-md-6">

                        <div class="detail-label">Full Name</div>
                        <div class="detail-value" id="modalName">N/A</div>

                        <div class="detail-label">Email Address</div>
                        <div class="detail-value" id="modalEmail">N/A</div>

                        <div class="detail-label">Contact Number</div>
                        <div class="detail-value" id="modalContact">N/A</div>

                        <div class="detail-label">Birthdate</div>
                        <div class="detail-value" id="modalBirthdate">N/A</div>

                    </div>

                    <div class="col-md-6">

                        <div class="detail-label">Barangay</div>
                        <div class="detail-value" id="modalBarangay">N/A</div>

                        <div class="detail-label">Complete Address</div>
                        <div class="detail-value" id="modalAddress">N/A</div>

                        <div class="detail-label">Verification Status</div>
                        <div class="detail-value" id="modalVerification">N/A</div>

                        <div class="detail-label">Account Status</div>
                        <div class="detail-value" id="modalStatus">N/A</div>

                    </div>

                </div>

                <hr>

                <h6 class="fw-bold mb-3" style="color: var(--navy);">
                    <i class="bi bi-card-image me-1"></i>
                    Uploaded Valid ID
                </h6>

                <div id="validIdContainer" class="valid-id-box">
                    No valid ID uploaded.
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary btn-sm px-3"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>

            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const residentModal = document.getElementById('residentModal');

residentModal.addEventListener('show.bs.modal', function (event) {

    const trigger = event.relatedTarget;
    let resident = {};

    try {
        resident = JSON.parse(
            trigger.getAttribute('data-resident') || '{}'
        );
    } catch (error) {
        console.error('Unable to read resident information.', error);
    }

    /*
    |--------------------------------------------------------------------------
    | RESIDENT DETAILS
    |--------------------------------------------------------------------------
    */

    const setText = (id, value) => {
        document.getElementById(id).textContent = value || 'N/A';
    };

    setText('modalName', resident.fullname);
    setText('modalEmail', resident.email);
    setText('modalContact', resident.contact_number);
    setText('modalBirthdate', resident.birthdate);
    setText('modalBarangay', resident.barangay);
    setText('modalAddress', resident.address);
    setText('modalVerification', resident.verification_status);
    setText('modalStatus', resident.status);

    /*
    |--------------------------------------------------------------------------
    | VALID ID PREVIEW
    |--------------------------------------------------------------------------
    */

    const container = document.getElementById('validIdContainer');

    container.replaceChildren();

    if (!resident.valid_id) {
        container.textContent = 'No valid ID uploaded by this resident.';
        return;
    }

    if (!resident.valid_id_url) {
        container.textContent =
            'The ID file exists, but its preview could not be generated. Please check the Supabase Storage server configuration.';
        return;
    }

    // Stored Storage object path.
    const reference = document.createElement('p');

    reference.className = 'mb-2 fw-semibold';
    reference.style.fontSize = '11px';
    reference.textContent = 'Uploaded ID reference: ' + resident.valid_id;

    container.appendChild(reference);

    // Preview wrapper.
    const previewWrapper = document.createElement('div');

    previewWrapper.className = 'valid-id-preview-wrapper';

    // ID image.
    const image = document.createElement('img');

    image.className = 'valid-id-preview';
    image.src = resident.valid_id_url;
    image.alt = 'Resident Valid ID';
    image.loading = 'lazy';

    image.onerror = function () {

        image.remove();

        const errorMessage = document.createElement('p');

        errorMessage.className = 'valid-id-error';

        errorMessage.textContent =
            'Unable to display the uploaded ID. The file may be unavailable or the signed link may have expired.';

        previewWrapper.appendChild(errorMessage);
    };

    previewWrapper.appendChild(image);

    // Open full-size image.
    const actions = document.createElement('div');

    actions.className = 'valid-id-actions';

    const openLink = document.createElement('a');

    openLink.className = 'valid-id-open';
    openLink.href = resident.valid_id_url;
    openLink.target = '_blank';
    openLink.rel = 'noopener noreferrer';

    const linkIcon = document.createElement('i');

    linkIcon.className = 'bi bi-box-arrow-up-right';

    openLink.appendChild(linkIcon);

    openLink.appendChild(
        document.createTextNode(' Open Full Image')
    );

    actions.appendChild(openLink);
    previewWrapper.appendChild(actions);

    container.appendChild(previewWrapper);
});

/*
|--------------------------------------------------------------------------
| CLEAR MODAL CONTENT WHEN CLOSED
|--------------------------------------------------------------------------
*/

residentModal.addEventListener('hidden.bs.modal', function () {
    const container = document.getElementById('validIdContainer');

    container.replaceChildren();
    container.textContent = 'No valid ID uploaded.';
});
</script>

</body>
</html>
