
<?php
session_start();

// TODO: Replace this placeholder with the same admin-session
// authorization check used by your existing protected admin pages.
require_once __DIR__ . '/../config/db.php';

// Confirm that config/db.php creates a PDO variable named $pdo.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection is not configured correctly.');
}

try {
    $stmt = $pdo->query(
        "SELECT id, request_category, barangay, address,
                description, contact_number, status, created_at
         FROM public.assistance_requests
         ORDER BY created_at DESC"
    );

    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Recovery requests query failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load assistance requests. Please check the server logs.');
}

function escapeHtml($value): string {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Assistance Requests</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #222;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
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
        }

        .status {
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    <h1>Recovery Assistance Requests</h1>
    <p>Review assistance requests submitted through the TaliResQ mobile app.</p>

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
                    <th>Date Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="7">No assistance requests found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= escapeHtml($request['request_category']) ?></td>
                            <td><?= escapeHtml($request['barangay']) ?></td>
                            <td><?= escapeHtml($request['address']) ?></td>
                            <td><?= escapeHtml($request['description']) ?></td>
                            <td><?= escapeHtml($request['contact_number']) ?></td>
                            <td class="status"><?= escapeHtml($request['status']) ?></td>
                            <td><?= escapeHtml($request['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
