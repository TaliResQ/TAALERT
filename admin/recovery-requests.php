
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection is not configured correctly.');
}

// Allowed request statuses
$statuses = [
    'pending' => 'Pending',
    'under_review' => 'Under Review',
    'verified' => 'Verified',
    'assistance_coordinated' => 'Assistance Coordinated',
    'completed' => 'Completed',
    'rejected' => 'Rejected',
    'needs_more_information' => 'Needs More Information',
];

// Create a CSRF token for forms
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

// Handle status and notes updates
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

    if (!array_key_exists($status, $statuses)) {
        http_response_code(400);
        exit('Invalid request status.');
    }

    if (strlen($notes) > 5000) {
        http_response_code(400);
        exit('Admin notes cannot exceed 5,000 characters.');
    }

    try {
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

        // Confirm the request exists, including when values did not change.
        $check = $pdo->prepare(
            'SELECT 1
             FROM public.assistance_requests
             WHERE id = :id'
        );
        $check->execute(['id' => $id]);

        if (!$check->fetchColumn()) {
            http_response_code(404);
            exit('Assistance request not found.');
        }

        header('Location: recovery-requests.php?updated=1');
        exit;

    } catch (PDOException $e) {
        error_log('Recovery request update failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to save changes. Please check the server logs.');
    }
}

// Load requests
try {
    $stmt = $pdo->query(
        'SELECT id, request_category, barangay, address,
                description, contact_number, status, admin_notes,
                created_at
         FROM public.assistance_requests
         ORDER BY created_at DESC'
    );

    $requests = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Recovery requests query failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load assistance requests. Please check the server logs.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Assistance Requests | TAALERT</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #222;
            background: #fff;
        }

        h1 {
            margin-bottom: 8px;
        }

        .success-message {
            padding: 12px 16px;
            margin: 20px 0;
            border: 1px solid #86c99a;
            border-radius: 6px;
            background: #eaf7ee;
            color: #176534;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1250px;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f5c928;
            white-space: nowrap;
        }

        td {
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        select, textarea {
            width: 180px;
            max-width: 100%;
            padding: 9px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            box-sizing: border-box;
        }

        textarea {
            width: 220px;
            min-height: 90px;
            resize: vertical;
        }

        .save-button {
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            background: #222;
            color: #fff;
            cursor: pointer;
            white-space: nowrap;
        }

        .save-button:hover {
            background: #444;
        }

        .status-label {
            text-transform: capitalize;
        }

        .empty-message {
            text-align: center;
            padding: 24px;
        }

        @media (max-width: 768px) {
            body {
                margin: 14px;
            }
        }
    </style>
</head>

<body>
    <h1>Recovery Assistance Requests</h1>

    <p>
        Review requests submitted through the TaliResQ mobile app.
        Update their status and add administrative notes below.
    </p>

    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
        <div class="success-message">
            Request updated successfully!
        </div>
    <?php endif; ?>

    <div class="table-container">
        <table>
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

            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9" class="empty-message">
                            No assistance requests found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $formId = 'update-' . $request['id'];
                        ?>

                        <tr>
                            <td>
                                <?= escapeHtml($request['request_category']) ?>
                            </td>

                            <td>
                                <?= escapeHtml($request['barangay']) ?>
                            </td>

                            <td>
                                <?= escapeHtml($request['address']) ?>
                            </td>

                            <td>
                                <?= nl2br(escapeHtml($request['description'])) ?>
                            </td>

                            <td>
                                <?= escapeHtml($request['contact_number']) ?>
                            </td>

                            <td>
                                <form id="<?= escapeHtml($formId) ?>"
                                      method="POST"
                                      action="recovery-requests.php">
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
                                </form>

                                <select
                                    name="status"
                                    form="<?= escapeHtml($formId) ?>"
                                    required
                                >
                                    <?php foreach ($statuses as $value => $label): ?>
                                        <option
                                            value="<?= escapeHtml($value) ?>"
                                            <?= $request['status'] === $value ? 'selected' : '' ?>
                                        >
                                            <?= escapeHtml($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <textarea
                                    name="admin_notes"
                                    form="<?= escapeHtml($formId) ?>"
                                    maxlength="5000"
                                    placeholder="Enter admin notes..."
                                ><?= escapeHtml($request['admin_notes']) ?></textarea>
                            </td>

                            <td>
                                <?= escapeHtml($request['created_at']) ?>
                            </td>

                            <td>
                                <button
                                    type="submit"
                                    form="<?= escapeHtml($formId) ?>"
                                    class="save-button"
                                >
                                    Save Changes
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
